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
