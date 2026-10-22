<?php

declare(strict_types=1);

namespace App\Http;

final class JsonResponse
{
    public static function send(?array $dados, int $status = 200): void
    {
        http_response_code($status);
        if ($status !== 204) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }
    }
}
