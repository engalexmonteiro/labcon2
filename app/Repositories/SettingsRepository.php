<?php

namespace App\Repositories;

class SettingsRepository extends BaseRepository
{
    public function get(string $key): ?string
    {
        $stmt = $this->db->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ?');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();

        return $value === false ? null : (string) $value;
    }

    public function set(string $key, ?string $value): void
    {
        $this->upsert('app_settings', [
            'setting_key' => $key,
            'setting_value' => $value,
        ]);
    }
}
