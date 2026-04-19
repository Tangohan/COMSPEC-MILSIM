<?php

declare(strict_types=1);

use App\Core\Request;
use App\Core\Router;

require_once __DIR__ . '/../app/Core/Request.php';
require_once __DIR__ . '/../app/Core/Router.php';

/** @return list<array{method:string,path:string,middleware:list<string>}> */
function loadRoutes(): array
{
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/';

    $router = new Router(new Request());
    $registrar = require __DIR__ . '/../routes/web.php';
    $registrar($router);

    $ref = new ReflectionClass($router);
    $prop = $ref->getProperty('routes');
    $prop->setAccessible(true);
    /** @var array<int,array<string,mixed>> $raw */
    $raw = $prop->getValue($router);

    $rows = [];
    foreach ($raw as $route) {
        $mw = [];
        foreach (($route['middleware'] ?? []) as $item) {
            if (is_string($item)) {
                $mw[] = $item;
            } elseif (is_object($item)) {
                $mw[] = get_class($item);
            }
        }

        $rows[] = [
            'method' => (string) ($route['method'] ?? ''),
            'path' => (string) ($route['path'] ?? ''),
            'middleware' => $mw,
        ];
    }

    return $rows;
}

/** @return list<array{path:string,requires_permission:bool}> */
function loadNavigationLinks(): array
{
    /** @var array<string,mixed> $nav */
    $nav = require __DIR__ . '/../config/navigation.php';
    $links = [];

    $walk = function (array $node) use (&$walk, &$links): void {
        $hasPermission = isset($node['permission']) || isset($node['any_permissions']);

        if (isset($node['path']) && is_string($node['path'])) {
            $links[] = [
                'path' => $node['path'],
                'requires_permission' => $hasPermission,
            ];
        }

        if (isset($node['cta_path']) && is_string($node['cta_path'])) {
            $links[] = [
                'path' => $node['cta_path'],
                'requires_permission' => isset($node['cta_permission']),
            ];
        }

        foreach ($node as $value) {
            if (is_array($value)) {
                $walk($value);
            }
        }
    };

    $walk($nav);

    $uniq = [];
    foreach ($links as $entry) {
        $uniq[$entry['path']] = ($uniq[$entry['path']] ?? false) || $entry['requires_permission'];
    }

    $out = [];
    foreach ($uniq as $path => $requiresPermission) {
        $out[] = ['path' => (string) $path, 'requires_permission' => (bool) $requiresPermission];
    }

    return $out;
}

function navPathToRoutePath(string $navPath): string
{
    if ($navPath === '') {
        return '/';
    }

    $fragmentStripped = explode('#', $navPath, 2)[0];
    $trimmed = trim((string) parse_url($fragmentStripped, PHP_URL_PATH));
    if ($trimmed === '') {
        return '/';
    }

    return '/' . ltrim($trimmed, '/');
}

function routeMatches(string $routePattern, string $path): bool
{
    if ($routePattern === $path) {
        return true;
    }

    $regex = '#^' . preg_replace('#\\\\\{[^/]+\\\\\}#', '[^/]+', preg_quote($routePattern, '#')) . '$#';

    return (bool) preg_match($regex, $path);
}

$routes = loadRoutes();
$navLinks = loadNavigationLinks();

$errors = [];

$seen = [];
foreach ($routes as $route) {
    $key = $route['method'] . ' ' . $route['path'];
    if (isset($seen[$key])) {
        $errors[] = "Route dupliquée détectée: {$key}";
    }
    $seen[$key] = true;
}

/** @var list<array{method:string,path:string,middleware:list<string>}> $getRoutes */
$getRoutes = array_values(array_filter($routes, static fn (array $r): bool => $r['method'] === 'GET'));

foreach ($navLinks as $entry) {
    $targetPath = navPathToRoutePath($entry['path']);

    $matched = null;
    foreach ($getRoutes as $route) {
        if (routeMatches($route['path'], $targetPath)) {
            $matched = $route;
            break;
        }
    }

    if ($matched === null) {
        $errors[] = "Navigation sans route GET: {$entry['path']} -> {$targetPath}";
        continue;
    }

    if ($entry['requires_permission']) {
        $mw = $matched['middleware'];
        $hasAuthMw = in_array('App\\Middleware\\AuthMiddleware', $mw, true)
            || in_array('App\\Middleware\\ComspecTacticalApiMiddleware', $mw, true)
            || in_array('App\\Middleware\\IntegrationsApiAuthMiddleware', $mw, true);

        if (!$hasAuthMw) {
            $errors[] = "Navigation permissionnée sans middleware d'accès identifié: {$entry['path']} ({$matched['path']})";
        }
    }
}

if ($errors !== []) {
    echo "❌ Vérification routes / accès / navigation: KO\n";
    foreach ($errors as $error) {
        echo " - {$error}\n";
    }
    exit(1);
}

echo "✅ Vérification routes / accès / navigation: OK\n";
echo 'Routes chargées: ' . count($routes) . "\n";
echo 'Liens navigation audités: ' . count($navLinks) . "\n";
