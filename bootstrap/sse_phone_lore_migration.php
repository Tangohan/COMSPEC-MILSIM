<?php

declare(strict_types=1);

use App\Support\Migrations\SchemaInspector;

/**
 * Fichiers SSE — téléphones du jeu et lore de session (étape 4350) :
 *  - sse_phone_links : téléphones ATAK du jeu synchronisés par un membre (compte relié en jeu), avec son accord
 *    (consent) ; le membre peut retirer son accord depuis le site (revoked_at) : les envois suivants sont refusés ;
 *  - sse_phone_extractions : extraction « type rapport d'exploitation » des données d'un téléphone du jeu (personnage
 *    joueur ou IA), rattachable à une fiche sujet, modérable ; empreinte SHA-256 de la copie ;
 *  - sse_phone_extraction_items : éléments de l'extraction (contacts, appels, messages, notes, photos, applications,
 *    positions, lieux, réseau) avec texte normalisé pour la recherche ;
 *  - sse_lore_sessions : lore d'une mission (clé d'opération), phase en cours, verrouillage, dates de dernière remontée
 *    du jeu et de dernière récupération par le jeu, rafraîchissement automatique ;
 *  - sse_lore_members : personnes de la session (identité du registre, identifiant stable de l'IA).
 *
 * Idempotente ; MySQL, MariaDB et sqlite (tests).
 */
