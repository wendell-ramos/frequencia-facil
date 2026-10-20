<?php
namespace App\Core;

/**
 * Roteador simples.
 *   $router->get('/api/alunos/{id}', [AlunoController::class, 'show']);
 * O {id} chega em $request->param('id').
 */
final class Router
{
    /** @var array<int, array{method:string, regex:string, handler:callable|array}> */
    private array $routes = [];

    public function get(string $path, callable|array $handler): void    { $this->add('GET', $path, $handler); }
    public function post(string $path, callable|array $handler): void   { $this->add('POST', $path, $handler); }
    public function put(string $path, callable|array $handler): void    { $this->add('PUT', $path, $handler); }
    public function delete(string $path, callable|array $handler): void { $this->add('DELETE', $path, $handler); }

    public function add(string $method, string $path, callable|array $handler): void
    {
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', rtrim($path, '/')) . '$#';
        $this->routes[] = ['method' => $method, 'regex' => $regex, 'handler' => $handler];
    }

    public function dispatch(Request $request): Response
    {
        $pathMatched = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $request->path, $m)) {
                continue;
            }
            $pathMatched = true;
            if ($route['method'] !== $request->method) {
                continue;
            }
            $request->params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);

            $handler = $route['handler'];
            if (is_array($handler) && is_string($handler[0])) {
                $handler = [new $handler[0](), $handler[1]];
            }
            return $handler($request);
        }

        return $pathMatched
            ? Response::error('Método não permitido', 405)
            : Response::error('Rota não encontrada', 404);
    }
}
