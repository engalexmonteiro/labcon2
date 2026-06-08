<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_auth();

$method = $_SERVER['REQUEST_METHOD'];
$db     = get_db();

if ($method === 'GET') {
    $rows = $db->query('SELECT * FROM users ORDER BY name')->fetchAll();
    json_response(array_map('user_to_array', $rows));
}

if ($method === 'POST' || $method === 'PUT') {
    $item = get_json_body();

    if (empty($item['id']) || empty($item['name']) || empty($item['role'])) {
        json_error('Campos obrigatórios ausentes: id, name, role.');
    }

    $fields = [
        'id'     => $item['id'],
        'name'   => $item['name'],
        'role'   => $item['role'],
        'source' => $item['source'] ?? 'manual',
    ];

    $nullables = [
        'email'                  => 'email',
        'level'                  => 'level',
        'course'                 => 'course',
        'program'                => 'program',
        'postgradType'           => 'postgrad_type',
        'advisorId'              => 'advisor_id',
        'advisorName'            => 'advisor_name',
        'researchProject'        => 'research_project',
        'entryDate'              => 'entry_date',
        'qualificationDeadline'  => 'qualification_deadline',
        'advisorMeetingUrl'      => 'advisor_meeting_url',
        'articleUrl'             => 'article_url',
        'qualificationUrl'       => 'qualification_url',
        'thesisUrl'              => 'thesis_url',
        'photoDataUrl'           => 'photo_data_url',
    ];
    foreach ($nullables as $jsKey => $dbCol) {
        $fields[$dbCol] = $item[$jsKey] ?? null;
    }

    // Se senha fornecida, criar/atualizar hash
    if (!empty($item['password'])) {
        $fields['password_hash'] = password_hash($item['password'], PASSWORD_BCRYPT);
    }

    $cols         = array_keys($fields);
    $placeholders = implode(', ', array_fill(0, count($fields), '?'));
    $updates      = implode(', ', array_map(fn($c) => "$c = VALUES($c)", $cols));
    $sql = 'INSERT INTO users (' . implode(', ', $cols) . ') VALUES (' . $placeholders . ')
            ON DUPLICATE KEY UPDATE ' . $updates;
    $db->prepare($sql)->execute(array_values($fields));

    $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$item['id']]);
    $saved = user_to_array($stmt->fetch());
    json_response(['success' => true, 'item' => $saved]);
}

if ($method === 'DELETE') {
    $id = $_GET['id'] ?? '';
    if (!$id) json_error('ID não informado.');

    // Limpa referências de orientador antes de excluir
    $db->prepare('UPDATE users SET advisor_id = NULL, advisor_name = NULL WHERE advisor_id = ?')->execute([$id]);
    $db->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    json_response(['success' => true]);
}

json_error('Método não permitido.', 405);
