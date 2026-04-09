<?php

declare(strict_types=1);

/**
 * Événements d’usage structurés + « j’aime » sur les formations LMS.
 */
return function (PDO $pdo): void {
    $pdo->exec(
        <<<'SQL'
CREATE TABLE IF NOT EXISTS `usage_analytics_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `actor_user_id` INT UNSIGNED NULL DEFAULT NULL,
  `session_hash` CHAR(64) NULL DEFAULT NULL,
  `category` VARCHAR(32) NOT NULL,
  `name` VARCHAR(64) NOT NULL,
  `subject_type` VARCHAR(32) NULL DEFAULT NULL,
  `subject_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `duration_seconds` INT UNSIGNED NULL DEFAULT NULL,
  `props` JSON NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_uae_tenant_cat_time` (`tenant_id`, `category`, `created_at`),
  KEY `idx_uae_tenant_subject_time` (`tenant_id`, `subject_type`, `subject_id`, `created_at`),
  KEY `idx_uae_name_time` (`name`, `created_at`),
  CONSTRAINT `fk_uae_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_uae_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
    );

    $chk = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'training_courses' LIMIT 1");
    if (!$chk || !$chk->fetch()) {
        echo "[ATTENTION] usage_analytics : table training_courses absente — training_course_likes ignoré.\n";

        return;
    }

    $pdo->exec(
        <<<'SQL'
CREATE TABLE IF NOT EXISTS `training_course_likes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `course_id` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tcl_user_course` (`user_id`,`course_id`),
  KEY `idx_tcl_course` (`course_id`),
  CONSTRAINT `fk_tcl_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_tcl_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_tcl_course` FOREIGN KEY (`course_id`) REFERENCES `training_courses` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
    );

    echo "usage_analytics + training_course_likes : OK.\n";
};
