<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Http\ApiException;
use App\Http\AuthSession;
use App\Http\JsonResponse;
use App\Services\ProfessorService;
use JsonException;
use stdClass;

final class AuthController
{
    public function handle(string $method, string $acao): void
    {
        $permitido = $acao === 'sessao' ? 'GET' : 'POST';
        if ($method !== $permitido) {
            header('Allow: ' . $permitido);
            throw new ApiException('Método não permitido.', 405);
        }
        if ($acao === 'sessao') {
            $professor = AuthSession::professor();
            JsonResponse::send(['autenticado' => $professor !== null, 'professor' => $professor, 'csrfToken' => AuthSession::csrf()]);
            return;
        }
        if ($acao === 'logout') {
            AuthSession::exigir();
            AuthSession::validarCsrf();
            AuthSession::sair();
            JsonResponse::send(null, 204);
            return;
        }
        AuthSession::validarCsrf();
        if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') {
            throw new ApiException('Envie os dados com Content-Type application/json.', 415);
        }
        try {
            $entrada = file_get_contents('php://input', false, null, 0, 4097);
            if (strlen($entrada) > 4096) {
                throw new ApiException('Corpo da requisição excede 4096 bytes.', 413);
            }
            $dados = json_decode($entrada, false, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ApiException('JSON inválido.', 400);
        }
        if (!$dados instanceof stdClass) {
            throw new ApiException('Envie um objeto JSON.', 400);
        }
        $professor = (new ProfessorService(Database::getConnection()))->autenticar((array) $dados);
        AuthSession::entrar($professor);
        JsonResponse::send(['autenticado' => true, 'professor' => $professor, 'csrfToken' => AuthSession::csrf()]);
    }
}
