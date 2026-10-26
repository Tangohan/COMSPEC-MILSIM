<?php

declare(strict_types=1);

/**
 * Boîte de réception : un membre peut retirer une conversation de sa boîte (tenant_message_thread_users.hidden_at).
 * La conversation n'est pas supprimée pour les autres participants et réapparaît si un nouveau message arrive.
 */
return static function (PDO $pdo): void {
    $st = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
    $st->execute(['tenant_message_thread_users']);
    if (!$st->fetchColumn()) {
        return;
    }
    $st = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1');
    $st->execute(['tenant_message_thread_users', 'hidden_at']);
    if (!$st->fetchColumn()) {
        $pdo->exec('ALTER TABLE tenant_message_thread_users ADD COLUMN hidden_at DATETIME NULL DEFAULT NULL');
    }
};
