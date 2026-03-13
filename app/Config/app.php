<?php

declare(strict_types=1);

return [
    'name' => env('APP_NAME', 'Athena'),
    'env' => env('APP_ENV', 'production'),
    'debug' => filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOL),
    'url' => rtrim(env('APP_URL', ''), '/'),
    'timezone' => 'Europe/Paris',
];
