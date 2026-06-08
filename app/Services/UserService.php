<?php

namespace App\Services;

use App\Repositories\UserRepository;
use InvalidArgumentException;

class UserService
{
    private UserRepository $users;

    public function __construct(?UserRepository $users = null)
    {
        $this->users = $users ?? new UserRepository();
    }

    public function all(): array
    {
        return array_map('user_to_array', $this->users->all());
    }

    public function save(array $item): array
    {
        if (empty($item['id']) || empty($item['name']) || empty($item['role'])) {
            throw new InvalidArgumentException('Campos obrigatórios ausentes: id, name, role.');
        }

        $fields = [
            'id' => $item['id'],
            'name' => $item['name'],
            'role' => $item['role'],
            'source' => $item['source'] ?? 'manual',
        ];

        $nullables = [
            'email' => 'email',
            'level' => 'level',
            'course' => 'course',
            'program' => 'program',
            'postgradType' => 'postgrad_type',
            'advisorId' => 'advisor_id',
            'advisorName' => 'advisor_name',
            'researchProject' => 'research_project',
            'entryDate' => 'entry_date',
            'qualificationDeadline' => 'qualification_deadline',
            'advisorMeetingUrl' => 'advisor_meeting_url',
            'articleUrl' => 'article_url',
            'qualificationUrl' => 'qualification_url',
            'thesisUrl' => 'thesis_url',
            'photoDataUrl' => 'photo_data_url',
        ];

        foreach ($nullables as $jsKey => $dbCol) {
            $fields[$dbCol] = $item[$jsKey] ?? null;
        }

        if (!empty($item['password'])) {
            $fields['password_hash'] = password_hash($item['password'], PASSWORD_BCRYPT);
        }

        return user_to_array($this->users->save($fields));
    }

    public function delete(string $id): void
    {
        if (!$id) {
            throw new InvalidArgumentException('ID não informado.');
        }

        $this->users->delete($id);
    }
}
