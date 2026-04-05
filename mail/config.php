<?php

/**
 * @deprecated Utiliser la configuration centralisée : `config/email.php` et variables d’environnement (voir `.env.example`).
 * Ce fichier est conservé pour compatibilité ; ne pas y placer de secrets.
 */
return [
    'from_email' => env('MAIL_FROM_ADDRESS', env('MAIL_FROM', 'noreply@localhost')),
    'from_name' => env('MAIL_FROM_NAME', 'Athena'),
    'smtp' => [
        'enabled' => env('MAIL_MAILER', 'file') === 'smtp',
        'host' => env('MAIL_HOST', ''),
        'port' => (int) env('MAIL_PORT', 587),
        'secure' => env('MAIL_ENCRYPTION', 'tls'),
        'username' => env('MAIL_USERNAME', ''),
        'password' => env('MAIL_PASSWORD', ''),
    ],
    'bcc_admins' => false,
];
