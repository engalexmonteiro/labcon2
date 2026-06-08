<?php

namespace App\Repositories;

class ReservationRepository extends BaseRepository
{
    public function all(): array
    {
        return $this->db->query('SELECT * FROM reservations ORDER BY day, start_time')->fetchAll();
    }

    public function find(string $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM reservations WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function hasConflict(array $item): bool
    {
        $stmt = $this->db->prepare(
            'SELECT id FROM reservations
             WHERE desk_id = ? AND day = ? AND id != ?
               AND start_time < ? AND end_time > ?'
        );
        $stmt->execute([$item['deskId'], $item['day'], $item['id'], $item['end'], $item['start']]);

        return (bool) $stmt->fetch();
    }

    public function save(array $fields): array
    {
        $this->upsert('reservations', $fields);

        return $this->find($fields['id']);
    }

    public function delete(string $id): void
    {
        $this->db->prepare('DELETE FROM reservations WHERE id = ?')->execute([$id]);
    }

    public function deleteAll(): void
    {
        $this->db->exec('DELETE FROM reservations');
    }
}
