<?php

declare(strict_types=1);

use App\Config\Environment;
use App\Controllers\HealthController;
use App\Controllers\HomeController;
use App\Controllers\PaginaController;

try {
    require __DIR__ . '/vendor/autoload.php';
    Environment::load(__DIR__ . '/.env');

    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $basePath = rtrim(getenv('APP_BASE_PATH') ?: '', '/');
    if ($basePath !== '' && ($path === $basePath || str_starts_with($path, $basePath . '/'))) {
        $path = substr($path, strlen($basePath));
    }
    $path = '/' . trim($path, '/');

    $rotas = ['/', '/index.php', '/health', '/alunos', '/chamada', '/historico'];

    if (!in_array($path, $rotas, true)) {
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
    } elseif ($path === '/alunos') {
        (new PaginaController())->alunos();
    } elseif ($path === '/chamada') {
        (new PaginaController())->chamada();
    } elseif ($path === '/historico') {
        (new PaginaController())->historico();
    } else {
        (new HomeController())->index();
    }
} catch (Throwable $error) {
    error_log('Falha na aplicacao: ' . $error->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['mensagem' => 'Erro interno ao iniciar a aplicacao.']);
}
