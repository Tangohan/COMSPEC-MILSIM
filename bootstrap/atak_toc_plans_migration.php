<?php

declare(strict_types=1);

use App\Support\Migrations\SchemaInspector;

/**
 * Poste TOC web et plans d'opération (étape 4370) :
 *  - atak_plans : plan d'opération dessiné sur un terrain (titre, terrain, partage, modèle, verrou, révision, calques,
 *    légende et ordre d'opération dans meta_json, dernier envoi en jeu) ;
 *  - atak_plan_elements : éléments du plan (symbole OTAN, graphisme tactique, trait, zone, texte…), un par identifiant
 *    stable (uid) ; le dernier enregistré gagne, élément par élément (updated_at_ms) ;
 *  - atak_plan_phases : phases ordonnées du plan (briefing pas à pas) ;
 *  - atak_plan_versions : versions enregistrées du plan (copie complète, restaurable) ;
 *  - atak_plan_shares : partage d'un plan avec une unité ou un membre précis ;
 *  - atak_plan_comments : commentaires sur le plan ou sur un élément ;
 *  - atak_plan_presence : membres qui ont le plan ouvert (présence, dernière vue) ;
 *  - atak_toc_rooms : salle TOC de chaque carte et de chaque camp (copie de l'état du jeu et état tenu sur le web) ;
 *  - atak_toc_actions : actions faites depuis le TOC web (chrono, main courante, EXCHECK, demandes, FLASH…) et leur
 *    passage en jeu (commande web envoyée, appliquée par le serveur du jeu).
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

    $create('atak_plans', [
        $id,
        'tenant_id INT UNSIGNED NOT NULL',
        'owner_user_id INT UNSIGNED NOT NULL',
        'title VARCHAR(120) NOT NULL',
        'map_id INT UNSIGNED NOT NULL DEFAULT 1',
        "world VARCHAR(64) NOT NULL DEFAULT ''",
        "sharing VARCHAR(12) NOT NULL DEFAULT 'private'",
        'unit_id INT UNSIGNED DEFAULT NULL',
        'share_token CHAR(32) DEFAULT NULL',
        'is_template TINYINT(1) NOT NULL DEFAULT 0',
        'locked TINYINT(1) NOT NULL DEFAULT 0',
        'rev INT UNSIGNED NOT NULL DEFAULT 0',
        "meta_json {$text} NULL",
        'published_at DATETIME DEFAULT NULL',
        "published_side VARCHAR(8) NOT NULL DEFAULT ''",
        'published_rev INT UNSIGNED NOT NULL DEFAULT 0',
        'published_count INT UNSIGNED NOT NULL DEFAULT 0',
        'created_at DATETIME NOT NULL',
        'updated_at DATETIME NOT NULL',
        'deleted_at DATETIME DEFAULT NULL',
    ], [
        ['idx_atak_plans_tenant', 'tenant_id, deleted_at, updated_at', false],
        ['idx_atak_plans_owner', 'tenant_id, owner_user_id', false],
        ['uq_atak_plans_share_token', 'share_token', true],
    ]);

    $create('atak_plan_elements', [
        $id,
        'tenant_id INT UNSIGNED NOT NULL',
        'plan_id INT UNSIGNED NOT NULL',
        'uid VARCHAR(32) NOT NULL',
        'kind VARCHAR(24) NOT NULL',
        "geom VARCHAR(8) NOT NULL DEFAULT 'point'",
        "pts_json {$text} NOT NULL",
        'props_json TEXT NULL',
        "phases_json VARCHAR(600) NOT NULL DEFAULT '[]'",
        "layer_uid VARCHAR(32) NOT NULL DEFAULT ''",
        "group_uid VARCHAR(32) NOT NULL DEFAULT ''",
        'z INT NOT NULL DEFAULT 0',
        'locked TINYINT(1) NOT NULL DEFAULT 0',
        'deleted TINYINT(1) NOT NULL DEFAULT 0',
        'updated_at_ms BIGINT NOT NULL DEFAULT 0',
        'updated_by INT UNSIGNED DEFAULT NULL',
    ], [
        ['uq_atak_plan_elements_uid', 'plan_id, uid', true],
        ['idx_atak_plan_elements_sync', 'plan_id, updated_at_ms', false],
    ]);

    $create('atak_plan_phases', [
        $id,
        'tenant_id INT UNSIGNED NOT NULL',
        'plan_id INT UNSIGNED NOT NULL',
        'uid VARCHAR(32) NOT NULL',
        'ord INT NOT NULL DEFAULT 0',
        'name VARCHAR(80) NOT NULL',
        'note TEXT NULL',
        "time_label VARCHAR(40) NOT NULL DEFAULT ''",
        'deleted TINYINT(1) NOT NULL DEFAULT 0',
        'updated_at_ms BIGINT NOT NULL DEFAULT 0',
    ], [
        ['uq_atak_plan_phases_uid', 'plan_id, uid', true],
    ]);

    $create('atak_plan_versions', [
        $id,
        'tenant_id INT UNSIGNED NOT NULL',
        'plan_id INT UNSIGNED NOT NULL',
        'rev INT UNSIGNED NOT NULL DEFAULT 0',
        "label VARCHAR(120) NOT NULL DEFAULT ''",
        "snapshot_json {$text} NOT NULL",
        'created_by INT UNSIGNED DEFAULT NULL',
        'created_at DATETIME NOT NULL',
    ], [
        ['idx_atak_plan_versions_plan', 'plan_id, id', false],
    ]);

    $create('atak_plan_shares', [
        $id,
        'tenant_id INT UNSIGNED NOT NULL',
        'plan_id INT UNSIGNED NOT NULL',
        'scope VARCHAR(8) NOT NULL',
        'ref_id INT UNSIGNED NOT NULL',
        'can_edit TINYINT(1) NOT NULL DEFAULT 0',
        'created_at DATETIME NOT NULL',
    ], [
        ['uq_atak_plan_shares', 'plan_id, scope, ref_id', true],
    ]);

    $create('atak_plan_comments', [
        $id,
        'tenant_id INT UNSIGNED NOT NULL',
        'plan_id INT UNSIGNED NOT NULL',
        'element_uid VARCHAR(32) DEFAULT NULL',
        'user_id INT UNSIGNED NOT NULL',
        "author_label VARCHAR(80) NOT NULL DEFAULT ''",
        'body TEXT NOT NULL',
        'resolved TINYINT(1) NOT NULL DEFAULT 0',
        'created_at DATETIME NOT NULL',
    ], [
        ['idx_atak_plan_comments_plan', 'plan_id, id', false],
    ]);

    $create('atak_plan_presence', [
        $id,
        'plan_id INT UNSIGNED NOT NULL',
        'user_id INT UNSIGNED NOT NULL',
        "label VARCHAR(80) NOT NULL DEFAULT ''",
        "tool VARCHAR(24) NOT NULL DEFAULT ''",
        'seen_at DATETIME NOT NULL',
    ], [
        ['uq_atak_plan_presence', 'plan_id, user_id', true],
    ]);

    $create('atak_toc_rooms', [
        $id,
        'tenant_id INT UNSIGNED NOT NULL',
        'map_id INT UNSIGNED NOT NULL DEFAULT 1',
        'side VARCHAR(8) NOT NULL',
        "mission_key VARCHAR(190) NOT NULL DEFAULT ''",
        "world VARCHAR(64) NOT NULL DEFAULT ''",
        'game_rev INT UNSIGNED NOT NULL DEFAULT 0',
        'game_seen_at DATETIME DEFAULT NULL',
        "state_json {$text} NOT NULL",
        'web_rev INT UNSIGNED NOT NULL DEFAULT 0',
        'updated_at DATETIME NOT NULL',
    ], [
        ['uq_atak_toc_rooms', 'tenant_id, map_id, side', true],
    ]);

    $create('atak_toc_actions', [
        $id,
        'tenant_id INT UNSIGNED NOT NULL',
        'map_id INT UNSIGNED NOT NULL DEFAULT 1',
        'side VARCHAR(8) NOT NULL',
        'user_id INT UNSIGNED DEFAULT NULL',
        "actor_label VARCHAR(80) NOT NULL DEFAULT ''",
        'act VARCHAR(24) NOT NULL',
        'args_json TEXT NULL',
        "status VARCHAR(12) NOT NULL DEFAULT 'queued'",
        'web_command_id INT UNSIGNED DEFAULT NULL',
        'created_at DATETIME NOT NULL',
        'applied_at DATETIME DEFAULT NULL',
    ], [
        ['idx_atak_toc_actions_room', 'tenant_id, map_id, side, status', false],
        ['idx_atak_toc_actions_cmd', 'web_command_id', false],
    ]);
};
