<?php

declare(strict_types=1);

/**
 * Steam en double : signalements « je ne reconnais pas l'autre compte » (Steam mis de côté sur les fiches du
 * déclarant, rendu possible par l'administration). Idempotent : étape steam_conflict_reports de la file
 * active (run-migrations.php), et filet de sécurité à la première requête du service.
 */
return static function (PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `steam_conflict_reports` (
          `id` int unsigned NOT NULL AUTO_INCREMENT,
          `steam_id` varchar(32) NOT NULL,
          `reporter_user_id` int unsigned NOT NULL,
          `reporter_tenant_id` int unsigned NOT NULL DEFAULT 0,
          `removed_user_ids` varchar(255) NOT NULL DEFAULT '',
          `other_user_ids` varchar(255) NOT NULL DEFAULT '',
          `status` varchar(16) NOT NULL DEFAULT 'open',
          `created_at` datetime NOT NULL,
          `resolved_at` datetime DEFAULT NULL,
          `resolved_by` int unsigned DEFAULT NULL,
          PRIMARY KEY (`id`),
          KEY `idx_scr_steam_status` (`steam_id`, `status`),
          KEY `idx_scr_status` (`status`, `created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
};
