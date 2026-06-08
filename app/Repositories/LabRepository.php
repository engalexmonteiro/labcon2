<?php

namespace App\Repositories;

class LabRepository extends BaseRepository
{
    public function all(): array
    {
        return $this->db->query('SELECT * FROM labs ORDER BY name')->fetchAll();
    }

    public function find(string $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM labs WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function save(array $fields): array
    {
        $this->upsert('labs', $fields);

        return $this->find($fields['id']);
    }

    public function delete(string $id): void
    {
        $this->db->prepare('DELETE FROM labs WHERE id = ?')->execute([$id]);
    }

    public function deleteAll(): void
    {
        $this->db->exec('DELETE FROM labs');
    }
}
