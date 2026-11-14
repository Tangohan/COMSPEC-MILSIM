<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Request;
use App\Core\Response;
use App\Core\Router;

/**
 * Carte des routes pour les tests de non-régression du back-office (audit, lot 7).
 *
 * Charge routes/web.php dans un vrai Router (aucune base de données, aucun serveur), puis permet de :
 * retrouver la route qui répond à une adresse (la première qui correspond, comme Router::dispatch()),
 * lire ses middlewares, et suivre une redirection héritée sans appeler un contrôleur.
 */
final class BackOfficeRouteMap
{
    /** @var list<array{method:string,path:string,pattern:string,handler:mixed,middleware:array}>|null */
    private static ?array $routes = null;

    public static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    /** @return list<array{method:string,path:string,pattern:string,handler:mixed,middleware:array}> */
    public static function routes(): array
    {
        if (self::$routes === null) {
            $server = $_SERVER;
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SERVER['REQUEST_URI'] = '/';
            try {
                $router = new Router(new Request());
                (require self::root() . '/routes/web.php')($router);
                self::$routes = (new \ReflectionProperty(Router::class, 'routes'))->getValue($router);
            } finally {
                $_SERVER = $server;
            }
        }

        return self::$routes;
    }

    /** Page du back-office (gestion de la communauté) ou de la plateforme. */
    public static function isBackOfficePath(string $path): bool
    {
        return preg_match('#^/(back-office|backoffice|admin|api/back-office)(/|$)#', $path) === 1;
    }

    /** Première route qui répond à cette requête (même ordre que Router::dispatch()), null si aucune. */
    public static function match(string $method, string $path): ?array
    {
        foreach (self::routes() as $route) {
            if ($route['method'] === $method && preg_match($route['pattern'], $path) === 1) {
                return $route;
            }
        }

        return null;
    }

    /** Adresse d'exemple pour une route à paramètres : « /a/{id}/b » donne « /a/7/b ». */
    public static function samplePath(string $routePath): string
    {
        return (string) preg_replace('/\{[a-zA-Z_]+\}/', '7', $routePath);
    }

    /** Noms courts des middlewares de la route (« AuthMiddleware »…), « closure » pour une fonction anonyme. */
    public static function middlewareNames(array $route): array
    {
        $out = [];
        foreach ($route['middleware'] as $m) {
            $out[] = is_string($m) ? substr($m, (int) strrpos($m, '\\') + 1) : 'closure';
        }

        return $out;
    }

    /** « GET /back-office/x » : la clé qui identifie une route dans les listes figées. */
    public static function key(array $route): string
    {
        return $route['method'] . ' ' . $route['path'];
    }

    /** Code source de la fonction anonyme ou de la méthode qui répond à la route. */
    public static function handlerSource(array $route): string
    {
        $handler = $route['handler'];
        $reflection = is_array($handler)
            ? new \ReflectionMethod($handler[0], $handler[1])
            : new \ReflectionFunction($handler);
        $file = $reflection->getFileName();
        if ($file === false) {
            return '';
        }
        $lines = file($file) ?: [];

        return implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
    }

    /** Fonction anonyme qui ne fait que rediriger (aucun contrôleur, aucune base). */
    public static function isRedirectClosure(array $route): bool
    {
        if (is_array($route['handler'])) {
            return false;
        }
        $src = self::handlerSource($route);

        return preg_match('/redirect/i', $src) === 1
            && !str_contains($src, 'BackOfficeTabs::dispatch');
    }

    /** Contrôleur dont le seul rôle est de rediriger (ancien favori). */
    public static function isRedirectController(array $route): bool
    {
        return is_array($route['handler'])
            && preg_match('/redirect|legacy/i', $route['handler'][0] . '::' . $route['handler'][1]) === 1;
    }

    public static function isRedirect(array $route): bool
    {
        return self::isRedirectClosure($route) || self::isRedirectController($route);
    }

    /**
     * Réponse d'une route de redirection : [code HTTP, chemin cible sans préfixe de site, requête cible].
     * Seules les fonctions anonymes de redirection et LegacyPersonnelRedirectController sont exécutées.
     *
     * @return array{0:int,1:string,2:string}|null null si la route n'est pas une redirection exécutable ici
     */
    public static function redirectOf(array $route): ?array
    {
        $params = [];
        if (preg_match_all('/\{([a-zA-Z_]+)\}/', $route['path'], $m) > 0) {
            foreach ($m[1] as $name) {
                $params[$name] = '7';
            }
        }
        $server = $_SERVER;
        $get = $_GET;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = self::samplePath($route['path']);
        $_SERVER['QUERY_STRING'] = '';
        $_GET = [];
        try {
            $request = new Request();
            if (self::isRedirectClosure($route)) {
                $response = ($route['handler'])($request, $params);
            } elseif (is_array($route['handler']) && $route['handler'][0] === \App\Controllers\Admin\LegacyPersonnelRedirectController::class) {
                $response = (new \App\Controllers\Admin\LegacyPersonnelRedirectController())->{$route['handler'][1]}($request, $params);
            } else {
                return null;
            }
        } catch (\Throwable) {
            return null;
        } finally {
            $_SERVER = $server;
            $_GET = $get;
        }
        if (!$response instanceof Response) {
            return null;
        }
        $location = (string) $response->headerValue('Location');
        if ($location === '') {
            return null;
        }

        return [$response->statusCode(), self::localPath($location), (string) parse_url($location, PHP_URL_QUERY)];
    }

    /** Chemin d'une adresse du site, sans domaine ni préfixe de déploiement (« /public »). */
    public static function localPath(string $location): string
    {
        $path = (string) parse_url($location, PHP_URL_PATH);
        $base = rtrim((string) parse_url(url(''), PHP_URL_PATH), '/');
        if ($base !== '' && str_starts_with($path, $base . '/')) {
            $path = substr($path, strlen($base));
        }

        return $path === '' ? '/' : '/' . ltrim($path, '/');
    }
}
