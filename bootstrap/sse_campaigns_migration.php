<?php

declare(strict_types=1);

/**
 * Campagnes SSE gérées dans Athena (mod COMSPEC SSE 0.9.0, addon campaign).
 *
 * sse_campaigns     : une ligne par campagne et par communauté. payload garde le texte exact
 *                     exporté par le jeu (["COMSPEC_SSE_CAMPAIGN", 1, …]), relisible en jeu.
 *                     shared = publiée dans le catalogue des autres communautés ;
 *                     source_* = copie d'une campagne partagée (même graine, mêmes personnages).
 * sse_campaign_log  : journal des gestes faits sur une campagne (jeu, Athena, partage).
 * Relançable sans risque.
 */
return static function (PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS sse_campaigns (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT UNSIGNED NOT NULL,
            campaign_key VARCHAR(64) NOT NULL,
            name VARCHAR(160) NOT NULL DEFAULT '',
            type VARCHAR(16) NOT NULL DEFAULT 'permanente',
            status VARCHAR(16) NOT NULL DEFAULT 'active',
            seed VARCHAR(120) NOT NULL DEFAULT '',
            payload MEDIUMTEXT NULL,
            payload_hash CHAR(64) NOT NULL DEFAULT '',
            missions INT UNSIGNED NOT NULL DEFAULT 0,
            persons_count INT UNSIGNED NOT NULL DEFAULT 0,
            orgs_count INT UNSIGNED NOT NULL DEFAULT 0,
            pieces_count INT UNSIGNED NOT NULL DEFAULT 0,
            game_updated VARCHAR(40) NOT NULL DEFAULT '',
            origin VARCHAR(16) NOT NULL DEFAULT 'jeu',
            shared TINYINT(1) NOT NULL DEFAULT 0,
            share_scope VARCHAR(16) NOT NULL DEFAULT 'structure',
            share_note VARCHAR(600) NOT NULL DEFAULT '',
            source_campaign_id INT UNSIGNED NULL,
            source_tenant_id INT UNSIGNED NULL,
            source_hash CHAR(64) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            UNIQUE KEY uk_sse_campaign (tenant_id, campaign_key),
            INDEX idx_sse_campaign_seed (seed),
            INDEX idx_sse_campaign_shared (shared, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS sse_campaign_log (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            campaign_id INT UNSIGNED NOT NULL,
            tenant_id INT UNSIGNED NOT NULL,
            actor VARCHAR(120) NOT NULL DEFAULT '',
            action VARCHAR(40) NOT NULL DEFAULT '',
            detail VARCHAR(255) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_sse_campaign_log (campaign_id, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "  sse_campaigns, sse_campaign_log prêtes\n";
};
