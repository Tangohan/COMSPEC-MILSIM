<?php

declare(strict_types=1);

/**
 * Parc ATAK des joueurs (administration du site, /admin/parc-atak).
 *   atak_admin_blocks   : blocages décidés par l’administration (un ATAK, un joueur, une ou toutes les communautés).
 *                         L’API refuse la connexion et les envois du jeu tant qu’un blocage est actif.
 *   atak_admin_messages : messages imposés au joueur en jeu (récupérés par le serveur Arma, accusés de lecture).
 *   atak_admin_actions  : journal de chaque action de l’administration sur un ATAK.
 * Idempotent.
 */
function run_atak_admin_control_migration(PDO $pdo): void
{
    $exists = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if (!$exists('atak_admin_blocks')) {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `atak_admin_blocks` (
              `id` int unsigned NOT NULL AUTO_INCREMENT,
              `tenant_id` int unsigned DEFAULT NULL COMMENT 'NULL = toutes les communautés',
              `user_id` int unsigned DEFAULT NULL,
              `steam_uid` varchar(32) DEFAULT NULL,
              `terminal_uid` varchar(64) DEFAULT NULL,
              `label` varchar(190) DEFAULT NULL COMMENT 'Indicatif ou nom au moment du blocage',
              `reason` varchar(500) DEFAULT NULL COMMENT 'Motif interne',
              `player_message` varchar(500) DEFAULT NULL COMMENT 'Texte renvoyé au joueur par l’API',
              `expires_at` datetime DEFAULT NULL,
              `created_by_user_id` int unsigned DEFAULT NULL,
              `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `lifted_at` datetime DEFAULT NULL,
              `lifted_by_user_id` int unsigned DEFAULT NULL,
              PRIMARY KEY (`id`),
              KEY `idx_aab_steam` (`steam_uid`, `lifted_at`),
              KEY `idx_aab_user` (`user_id`, `lifted_at`),
              KEY `idx_aab_terminal` (`terminal_uid`, `lifted_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        echo "  atak_admin_blocks : table créée.\n";
    }

    if (!$exists('atak_admin_messages')) {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `atak_admin_messages` (
              `id` int unsigned NOT NULL AUTO_INCREMENT,
              `tenant_id` int unsigned NOT NULL,
              `user_id` int unsigned DEFAULT NULL,
              `steam_uid` varchar(32) DEFAULT NULL,
              `terminal_uid` varchar(64) DEFAULT NULL,
              `title` varchar(120) NOT NULL,
              `body` text NOT NULL,
              `level` varchar(16) NOT NULL DEFAULT 'info' COMMENT 'info | warning | critical',
              `require_ack` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Le joueur doit valider pour fermer',
              `expires_at` datetime DEFAULT NULL,
              `created_by_user_id` int unsigned DEFAULT NULL,
              `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `delivered_at` datetime DEFAULT NULL,
              `acked_at` datetime DEFAULT NULL,
              `cancelled_at` datetime DEFAULT NULL,
              PRIMARY KEY (`id`),
              KEY `idx_aam_pending` (`tenant_id`, `acked_at`, `cancelled_at`),
              KEY `idx_aam_user` (`user_id`),
              KEY `idx_aam_steam` (`steam_uid`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        echo "  atak_admin_messages : table créée.\n";
    }

    if (!$exists('atak_admin_actions')) {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `atak_admin_actions` (
              `id` bigint unsigned NOT NULL AUTO_INCREMENT,
              `tenant_id` int unsigned DEFAULT NULL,
              `user_id` int unsigned DEFAULT NULL COMMENT 'Joueur visé',
              `terminal_uid` varchar(64) DEFAULT NULL,
              `label` varchar(190) DEFAULT NULL,
              `action` varchar(32) NOT NULL,
              `detail` varchar(1000) DEFAULT NULL,
              `actor_user_id` int unsigned DEFAULT NULL,
              `actor_label` varchar(190) DEFAULT NULL,
              `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_aaa_created` (`created_at`),
              KEY `idx_aaa_user` (`user_id`, `created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        echo "  atak_admin_actions : table créée.\n";
    }
}
