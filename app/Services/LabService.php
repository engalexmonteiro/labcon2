<?php

namespace App\Services;

use App\Repositories\LabRepository;
use InvalidArgumentException;

class LabService
{
    private LabRepository $labs;

    public function __construct(?LabRepository $labs = null)
    {
        $this->labs = $labs ?? new LabRepository();
    }

    public function all(): array
    {
        return array_map('lab_to_array', $this->labs->all());
    }

    public function save(array $item): array
    {
        if (empty($item['id']) || empty($item['name'])) {
            throw new InvalidArgumentException('Campos obrigatórios ausentes: id, name.');
        }

        return lab_to_array($this->labs->save([
            'id' => $item['id'],
            'name' => $item['name'],
            'location' => $item['location'] ?? null,
        ]));
    }

    public function delete(string $id): void
    {
        if (!$id) {
            throw new InvalidArgumentException('ID não informado.');
        }

        $this->labs->delete($id);
    }
}
