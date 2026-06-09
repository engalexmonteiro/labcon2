<?php
require_once __DIR__ . '/config.php';

function start_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_start();
    }
}

function csrf_token(): string {
    start_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token(): void {
    $header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $token  = csrf_token();
    if (!$header || !hash_equals($token, $header)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Token CSRF inválido.']);
        exit;
    }
}

function verify_csrf_if_mutating(): void {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        verify_csrf_token();
    }
}

function require_role(string ...$roles): array {
    $user = require_auth();
    if (!in_array($user['role'] ?? '', $roles, true)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Acesso negado.']);
        exit;
    }
    return $user;
}

function require_auth(): array {
    start_session();
    if (empty($_SESSION['user_id'])) {
        // Retorna JSON 401 para chamadas de API (fetch/XHR)
        $isApiCall = (
            (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
            (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) ||
            (str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/'))
        );
        if ($isApiCall) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'Não autenticado.', 'redirect' => 'login.php']);
            exit;
        }
        header('Location: login.php');
        exit;
    }
    return $_SESSION['user'] ?? [];
}

function get_session_user(): ?array {
    start_session();
    if (empty($_SESSION['user_id'])) return null;
    return $_SESSION['user'] ?? null;
}

function set_session_user(array $user): void {
    start_session();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user']    = $user;
}

function clear_session(): void {
    start_session();
    session_unset();
    session_destroy();
}
