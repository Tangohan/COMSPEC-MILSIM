<?php

declare(strict_types=1);

use App\Support\Migrations\SchemaInspector;

/**
 * COMSPEC IDENTITY (Lot 1) — registre des identités, papiers et contrôles d'identité du SSE.
 *
 * Tables (communauté = tenant_id ; carte = context_id, NULL = toutes les cartes ; opération = op_key, NULL = toutes) :
 * - sse_id_countries : pays émetteurs (fictifs par défaut : ALT, TNA, LVN, MLD), format du numéro, code MRZ.
 * - sse_identities : identités (civile, alias, couverture, falsifiée, générée), rattachables à une fiche sse_persons.
 * - sse_id_documents : papiers (carte d'identité, passeport, permis), statuts multidimensionnels et cycle
 *   brouillon → validé → émis → révoqué ; numéro unique par communauté, pays et type.
 * - sse_id_document_instances : exemplaires physiques en jeu (classe d'objet, détenteur, altérations).
 * - sse_id_checks : contrôles remontés du terrain (N0 à N3), idempotents par communauté et clé.
 *
 * Idempotente (CREATE TABLE IF NOT EXISTS, pays ajoutés seulement s'ils manquent). Fonctionne sur MySQL et sur
 * sqlite (tests) : une seule description des tables, rendue pour chaque moteur.
 */
