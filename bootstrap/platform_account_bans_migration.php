<?php

declare(strict_types=1);

/**
 * Bannissements à l’échelle du site (administration plateforme).
 * Une ligne par décision : e-mail visé, motif, entrées de la liste de restriction créées,
 * statuts des fiches avant le bannissement (pour tout remettre à l’identique en le levant).
 * Idempotent.
 */
function run_platform_account_bans_migration(PDO $pdo): void
{
    $st = $pdo->query(
        "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'platform_account_bans' LIMIT 1"
    );
    if ($st && $st->fetchColumn()) {
        return;
    }
    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `platform_account_bans` (
              `id` int unsigned NOT NULL AUTO_INCREMENT,
              `email_norm` varchar(255) NOT NULL,
              `user_id` int unsigned DEFAULT NULL,
              `display_label` varchar(255) DEFAULT NULL,
              `reason` text,
              `indicator_ids` text COMMENT 'JSON : ids blocked_indicators créés avec ce bannissement',
              `status_snapshot` text COMMENT 'JSON : user_id => statut avant bannissement',
              `banned_by_user_id` int unsigned DEFAULT NULL,
              `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `lifted_at` datetime DEFAULT NULL,
              `lifted_by_user_id` int unsigned DEFAULT NULL,
              PRIMARY KEY (`id`),
              KEY `idx_pab_email_active` (`email_norm`, `lifted_at`),
              KEY `idx_pab_created` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        echo "  platform_account_bans : table créée.\n";
    } catch (Throwable $e) {
        echo '  [ATTENTION] platform_account_bans : ' . $e->getMessage() . "\n";
    }
}
