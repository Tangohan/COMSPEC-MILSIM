<?php

declare(strict_types=1);

/**
 * Configuration emails transactionnels — envoi via PHPMailer (voir .env.example).
 *
 * MAIL_MAILER : file | smtp | sendgrid | mailgun | ses (sendgrid/mailgun/ses = SMTP relais avec hôte par défaut si MAIL_HOST vide).
 */
return [
    'default_mailer' => env('MAIL_MAILER', 'file'),
    'from_address' => env('MAIL_FROM_ADDRESS', env('MAIL_FROM', 'noreply@localhost')),
    'from_name' => env('MAIL_FROM_NAME', 'Athena'),
    'reply_to' => env('MAIL_REPLY_TO') ?: null,
    'smtp' => [
        'host' => env('MAIL_HOST', ''),
        'port' => (int) env('MAIL_PORT', 587),
        'username' => env('MAIL_USERNAME', ''),
        'password' => env('MAIL_PASSWORD', ''),
        'encryption' => env('MAIL_ENCRYPTION', 'tls'),
        'timeout' => (int) env('MAIL_SMTP_TIMEOUT', 30),
        /** true = vérif TLS stricte ; false = dépannage uniquement (certificat auto-signé / chaîne incomplète). */
        'ssl_verify_peer' => filter_var(env('MAIL_SSL_VERIFY_PEER', 'true'), FILTER_VALIDATE_BOOLEAN),
    ],
    'file_path' => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'mail-outbox',
    'login_attempt_threshold' => (int) env('MAIL_LOGIN_BRUTE_THRESHOLD', 8),
    'login_attempt_window_sec' => (int) env('MAIL_LOGIN_BRUTE_WINDOW', 60),
    'security_alert_emails' => array_values(array_filter(array_map('trim', explode(',', (string) env('SECURITY_ALERT_EMAILS', ''))))),
    'security_alert_levels' => ['INFO', 'WARNING', 'CRITICAL'],
    'geoip_enabled' => filter_var(env('MAIL_GEOIP_ENABLED', 'true'), FILTER_VALIDATE_BOOLEAN),
];
