<?php

declare(strict_types=1);

/**
 * Demandes de correction RH sur fiche opérateur (anomalie → validation organisateur).
 * Idempotent.
 *
 * @return callable(PDO): void
 */
return static function (PDO $pdo): void {
    $cli = PHP_SAPI === 'cli';
    $st = $pdo->prepare(
        'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
    );
    $st->execute(['personnel_correction_requests']);
    if ($st->fetchColumn()) {
        if ($cli) {
            echo "  [OK] personnel_correction_requests déjà présente\n";
        }

        return;
    }

    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `personnel_correction_requests` (
              `id` int unsigned NOT NULL AUTO_INCREMENT,
              `tenant_id` int unsigned NOT NULL,
              `target_user_id` int unsigned NOT NULL,
              `requested_by` int unsigned NOT NULL,
              `note` text,
              `proposed_json` mediumtext NOT NULL,
              `before_json` mediumtext,
              `status` enum('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
              `resolution_note` text,
              `resolved_by` int unsigned DEFAULT NULL,
              `resolved_at` datetime DEFAULT NULL,
              `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_pcr_tenant_status` (`tenant_id`,`status`),
              KEY `idx_pcr_target` (`tenant_id`,`target_user_id`),
              KEY `idx_pcr_requester` (`tenant_id`,`requested_by`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        if ($cli) {
            echo "  [OK] personnel_correction_requests créée\n";
        }
    } catch (Throwable $e) {
        if ($cli) {
            echo '  [ATTENTION] personnel_correction_requests : ' . $e->getMessage() . "\n";
        }
    }
};
