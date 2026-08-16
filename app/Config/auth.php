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
    // Durée en minutes. 300 = 5 h : une séance d'administration ou une opération ne doit
    // pas être coupée en pleine saisie. Le serveur applique la même durée côté données
    // (cf. App\Core\Session::start()), sans quoi le cookie survivrait à la session.
    'session_lifetime' => max(15, (int) env('SESSION_LIFETIME', 1440)),
    'session_secure_cookie' => $secureCookieRaw === null || $secureCookieRaw === ''
        ? $secureCookieDefault
        : filter_var($secureCookieRaw, FILTER_VALIDATE_BOOL),
    // Chemin absolu hors arbre FTP recommandé (ex. /home/uXXXX/tmp/athena_sessions).
    // Vide = storage/sessions dans l'application (exclu du sync FTP).
    'session_save_path' => trim((string) env('SESSION_SAVE_PATH', '')),
    'password_algo' => PASSWORD_ARGON2ID,
    'login_max_attempts' => 5,
    'login_lockout_minutes' => 15,
];
