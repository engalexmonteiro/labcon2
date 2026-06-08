<?php

namespace App\Support;

final class Response
{
    public static function json(array $data, int $status = 200): void
    {
        json_response($data, $status);
    }

    public static function error(string $message, int $status = 400): void
    {
        json_error($message, $status);
    }
}
