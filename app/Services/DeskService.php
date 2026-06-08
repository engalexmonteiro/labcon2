<?php

namespace App\Services;

use App\Repositories\DeskRepository;
use InvalidArgumentException;

class DeskService
{
    private DeskRepository $desks;

    public function __construct(?DeskRepository $desks = null)
    {
        $this->desks = $desks ?? new DeskRepository();
    }

    public function all(): array
    {
        return array_map('desk_to_array', $this->desks->all());
    }

    public function save(array $item): array
    {
        if (empty($item['id']) || empty($item['labId']) || empty($item['name'])) {
            throw new InvalidArgumentException('Campos obrigatórios ausentes: id, labId, name.');
        }

        return desk_to_array($this->desks->save([
            'id' => $item['id'],
            'lab_id' => $item['labId'],
            'name' => $item['name'],
        ]));
    }

    public function delete(string $id): void
    {
        if (!$id) {
            throw new InvalidArgumentException('ID não informado.');
        }

        $this->desks->delete($id);
    }
}
