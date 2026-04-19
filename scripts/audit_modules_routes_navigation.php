<?php

declare(strict_types=1);

use App\Core\Request;
use App\Core\Router;

require_once __DIR__ . '/../app/Core/Request.php';
require_once __DIR__ . '/../app/Core/Router.php';

/** @return list<array{method:string,path:string}> */
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
        $rows[] = [
            'method' => (string) ($route['method'] ?? ''),
            'path' => (string) ($route['path'] ?? ''),
        ];
    }

    return $rows;
}

/** @return list<string> */
function loadNavPaths(): array
{
    /** @var array<string,mixed> $nav */
    $nav = require __DIR__ . '/../config/navigation.php';
    $links = [];

    $walk = function (array $node) use (&$walk, &$links): void {
        if (isset($node['path']) && is_string($node['path'])) {
            $links[] = (string) parse_url((string) $node['path'], PHP_URL_PATH);
        }
        if (isset($node['cta_path']) && is_string($node['cta_path'])) {
            $links[] = (string) parse_url((string) $node['cta_path'], PHP_URL_PATH);
        }
        foreach ($node as $value) {
            if (is_array($value)) {
                $walk($value);
            }
        }
    };

    $walk($nav);

    return array_values(array_unique(array_map(
        static fn (string $p): string => '/' . ltrim(trim($p), '/'),
        array_filter($links, static fn (string $p): bool => $p !== '')
    )));
}

/** @param list<array{method:string,path:string}> $routes */
function hasRoutePrefix(array $routes, string $prefix, bool $apiOnly = false): bool
{
    foreach ($routes as $route) {
        $path = $route['path'];
        if ($apiOnly && strncmp($path, '/api/', 5) !== 0) {
            continue;
        }
        if (!$apiOnly && strncmp($path, '/api/', 5) === 0) {
            continue;
        }
        if ($path === $prefix || strncmp($path, $prefix . '/', strlen($prefix) + 1) === 0) {
            return true;
        }
    }

    return false;
}

/** @param list<string> $navPaths */
function hasNavPrefix(array $navPaths, string $prefix): bool
{
    foreach ($navPaths as $path) {
        if ($path === $prefix || strncmp($path, $prefix . '/', strlen($prefix) + 1) === 0) {
            return true;
        }
    }

    return false;
}

$modules = [
    'Authentification & compte' => ['web' => ['/login', '/account'], 'api' => [], 'nav' => ['/account']],
    'Communautés multi-tenant' => ['web' => ['/communities', '/c/{slug}'], 'api' => [], 'nav' => ['/communities']],
    'Personnel & ORBAT' => ['web' => ['/personnel', '/orbat'], 'api' => ['/api/orbat'], 'nav' => ['/personnel', '/orbat']],
    'Documents' => ['web' => ['/documents'], 'api' => [], 'nav' => ['/documents']],
    'Formations' => ['web' => ['/formations', '/formation'], 'api' => ['/api/training'], 'nav' => ['/formations', '/formation']],
    'Forum' => ['web' => ['/forum'], 'api' => ['/api/forum'], 'nav' => ['/forum']],
    'Événements & pointage' => ['web' => ['/evenements', '/pointage'], 'api' => [], 'nav' => ['/evenements', '/pointage']],
    'Messagerie interne' => ['web' => ['/messages'], 'api' => [], 'nav' => ['/messages']],
    'Courrier officiel' => ['web' => ['/courrier'], 'api' => [], 'nav' => ['/courrier']],
    'Équipement / Modpacks / ATAK' => ['web' => ['/equipment', '/modpacks', '/atak'], 'api' => ['/api/atak', '/api/intel', '/api/logistics'], 'nav' => ['/equipment', '/modpacks', '/atak']],
    'Administration système / organisation' => ['web' => ['/admin', '/back-office'], 'api' => ['/api/admin'], 'nav' => ['/back-office']],
    'Interopérations inter-équipes' => ['web' => ['/back-office/cooperation'], 'api' => [], 'nav' => ['/back-office/cooperation']],
];

$routes = loadRoutes();
$navPaths = loadNavPaths();

$errors = [];
$lines = [];
foreach ($modules as $name => $spec) {
    $webOk = false;
    foreach ($spec['web'] as $prefix) {
        $plainPrefix = preg_replace('/\{[^}]+\}/', '', $prefix);
        $plainPrefix = rtrim((string) $plainPrefix, '/');
        if ($plainPrefix === '') {
            $plainPrefix = '/';
        }
        if (hasRoutePrefix($routes, $plainPrefix, false)) {
            $webOk = true;
            break;
        }
    }

    $apiOk = empty($spec['api']);
    if (!$apiOk) {
        foreach ($spec['api'] as $prefix) {
            if (hasRoutePrefix($routes, $prefix, true)) {
                $apiOk = true;
                break;
            }
        }
    }

    $navOk = false;
    foreach ($spec['nav'] as $prefix) {
        if (hasNavPrefix($navPaths, $prefix)) {
            $navOk = true;
            break;
        }
    }

    $status = ($webOk && $apiOk && $navOk) ? 'OK' : 'KO';
    $lines[] = sprintf('- [%s] %s | web:%s api:%s nav:%s', $status, $name, $webOk ? 'yes' : 'no', $apiOk ? 'yes' : 'no', $navOk ? 'yes' : 'no');

    if (!$webOk || !$apiOk || !$navOk) {
        $errors[] = $name;
    }
}

echo "Audit modules — routes/API/navigation\n";
echo 'Routes: ' . count($routes) . " | Nav links: " . count($navPaths) . "\n";
echo implode("\n", $lines) . "\n";

if ($errors !== []) {
    exit(1);
}
