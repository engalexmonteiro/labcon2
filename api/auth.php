<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $user = get_session_user();
    json_response(['authenticated' => $user !== null, 'user' => $user]);
}

if ($method !== 'POST') {
    json_error('Método não permitido.', 405);
}

$body   = get_json_body();
$action = $body['action'] ?? '';

if ($action === 'login') {
    $email    = trim($body['email'] ?? '');
    $password = $body['password'] ?? '';

    if (!$email || !$password) {
        json_error('E-mail e senha são obrigatórios.');
    }

    $db   = get_db();
    $stmt = $db->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $row = $stmt->fetch();

    if (!$row || !$row['password_hash'] || !password_verify($password, $row['password_hash'])) {
        json_error('E-mail ou senha incorretos.', 401);
    }

    $user = user_to_array($row);
    set_session_user($user);
    json_response(['success' => true, 'user' => $user]);
}

if ($action === 'register') {
    $name     = trim($body['name'] ?? '');
    $email    = trim($body['email'] ?? '');
    $password = $body['password'] ?? '';
    $role     = $body['role'] ?? 'aluno';

    if (!$name || !$email || !$password) {
        json_error('Nome, e-mail e senha são obrigatórios.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_error('E-mail inválido.');
    }
    if (mb_strlen($password) < 8) {
        json_error('A senha deve ter pelo menos 8 caracteres.');
    }
    if (!in_array($role, ['aluno', 'professor', 'tecnico'], true)) {
        json_error('Perfil inválido.');
    }

    $db   = get_db();
    $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        json_error('Já existe um cadastro com este e-mail.');
    }

    $id     = 'auth-' . bin2hex(random_bytes(8));
    $hash   = password_hash($password, PASSWORD_BCRYPT);
    $fields = [
        'id'            => $id,
        'email'         => $email,
        'password_hash' => $hash,
        'name'          => $name,
        'role'          => $role,
        'source'        => 'auth',
    ];

    if ($role === 'aluno') {
        $level = $body['level'] ?? 'graduacao';
        $fields['level']          = $level;
        $fields['advisor_id']     = $body['advisorId'] ?? null;
        $fields['advisor_name']   = $body['advisorName'] ?? null;
        $fields['research_project'] = $body['researchProject'] ?? null;
        if ($level === 'graduacao') {
            $fields['course']   = $body['course'] ?? null;
            $fields['program']  = $body['program'] ?? null;
        } else {
            $fields['postgrad_type'] = $body['postgradType'] ?? null;
        }
    }

    // Remove nulls para não inserir campos vazios
    $fields = array_filter($fields, fn($v) => $v !== null);

    $cols         = implode(', ', array_keys($fields));
    $placeholders = implode(', ', array_fill(0, count($fields), '?'));
    $db->prepare("INSERT INTO users ($cols) VALUES ($placeholders)")->execute(array_values($fields));

    $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $user = user_to_array($stmt->fetch());
    set_session_user($user);
    json_response(['success' => true, 'user' => $user]);
}

if ($action === 'logout') {
    clear_session();
    json_response(['success' => true]);
}

json_error('Ação inválida.');
