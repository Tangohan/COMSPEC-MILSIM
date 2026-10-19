<?php

declare(strict_types=1);

/**
 * Envois d'e-mails sous quota (Administration > Envois d'e-mails).
 *
 * - email_digest_items : alertes d'exploitation en attente du prochain récapitulatif (une ligne par alerte).
 * - email_deliveries.created_at indexé : le compteur du jour est lu avant chaque envoi.
 *
 * Idempotent.
 */
return static function (PDO $pdo): void {
    $tableExists = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };
    $indexExists = static function (string $table, string $index) use ($pdo): bool {
        $st = $pdo->prepare('SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1');
        $st->execute([$table, $index]);

        return (bool) $st->fetchColumn();
    };

    if (!$tableExists('email_digest_items')) {
        $pdo->exec(
            "CREATE TABLE email_digest_items (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                recipient VARCHAR(255) NOT NULL,
                event_code VARCHAR(80) NOT NULL,
                subject VARCHAR(500) NOT NULL,
                body_text TEXT DEFAULT NULL,
                tenant_id INT UNSIGNED DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                digested_at DATETIME DEFAULT NULL,
                PRIMARY KEY (id),
                KEY idx_email_digest_pending (digested_at, recipient, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if ($tableExists('email_deliveries') && !$indexExists('email_deliveries', 'idx_email_deliveries_created')) {
        $pdo->exec('ALTER TABLE email_deliveries ADD KEY idx_email_deliveries_created (created_at, status)');
    }
};
