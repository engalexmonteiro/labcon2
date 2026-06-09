<?php

namespace App\Repositories;

use PDO;

abstract class BaseRepository
{
    protected PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? get_db();
    }

    protected function upsert(string $table, array $fields): void
    {
        $cols         = array_keys($fields);
        $quotedCols   = array_map(fn(string $c): string => '`' . str_replace('`', '``', $c) . '`', $cols);
        $placeholders = implode(', ', array_fill(0, count($fields), '?'));
        $updates      = implode(', ', array_map(
            fn(string $qc): string => "$qc = VALUES($qc)",
            $quotedCols
        ));

        $sql = 'INSERT INTO `' . str_replace('`', '``', $table) . '`'
             . ' (' . implode(', ', $quotedCols) . ') VALUES (' . $placeholders . ')'
             . ' ON DUPLICATE KEY UPDATE ' . $updates;

        $this->db->prepare($sql)->execute(array_values($fields));
    }
}
