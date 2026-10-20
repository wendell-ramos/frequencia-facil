<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use Throwable;

class HealthController
{
    public function index(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            Database::getConnection()->query('SELECT 1');
            echo json_encode(['status' => 'ok', 'banco' => 'conectado']);
        } catch (Throwable $error) {
            error_log('Falha no health check: ' . $error->getMessage());
            http_response_code(503);
            echo json_encode(['status' => 'indisponivel', 'mensagem' => 'Banco de dados indisponivel.']);
        }
    }
}
