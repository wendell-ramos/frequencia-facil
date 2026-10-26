<?php

declare(strict_types=1);

use App\Config\Environment;
use App\Controllers\AlunoController;
use App\Controllers\HealthController;
use App\Controllers\HistoricoController;
use App\Controllers\HomeController;
use App\Controllers\PaginaController;
use App\Controllers\PresencaController;
use App\Http\ApiException;
use App\Http\JsonResponse;
use App\Http\AuthSession;
use App\Controllers\AuthController;

try {
    require __DIR__ . '/vendor/autoload.php';
    Environment::load(__DIR__ . '/.env');

    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $basePath = rtrim(getenv('APP_BASE_PATH') ?: '', '/');
    if ($basePath !== '' && ($path === $basePath || str_starts_with($path, $basePath . '/'))) {
        $path = substr($path, strlen($basePath));
    }
    $path = '/' . trim($path, '/');

    $rotas = ['/', '/index.php', '/health', '/login', '/alunos', '/chamada', '/historico'];
    $paginasPublicas = ['/health', '/login'];

    if ($path !== '/health') {
        AuthSession::iniciar();
    }
    $paginaProtegida = in_array($path, $rotas, true) && !in_array($path, $paginasPublicas, true);
    $recursoProtegido = preg_match('#^/api/alunos(?:/([0-9]+)(?:/presencas)?)?$#D', $path)
        || $path === '/api/presencas' || $paginaProtegida;

    // Navegador abrindo uma tela sem sessão: vai para o login e volta para a mesma tela depois.
    // Clientes de API (curl, testes, fetch) continuam recebendo o 401 em JSON.
    $pedeHtml = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'text/html');
    if ($paginaProtegida && $pedeHtml && $_SERVER['REQUEST_METHOD'] === 'GET' && AuthSession::professor() === null) {
        $volta = $path . (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? '?' . $_SERVER['QUERY_STRING'] : '');
        header('Location: ' . $basePath . '/login' . ($path === '/' ? '' : '?volta=' . rawurlencode($volta)), true, 302);
        return;
    }

    if ($recursoProtegido) {
        AuthSession::exigir();
        if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            AuthSession::validarCsrf();
        }
    }

    if (preg_match('#^/api/auth/(sessao|login|logout)$#D', $path, $matches)) {
        (new AuthController())->handle($_SERVER['REQUEST_METHOD'], $matches[1]);
    } elseif (preg_match('#^/api/alunos(?:/([0-9]+))?$#D', $path, $matches)) {
        (new AlunoController())->handle($_SERVER['REQUEST_METHOD'], $matches[1] ?? null);
    } elseif (preg_match('#^/api/alunos/([0-9]+)/presencas$#D', $path, $matches)) {
        (new HistoricoController())->handle($_SERVER['REQUEST_METHOD'], $matches[1]);
    } elseif ($path === '/api/presencas') {
        (new PresencaController())->handle($_SERVER['REQUEST_METHOD']);
    } elseif (!in_array($path, $rotas, true)) {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['mensagem' => 'Rota nao encontrada.']);
    } elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        header('Allow: GET');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['mensagem' => 'Metodo nao permitido.']);
    } elseif ($path === '/health') {
        (new HealthController())->index();
    } elseif ($path === '/login') {
        (new PaginaController())->login();
    } elseif ($path === '/alunos') {
        (new PaginaController())->alunos();
    } elseif ($path === '/chamada') {
        (new PaginaController())->chamada();
    } elseif ($path === '/historico') {
        (new PaginaController())->historico();
    } else {
        (new HomeController())->index();
    }
} catch (ApiException $error) {
    $resposta = ['mensagem' => $error->getMessage()];
    if ($error->erros !== []) {
        $resposta['erros'] = $error->erros;
    }
    JsonResponse::send($resposta, $error->status);
} catch (Throwable $error) {
    error_log('Falha na aplicacao: ' . $error->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['mensagem' => 'Erro interno ao iniciar a aplicacao.']);
}
