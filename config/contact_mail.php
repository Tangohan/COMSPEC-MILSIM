<?php

declare(strict_types=1);

/**
 * Boîte de contact de la plateforme (Administration du site > Boîte de contact).
 * Toutes les adresses et tous les secrets viennent du .env du serveur : voir docs/boite-contact.md et .env.example.
 * Sans CONTACT_MAIL_PASSWORD, la boîte reste « non configurée » et aucune connexion n'est ouverte.
 */
$address = trim((string) env('CONTACT_MAIL_ADDRESS', '')) ?: 'contact@athena-comspec.eu';

return [
    'address' => $address,
    'user' => trim((string) env('CONTACT_MAIL_USER', '')) ?: $address,
    'password' => (string) env('CONTACT_MAIL_PASSWORD', ''),
    'imap_host' => trim((string) env('CONTACT_MAIL_IMAP_HOST', '')) ?: 'imap.hostinger.com',
    'imap_port' => (int) (trim((string) env('CONTACT_MAIL_IMAP_PORT', '')) ?: 993),
    'smtp_host' => trim((string) env('CONTACT_MAIL_SMTP_HOST', '')) ?: 'smtp.hostinger.com',
    'smtp_port' => (int) (trim((string) env('CONTACT_MAIL_SMTP_PORT', '')) ?: 465),
    'from_name' => trim((string) env('CONTACT_MAIL_FROM_NAME', '')) ?: 'Athena',
    'signature' => str_replace(['\n', "\r"], ["\n", ''], (string) env('CONTACT_MAIL_SIGNATURE', '')),
    'sent_folder' => trim((string) env('CONTACT_MAIL_SENT_FOLDER', '')) ?: 'INBOX.Sent',
    'send_per_hour' => max(1, (int) (trim((string) env('CONTACT_MAIL_SEND_PER_HOUR', '')) ?: 20)),
    'timeout' => 20,
    'reply_to_mode' => strtolower(trim((string) env('MAIL_REPLY_TO_MODE', ''))) === 'none' ? 'none' : 'contact',
];
