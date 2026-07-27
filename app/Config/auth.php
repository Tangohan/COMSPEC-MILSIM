<?php

declare(strict_types=1);

/**
 * Cookie de session en `secure` par défaut dès que l'application tourne en production :
 * un déploiement qui recopie .env.example sans y penser ne doit pas émettre un cookie
 * de session transmissible en clair. SESSION_SECURE_COOKIE reste prioritaire lorsqu'elle
 * est explicitement renseignée (utile en préproduction sans TLS).
 */
$appEnv = strtolower(trim((string) env('APP_ENV', ''))); // production | prod | local | …
$secureCookieDefault = $appEnv === 'production' || $appEnv === 'prod';
$secureCookieRaw = env('SESSION_SECURE_COOKIE', null);

return [
    'session_lifetime' => (int) env('SESSION_LIFETIME', 120),
    'session_secure_cookie' => $secureCookieRaw === null || $secureCookieRaw === ''
        ? $secureCookieDefault
        : filter_var($secureCookieRaw, FILTER_VALIDATE_BOOL),
    'password_algo' => PASSWORD_ARGON2ID,
    'login_max_attempts' => 5,
    'login_lockout_minutes' => 15,
];
