<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_auth();

$method = $_SERVER['REQUEST_METHOD'];
$db     = get_db();

if ($method === 'GET') {
    $rows = $db->query('SELECT * FROM desks ORDER BY name')->fetchAll();
    json_response(array_map('desk_to_array', $rows));
}

if ($method === 'POST' || $method === 'PUT') {
    $item = get_json_body();

    if (empty($item['id']) || empty($item['labId']) || empty($item['name'])) {
        json_error('Campos obrigatórios ausentes: id, labId, name.');
    }

    $fields = [
        'id'     => $item['id'],
        'lab_id' => $item['labId'],
        'name'   => $item['name'],
    ];

    $cols         = array_keys($fields);
    $placeholders = implode(', ', array_fill(0, count($fields), '?'));
    $updates      = implode(', ', array_map(fn($c) => "$c = VALUES($c)", $cols));
    $sql = 'INSERT INTO desks (' . implode(', ', $cols) . ') VALUES (' . $placeholders . ')
            ON DUPLICATE KEY UPDATE ' . $updates;
    $db->prepare($sql)->execute(array_values($fields));

    $stmt = $db->prepare('SELECT * FROM desks WHERE id = ?');
    $stmt->execute([$item['id']]);
    json_response(['success' => true, 'item' => desk_to_array($stmt->fetch())]);
}

if ($method === 'DELETE') {
    $id = $_GET['id'] ?? '';
    if (!$id) json_error('ID não informado.');
    // ON DELETE CASCADE cuida de reservations
    $db->prepare('DELETE FROM desks WHERE id = ?')->execute([$id]);
    json_response(['success' => true]);
}

json_error('Método não permitido.', 405);
