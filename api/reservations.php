<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_auth();

$method = $_SERVER['REQUEST_METHOD'];
$db     = get_db();

if ($method === 'GET') {
    $rows = $db->query('SELECT * FROM reservations ORDER BY day, start_time')->fetchAll();
    json_response(array_map('reservation_to_array', $rows));
}

if ($method === 'POST') {
    $body = get_json_body();

    // Suporte a batch: { items: [...] }
    $items = isset($body['items']) ? $body['items'] : [$body];

    $saved = [];
    $db->beginTransaction();
    try {
        foreach ($items as $item) {
            if (empty($item['id']) || empty($item['userId']) || empty($item['labId'])
                || empty($item['deskId']) || empty($item['day'])
                || empty($item['start']) || empty($item['end'])) {
                throw new InvalidArgumentException('Campos obrigatórios ausentes na reserva.');
            }

            // Verificar conflito de horário na mesa
            $stmt = $db->prepare(
                'SELECT id FROM reservations
                 WHERE desk_id = ? AND day = ? AND id != ?
                   AND start_time < ? AND end_time > ?'
            );
            $stmt->execute([$item['deskId'], $item['day'], $item['id'], $item['end'], $item['start']]);
            if ($stmt->fetch()) {
                throw new RuntimeException("Conflito de horário: mesa {$item['deskId']} já reservada em {$item['day']} {$item['start']}-{$item['end']}.");
            }

            $fields = [
                'id'         => $item['id'],
                'user_id'    => $item['userId'],
                'lab_id'     => $item['labId'],
                'desk_id'    => $item['deskId'],
                'day'        => $item['day'],
                'start_time' => $item['start'],
                'end_time'   => $item['end'],
            ];
            $cols         = array_keys($fields);
            $placeholders = implode(', ', array_fill(0, count($fields), '?'));
            $updates      = implode(', ', array_map(fn($c) => "$c = VALUES($c)", $cols));
            $sql = 'INSERT INTO reservations (' . implode(', ', $cols) . ') VALUES (' . $placeholders . ')
                    ON DUPLICATE KEY UPDATE ' . $updates;
            $db->prepare($sql)->execute(array_values($fields));

            $stmt = $db->prepare('SELECT * FROM reservations WHERE id = ?');
            $stmt->execute([$item['id']]);
            $saved[] = reservation_to_array($stmt->fetch());
        }
        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        json_error($e->getMessage());
    }

    if (count($saved) === 1) {
        json_response(['success' => true, 'item' => $saved[0], 'items' => $saved]);
    }
    json_response(['success' => true, 'items' => $saved]);
}

if ($method === 'PUT') {
    $item = get_json_body();

    if (empty($item['id'])) json_error('ID não informado.');

    // Verificar conflito
    $stmt = $db->prepare(
        'SELECT id FROM reservations
         WHERE desk_id = ? AND day = ? AND id != ?
           AND start_time < ? AND end_time > ?'
    );
    $stmt->execute([$item['deskId'], $item['day'], $item['id'], $item['end'], $item['start']]);
    if ($stmt->fetch()) {
        json_error('Já existe reserva nessa mesa para esse dia e faixa de horário.');
    }

    $fields = [
        'id'         => $item['id'],
        'user_id'    => $item['userId'],
        'lab_id'     => $item['labId'],
        'desk_id'    => $item['deskId'],
        'day'        => $item['day'],
        'start_time' => $item['start'],
        'end_time'   => $item['end'],
    ];
    $cols         = array_keys($fields);
    $placeholders = implode(', ', array_fill(0, count($fields), '?'));
    $updates      = implode(', ', array_map(fn($c) => "$c = VALUES($c)", $cols));
    $sql = 'INSERT INTO reservations (' . implode(', ', $cols) . ') VALUES (' . $placeholders . ')
            ON DUPLICATE KEY UPDATE ' . $updates;
    $db->prepare($sql)->execute(array_values($fields));

    $stmt = $db->prepare('SELECT * FROM reservations WHERE id = ?');
    $stmt->execute([$item['id']]);
    json_response(['success' => true, 'item' => reservation_to_array($stmt->fetch())]);
}

if ($method === 'DELETE') {
    $id = $_GET['id'] ?? '';
    if (!$id) json_error('ID não informado.');
    $db->prepare('DELETE FROM reservations WHERE id = ?')->execute([$id]);
    json_response(['success' => true]);
}

json_error('Método não permitido.', 405);
