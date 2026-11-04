<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Http\ApiException;
use App\Http\JsonResponse;
use App\Services\RelatorioService;

final class RelatorioController
{
    public function handle(string $method): void
    {
        if ($method !== 'GET') {
            header('Allow: GET');
            throw new ApiException('Método não permitido.', 405);
        }
        JsonResponse::send((new RelatorioService(Database::getConnection()))->consultar($_GET));
    }
}
