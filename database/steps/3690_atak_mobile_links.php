<?php

declare(strict_types=1);

/**
 * Étape « Sur mon téléphone : liens QR vers les apps mobiles » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente :
 * - table atak_mobile_links créée si elle manque : un lien demandé par l'ATAK en jeu (apps Comptes rendus,
 *   CR fin d'op, Carte, briefs air) pour ouvrir la version mobile sur le vrai téléphone du joueur.
 *   Le jeton du QR code (empreinte seule, usage unique, 5 minutes) ouvre une session mobile limitée à ces pages,
 *   liée au compte, à la communauté et à la partie, révocable depuis le profil (Appareils connectés).
 */

$mobileLinksSchema = new \App\Support\Migrations\SchemaInspector($pdo);

if ($mobileLinksSchema->tableExists('atak_mobile_links')) {
    echo "  [OK] Table atak_mobile_links déjà présente.\n";
} else {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `atak_mobile_links` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `tenant_id` INT UNSIGNED NOT NULL,
        `user_id` INT UNSIGNED NOT NULL,
        `steam_uid` VARCHAR(32) NULL DEFAULT NULL,
        `mission_key` VARCHAR(190) NULL DEFAULT NULL,
        `map_id` INT UNSIGNED NOT NULL DEFAULT 1,
        `app` VARCHAR(24) NOT NULL DEFAULT 'cr',
        `player_name` VARCHAR(80) NOT NULL DEFAULT '',
        `pos_x` DOUBLE NULL DEFAULT NULL,
        `pos_y` DOUBLE NULL DEFAULT NULL,
        `context_json` MEDIUMTEXT NULL,
        `token_hash` CHAR(64) NOT NULL,
        `token_expires_at` DATETIME NOT NULL,
        `redeemed_at` DATETIME NULL DEFAULT NULL,
        `session_expires_at` DATETIME NULL DEFAULT NULL,
        `revoked_at` DATETIME NULL DEFAULT NULL,
        `last_seen_at` DATETIME NULL DEFAULT NULL,
        `ip` VARCHAR(45) NULL DEFAULT NULL,
        `user_agent` VARCHAR(255) NULL DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_atak_mobile_links_token` (`token_hash`),
        KEY `idx_atak_mobile_links_user` (`tenant_id`, `user_id`, `revoked_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "  [OK] Table atak_mobile_links créée.\n";
}
