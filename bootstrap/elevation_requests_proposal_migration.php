<?php

declare(strict_types=1);

/**
 * Demandes d’élévation : table + colonnes de proposition (grade, rôle, fonction, affectation).
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

    $hasColumn = static function (string $table, string $column) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };

    if (!$hasTable('elevation_requests')) {
        try {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS `elevation_requests` (
                  `id` int unsigned NOT NULL AUTO_INCREMENT,
                  `tenant_id` int unsigned NOT NULL,
                  `target_user_id` int unsigned NOT NULL,
                  `requested_by` int unsigned NOT NULL,
                  `kind` varchar(32) NOT NULL DEFAULT 'general',
                  `note` text,
                  `proposed_grade_id` int unsigned DEFAULT NULL,
                  `proposed_role_id` int unsigned DEFAULT NULL,
                  `proposed_job_role_id` int unsigned DEFAULT NULL,
                  `proposed_unit_id` int unsigned DEFAULT NULL,
                  `status` enum('pending','in_review','approved','rejected') NOT NULL DEFAULT 'pending',
                  `resolution_note` text,
                  `resolved_by` int unsigned DEFAULT NULL,
                  `resolved_at` datetime DEFAULT NULL,
                  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                  PRIMARY KEY (`id`),
                  KEY `idx_elevation_requests_tenant_status` (`tenant_id`,`status`),
                  KEY `idx_elevation_requests_target` (`target_user_id`),
                  KEY `idx_elevation_requests_requester` (`requested_by`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
            echo "  elevation_requests : table créée.\n";
        } catch (Throwable $e) {
            echo '  [ATTENTION] elevation_requests CREATE : ' . $e->getMessage() . "\n";
        }
    }

    if (!$hasTable('elevation_requests')) {
        return;
    }

    $columns = [
        'proposed_grade_id' => 'ADD COLUMN `proposed_grade_id` int unsigned DEFAULT NULL AFTER `note`',
        'proposed_role_id' => 'ADD COLUMN `proposed_role_id` int unsigned DEFAULT NULL AFTER `proposed_grade_id`',
        'proposed_job_role_id' => 'ADD COLUMN `proposed_job_role_id` int unsigned DEFAULT NULL AFTER `proposed_role_id`',
        'proposed_unit_id' => 'ADD COLUMN `proposed_unit_id` int unsigned DEFAULT NULL AFTER `proposed_job_role_id`',
    ];

    foreach ($columns as $col => $ddl) {
        if ($hasColumn('elevation_requests', $col)) {
            continue;
        }
        try {
            $pdo->exec('ALTER TABLE `elevation_requests` ' . $ddl);
            echo "  elevation_requests.{$col} : colonne ajoutée.\n";
        } catch (Throwable $e) {
            echo "  [ATTENTION] elevation_requests.{$col} : " . $e->getMessage() . "\n";
        }
    }
};
