<?php

namespace App\Services;

use App\Repositories\UserRepository;
use InvalidArgumentException;
use RuntimeException;

class AuthService
{
    private UserRepository $users;

    public function __construct(?UserRepository $users = null)
    {
        $this->users = $users ?? new UserRepository();
    }

    public function login(string $email, string $password): array
    {
        $email = trim($email);
        if (!$email || !$password) {
            throw new InvalidArgumentException('E-mail e senha são obrigatórios.');
        }

        $row = $this->users->findByEmail($email);
        if (!$row || !$row['password_hash'] || !password_verify($password, $row['password_hash'])) {
            throw new RuntimeException('E-mail ou senha incorretos.');
        }

        $user = user_to_array($row);
        set_session_user($user);

        return $user;
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
}