return static function (PDO $pdo, ?callable $log = null): void {
    $log ??= static function (string $msg): void {
        // Silence web.
    };
    $schema = new SchemaInspector($pdo);
    $sqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    $id = $sqlite ? 'id INTEGER PRIMARY KEY AUTOINCREMENT' : 'id INT UNSIGNED NOT NULL AUTO_INCREMENT';
    $engine = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    $text = $sqlite ? 'TEXT' : 'MEDIUMTEXT';

    /**
     * @param list<string> $columns
     * @param list<array{0: string, 1: string, 2: bool}> $indexes
     */
    $create = static function (string $table, array $columns, array $indexes) use ($pdo, $schema, $sqlite, $engine, $log): void {
        if ($schema->tableExists($table)) {
            $log("  [OK] {$table} (déjà présente)\n");

            return;
        }
        $lines = $columns;
        if (!$sqlite) {
            $lines[] = 'PRIMARY KEY (id)';
            foreach ($indexes as [$name, $cols, $unique]) {
                $lines[] = ($unique ? 'UNIQUE KEY ' : 'KEY ') . $name . ' (' . $cols . ')';
            }
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS {$table} (\n    " . implode(",\n    ", $lines) . "\n)" . $engine);
        if ($sqlite) {
            foreach ($indexes as [$name, $cols, $unique]) {
                $pdo->exec('CREATE ' . ($unique ? 'UNIQUE ' : '') . "INDEX IF NOT EXISTS {$name} ON {$table} ({$cols})");
            }
        }
        $log("  [OK] {$table}\n");
    };

    $create('sse_phone_links', [
        $id,
        'tenant_id INT UNSIGNED NOT NULL',
        'user_id INT UNSIGNED NOT NULL',
        'device_uid VARCHAR(64) NOT NULL',
        'device_label VARCHAR(80) DEFAULT NULL',
        'consent TINYINT(1) NOT NULL DEFAULT 0',
        'linked_at DATETIME DEFAULT NULL',
        'last_sync_at DATETIME DEFAULT NULL',
        'revoked_at DATETIME DEFAULT NULL',
        'created_at DATETIME NOT NULL',
    ], [
        ['uq_sse_phone_links_device', 'tenant_id, user_id, device_uid', true],
    ]);

    $create('sse_phone_extractions', [
        $id,
        'tenant_id INT UNSIGNED NOT NULL',
        'ref VARCHAR(24) DEFAULT NULL',
        'idempotency_key VARCHAR(80) NOT NULL',
        'uploader_user_id INT UNSIGNED DEFAULT NULL',
        'link_id INT UNSIGNED DEFAULT NULL',
        'device_uid VARCHAR(64) NOT NULL',
        'device_label VARCHAR(80) DEFAULT NULL',
        'device_model VARCHAR(80) DEFAULT NULL',
        'owner_label VARCHAR(120) DEFAULT NULL',
        'owner_ai_uid VARCHAR(64) DEFAULT NULL',
        "owner_kind VARCHAR(8) NOT NULL DEFAULT 'player'",
        'identity_id INT UNSIGNED DEFAULT NULL',
        'op_key VARCHAR(64) DEFAULT NULL',
        'context_id INT UNSIGNED DEFAULT NULL',
        'grid VARCHAR(32) DEFAULT NULL',
        'game_time VARCHAR(32) DEFAULT NULL',
        'sha256 CHAR(64) NOT NULL',
        'bytes INT UNSIGNED NOT NULL DEFAULT 0',
        'counts_json VARCHAR(500) DEFAULT NULL',
        'network_json TEXT DEFAULT NULL',
        "status VARCHAR(8) NOT NULL DEFAULT 'visible'",
        'moderated_by_user_id INT UNSIGNED DEFAULT NULL',
        'moderation_note VARCHAR(255) DEFAULT NULL',
        'created_at DATETIME NOT NULL',
    ], [
        ['uq_sse_phone_extractions_key', 'tenant_id, idempotency_key', true],
        ['idx_sse_phone_extractions_list', 'tenant_id, status, created_at', false],
        ['idx_sse_phone_extractions_device', 'tenant_id, device_uid', false],
        ['idx_sse_phone_extractions_identity', 'tenant_id, identity_id', false],
    ]);

    $create('sse_phone_extraction_items', [
        $id,
        'tenant_id INT UNSIGNED NOT NULL',
        'extraction_id INT UNSIGNED NOT NULL',
        'category VARCHAR(10) NOT NULL',
        'at VARCHAR(32) DEFAULT NULL',
        'title VARCHAR(190) DEFAULT NULL',
        'body TEXT DEFAULT NULL',
        'number VARCHAR(40) DEFAULT NULL',
        'number_norm VARCHAR(40) DEFAULT NULL',
        'grid VARCHAR(32) DEFAULT NULL',
        'direction VARCHAR(4) DEFAULT NULL',
        'extra_json TEXT DEFAULT NULL',
        'norm ' . $text . ' DEFAULT NULL',
    ], [
        ['idx_sse_phone_items_extraction', 'tenant_id, extraction_id, category', false],
        ['idx_sse_phone_items_number', 'tenant_id, number_norm', false],
    ]);

    $create('sse_lore_sessions', [
        $id,
        'tenant_id INT UNSIGNED NOT NULL',
        'op_key VARCHAR(64) NOT NULL',
        'context_id INT UNSIGNED DEFAULT NULL',
        'title VARCHAR(160) NOT NULL',
        'summary VARCHAR(2000) DEFAULT NULL',
        'country VARCHAR(3) DEFAULT NULL',
        'phase SMALLINT UNSIGNED NOT NULL DEFAULT 1',
        'locked_at DATETIME DEFAULT NULL',
        'locked_by_user_id INT UNSIGNED DEFAULT NULL',
        'auto_refresh_s INT UNSIGNED NOT NULL DEFAULT 0',
        'last_push_at DATETIME DEFAULT NULL',
        'last_push_count INT UNSIGNED NOT NULL DEFAULT 0',
        'last_pull_at DATETIME DEFAULT NULL',
        'content_updated_at DATETIME DEFAULT NULL',
        'created_by_user_id INT UNSIGNED DEFAULT NULL',
        'created_at DATETIME NOT NULL',
        'updated_at DATETIME DEFAULT NULL',
    ], [
        ['uq_sse_lore_sessions_op', 'tenant_id, op_key', true],
    ]);

    $create('sse_lore_members', [
        $id,
        'tenant_id INT UNSIGNED NOT NULL',
        'session_id INT UNSIGNED NOT NULL',
        'identity_id INT UNSIGNED NOT NULL',
        'ai_uid VARCHAR(64) DEFAULT NULL',
        "origin VARCHAR(8) NOT NULL DEFAULT 'web'",
        'last_seen_at DATETIME DEFAULT NULL',
        'created_at DATETIME NOT NULL',
    ], [
        ['uq_sse_lore_members_identity', 'session_id, identity_id', true],
        ['idx_sse_lore_members_ai', 'tenant_id, session_id, ai_uid', false],
    ]);
};
