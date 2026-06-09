<?php

namespace App\Services;

use App\Repositories\ReservationRepository;
use Exception;
use InvalidArgumentException;
use RuntimeException;

class ReservationService
{
    private ReservationRepository $reservations;

    public function __construct(?ReservationRepository $reservations = null)
    {
        $this->reservations = $reservations ?? new ReservationRepository();
    }

    public function all(): array
    {
        return array_map('reservation_to_array', $this->reservations->all());
    }

    public function saveOne(array $item): array
    {
        $this->validate($item);

        if ($this->reservations->hasConflict($item)) {
            throw new RuntimeException('Já existe reserva nessa mesa para esse dia e faixa de horário.');
        }

        return reservation_to_array($this->reservations->save($this->toFields($item)));
    }

    public function saveMany(array $items): array
    {
        $db = get_db();
        $saved = [];

        $db->beginTransaction();
        try {
            foreach ($items as $item) {
                $this->validate($item);

                if ($this->reservations->hasConflict($item)) {
                    throw new RuntimeException("Conflito de horário: mesa {$item['deskId']} já reservada em {$item['day']} {$item['start']}-{$item['end']}.");
                }

                $saved[] = reservation_to_array($this->reservations->save($this->toFields($item)));
            }
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }

        return $saved;
    }

    public function delete(string $id): void
    {
        if (!$id) {
            throw new InvalidArgumentException('ID não informado.');
        }

        $this->reservations->delete($id);
    }

    public function deleteOwned(string $id, string $userId): void
    {
        if (!$id) {
            throw new InvalidArgumentException('ID não informado.');
        }

        $row = $this->reservations->find($id);
        if (!$row) {
            throw new InvalidArgumentException('Reserva não encontrada.');
        }
        if ($row['user_id'] !== $userId) {
            throw new RuntimeException('Sem permissão para excluir esta reserva.');
        }

        $this->reservations->delete($id);
    }

    private function validate(array $item): void
    {
        if (empty($item['id']) || empty($item['userId']) || empty($item['labId'])
            || empty($item['deskId']) || empty($item['day'])
            || empty($item['start']) || empty($item['end'])) {
            throw new InvalidArgumentException('Campos obrigatórios ausentes na reserva.');
        }
    }

    private function toFields(array $item): array
    {
        return [
            'id' => $item['id'],
            'user_id' => $item['userId'],
            'lab_id' => $item['labId'],
            'desk_id' => $item['deskId'],
            'day' => $item['day'],
            'start_time' => $item['start'],
            'end_time' => $item['end'],
        ];
    }
}
