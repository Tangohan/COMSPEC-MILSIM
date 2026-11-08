<?php

declare(strict_types=1);

use App\Support\Migrations\SchemaInspector;

/**
 * Bannissements à l’échelle du site (administration plateforme).
 * Une ligne par décision : e-mail visé, motif, entrées de la liste de restriction créées,
 * statuts des fiches avant le bannissement (pour tout remettre à l’identique en le levant),
 * date de fin éventuelle (sanction temporaire) et note de levée.
 * Idempotent : l’état réel de la base est vérifié avant chaque ajout.
 */
function run_platform_account_bans_migration(PDO $pdo): void
{
    $schema = new SchemaInspector($pdo);
    try {
        if (!$schema->tableExists('platform_account_bans')) {
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
                  `ends_at` datetime DEFAULT NULL,
                  `lifted_at` datetime DEFAULT NULL,
                  `lifted_by_user_id` int unsigned DEFAULT NULL,
                  `lift_note` varchar(500) DEFAULT NULL,
                  PRIMARY KEY (`id`),
                  KEY `idx_pab_email_active` (`email_norm`, `lifted_at`),
                  KEY `idx_pab_created` (`created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            echo "  platform_account_bans : table créée.\n";
        }
        // Sanction temporaire : NULL = bannissement sans fin. lift_note : raison de la levée (ou « fin de la sanction »).
        foreach (['ends_at' => 'DATETIME NULL DEFAULT NULL', 'lift_note' => 'VARCHAR(500) NULL DEFAULT NULL'] as $column => $definition) {
            if ($schema->addColumnIfMissing('platform_account_bans', $column, $definition)) {
                echo "  platform_account_bans : colonne {$column} ajoutée.\n";
            }
        }
        if ($schema->addIndexIfMissing('platform_account_bans', 'idx_pab_due', ['lifted_at', 'ends_at'])) {
            echo "  platform_account_bans : index idx_pab_due ajouté.\n";
        }
    } catch (Throwable $e) {
        echo '  [ATTENTION] platform_account_bans : ' . $e->getMessage() . "\n";
    }
}
