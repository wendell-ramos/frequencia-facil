<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Http\ApiException;
use App\Http\JsonResponse;
use App\Services\HistoricoService;

final class HistoricoController
{
    public function handle(string $method, string $id): void
    {
        if ($method !== 'GET') {
            header('Allow: GET');
            throw new ApiException('Método não permitido.', 405);
        }
        if (strlen($id) > 10 || (int) $id < 1 || (int) $id > 4294967295) {
            throw new ApiException('Aluno não encontrado.', 404);
        }
        $service = new HistoricoService(Database::getConnection());
        JsonResponse::send($service->consultar((int) $id, $_GET));
    }
}
