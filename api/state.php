<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $db = get_db();

    $users        = $db->query('SELECT * FROM users ORDER BY name')->fetchAll();
    $labs         = $db->query('SELECT * FROM labs ORDER BY name')->fetchAll();
    $desks        = $db->query('SELECT * FROM desks ORDER BY name')->fetchAll();
    $reservations = $db->query('SELECT * FROM reservations ORDER BY day, start_time')->fetchAll();

    json_response([
        'users'        => array_map('user_to_array', $users),
        'labs'         => array_map('lab_to_array', $labs),
        'desks'        => array_map('desk_to_array', $desks),
        'reservations' => array_map('reservation_to_array', $reservations),
    ]);
}

if ($method === 'DELETE') {
    require_auth();
    $db = get_db();
    $db->exec('DELETE FROM reservations');
    $db->exec('DELETE FROM desks');
    $db->exec('DELETE FROM labs');
    $db->exec('DELETE FROM users');
    json_response(['success' => true]);
}

if ($method === 'POST') {
    require_auth();
    $body   = get_json_body();
    $action = $body['action'] ?? '';

    if ($action === 'seed') {
        $db    = get_db();
        $labId = 'lab-' . bin2hex(random_bytes(6));
        $db->prepare('INSERT IGNORE INTO labs (id, name, location) VALUES (?, ?, ?)')
           ->execute([$labId, 'ETSS', 'Laboratório ETSS']);

        $professors = [
            ['id' => 'user-' . bin2hex(random_bytes(5)), 'name' => 'Eduardo Souto',   'role' => 'professor'],
            ['id' => 'user-' . bin2hex(random_bytes(5)), 'name' => 'Eduardo Feitosa', 'role' => 'professor'],
        ];
        foreach ($professors as $p) {
            $db->prepare('INSERT IGNORE INTO users (id, name, role) VALUES (?, ?, ?)')
               ->execute([$p['id'], $p['name'], $p['role']]);
        }

        foreach (str_split('ABCDEFGHIJKLMNOP') as $letter) {
            $deskId = 'desk-' . bin2hex(random_bytes(5));
            $db->prepare('INSERT IGNORE INTO desks (id, lab_id, name) VALUES (?, ?, ?)')
               ->execute([$deskId, $labId, "Baia $letter"]);
        }

        json_response(['success' => true]);
    }

    json_error('Ação inválida.');
}

json_error('Método não permitido.', 405);
