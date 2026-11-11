<?php

declare(strict_types=1);

use App\Support\Migrations\SchemaInspector;

/**
 * Fichiers SSE — fiche sujet complète, recherche avancée et recoupements (étape 4340) :
 *  - sse_file_profiles : signalement d'une identité (taille, poids, corpulence, cheveux, yeux, signes), pays du sujet,
 *    codes de prudence, présence aux fichiers, identifiant stable d'IA, session de lore, visibilité par donnée
 *    (connu des fichiers / caché MJ, phase de révélation), ce que la personne sait et réponses de l'IA (MJ seulement) ;
 *  - sse_file_aliases : noms connus (nom légal recopié, alias, surnoms, variantes d'orthographe), forme normalisée et
 *    clé phonétique pour la recherche tolérante ;
 *  - sse_file_links : adresses, véhicules, téléphones, courriels et personnes liées (famille, associé, cellule) ;
 *  - sse_file_photos : photos signalétiques et photos rattachées (envoi d'un membre, photo ATAK), jamais de biométrie ;
 *  - sse_file_notes : annotations du dossier par les membres ;
 *  - sse_file_searches : recherches récentes et enregistrées de chaque membre ;
 *  - sse_file_settings : réglages des fichiers par communauté (pays par défaut, conservation, droits) ;
 *  - sse_file_xref, sse_file_xref_cursor, sse_file_xref_decisions : index des mots et numéros cités dans les sources
 *    (comptes rendus, fiches, contrôles, extractions), avancement de l'indexation, « rattaché » / « ce n'est pas lui » ;
 *  - sse_file_watches : surveillances posées par un membre (sujet, nom, numéro) ;
 *  - sse_file_queries : sujet consulté (identity_id) et motif de la consultation (purpose).
 *
 * Idempotente ; MySQL, MariaDB et sqlite (tests). Pas de clé étrangère hors communauté (types variables d'une base à
 * l'autre) : la communauté est vérifiée à chaque requête.
 */
