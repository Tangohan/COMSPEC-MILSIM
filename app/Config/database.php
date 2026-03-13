<?php

declare(strict_types=1);

/**
 * Lit les variables DB_* depuis .env à la racine du projet (secours si env() est vide).
 */
function database_env(string $key, string $default = ''): string
{
    $v = env($key, $default);
    if ($v !== '') {
        return $v;
    }
    $root = dirname(__DIR__, 2);
    $path = $root . DIRECTORY_SEPARATOR . '.env';
    if (!is_file($path) || !is_readable($path)) {
        return $default;
    }
    $raw = file_get_contents($path);
    if ($raw === false) {
        return $default;
    }
    if (strpos($raw, "\xEF\xBB\xBF") === 0) {
        $raw = substr($raw, 3);
    }
    $raw = str_replace(["\r\n", "\r"], "\n", $raw);
    $search = $key . '=';
    $len = strlen($search);
    foreach (explode("\n", $raw) as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }
        if (strncasecmp($line, $search, $len) === 0) {
            return trim(trim(substr($line, $len), " \t\"'"));
        }
    }
    return $default;
}

return [
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => database_env('DB_HOST', 'localhost'),
            'port' => database_env('DB_PORT', '3306'),
            'database' => database_env('DB_NAME', ''),
            'username' => database_env('DB_USER', ''),
            'password' => database_env('DB_PASSWORD', ''),
            'charset' => database_env('DB_CHARSET', 'utf8mb4'),
            'collation' => database_env('DB_COLLATION', 'utf8mb4_unicode_ci'),
        ],
    ],
];
