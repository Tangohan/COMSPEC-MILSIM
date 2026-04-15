<?php

declare(strict_types=1);

/**
 * Chaîne pédagogique multi-rôles : tables d’habilitation, éligibilité instructeur,
 * parcours, audit, anomalies, colonnes LMS (responsable / validateur), unités minimales.
 * Idempotent (information_schema + INSERT IGNORE).
 */
return function (PDO $pdo): void {
    $hasTenants = (bool) $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenants' LIMIT 1")?->fetchColumn();
    if (!$hasTenants) {
        echo "  [ATTENTION] pedagogy_chain : table tenants absente — ignoré.\n";

        return;
    }

    $exec = static function (PDO $pdo, string $sql, string $label): void {
        try {
            $pdo->exec($sql);
        } catch (\PDOException $e) {
            echo '  [ATTENTION] pedagogy_chain (' . $label . ') : ' . $e->getMessage() . "\n";
        }
    };

    $exec(
        $pdo,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS `tenant_pedagogy_role_sets` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `role_id` INT UNSIGNED NOT NULL,
  `pedagogy_kind` VARCHAR(40) NOT NULL COMMENT 'design_trainer|delivery_instructor|instructor_certifier|trainer_certifier',
  `created_by_user_id` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tprs_tenant_role_kind` (`tenant_id`,`role_id`,`pedagogy_kind`),
  KEY `idx_tprs_tenant_kind` (`tenant_id`,`pedagogy_kind`),
  CONSTRAINT `fk_tprs_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_tprs_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_tprs_actor` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        ,
        'tenant_pedagogy_role_sets'
    );

    $exec(
        $pdo,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS `instructor_delivery_eligibility` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `scope` ENUM('course','family') NOT NULL DEFAULT 'course',
  `course_id` BIGINT UNSIGNED NULL,
  `family_code` VARCHAR(80) NULL,
  `status` ENUM('active','suspended','revoked','expired') NOT NULL DEFAULT 'active',
  `valid_from` DATETIME NULL,
  `valid_until` DATETIME NULL,
  `certified_by_user_id` INT UNSIGNED NULL,
  `revoked_by_user_id` INT UNSIGNED NULL,
  `revoked_at` DATETIME NULL,
  `notes` VARCHAR(500) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ide_tenant_user` (`tenant_id`,`user_id`,`status`),
  KEY `idx_ide_course` (`tenant_id`,`course_id`,`status`),
  CONSTRAINT `fk_ide_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ide_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ide_certifier` FOREIGN KEY (`certified_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_ide_revoker` FOREIGN KEY (`revoked_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        ,
        'instructor_delivery_eligibility'
    );

    $hasCourses = (bool) $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'training_courses' LIMIT 1")?->fetchColumn();

    $exec(
        $pdo,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS `user_pedagogy_pathway` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `pathway_slug` VARCHAR(64) NOT NULL DEFAULT 'montee_en_puissance',
  `stage_slug` VARCHAR(64) NOT NULL,
  `status` ENUM('active','suspended','expired') NOT NULL DEFAULT 'active',
  `valid_from` DATETIME NULL,
  `valid_until` DATETIME NULL,
  `certified_by_user_id` INT UNSIGNED NULL,
  `metadata` JSON NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_upp_user_pathway_stage` (`tenant_id`,`user_id`,`pathway_slug`,`stage_slug`),
  KEY `idx_upp_tenant_status` (`tenant_id`,`status`),
  CONSTRAINT `fk_upp_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_upp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_upp_cert` FOREIGN KEY (`certified_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        ,
        'user_pedagogy_pathway'
    );

    $exec(
        $pdo,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS `pedagogy_audit_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `actor_user_id` INT UNSIGNED NULL,
  `action_code` VARCHAR(80) NOT NULL,
  `entity_type` VARCHAR(80) NOT NULL,
  `entity_id` BIGINT UNSIGNED NULL,
  `payload` JSON NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pae_tenant_created` (`tenant_id`,`created_at`),
  KEY `idx_pae_entity` (`entity_type`,`entity_id`),
  CONSTRAINT `fk_pae_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_pae_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        ,
        'pedagogy_audit_events'
    );

    $exec(
        $pdo,
        <<<'SQL'
CREATE TABLE IF NOT EXISTS `training_pedagogy_anomalies` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `course_id` BIGINT UNSIGNED NULL,
  `session_id` BIGINT UNSIGNED NULL,
  `reported_by_user_id` INT UNSIGNED NOT NULL,
  `severity` ENUM('info','attention','critique') NOT NULL DEFAULT 'attention',
  `status` ENUM('open','in_progress','closed') NOT NULL DEFAULT 'open',
  `title` VARCHAR(200) NOT NULL,
  `body` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tpa_tenant_status` (`tenant_id`,`status`),
  CONSTRAINT `fk_tpa_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_tpa_reporter` FOREIGN KEY (`reported_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        ,
        'training_pedagogy_anomalies'
    );

    if ($hasCourses) {
        foreach (
            [
                'pedagogical_owner_user_id' => 'INT UNSIGNED NULL DEFAULT NULL COMMENT \'Responsable pédagogique (conception)\'',
                'final_validator_user_id' => 'INT UNSIGNED NULL DEFAULT NULL COMMENT \'Validateur final dist\'',
            ] as $col => $fragment
        ) {
            try {
                $q = $pdo->query(
                    'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ' . $pdo->quote('training_courses') . ' AND COLUMN_NAME = ' . $pdo->quote($col) . ' LIMIT 1'
                );
                if ($q && !$q->fetch()) {
                    $pdo->exec('ALTER TABLE training_courses ADD COLUMN `' . $col . '` ' . $fragment);
                    echo "  pedagogy_chain : training_courses.{$col} ajouté.\n";
                }
            } catch (\PDOException $e) {
                echo '  [ATTENTION] pedagogy_chain (training_courses.' . $col . ') : ' . $e->getMessage() . "\n";
            }
        }
        foreach (
            [
                ['col' => 'pedagogical_owner_user_id', 'cname' => 'fk_tc_pedagogical_owner'],
                ['col' => 'final_validator_user_id', 'cname' => 'fk_tc_final_validator'],
            ] as $fk
        ) {
            try {
                $cn = $fk['cname'];
                $st = $pdo->query(
                    "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'training_courses' AND CONSTRAINT_NAME = " . $pdo->quote($cn) . ' LIMIT 1'
                );
                if ($st && !$st->fetch()) {
                    $pdo->exec(
                        'ALTER TABLE training_courses ADD CONSTRAINT `' . $cn . '` FOREIGN KEY (`' . $fk['col'] . '`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE'
                    );
                }
            } catch (\PDOException) {
            }
        }
    }

    try {
        $ttr = $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'training_trainer_roles' LIMIT 1");
        $tprs = $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenant_pedagogy_role_sets' LIMIT 1");
        if ($ttr && $ttr->fetch() && $tprs && $tprs->fetch()) {
            $pdo->exec(
                "INSERT IGNORE INTO tenant_pedagogy_role_sets (tenant_id, role_id, pedagogy_kind, created_by_user_id, created_at)
                 SELECT tenant_id, role_id, 'design_trainer', created_by_user_id, created_at FROM training_trainer_roles"
            );
        }
    } catch (\PDOException $e) {
        echo '  [ATTENTION] pedagogy_chain (migrate trainer roles) : ' . $e->getMessage() . "\n";
    }

    echo "  pedagogy_chain : traité.\n";
};
