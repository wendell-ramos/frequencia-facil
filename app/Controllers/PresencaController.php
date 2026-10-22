<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Http\ApiException;
use App\Http\JsonResponse;
use App\Services\PresencaService;
use App\Services\PresencaValidator;
use JsonException;
use stdClass;

final class PresencaController
{
    public function handle(string $method): void
    {
        if (!in_array($method, ['GET', 'POST'], true)) {
            header('Allow: GET, POST');
            throw new ApiException('Método não permitido.', 405);
        }
        if ($method === 'GET') {
            $data = PresencaValidator::data($_GET['data'] ?? PresencaValidator::hoje());
            $service = new PresencaService(Database::getConnection());
            JsonResponse::send($service->consultar($data));
        } else {
            $dados = $this->lerDados();
            $service = new PresencaService(Database::getConnection());
            JsonResponse::send($service->salvar($dados));
        }
    }

    private function lerDados(): array
    {
        $contentType = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
        if ($contentType !== 'application/json') {
            throw new ApiException('Envie os dados com Content-Type application/json.', 415);
        }
        try {
            $dados = json_decode(file_get_contents('php://input'), false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ApiException('JSON inválido.', 400);
        }
        if (!$dados instanceof stdClass) {
            throw new ApiException('Envie um objeto JSON com a data e os registros da chamada.', 400);
        }
        return (array) $dados;
    }
}
