<?php

namespace App\Services;

use RuntimeException;

class SmtpMailer
{
    private array $settings;
    private mixed $socket = null;

    public function __construct(array $settings)
    {
        $this->settings = $settings;
    }

    public function send(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody): void
    {
        if (empty($this->settings['enabled'])) {
            throw new RuntimeException('SMTP nao configurado.');
        }

        $host = $this->settings['host'];
        $this->validateSmtpHost($host);

        $port   = (int) $this->settings['port'];
        $scheme = $this->settings['encryption'] === 'ssl' ? 'ssl://' : '';
        $this->socket = @stream_socket_client($scheme . $host . ':' . $port, $errno, $errstr, 20, STREAM_CLIENT_CONNECT);
        if (!$this->socket) {
            throw new RuntimeException('Nao foi possivel conectar ao SMTP: ' . $errstr);
        }
        stream_set_timeout($this->socket, 20);

        $this->expect([220]);
        $server = $_SERVER['SERVER_NAME'] ?? 'localhost';
        $this->command('EHLO ' . $server, [250]);

        if ($this->settings['encryption'] === 'tls') {
            $this->command('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Falha ao iniciar TLS no SMTP.');
            }
            $this->command('EHLO ' . $server, [250]);
        }

        $fromEmail = $this->sanitizeSmtpAddress($this->settings['fromEmail']);
        $toEmail   = $this->sanitizeSmtpAddress($toEmail);

        $this->command('AUTH LOGIN', [334]);
        $this->command(base64_encode($this->settings['username']), [334]);
        $this->command(base64_encode($this->settings['password']), [235]);
        $this->command('MAIL FROM:<' . $fromEmail . '>', [250]);
        $this->command('RCPT TO:<' . $toEmail . '>', [250, 251]);
        $this->command('DATA', [354]);

        $message = $this->buildMessage($toEmail, $toName, $subject, $htmlBody, $textBody);
        fwrite($this->socket, $message . "\r\n.\r\n");
        $this->expect([250]);
        $this->command('QUIT', [221]);
        fclose($this->socket);
    }

    private function validateSmtpHost(string $host): void
    {
        $host = strtolower(trim($host));
        if ($host === '') {
            throw new RuntimeException('Host SMTP não configurado.');
        }

        // Bloquear loopback, link-local e RFC-1918
        $blockedPatterns = [
            '/^localhost$/i',
            '/^127\./i',
            '/^::1$/',
            '/^0\./i',
            '/^10\./i',
            '/^172\.(1[6-9]|2\d|3[01])\./i',
            '/^192\.168\./i',
            '/^169\.254\./i',
            '/^fc00:/i',
            '/^fe80:/i',
            '/^metadata\.google\.internal$/i',
        ];

        foreach ($blockedPatterns as $pattern) {
            if (preg_match($pattern, $host)) {
                throw new RuntimeException('Host SMTP inválido.');
            }
        }
    }

    private function sanitizeSmtpAddress(string $email): string
    {
        // Remove caracteres que poderiam injetar comandos no protocolo SMTP
        return preg_replace('/[\r\n<>]/', '', $email) ?? '';
    }

    private function buildMessage(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody): string
    {
        $boundary = 'b_' . bin2hex(random_bytes(12));
        $headers = [
            'MIME-Version: 1.0',
            'Date: ' . date(DATE_RFC2822),
            'From: ' . $this->address($this->settings['fromEmail'], $this->settings['fromName']),
            'To: ' . $this->address($toEmail, $toName),
            'Subject: ' . $this->encodeHeader($subject),
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];

        return implode("\r\n", $headers) . "\r\n\r\n"
            . '--' . $boundary . "\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n"
            . $this->dotEscape($textBody) . "\r\n"
            . '--' . $boundary . "\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n"
            . $this->dotEscape($htmlBody) . "\r\n"
            . '--' . $boundary . '--';
    }

    private function command(string $command, array $expected): string
    {
        fwrite($this->socket, $command . "\r\n");
        return $this->expect($expected);
    }

    private function expect(array $expected): string
    {
        $response = '';
        while (($line = fgets($this->socket, 515)) !== false) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $expected, true)) {
            throw new RuntimeException('Resposta SMTP inesperada: ' . trim($response));
        }
        return $response;
    }

    private function address(string $email, string $name): string
    {
        $name = trim($name);
        return ($name ? $this->encodeHeader($name) . ' ' : '') . '<' . $this->sanitizeSmtpAddress($email) . '>';
    }

    private function encodeHeader(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private function dotEscape(string $body): string
    {
        $body = str_replace(["\r\n", "\r"], "\n", $body);
        $body = preg_replace('/^\./m', '..', $body) ?? $body;

        return str_replace("\n", "\r\n", $body);
    }
}
