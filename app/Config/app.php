<?php

declare(strict_types=1);

return [
    'name' => env('APP_NAME', 'Athena'),
    'env' => env('APP_ENV', 'production'),
    'debug' => filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOL),
    'url' => rtrim((string) env('APP_URL', ''), '/'),
    'timezone' => env('APP_TIMEZONE', 'Europe/Paris'),
    'locale' => env('APP_LOCALE', 'fr'),

    'maintenance' => [
        'enabled' => filter_var(env('MAINTENANCE_ENABLED', false), FILTER_VALIDATE_BOOL),
        'message' => env('MAINTENANCE_MESSAGE', 'Maintenance en cours. Merci de réessayer dans quelques minutes.'),
        'allowed_ips' => array_filter(array_map('trim', explode(',', (string) env('MAINTENANCE_ALLOWED_IPS', '')))),
    ],

    'log' => [
        'channel' => env('LOG_CHANNEL', 'file'),
        'level' => env('LOG_LEVEL', 'warning'),
        'path' => env('LOG_PATH', 'storage/logs'),
    ],
];
