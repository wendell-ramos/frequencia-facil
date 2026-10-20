<?php
// Ponto de entrada da API. O Apache manda para cá toda URL que não é arquivo
// estático (ver .htaccess). As telas (HTML/CSS/JS) são servidas direto.

declare(strict_types=1);

use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;

date_default_timezone_set(getenv('TZ') ?: 'America/Sao_Paulo');

$root = dirname(__DIR__);

if (file_exists("$root/vendor/autoload.php")) {
    require "$root/vendor/autoload.php";
} else {
    // Fallback para rodar sem `composer install` (ex.: php -S local)
    spl_autoload_register(function (string $class) use ($root) {
        if (str_starts_with($class, 'App\\')) {
            $file = "$root/src/" . str_replace('\\', '/', substr($class, 4)) . '.php';
            if (is_file($file)) require $file;
        }
    });
}

$request = Request::fromGlobals();

try {
    $router = new Router();
    (require "$root/src/routes.php")($router);
    $response = $router->dispatch($request);
} catch (Throwable $e) {
    Logger::error('Erro não tratado', [
        'method'    => $request->method,
        'path'      => $request->path,
        'exception' => get_class($e),
        'message'   => $e->getMessage(),
        'file'      => $e->getFile() . ':' . $e->getLine(),
    ]);
    $response = Response::error('Erro interno no servidor', 500);
}

if ($response->status >= 400) {
    Logger::warning('Resposta com erro', [
        'method' => $request->method, 'path' => $request->path, 'status' => $response->status,
    ]);
}

$response->send();