return static function (PDO $pdo, ?callable $log = null): void {
    $log ??= static function (string $msg): void {
        // Silence web.
    };
    $schema = new SchemaInspector($pdo);
    $sqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';

    if (!$schema->tableExists('tenants')) {
        $log("  [ATTENTION] tenants absente — registre d'identité SSE reporté\n");

        return;
    }

    $tables = [
        'sse_id_countries' => [
            'cols' => [
                'tenant_id' => 'INT UNSIGNED NOT NULL',
                'code' => 'VARCHAR(3) NOT NULL',
                'name' => "VARCHAR(120) NOT NULL DEFAULT ''",
                'name_en' => 'VARCHAR(120) DEFAULT NULL',
                'mrz_code' => 'VARCHAR(3) NOT NULL',
                'number_format' => "VARCHAR(20) NOT NULL DEFAULT '@@#######'",
                'authority' => 'VARCHAR(160) DEFAULT NULL',
                'region' => 'VARCHAR(24) DEFAULT NULL',
                'validity_years' => 'TINYINT UNSIGNED NOT NULL DEFAULT 10',
                'fictional' => 'TINYINT(1) NOT NULL DEFAULT 1',
                'active' => 'TINYINT(1) NOT NULL DEFAULT 1',
                'created_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
                'updated_at' => 'DATETIME DEFAULT NULL',
            ],
            'unique' => ['uq_sse_id_country' => ['tenant_id', 'code']],
            'keys' => [],
            'fks' => ['fk_sse_id_countries_tenant' => ['tenant_id', 'tenants', 'id', 'CASCADE']],
        ],
        'sse_identities' => [
            'cols' => [
                'tenant_id' => 'INT UNSIGNED NOT NULL',
                'context_id' => 'INT UNSIGNED DEFAULT NULL',
                'op_key' => 'VARCHAR(64) DEFAULT NULL',
                'person_id' => 'INT UNSIGNED DEFAULT NULL',
                'kind' => "VARCHAR(16) NOT NULL DEFAULT 'civil'",
                'last_name' => "VARCHAR(120) NOT NULL DEFAULT ''",
                'first_name' => "VARCHAR(120) NOT NULL DEFAULT ''",
                'sex' => "VARCHAR(1) NOT NULL DEFAULT 'X'",
                'birth_date' => 'DATE DEFAULT NULL',
                'birth_place' => 'VARCHAR(120) DEFAULT NULL',
                'nationality' => 'VARCHAR(3) DEFAULT NULL',
                'arma_key' => 'VARCHAR(64) DEFAULT NULL',
                'profession' => 'VARCHAR(120) DEFAULT NULL',
                'seed' => 'INT UNSIGNED DEFAULT NULL',
                'notes' => 'TEXT NULL',
                'created_by_user_id' => 'INT UNSIGNED DEFAULT NULL',
                'created_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
                'updated_at' => 'DATETIME DEFAULT NULL',
            ],
            'unique' => [],
            'keys' => [
                'idx_sse_identities_ctx' => ['tenant_id', 'context_id'],
                'idx_sse_identities_person' => ['tenant_id', 'person_id'],
                'idx_sse_identities_name' => ['tenant_id', 'last_name'],
            ],
            'fks' => ['fk_sse_identities_tenant' => ['tenant_id', 'tenants', 'id', 'CASCADE']],
        ],
        'sse_id_documents' => [
            'cols' => [
                'tenant_id' => 'INT UNSIGNED NOT NULL',
                'context_id' => 'INT UNSIGNED DEFAULT NULL',
                'op_key' => 'VARCHAR(64) DEFAULT NULL',
                'identity_id' => 'INT UNSIGNED NOT NULL',
                'holder_person_id' => 'INT UNSIGNED DEFAULT NULL',
                'doc_type' => "VARCHAR(20) NOT NULL DEFAULT 'id_card'",
                'country_code' => 'VARCHAR(3) NOT NULL',
                'doc_number' => 'VARCHAR(20) NOT NULL',
                'issued_on' => 'DATE DEFAULT NULL',
                'expires_on' => 'DATE DEFAULT NULL',
                'authority' => 'VARCHAR(160) DEFAULT NULL',
                'chip_status' => "VARCHAR(12) NOT NULL DEFAULT 'ok'",
                'st_origin' => "VARCHAR(16) NOT NULL DEFAULT 'official'",
                'st_integrity' => "VARCHAR(16) NOT NULL DEFAULT 'intact'",
                'st_validity' => "VARCHAR(16) NOT NULL DEFAULT 'valid'",
                'st_report' => "VARCHAR(16) NOT NULL DEFAULT 'none'",
                'st_attribution' => "VARCHAR(16) NOT NULL DEFAULT 'holder'",
                'forgery_kind' => 'VARCHAR(24) DEFAULT NULL',
                'forgery_quality' => 'TINYINT UNSIGNED DEFAULT NULL',
                'workflow' => "VARCHAR(12) NOT NULL DEFAULT 'draft'",
                'validated_by_user_id' => 'INT UNSIGNED DEFAULT NULL',
                'validated_at' => 'DATETIME DEFAULT NULL',
                'issued_at' => 'DATETIME DEFAULT NULL',
                'revoked_at' => 'DATETIME DEFAULT NULL',
                'revoked_reason' => 'VARCHAR(255) DEFAULT NULL',
                'notes' => 'TEXT NULL',
                'created_by_user_id' => 'INT UNSIGNED DEFAULT NULL',
                'created_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
                'updated_at' => 'DATETIME DEFAULT NULL',
            ],
            'unique' => ['uq_sse_id_document_number' => ['tenant_id', 'country_code', 'doc_type', 'doc_number']],
            'keys' => [
                'idx_sse_id_documents_pack' => ['tenant_id', 'workflow', 'context_id'],
                'idx_sse_id_documents_identity' => ['identity_id'],
                'idx_sse_id_documents_holder' => ['tenant_id', 'holder_person_id'],
            ],
            'fks' => [
                'fk_sse_id_documents_tenant' => ['tenant_id', 'tenants', 'id', 'CASCADE'],
                'fk_sse_id_documents_identity' => ['identity_id', 'sse_identities', 'id', 'CASCADE'],
            ],
        ],
        'sse_id_document_instances' => [
            'cols' => [
                'tenant_id' => 'INT UNSIGNED NOT NULL',
                'document_id' => 'INT UNSIGNED NOT NULL',
                'instance_class' => 'VARCHAR(64) DEFAULT NULL',
                'holder_person_id' => 'INT UNSIGNED DEFAULT NULL',
                'holder_netid' => 'VARCHAR(64) DEFAULT NULL',
                'state' => "VARCHAR(16) NOT NULL DEFAULT 'in_hand'",
                'altered_json' => 'TEXT NULL',
                'last_seen_at' => 'DATETIME DEFAULT NULL',
                'created_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
                'updated_at' => 'DATETIME DEFAULT NULL',
            ],
            'unique' => [],
            'keys' => ['idx_sse_id_instances_doc' => ['tenant_id', 'document_id']],
            'fks' => [
                'fk_sse_id_instances_tenant' => ['tenant_id', 'tenants', 'id', 'CASCADE'],
                'fk_sse_id_instances_document' => ['document_id', 'sse_id_documents', 'id', 'CASCADE'],
            ],
        ],
        'sse_id_checks' => [
            'cols' => [
                'tenant_id' => 'INT UNSIGNED NOT NULL',
                'context_id' => 'INT UNSIGNED NOT NULL DEFAULT 1',
                'op_key' => 'VARCHAR(64) DEFAULT NULL',
                'idempotency_key' => 'VARCHAR(80) NOT NULL',
                'document_id' => 'INT UNSIGNED DEFAULT NULL',
                'identity_id' => 'INT UNSIGNED DEFAULT NULL',
                'person_id' => 'INT UNSIGNED DEFAULT NULL',
                'doc_type' => 'VARCHAR(20) DEFAULT NULL',
                'country_code' => 'VARCHAR(3) DEFAULT NULL',
                'doc_number' => 'VARCHAR(20) DEFAULT NULL',
                'presented_last' => 'VARCHAR(120) DEFAULT NULL',
                'presented_first' => 'VARCHAR(120) DEFAULT NULL',
                'presented_dob' => 'VARCHAR(16) DEFAULT NULL',
                'subject_netid' => 'VARCHAR(64) DEFAULT NULL',
                'subject_bio_key' => 'VARCHAR(64) DEFAULT NULL',
                'controller_callsign' => 'VARCHAR(80) DEFAULT NULL',
                'controller_steam_id' => 'VARCHAR(32) DEFAULT NULL',
                'levels' => 'VARCHAR(32) DEFAULT NULL',
                'results_json' => 'TEXT NULL',
                'verdict' => 'VARCHAR(24) DEFAULT NULL',
                'grid' => 'VARCHAR(32) DEFAULT NULL',
                'pos_x' => 'DOUBLE DEFAULT NULL',
                'pos_y' => 'DOUBLE DEFAULT NULL',
                'pos_z' => 'DOUBLE DEFAULT NULL',
                'game_time' => 'VARCHAR(32) DEFAULT NULL',
                'case_ref' => 'VARCHAR(64) DEFAULT NULL',
                'created_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
            ],
            'unique' => ['uq_sse_id_check_idem' => ['tenant_id', 'idempotency_key']],
            'keys' => [
                'idx_sse_id_checks_recent' => ['tenant_id', 'created_at'],
                'idx_sse_id_checks_person' => ['tenant_id', 'person_id'],
                'idx_sse_id_checks_document' => ['tenant_id', 'document_id'],
            ],
            'fks' => ['fk_sse_id_checks_tenant' => ['tenant_id', 'tenants', 'id', 'CASCADE']],
        ],
    ];

    foreach ($tables as $table => $spec) {
        if ($schema->tableExists($table)) {
            $log("  [OK] {$table} (déjà présente)\n");
            continue;
        }
        $lines = [$sqlite ? 'id INTEGER PRIMARY KEY AUTOINCREMENT' : 'id INT UNSIGNED NOT NULL AUTO_INCREMENT'];
        foreach ($spec['cols'] as $col => $def) {
            $lines[] = $col . ' ' . ($sqlite ? str_replace(' UNSIGNED', '', $def) : $def);
        }
        if (!$sqlite) {
            $lines[] = 'PRIMARY KEY (id)';
        }
        foreach ($spec['unique'] as $name => $cols) {
            $lines[] = $sqlite
                ? 'CONSTRAINT ' . $name . ' UNIQUE (' . implode(', ', $cols) . ')'
                : 'UNIQUE KEY ' . $name . ' (' . implode(', ', $cols) . ')';
        }
        if (!$sqlite) {
            foreach ($spec['keys'] as $name => $cols) {
                $lines[] = 'KEY ' . $name . ' (' . implode(', ', $cols) . ')';
            }
            foreach ($spec['fks'] as $name => [$col, $refTable, $refCol, $onDelete]) {
                if ($refTable !== 'tenants' && !$schema->tableExists($refTable)) {
                    continue;
                }
                $lines[] = 'CONSTRAINT ' . $name . ' FOREIGN KEY (' . $col . ') REFERENCES ' . $refTable
                    . ' (' . $refCol . ') ON DELETE ' . $onDelete . ' ON UPDATE CASCADE';
            }
        }
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS ' . $table . " (\n    " . implode(",\n    ", $lines) . "\n)"
            . ($sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci')
        );
        if ($sqlite) {
            foreach ($spec['keys'] as $name => $cols) {
                $pdo->exec('CREATE INDEX IF NOT EXISTS ' . $name . ' ON ' . $table . ' (' . implode(', ', $cols) . ')');
            }
        }
        $log("  [OK] {$table}\n");
    }

    // Pays fictifs par défaut : ceux qui manquent à chaque communauté (jamais modifiés s'ils existent).
    $seeded = 0;
    $tenantIds = $pdo->query('SELECT id FROM tenants')->fetchAll(PDO::FETCH_COLUMN);
    $has = $pdo->prepare('SELECT 1 FROM sse_id_countries WHERE tenant_id = ? AND code = ?');
    $insert = $pdo->prepare(
        'INSERT INTO sse_id_countries (tenant_id, code, name, name_en, mrz_code, number_format, authority, region, validity_years, fictional, active)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1)'
    );
    foreach ($tenantIds as $tid) {
        foreach (\App\Repositories\SseIdCountryRepository::DEFAULTS as $c) {
            $has->execute([(int) $tid, $c['code']]);
            $exists = $has->fetchColumn() !== false;
            $has->closeCursor();
            if ($exists) {
                continue;
            }
            $insert->execute([
                (int) $tid, $c['code'], $c['name'], $c['name_en'], $c['code'], $c['number_format'],
                $c['authority'], $c['region'], $c['validity_years'],
            ]);
            $seeded++;
        }
    }
    $log('  [OK] Pays fictifs du registre d’identité : ' . $seeded . " ajout(s)\n");
};
