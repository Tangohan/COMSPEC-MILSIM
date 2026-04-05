<?php

declare(strict_types=1);

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        return $_ENV[$key] ?? $default;
    }
}

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        $root = dirname(__DIR__, 2);
        return $root . ($path ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : '');
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        $config = $GLOBALS['__app_config'] ?? null;
        if ($config === null) {
            $config = require base_path('app/Config/app.php');
            $config = ['app' => $config];
        }
        $keys = explode('.', $key);
        $value = $config;
        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return $default;
            }
            $value = $value[$k];
        }
        return $value;
    }
}

if (!function_exists('email_config')) {
    /**
     * @return array<string, mixed>
     */
    function email_config(): array
    {
        static $cfg = null;
        if ($cfg === null) {
            $path = base_path('config/email.php');
            $cfg = is_file($path) ? require $path : [];
        }

        return $cfg;
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        $base = rtrim(env('APP_URL', ''), '/');
        $prefix = rtrim((string) env('APP_BASE_PATH', ''), '/');
        // Si APP_BASE_PATH non défini (ex. .env non chargé), déduire /public depuis SCRIPT_NAME
        if ($prefix === '' && isset($_SERVER['SCRIPT_NAME']) && str_contains((string) $_SERVER['SCRIPT_NAME'], '/public/')) {
            $prefix = '/public';
        }
        $base = $base . $prefix;
        return $base . ($path ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('can')) {
    function can(string $permission): bool
    {
        $gate = \App\Core\Gate::getInstance();
        return $gate->allows($permission);
    }
}

if (!function_exists('detect_current_module')) {
    /**
     * Module métier pour les scopes `module:` (aligné sur routes/web.php).
     */
    function detect_current_module(string $path): ?string
    {
        if (preg_match('#^/c/[^/]+/forum#', $path) === 1) {
            return 'forum';
        }
        if (str_starts_with($path, '/forum') || str_starts_with($path, '/api/forum')) {
            return 'forum';
        }

        $atakPrefixes = [
            '/atak', '/api/atak', '/api/markers', '/api/units', '/api/chat', '/api/pings',
            '/api/nine-line', '/api/cas', '/api/recon', '/api/map-shapes', '/api/flight-manifest',
            '/api/intel', '/api/fire-support', '/api/danger-zones', '/api/logistics', '/api/replay', '/api/iff',
        ];
        foreach ($atakPrefixes as $pre) {
            if ($path === $pre || str_starts_with($path, $pre . '/')) {
                return 'atak';
            }
        }

        if (str_starts_with($path, '/documents')) {
            return 'documents';
        }
        if (str_starts_with($path, '/courrier')) {
            return 'courrier';
        }
        if (
            str_starts_with($path, '/formations')
            || str_starts_with($path, '/api/training')
            || str_starts_with($path, '/admin/training')
        ) {
            return 'training';
        }
        if (str_starts_with($path, '/admin')) {
            return 'admin';
        }

        return null;
    }
}

if (is_file(base_path('app/Support/navigation_menu.php'))) {
    require_once base_path('app/Support/navigation_menu.php');
}

if (!function_exists('community_display_name')) {
    /**
     * Libellé UI pour le tenant système (slug `default`) : jamais « Default Organisation ».
     *
     * @param array{name?: string, slug?: string} $tenantOrMembershipRow
     */
    function community_display_name(array $tenantOrMembershipRow): string
    {
        if (($tenantOrMembershipRow['slug'] ?? '') === 'default') {
            return 'Pas d\'organisation';
        }

        return (string) ($tenantOrMembershipRow['name'] ?? '');
    }
}

if (is_file(base_path('app/Support/portal_header.php'))) {
    require_once base_path('app/Support/portal_header.php');
}

require __DIR__ . '/forum_helpers.php';
