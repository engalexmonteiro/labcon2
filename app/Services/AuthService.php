<?php

namespace App\Services;

use App\Repositories\PasswordResetRepository;
use App\Repositories\UserRepository;
use InvalidArgumentException;
use RuntimeException;

class AuthService
{
    private UserRepository $users;
    private PasswordResetRepository $resets;
    private SmtpSettingsService $smtpSettings;

    public function __construct(
        ?UserRepository $users = null,
        ?PasswordResetRepository $resets = null,
        ?SmtpSettingsService $smtpSettings = null
    )
    {
        $this->users = $users ?? new UserRepository();
        $this->resets = $resets ?? new PasswordResetRepository();
        $this->smtpSettings = $smtpSettings ?? new SmtpSettingsService();
    }

    private const MAX_ATTEMPTS   = 10;
    private const WINDOW_SECONDS = 300;

    public function login(string $email, string $password): array
    {
        $email = trim($email);
        if (!$email || !$password) {
            throw new InvalidArgumentException('E-mail e senha são obrigatórios.');
        }

        $ip         = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $identifier = hash('sha256', $ip . '|' . strtolower($email));

        $this->enforceRateLimit($identifier);

        $row = $this->users->findByEmail($email);
        if (!$row || !$row['password_hash'] || !password_verify($password, $row['password_hash'])) {
            $this->recordFailedAttempt($identifier);
            throw new RuntimeException('E-mail ou senha incorretos.');
        }

        $this->clearAttempts($identifier);

        $user = user_to_array($row);
        set_session_user($user);

        return $user;
    }

