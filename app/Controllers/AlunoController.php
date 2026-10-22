<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Http\ApiException;
use App\Http\JsonResponse;
use App\Services\AlunoService;
use JsonException;
use stdClass;

final class AlunoController
{
    public function handle(string $method, ?string $id): void
    {
        $permitidos = $id === null ? ['GET', 'POST'] : ['GET', 'PUT', 'DELETE'];
        if (!in_array($method, $permitidos, true)) {
            header('Allow: ' . implode(', ', $permitidos));
            throw new ApiException('Método não permitido.', 405);
        }
        if ($id !== null && (strlen($id) > 10 || (int) $id < 1 || (int) $id > 4294967295)) {
            throw new ApiException('Aluno não encontrado.', 404);
        }

        $dados = in_array($method, ['POST', 'PUT'], true) ? $this->lerDados() : [];
        $busca = $_GET['busca'] ?? '';
        if ($method === 'GET' && $id === null && (!is_string($busca) || !preg_match('/^.{0,150}$/us', $busca))) {
            throw new ApiException('Busca inválida.', 422, ['busca' => 'Informe um texto de até 150 caracteres.']);
        }

        $service = new AlunoService(Database::getConnection());
        if ($method === 'GET') {
            JsonResponse::send($id === null ? $service->listar(trim($busca)) : $service->buscar((int) $id));
        } elseif ($method === 'POST') {
            $aluno = $service->criar($dados);
            header('Location: ' . rtrim(getenv('APP_BASE_PATH') ?: '', '/') . '/api/alunos/' . $aluno['id']);
            JsonResponse::send($aluno, 201);
        } elseif ($method === 'PUT') {
            JsonResponse::send($service->atualizar((int) $id, $dados));
        } else {
            $service->excluir((int) $id);
            JsonResponse::send(null, 204);
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
            throw new ApiException('Envie um objeto JSON com os dados do aluno.', 400);
        }
        return (array) $dados;
    }
}
