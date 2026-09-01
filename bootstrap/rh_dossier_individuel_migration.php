<?php

declare(strict_types=1);

/**
 * Dossier RH individuel : documents typés, mobilité interne, vivier/succession,
 * colonnes offboarding (archivage + réintégration).
 * Idempotent.
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
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };

    if (!$hasTable('personnel_hr_documents')) {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `personnel_hr_documents` (
              `id` int unsigned NOT NULL AUTO_INCREMENT,
              `tenant_id` int unsigned NOT NULL,
              `user_id` int unsigned NOT NULL,
              `doc_type` enum('candidature','charte','reglement','certificat','qualification','affectation','evaluation','autre') NOT NULL DEFAULT 'autre',
              `title` varchar(200) NOT NULL,
              `description` text,
              `file_path` varchar(500) DEFAULT NULL,
              `original_name` varchar(255) DEFAULT NULL,
              `visibility` enum('MEMBER','STAFF') NOT NULL DEFAULT 'STAFF',
              `uploaded_by` int unsigned DEFAULT NULL,
              `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `archived_at` datetime DEFAULT NULL,
              PRIMARY KEY (`id`),
              KEY `idx_hr_docs_user` (`tenant_id`,`user_id`,`doc_type`),
              KEY `idx_hr_docs_type` (`tenant_id`,`doc_type`,`created_at`),
              CONSTRAINT `personnel_hr_documents_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `personnel_hr_documents_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `personnel_hr_documents_uploader_fk` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        echo "  personnel_hr_documents : table créée.\n";
    } elseif (!$hasColumn('personnel_hr_documents', 'original_name')) {
        $pdo->exec(
            'ALTER TABLE `personnel_hr_documents`
             ADD COLUMN `original_name` varchar(255) DEFAULT NULL AFTER `file_path`'
        );
        echo "  personnel_hr_documents : colonne original_name ajoutée.\n";
    }

    if (!$hasTable('personnel_mobility_requests')) {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `personnel_mobility_requests` (
              `id` int unsigned NOT NULL AUTO_INCREMENT,
              `tenant_id` int unsigned NOT NULL,
              `user_id` int unsigned NOT NULL,
              `request_type` enum('unit_change','specialty_change','job_application','career_wish') NOT NULL,
              `target_unit_id` int unsigned DEFAULT NULL,
              `target_job_role_id` int unsigned DEFAULT NULL,
              `target_label` varchar(200) DEFAULT NULL,
              `motivation` text,
              `status` enum('pending','approved','rejected','cancelled','applied') NOT NULL DEFAULT 'pending',
              `requested_by` int unsigned DEFAULT NULL,
              `reviewed_by` int unsigned DEFAULT NULL,
              `reviewed_at` datetime DEFAULT NULL,
              `resolution_note` text,
              `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_mobility_tenant_status` (`tenant_id`,`status`,`created_at`),
              KEY `idx_mobility_user` (`tenant_id`,`user_id`),
              CONSTRAINT `personnel_mobility_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `personnel_mobility_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        echo "  personnel_mobility_requests : table créée.\n";
    }

    if (!$hasTable('personnel_succession_entries')) {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `personnel_succession_entries` (
              `id` int unsigned NOT NULL AUTO_INCREMENT,
              `tenant_id` int unsigned NOT NULL,
              `user_id` int unsigned NOT NULL,
              `target_role_label` varchar(120) NOT NULL,
              `target_job_role_id` int unsigned DEFAULT NULL,
              `readiness` enum('ready_now','ready_3m','develop') NOT NULL DEFAULT 'develop',
              `notes` text,
              `assessed_by` int unsigned DEFAULT NULL,
              `assessed_at` datetime DEFAULT NULL,
              `is_active` tinyint(1) NOT NULL DEFAULT 1,
              `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_succession_tenant_role` (`tenant_id`,`target_role_label`,`readiness`),
              KEY `idx_succession_user` (`tenant_id`,`user_id`,`is_active`),
              CONSTRAINT `personnel_succession_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              CONSTRAINT `personnel_succession_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        echo "  personnel_succession_entries : table créée.\n";
    }

    if ($hasTable('member_departures')) {
        if (!$hasColumn('member_departures', 'dossier_archived')) {
            try {
                $pdo->exec(
                    'ALTER TABLE `member_departures`
                     ADD COLUMN `dossier_archived` tinyint(1) NOT NULL DEFAULT 0 AFTER `access_revoked_at`,
                     ADD COLUMN `dossier_archived_at` datetime DEFAULT NULL AFTER `dossier_archived`,
                     ADD COLUMN `reinstated_at` datetime DEFAULT NULL AFTER `dossier_archived_at`,
                     ADD COLUMN `reinstated_by` int unsigned DEFAULT NULL AFTER `reinstated_at`'
                );
                echo "  member_departures : colonnes archivage/réintégration ajoutées.\n";
            } catch (Throwable $e) {
                echo '  [ATTENTION] member_departures alter : ' . $e->getMessage() . "\n";
            }
        }
    }

    echo "  [OK] rh_dossier_individuel\n";
};
