<?php

declare(strict_types=1);

/**
 * Offboarding structuré : historique des départs (motif, date, reprise d’accès).
 * Idempotent — safe à ré-exécuter.
 *
 * @return callable(PDO): void
 */
return static function (PDO $pdo): void {
    $hasTable = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if ($hasTable('member_departures')) {
        return;
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `member_departures` (
          `id` int unsigned NOT NULL AUTO_INCREMENT,
          `tenant_id` int unsigned NOT NULL,
          `user_id` int unsigned NOT NULL,
          `initiated_by` int unsigned DEFAULT NULL,
          `reason` enum('end_of_engagement','exclusion','pause','other') NOT NULL DEFAULT 'other',
          `reason_note` text,
          `departed_at` date NOT NULL,
          `access_revoked` tinyint(1) NOT NULL DEFAULT 0,
          `access_revoked_at` datetime DEFAULT NULL,
          `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_member_departures_tenant` (`tenant_id`,`departed_at`),
          KEY `idx_member_departures_user` (`user_id`),
          CONSTRAINT `member_departures_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
          CONSTRAINT `member_departures_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
          CONSTRAINT `member_departures_initiator_fk` FOREIGN KEY (`initiated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    echo "  member_departures : table créée.\n";
};