return static function (PDO $pdo, ?callable $log = null): void {
    $log ??= static function (string $msg): void {
        // Silence web.
    };
    $schema = new SchemaInspector($pdo);
    $sqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    $id = $sqlite ? 'id INTEGER PRIMARY KEY AUTOINCREMENT' : 'id INT UNSIGNED NOT NULL AUTO_INCREMENT';
    $engine = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    /**
     * @param list<string> $columns
     * @param list<array{0: string, 1: string, 2: bool}> $indexes nom, colonnes, unique
     */
    $create = static function (string $table, array $columns, array $indexes, bool $withId = true) use ($pdo, $schema, $sqlite, $engine, $log): void {
        if ($schema->tableExists($table)) {
            $log("  [OK] {$table} (déjà présente)\n");

            return;
        }
        $lines = $columns;
        if (!$sqlite) {
            if ($withId) {
                $lines[] = 'PRIMARY KEY (id)';
            }
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

    $create('sse_file_profiles', [
        $id,
        'tenant_id INT UNSIGNED NOT NULL',
        'identity_id INT UNSIGNED NOT NULL',
        'country VARCHAR(3) DEFAULT NULL',
        'height_cm SMALLINT UNSIGNED DEFAULT NULL',
        'weight_kg SMALLINT UNSIGNED DEFAULT NULL',
        'build VARCHAR(16) DEFAULT NULL',
        'hair VARCHAR(16) DEFAULT NULL',
        'eyes VARCHAR(16) DEFAULT NULL',
        'complexion VARCHAR(24) DEFAULT NULL',
        'marks VARCHAR(1000) DEFAULT NULL',
        'marks_norm VARCHAR(1000) DEFAULT NULL',
        'caution_codes VARCHAR(255) DEFAULT NULL',
        'in_files TINYINT(1) NOT NULL DEFAULT 1',
        'affiliation VARCHAR(160) DEFAULT NULL',
        'cover_note VARCHAR(1000) DEFAULT NULL',
        'knowledge TEXT DEFAULT NULL',
        'ai_answers_json TEXT DEFAULT NULL',
        'visibility_json TEXT DEFAULT NULL',
        'ai_uid VARCHAR(64) DEFAULT NULL',
        'lore_session_id INT UNSIGNED DEFAULT NULL',
        'merged_into_id INT UNSIGNED DEFAULT NULL',
        'updated_by_user_id INT UNSIGNED DEFAULT NULL',
        'created_at DATETIME NOT NULL',
        'updated_at DATETIME DEFAULT NULL',
    ], [
        ['uq_sse_file_profiles_identity', 'tenant_id, identity_id', true],
        ['idx_sse_file_profiles_height', 'tenant_id, height_cm', false],
        ['idx_sse_file_profiles_ai', 'tenant_id, ai_uid', false],
        ['idx_sse_file_profiles_lore', 'tenant_id, lore_session_id', false],
    ]);

    $create('sse_file_aliases', [
        $id,
        'tenant_id INT UNSIGNED NOT NULL',
        'identity_id INT UNSIGNED NOT NULL',
        'alias VARCHAR(160) NOT NULL',
        "kind VARCHAR(12) NOT NULL DEFAULT 'aka'",
        'norm VARCHAR(160) NOT NULL',
        'phonetic VARCHAR(160) NOT NULL',
        "visibility VARCHAR(8) NOT NULL DEFAULT 'known'",
        'reveal_phase SMALLINT UNSIGNED NOT NULL DEFAULT 0',
        'created_by_user_id INT UNSIGNED DEFAULT NULL',
        'created_at DATETIME NOT NULL',
    ], [
        ['idx_sse_file_aliases_norm', 'tenant_id, norm', false],
        ['idx_sse_file_aliases_phon', 'tenant_id, phonetic', false],
        ['idx_sse_file_aliases_identity', 'tenant_id, identity_id', false],
    ]);

    $create('sse_file_links', [
        $id,
        'tenant_id INT UNSIGNED NOT NULL',
        'identity_id INT UNSIGNED NOT NULL',
        'kind VARCHAR(12) NOT NULL',
        'value VARCHAR(255) NOT NULL',
        'value_norm VARCHAR(255) NOT NULL',
        'detail_json TEXT DEFAULT NULL',
        'linked_identity_id INT UNSIGNED DEFAULT NULL',
        'relation VARCHAR(24) DEFAULT NULL',
        'flag VARCHAR(16) DEFAULT NULL',
        "visibility VARCHAR(8) NOT NULL DEFAULT 'known'",
        'reveal_phase SMALLINT UNSIGNED NOT NULL DEFAULT 0',
        'created_by_user_id INT UNSIGNED DEFAULT NULL',
        'created_at DATETIME NOT NULL',
    ], [
        ['idx_sse_file_links_value', 'tenant_id, kind, value_norm', false],
        ['idx_sse_file_links_identity', 'tenant_id, identity_id', false],
        ['idx_sse_file_links_linked', 'tenant_id, linked_identity_id', false],
    ]);

    $create('sse_file_photos', [
        $id,
        'tenant_id INT UNSIGNED NOT NULL',
        'identity_id INT UNSIGNED DEFAULT NULL',
        'path VARCHAR(255) NOT NULL',
        'mime VARCHAR(32) NOT NULL',
        'width SMALLINT UNSIGNED DEFAULT NULL',
        'height SMALLINT UNSIGNED DEFAULT NULL',
        'bytes INT UNSIGNED DEFAULT NULL',
        'sha1 CHAR(40) DEFAULT NULL',
        "kind VARCHAR(12) NOT NULL DEFAULT 'field'",
        "source VARCHAR(12) NOT NULL DEFAULT 'upload'",
        'source_ref VARCHAR(80) DEFAULT NULL',
        'caption VARCHAR(255) DEFAULT NULL',
        "visibility VARCHAR(8) NOT NULL DEFAULT 'known'",
        'uploaded_by_user_id INT UNSIGNED DEFAULT NULL',
        'created_at DATETIME NOT NULL',
    ], [
        ['idx_sse_file_photos_identity', 'tenant_id, identity_id', false],
        ['idx_sse_file_photos_source', 'tenant_id, source, source_ref', false],
    ]);

    $create('sse_file_notes', [
        $id,
        'tenant_id INT UNSIGNED NOT NULL',
        'identity_id INT UNSIGNED NOT NULL',
        'user_id INT UNSIGNED DEFAULT NULL',
        'author_label VARCHAR(80) DEFAULT NULL',
        'body VARCHAR(2000) NOT NULL',
        'created_at DATETIME NOT NULL',
        'deleted_at DATETIME DEFAULT NULL',
    ], [
        ['idx_sse_file_notes_identity', 'tenant_id, identity_id', false],
    ]);

    $create('sse_file_searches', [
        $id,
        'tenant_id INT UNSIGNED NOT NULL',
        'user_id INT UNSIGNED NOT NULL',
        'label VARCHAR(80) DEFAULT NULL',
        'params_json VARCHAR(2000) NOT NULL',
        'params_hash CHAR(40) NOT NULL',
        'saved TINYINT(1) NOT NULL DEFAULT 0',
        'uses INT UNSIGNED NOT NULL DEFAULT 1',
        'last_used_at DATETIME NOT NULL',
    ], [
        ['uq_sse_file_searches_hash', 'tenant_id, user_id, params_hash', true],
        ['idx_sse_file_searches_user', 'tenant_id, user_id, saved, last_used_at', false],
    ]);

    $create('sse_file_settings', [
        'tenant_id INT UNSIGNED NOT NULL PRIMARY KEY',
        'settings_json TEXT NOT NULL',
        'updated_by_user_id INT UNSIGNED DEFAULT NULL',
        'updated_at DATETIME NOT NULL',
    ], [], false);

    $create('sse_file_xref', [
        $id,
        'tenant_id INT UNSIGNED NOT NULL',
        'source_type VARCHAR(16) NOT NULL',
        'source_id INT UNSIGNED NOT NULL',
        "token_kind VARCHAR(8) NOT NULL DEFAULT 'word'",
        'token VARCHAR(64) NOT NULL',
        'phonetic VARCHAR(64) DEFAULT NULL',
        'scope VARCHAR(24) DEFAULT NULL',
        'op_key VARCHAR(64) DEFAULT NULL',
        'created_at DATETIME NOT NULL',
    ], [
        ['idx_sse_file_xref_token', 'tenant_id, token', false],
        ['idx_sse_file_xref_phon', 'tenant_id, phonetic', false],
        ['idx_sse_file_xref_source', 'tenant_id, source_type, source_id', false],
    ]);

    $create('sse_file_xref_cursor', [
        $id,
        'tenant_id INT UNSIGNED NOT NULL',
        'source_type VARCHAR(16) NOT NULL',
        'last_id INT UNSIGNED NOT NULL DEFAULT 0',
        'updated_at DATETIME NOT NULL',
    ], [
        ['uq_sse_file_xref_cursor', 'tenant_id, source_type', true],
    ]);

    $create('sse_file_xref_decisions', [
        $id,
        'tenant_id INT UNSIGNED NOT NULL',
        'identity_id INT UNSIGNED NOT NULL',
        'source_type VARCHAR(16) NOT NULL',
        'source_id INT UNSIGNED NOT NULL',
        'decision VARCHAR(8) NOT NULL',
        'user_id INT UNSIGNED DEFAULT NULL',
        'created_at DATETIME NOT NULL',
    ], [
        ['uq_sse_file_xref_decisions', 'tenant_id, identity_id, source_type, source_id', true],
    ]);

    $create('sse_file_watches', [
        $id,
        'tenant_id INT UNSIGNED NOT NULL',
        'user_id INT UNSIGNED NOT NULL',
        'kind VARCHAR(10) NOT NULL',
        'value VARCHAR(120) NOT NULL',
        'token VARCHAR(64) NOT NULL',
        'identity_id INT UNSIGNED DEFAULT NULL',
        'hits INT UNSIGNED NOT NULL DEFAULT 0',
        'last_hit_at DATETIME DEFAULT NULL',
        'created_at DATETIME NOT NULL',
    ], [
        ['idx_sse_file_watches_token', 'tenant_id, token', false],
        ['idx_sse_file_watches_user', 'tenant_id, user_id', false],
    ]);

    if ($schema->tableExists('sse_file_queries')) {
        $u = static fn (string $d): string => $sqlite ? str_replace(' UNSIGNED', '', $d) : $d;
        if ($schema->addColumnIfMissing('sse_file_queries', 'identity_id', $u('INT UNSIGNED DEFAULT NULL'))) {
            $log("  [OK] sse_file_queries.identity_id\n");
        }
        if ($schema->addColumnIfMissing('sse_file_queries', 'purpose', 'VARCHAR(4) DEFAULT NULL')) {
            $log("  [OK] sse_file_queries.purpose\n");
        }
        if ($schema->addIndexIfMissing('sse_file_queries', 'idx_sse_file_queries_identity', ['tenant_id', 'identity_id'])) {
            $log("  [OK] idx_sse_file_queries_identity\n");
        }
    }
};