    private function enforceRateLimit(string $identifier): void
    {
        $db   = get_db();
        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE identifier = ? AND attempted_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)'
        );
        $stmt->execute([$identifier, self::WINDOW_SECONDS]);
        $count = (int) $stmt->fetchColumn();

        if ($count >= self::MAX_ATTEMPTS) {
            throw new RuntimeException('Muitas tentativas. Aguarde alguns minutos e tente novamente.');
        }
    }

    private function recordFailedAttempt(string $identifier): void
    {
        $db   = get_db();
        $stmt = $db->prepare('INSERT INTO login_attempts (identifier) VALUES (?)');
        $stmt->execute([$identifier]);

        $stmt = $db->prepare(
            'DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL ? SECOND)'
        );
        $stmt->execute([self::WINDOW_SECONDS * 2]);
    }

    private function clearAttempts(string $identifier): void
    {
        $db   = get_db();
        $stmt = $db->prepare('DELETE FROM login_attempts WHERE identifier = ?');
        $stmt->execute([$identifier]);
    }

    public function register(array $body): array
    {
        $name = trim($body['name'] ?? '');
        $email = trim($body['email'] ?? '');
        $password = $body['password'] ?? '';
        $role = $body['role'] ?? 'aluno';

        if (!$name || !$email || !$password) {
            throw new InvalidArgumentException('Nome, e-mail e senha são obrigatórios.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('E-mail inválido.');
        }
        if (mb_strlen($password) < 8) {
            throw new InvalidArgumentException('A senha deve ter pelo menos 8 caracteres.');
        }
        if (!in_array($role, ['aluno', 'professor', 'tecnico'], true)) {
            throw new InvalidArgumentException('Perfil inválido.');
        }
        if ($this->users->findByEmail($email)) {
            throw new InvalidArgumentException('Já existe um cadastro com este e-mail.');
        }

        $fields = [
            'id' => 'auth-' . bin2hex(random_bytes(8)),
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
            'name' => $name,
            'role' => $role,
            'source' => 'auth',
        ];

        if ($role === 'aluno') {
            $level = $body['level'] ?? 'graduacao';
            $fields['level'] = $level;
            $fields['advisor_id'] = $body['advisorId'] ?? null;
            $fields['advisor_name'] = $body['advisorName'] ?? null;
            $fields['research_project'] = $body['researchProject'] ?? null;

            if ($level === 'graduacao') {
                $fields['course'] = $body['course'] ?? null;
                $fields['program'] = $body['program'] ?? null;
            } else {
                $fields['postgrad_type'] = $body['postgradType'] ?? null;
            }
        }

        $fields = array_filter($fields, fn(mixed $value): bool => $value !== null);
        $user = user_to_array($this->users->save($fields));
        set_session_user($user);

        return $user;
    }

    public function logout(): void
    {
        clear_session();
    }

    public function requestPasswordReset(string $email): void
    {
        $email = trim($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Informe um e-mail valido.');
        }

        $row = $this->users->findByEmail($email);
        if (!$row) {
            return;
        }

        $settings = $this->smtpSettings->get(true);
        if (empty($settings['enabled'])) {
            throw new RuntimeException('SMTP nao configurado. Solicite ao administrador a configuracao do envio de e-mails.');
        }

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + 60 * 60);
        $this->resets->create($row['id'], $tokenHash, $expiresAt);

        $link = $this->baseUrl() . '/reset_password.php?token=' . urlencode($token);
        $name = $row['name'] ?? 'Usuario';
        $subject = APP_NAME . ' - recuperacao de senha';
        $text = "Ola, {$name}.\n\nAcesse o link abaixo para criar uma nova senha. O link expira em 1 hora.\n\n{$link}\n\nSe voce nao solicitou a recuperacao, ignore este e-mail.";
        $html = '<p>Ola, ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '.</p>'
            . '<p>Acesse o link abaixo para criar uma nova senha. O link expira em 1 hora.</p>'
            . '<p><a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">Redefinir senha</a></p>'
            . '<p>Se voce nao solicitou a recuperacao, ignore este e-mail.</p>';

        (new SmtpMailer($settings))->send($email, $name, $subject, $html, $text);
    }

    public function resetPassword(string $token, string $password): void
    {
        $token = trim($token);
        if ($token === '' || mb_strlen($token) < 32) {
            throw new InvalidArgumentException('Token invalido.');
        }
        if (mb_strlen($password) < 8) {
            throw new InvalidArgumentException('A senha deve ter pelo menos 8 caracteres.');
        }

        $reset = $this->resets->findValid(hash('sha256', $token));
        if (!$reset) {
            throw new RuntimeException('Link de recuperacao invalido ou expirado.');
        }

        $user = $this->users->find($reset['user_id']);
        if (!$user) {
            throw new RuntimeException('Usuario nao encontrado.');
        }

        $this->users->save([
            'id' => $user['id'],
            'name' => $user['name'],
            'role' => $user['role'],
            'source' => $user['source'] ?? 'manual',
            'email' => $user['email'] ?? null,
            'level' => $user['level'] ?? null,
            'course' => $user['course'] ?? null,
            'program' => $user['program'] ?? null,
            'postgrad_type' => $user['postgrad_type'] ?? null,
            'advisor_id' => $user['advisor_id'] ?? null,
            'advisor_name' => $user['advisor_name'] ?? null,
            'research_project' => $user['research_project'] ?? null,
            'entry_date' => $user['entry_date'] ?? null,
            'qualification_deadline' => $user['qualification_deadline'] ?? null,
            'advisor_meeting_url' => $user['advisor_meeting_url'] ?? null,
            'article_url' => $user['article_url'] ?? null,
            'qualification_url' => $user['qualification_url'] ?? null,
            'thesis_url' => $user['thesis_url'] ?? null,
            'photo_data_url' => $user['photo_data_url'] ?? null,
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
        ]);
        $this->resets->markUsed($reset['id']);
    }

    private function baseUrl(): string
    {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
        $scheme = $https ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        if ($dir === '/api') {
            $dir = '';
        } elseif (str_ends_with($dir, '/api')) {
            $dir = substr($dir, 0, -4);
        }

        return $scheme . '://' . $host . ($dir ? $dir : '');
    }
}
