<?php

declare(strict_types=1);

namespace App\Core;

class Router
{
    private array $routes = [];
    private Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function get(string $path, callable|array $handler, array $middleware = []): self
    {
        $this->addRoute('GET', $path, $handler, $middleware);
        return $this;
    }

    public function post(string $path, callable|array $handler, array $middleware = []): self
    {
        $this->addRoute('POST', $path, $handler, $middleware);
        return $this;
    }

    public function put(string $path, callable|array $handler, array $middleware = []): self
    {
        $this->addRoute('PUT', $path, $handler, $middleware);
        return $this;
    }

    public function patch(string $path, callable|array $handler, array $middleware = []): self
    {
        $this->addRoute('PATCH', $path, $handler, $middleware);
        return $this;
    }

    public function delete(string $path, callable|array $handler, array $middleware = []): self
    {
        $this->addRoute('DELETE', $path, $handler, $middleware);
        return $this;
    }

    private function addRoute(string $method, string $path, callable|array $handler, array $middleware): void
    {
        $pattern = $this->pathToRegex($path);
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'pattern' => $pattern,
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    private function pathToRegex(string $path): string
    {
        // Échapper les segments littéraux (ex. « qr.png ») pour que « . » ne signifie pas « n’importe quel caractère ».
        $parts = preg_split('/(\{[a-zA-Z_]+\})/', $path, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $regex = '';
        foreach ($parts ?: [] as $part) {
            if (preg_match('/^\{([a-zA-Z_]+)\}$/', $part, $m) === 1) {
                $regex .= '(?P<' . $m[1] . '>[^/]+)';
            } else {
                $regex .= preg_quote($part, '#');
            }
        }

        return '#^' . $regex . '$#';
    }

    public function dispatch(): Response
    {
        $method = $this->request->method();
        $path = $this->request->path();

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            if (preg_match($route['pattern'], $path, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $handler = $route['handler'];
                $middleware = $route['middleware'] ?? [];

                $runner = function (Request $req) use ($handler, $params): Response {
                    if (is_array($handler)) {
                        [$class, $action] = $handler;
                        try {
                            $controller = \App\Core\Container::get($class);
                        } catch (\InvalidArgumentException $e) {
                            // Ne pas confondre « contrôleur absent du Container » avec « dépendance manquante » :
                            // dans le second cas, new $class() masque l'erreur (constructeur avec injection).
                            $unknownController = 'Unknown service: ' . $class;
                            if ($e->getMessage() !== $unknownController) {
                                throw $e;
                            }
                            $controller = new $class();
                        }
                        $response = $controller->$action($req, $params);
                    } else {
                        $response = $handler($req, $params);
                    }
                    return $response instanceof Response ? $response : new Response();
                };

                foreach (array_reverse($middleware) as $m) {
                    $next = $runner;
                    $instance = is_string($m) ? new $m() : $m;
                    $runner = fn (Request $req) => $instance($req, $next);
                }

                $response = $runner($this->request);
                if ($response instanceof Response) {
                    return $response;
                }
            }
        }

        return (new Response())
            ->setStatusCode(404)
            ->header('Content-Type', 'text/html; charset=utf-8')
            ->setBody($this->render404());
    }

    private function render404(): string
    {
        $path = base_path('views/errors/404.php');
        if (is_file($path)) {
            ob_start();
            require $path;
            return ob_get_clean() ?: 'Not Found';
        }
        return '<h1>404 Not Found</h1>';
    }
}
