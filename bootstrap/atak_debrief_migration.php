<?php

declare(strict_types=1);

/**
 * CR de fin d'opération imposé sur le téléphone ATAK des joueurs (back-office /back-office/atak/suivi-cr).
 *
 *   atak_debriefs            : une demande de CR (site ou jeu) : opération (clé de mission), mode « remplir » ou « lire »,
 *                              questionnaire figé au moment de l'envoi, texte à lire, auteur, clôture ;
 *   atak_debrief_recipients  : un joueur visé par la demande : avis ATAK (atak_admin_messages), affiché, ouvert,
 *                              remis (réponses), lu, compte rendu remonté (atak_tactical_reports) ;
 *   atak_debrief_settings    : réglages de la communauté : mode et questionnaire par défaut, droit des Zeus en jeu,
 *                              UID Steam autorisés à imposer le CR depuis le téléphone.
 *
 * Lancée à la demande par AtakDebriefRepository::ready() et par run-migrations.php ; sans effet si tout existe.
 */
function run_atak_debrief_migration(PDO $pdo): void
{
    $exists = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if (!$exists('atak_debriefs')) {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `atak_debriefs` (
              `id` int unsigned NOT NULL AUTO_INCREMENT,
              `tenant_id` int unsigned NOT NULL,
              `mission_key` varchar(190) NOT NULL DEFAULT '' COMMENT 'Session jouée (nom@carte#UTC), vide = hors session',
              `label` varchar(200) NOT NULL,
              `mode` varchar(8) NOT NULL DEFAULT 'fill' COMMENT 'fill = à remplir | read = à lire',
              `template_id` int unsigned DEFAULT NULL COMMENT 'Modèle de debriefing (aar_report_templates)',
              `aar_report_id` int unsigned DEFAULT NULL COMMENT 'Compte rendu post-op donné à lire',
              `intro` varchar(600) DEFAULT NULL,
              `read_text` text DEFAULT NULL,
              `questions_json` json DEFAULT NULL COMMENT 'Questionnaire figé à l’envoi',
              `source` varchar(8) NOT NULL DEFAULT 'site' COMMENT 'site | game',
              `game_ref` varchar(64) DEFAULT NULL COMMENT 'Référence de la demande faite en jeu',
              `created_by_user_id` int unsigned DEFAULT NULL,
              `created_by_label` varchar(120) DEFAULT NULL,
              `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `expires_at` datetime DEFAULT NULL,
              `closed_at` datetime DEFAULT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uniq_atak_debrief_game` (`tenant_id`, `game_ref`),
              KEY `idx_atak_debrief_tenant` (`tenant_id`, `created_at`),
              KEY `idx_atak_debrief_mission` (`tenant_id`, `mission_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        echo "  atak_debriefs : table créée.\n";
    }

    if (!$exists('atak_debrief_recipients')) {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `atak_debrief_recipients` (
              `id` int unsigned NOT NULL AUTO_INCREMENT,
              `debrief_id` int unsigned NOT NULL,
              `tenant_id` int unsigned NOT NULL,
              `who_key` varchar(64) NOT NULL COMMENT 'u:<id utilisateur> ou s:<Steam>',
              `user_id` int unsigned DEFAULT NULL,
              `steam_uid` varchar(32) DEFAULT NULL,
              `callsign` varchar(80) DEFAULT NULL,
              `message_id` int unsigned DEFAULT NULL COMMENT 'Avis ATAK (atak_admin_messages)',
              `sent_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `delivered_at` datetime DEFAULT NULL,
              `opened_at` datetime DEFAULT NULL,
              `submitted_at` datetime DEFAULT NULL,
              `read_at` datetime DEFAULT NULL,
              `answers_json` json DEFAULT NULL,
              `report_id` int unsigned DEFAULT NULL COMMENT 'Remontée (atak_tactical_reports)',
              PRIMARY KEY (`id`),
              UNIQUE KEY `uniq_atak_debrief_who` (`debrief_id`, `who_key`),
              KEY `idx_atak_debrief_rcpt_msg` (`message_id`),
              KEY `idx_atak_debrief_rcpt_user` (`tenant_id`, `user_id`),
              KEY `idx_atak_debrief_rcpt_steam` (`tenant_id`, `steam_uid`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        echo "  atak_debrief_recipients : table créée.\n";
    }

    if (!$exists('atak_debrief_settings')) {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `atak_debrief_settings` (
              `tenant_id` int unsigned NOT NULL,
              `default_mode` varchar(8) NOT NULL DEFAULT 'fill',
              `template_id` int unsigned DEFAULT NULL,
              `intro` varchar(600) DEFAULT NULL,
              `zeus_allowed` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Les Zeus peuvent imposer le CR en jeu',
              `authorized_uids` text DEFAULT NULL COMMENT 'Steam autorisés à imposer le CR en jeu, séparés par des virgules',
              `updated_by_user_id` int unsigned DEFAULT NULL,
              `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`tenant_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        echo "  atak_debrief_settings : table créée.\n";
    }
}
