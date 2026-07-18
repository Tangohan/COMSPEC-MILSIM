<?php

declare(strict_types=1);

/**
 * Accès web à run-migrations.php (UI + authentification).
 *
 * Mot de passe : défini ci-dessous (empreinte) ou via MIGRATIONS_WEB_PASSWORD (.env).
 */
return [
    /** Empreinte SHA-256(pepper + mot de passe). Pepper fixe côté code. */
    'password_digest' => '51631febe8af5b4fb8cde543503dae24e450e53ed85d558a56953f1e893bad64',
    'password_pepper' => 'athena-migrations-web-v1',

    /** Surcharge claire via environnement (prioritaire si non vide). */
    'env_password_key' => 'MIGRATIONS_WEB_PASSWORD',

    'session_name' => 'athena_mig_web',
    'session_auth_key' => 'migrations_web_ok',
    'session_attempts_key' => 'migrations_web_attempts',
    'session_lock_until_key' => 'migrations_web_lock_until',

    'max_attempts' => 5,
    'lockout_seconds' => 900,

    'log_relative' => 'storage/logs/migrations-last-run.json',
    'log_text_relative' => 'storage/logs/migrations-last-run.txt',
];
