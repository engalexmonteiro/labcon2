<?php

namespace App\Services;

use App\Repositories\SettingsRepository;
use InvalidArgumentException;

class SmtpSettingsService
{
    private const KEY = 'smtp';

    private SettingsRepository $settings;

    public function __construct(?SettingsRepository $settings = null)
    {
        $this->settings = $settings ?? new SettingsRepository();
    }

    public function get(bool $includeSecret = false): array
    {
        $stored = $this->decode($this->settings->get(self::KEY));
        $defaults = [
            'enabled' => false,
            'host' => '',
            'port' => 587,
            'encryption' => 'tls',
            'username' => '',
            'password' => '',
            'fromEmail' => '',
            'fromName' => APP_NAME,
        ];
        $smtp = array_merge($defaults, $stored);
        $smtp['port'] = (int) $smtp['port'];
        $smtp['passwordSet'] = !empty($smtp['password']);

        if (!$includeSecret) {
            unset($smtp['password']);
        }

        return $smtp;
    }

    public function save(array $body): array
    {
        $current = $this->get(true);
        $smtp = [
            'enabled' => !empty($body['enabled']),
            'host' => trim($body['host'] ?? ''),
            'port' => (int) ($body['port'] ?? 587),
            'encryption' => $body['encryption'] ?? 'tls',
            'username' => trim($body['username'] ?? ''),
            'password' => (string) ($body['password'] ?? ''),
            'fromEmail' => trim($body['fromEmail'] ?? ''),
            'fromName' => trim($body['fromName'] ?? APP_NAME),
        ];

        if ($smtp['password'] === '' && !empty($body['keepPassword'])) {
            $smtp['password'] = $current['password'] ?? '';
        }

        if (!in_array($smtp['encryption'], ['tls', 'ssl', 'none'], true)) {
            throw new InvalidArgumentException('Criptografia SMTP invalida.');
        }
        if ($smtp['port'] < 1 || $smtp['port'] > 65535) {
            throw new InvalidArgumentException('Porta SMTP invalida.');
        }
        if ($smtp['enabled']) {
            foreach (['host' => 'host', 'username' => 'usuario', 'password' => 'senha', 'fromEmail' => 'e-mail remetente'] as $key => $label) {
                if ($smtp[$key] === '') {
                    throw new InvalidArgumentException('Informe o ' . $label . ' do SMTP.');
                }
            }
            if (!filter_var($smtp['fromEmail'], FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('E-mail remetente invalido.');
            }
        }

        $this->settings->set(self::KEY, $this->encode($smtp));

        return $this->get();
    }

    private function decode(?string $value): array
    {
        if (!$value) {
            return [];
        }
        $data = json_decode($value, true);
        if (!is_array($data)) {
            return [];
        }
        if (!empty($data['password'])) {
            $data['password'] = $this->decrypt($data['password']);
        }
        return $data;
    }

    private function encode(array $smtp): string
    {
        if (!empty($smtp['password'])) {
            $smtp['password'] = $this->encrypt($smtp['password']);
        }
        return json_encode($smtp, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function encrypt(string $plain): string
    {
        $iv = random_bytes(16);
        $key = hash('sha256', APP_SECRET, true);
        $cipher = openssl_encrypt($plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        return base64_encode($iv . $cipher);
    }

    private function decrypt(string $encoded): string
    {
        $raw = base64_decode($encoded, true);
        if ($raw === false || strlen($raw) <= 16) {
            return '';
        }
        $iv = substr($raw, 0, 16);
        $cipher = substr($raw, 16);
        $plain = openssl_decrypt($cipher, 'AES-256-CBC', hash('sha256', APP_SECRET, true), OPENSSL_RAW_DATA, $iv);

        return $plain === false ? '' : $plain;
    }
}
