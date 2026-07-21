<?php

declare(strict_types=1);

/**
 * Matrice de compétences × grades : pour chaque compétence/module d'un palier de formation,
 * le grade auquel elle est attendue et le niveau d'acquisition visé. Catalogue de référence
 * défini par le staff (pas de suivi individuel par opérateur).
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

    if ($hasTable('competency_grade_requirements')) {
        return;
    }

    $pdo->exec(
        "CREATE TABLE `competency_grade_requirements` (
          `id` bigint unsigned NOT NULL AUTO_INCREMENT,
          `tenant_id` int unsigned NOT NULL,
          `palier` varchar(120) NOT NULL,
          `palier_order` int NOT NULL DEFAULT 0,
          `label` varchar(255) NOT NULL,
          `grade_id` bigint unsigned DEFAULT NULL,
          `acquisition_level` varchar(30) DEFAULT NULL,
          `sort_order` int NOT NULL DEFAULT 0,
          `created_by_user_id` int unsigned DEFAULT NULL,
          `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_cgr_tenant_order` (`tenant_id`,`palier_order`,`sort_order`),
          KEY `idx_cgr_grade` (`grade_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
};
