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
            $db = Database::getConnection();
            $db->query('SELECT 1');
            // A versão autenticada não está pronta sem a migração de professores.
            $db->query('SELECT id FROM professores LIMIT 0');
            echo json_encode(['status' => 'ok', 'banco' => 'conectado']);
        } catch (Throwable $error) {
            error_log('Falha no health check: ' . $error->getMessage());
            http_response_code(503);
            echo json_encode(['status' => 'indisponivel', 'mensagem' => 'Banco de dados indisponivel.']);
        }
    }
}
