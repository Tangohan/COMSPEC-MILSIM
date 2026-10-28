<?php

declare(strict_types=1);

/**
 * Étape « Migration overrides_grades_par_tenant » (Schéma).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Overrides grades par tenant
$stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenant_grade_overrides'");
if ($stmt && !$stmt->fetch()) {
    echo "Création table tenant_grade_overrides...\n";
    $pdo->exec("CREATE TABLE `tenant_grade_overrides` (
      `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
      `tenant_id` int(10) UNSIGNED NOT NULL,
      `grade_id` bigint(20) UNSIGNED NOT NULL,
      `label_short_override` varchar(100) DEFAULT NULL,
      `label_long_override` varchar(150) DEFAULT NULL,
      `sort_order_override` int(11) DEFAULT NULL,
      `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
      `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
      `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `tenant_grade` (`tenant_id`,`grade_id`),
      KEY `tenant_id` (`tenant_id`),
      KEY `grade_id` (`grade_id`),
      CONSTRAINT `tenant_grade_overrides_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "Table tenant_grade_overrides OK.\n";
}
