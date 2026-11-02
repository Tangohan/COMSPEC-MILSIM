<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Request;
use App\Core\Router;
use PHPUnit\Framework\TestCase;

/**
 * Chaque route [Classe, 'méthode'] doit pointer vers une méthode publique existante, et chaque middleware
 * doit pouvoir être instancié par le Router (`new $m()`) : sinon la page tombe en erreur 500 au premier clic.
 * Les routes sont chargées comme en production (routes/web.php appliqué à un vrai Router).
 */
final class RoutedControllerMethodsExistTest extends TestCase
{
    /** @return list<array<string, mixed>> */
    private static function routes(): array
    {
        $server = $_SERVER;
        // Un autre test peut avoir retiré $_GET / $_POST : Request les copie dans des propriétés typées array.
        $hadGet = isset($_GET);
        $hadPost = isset($_POST);
        $_GET ??= [];
        $_POST ??= [];
        try {
            $router = new Router(new Request());
            $register = require dirname(__DIR__, 2) . '/routes/web.php';
            $register($router);
        } finally {
            $_SERVER = $server;
            if (!$hadGet) {
                unset($_GET);
            }
            if (!$hadPost) {
                unset($_POST);
            }
        }

        return (new \ReflectionProperty(Router::class, 'routes'))->getValue($router);
    }

    public function testEveryRouteHandlerIsAPublicControllerMethod(): void
    {
        $routes = self::routes();
        self::assertGreaterThan(100, count($routes));
        $problems = [];
        foreach ($routes as $route) {
            $handler = $route['handler'];
            if (!is_array($handler)) {
                continue;
            }
            [$class, $method] = $handler;
            $where = $route['method'] . ' ' . $route['path'];
            if (!is_string($class) || !class_exists($class)) {
                $problems[] = $where . ' : classe introuvable ' . (is_string($class) ? $class : get_debug_type($class));
                continue;
            }
            if (!method_exists($class, (string) $method)) {
                $problems[] = $where . ' : méthode absente ' . $class . '::' . $method . '()';
                continue;
            }
            $ref = new \ReflectionMethod($class, (string) $method);
            if (!$ref->isPublic() || $ref->isStatic() || $ref->getDeclaringClass()->isInterface()) {
                $problems[] = $where . ' : ' . $class . '::' . $method . '() doit être publique et non statique';
            }
            if ((new \ReflectionClass($class))->isAbstract()) {
                $problems[] = $where . ' : ' . $class . ' est abstraite';
            }
            if ($ref->getNumberOfRequiredParameters() > 2) {
                $problems[] = $where . ' : ' . $class . '::' . $method . '() attend plus que ($request, $params)';
            }
        }

        self::assertSame([], $problems);
    }

    public function testEveryMiddlewareCanBeBuiltByTheRouter(): void
    {
        $problems = [];
        $seen = [];
        foreach (self::routes() as $route) {
            foreach ($route['middleware'] ?? [] as $middleware) {
                if (!is_string($middleware) || isset($seen[$middleware])) {
                    continue;
                }
                $seen[$middleware] = true;
                if (!class_exists($middleware)) {
                    $problems[] = 'middleware introuvable : ' . $middleware;
                    continue;
                }
                $ctor = (new \ReflectionClass($middleware))->getConstructor();
                if ($ctor !== null && $ctor->getNumberOfRequiredParameters() > 0) {
                    $problems[] = 'middleware avec dépendances obligatoires (le Router fait new $m()) : ' . $middleware;
                }
                if (!method_exists($middleware, '__invoke')) {
                    $problems[] = 'middleware sans __invoke : ' . $middleware;
                }
            }
        }

        self::assertSame([], $problems);
    }
}
