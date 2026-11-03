<?php

declare(strict_types=1);

use App\Support\Migrations\SchemaInspector;

/**
 * COMSPEC IDENTITY (Lot 6) — atelier de documents, kits types, préparation de mission en masse, briefs de couverture.
 *
 * - sse_identities : lot de génération (batch_id), catégorie de population (civil, militaire, suspect, HVT), camp,
 *   faction, langue, zone, alias, photo (site seulement).
 * - sse_id_documents : modèle d'impression (layout), champs imprimés en plus (print_json), papier d'origine d'une
 *   copie (duplicated_from_id), lot de génération (batch_id).
 * - sse_id_kit_templates : kits documentaires types (civil, militaire, diplomatique, journaliste, contractor, agent
 *   infiltré…) réutilisables, affectés à des membres.
 * - sse_id_batches : lots de population générés pour une opération (paramètres, graine, fichiers visibles par camp).
 * - sse_id_op_wanted : recherchés et HVT d'une opération (camps qui y ont accès).
 * - sse_id_briefs : fiche de couverture remise à un joueur (biographie, légende, contacts, consignes).
 *
 * Idempotente (colonnes, index et tables ajoutés seulement s'ils manquent) ; MySQL et sqlite (tests). Suppose le
 * registre (atak_sse_identity_migration.php) déjà passé : sinon ne fait rien.
 */
return static function (PDO $pdo, ?callable $log = null): void {
    $log ??= static function (string $msg): void {
        // Silence web.
    };
    $schema = new SchemaInspector($pdo);
    $sqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    $u = static fn (string $def): string => $sqlite ? str_replace(' UNSIGNED', '', $def) : $def;

    if (!$schema->tableExists('sse_identities') || !$schema->tableExists('sse_id_documents')) {
        $log("  [ATTENTION] registre d'identité SSE absent — atelier de documents reporté\n");

        return;
    }

    $columns = [
        'sse_identities' => [
            'batch_id' => 'INT UNSIGNED DEFAULT NULL',
            'category' => 'VARCHAR(16) DEFAULT NULL',
            'side' => 'VARCHAR(12) DEFAULT NULL',
            'faction' => 'VARCHAR(64) DEFAULT NULL',
            'language' => 'VARCHAR(8) DEFAULT NULL',
            'zone' => 'VARCHAR(64) DEFAULT NULL',
            'alias' => 'VARCHAR(120) DEFAULT NULL',
            'photo_path' => 'VARCHAR(255) DEFAULT NULL',
        ],
        'sse_id_documents' => [
            'layout' => 'VARCHAR(16) DEFAULT NULL',
            'print_json' => 'TEXT NULL',
            'duplicated_from_id' => 'INT UNSIGNED DEFAULT NULL',
            'batch_id' => 'INT UNSIGNED DEFAULT NULL',
        ],
    ];
    foreach ($columns as $table => $cols) {
        foreach ($cols as $col => $def) {
            if ($schema->addColumnIfMissing($table, $col, $u($def))) {
                $log("  [OK] {$table}.{$col}\n");
            }
        }
    }
    foreach ([
        ['sse_identities', 'idx_sse_identities_batch', ['tenant_id', 'batch_id']],
        ['sse_id_documents', 'idx_sse_id_documents_batch', ['tenant_id', 'batch_id']],
        ['sse_id_documents', 'idx_sse_id_documents_dup', ['tenant_id', 'duplicated_from_id']],
    ] as [$table, $name, $cols]) {
        if ($schema->addIndexIfMissing($table, $name, $cols)) {
            $log("  [OK] index {$name}\n");
        }
    }

    $tables = [
        'sse_id_kit_templates' => [
            'cols' => [
                'tenant_id INT UNSIGNED NOT NULL',
                "name VARCHAR(120) NOT NULL DEFAULT ''",
                "category VARCHAR(16) NOT NULL DEFAULT 'civil'",
                'description VARCHAR(500) DEFAULT NULL',
                'country_code VARCHAR(3) DEFAULT NULL',
                "doc_types VARCHAR(80) NOT NULL DEFAULT 'id_card'",
                "identity_mode VARCHAR(12) NOT NULL DEFAULT 'primary'",
                'profession VARCHAR(120) DEFAULT NULL',
                'layout VARCHAR(16) DEFAULT NULL',
                'phone VARCHAR(40) DEFAULT NULL',
                'address VARCHAR(255) DEFAULT NULL',
                'biography TEXT NULL',
                'legend TEXT NULL',
                'contacts TEXT NULL',
                'instructions TEXT NULL',
                'print_json TEXT NULL',
                'archived_at DATETIME DEFAULT NULL',
                'created_by_user_id INT UNSIGNED DEFAULT NULL',
                'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
                'updated_at DATETIME DEFAULT NULL',
            ],
            'keys' => ['idx_sse_id_kit_templates_tenant' => ['tenant_id', 'archived_at']],
        ],
        'sse_id_batches' => [
            'cols' => [
                'tenant_id INT UNSIGNED NOT NULL',
                'context_id INT UNSIGNED DEFAULT NULL',
                'op_key VARCHAR(64) DEFAULT NULL',
                "label VARCHAR(120) NOT NULL DEFAULT ''",
                'params_json TEXT NULL',
                'seed INT UNSIGNED NOT NULL DEFAULT 0',
                'identities_count INT UNSIGNED NOT NULL DEFAULT 0',
                'documents_count INT UNSIGNED NOT NULL DEFAULT 0',
                'visible_sides VARCHAR(64) DEFAULT NULL',
                'archived_at DATETIME DEFAULT NULL',
                'created_by_user_id INT UNSIGNED DEFAULT NULL',
                'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
            ],
            'keys' => ['idx_sse_id_batches_scope' => ['tenant_id', 'context_id', 'op_key']],
        ],
        'sse_id_op_wanted' => [
            'cols' => [
                'tenant_id INT UNSIGNED NOT NULL',
                'context_id INT UNSIGNED DEFAULT NULL',
                'op_key VARCHAR(64) DEFAULT NULL',
                'identity_id INT UNSIGNED NOT NULL',
                "level VARCHAR(16) NOT NULL DEFAULT 'recherche'",
                'alias VARCHAR(120) DEFAULT NULL',
                'sides VARCHAR(64) DEFAULT NULL',
                'notes VARCHAR(1000) DEFAULT NULL',
                'batch_id INT UNSIGNED DEFAULT NULL',
                'created_by_user_id INT UNSIGNED DEFAULT NULL',
                'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
            ],
            'keys' => [
                'idx_sse_id_op_wanted_scope' => ['tenant_id', 'context_id', 'op_key'],
                'idx_sse_id_op_wanted_identity' => ['tenant_id', 'identity_id'],
            ],
        ],
        'sse_id_briefs' => [
            'cols' => [
                'tenant_id INT UNSIGNED NOT NULL',
                'user_id INT UNSIGNED NOT NULL',
                'identity_id INT UNSIGNED DEFAULT NULL',
                'kit_template_id INT UNSIGNED DEFAULT NULL',
                'context_id INT UNSIGNED DEFAULT NULL',
                'op_key VARCHAR(64) DEFAULT NULL',
                "title VARCHAR(160) NOT NULL DEFAULT ''",
                'biography TEXT NULL',
                'legend TEXT NULL',
                'contacts TEXT NULL',
                'instructions TEXT NULL',
                'phone VARCHAR(40) DEFAULT NULL',
                'address VARCHAR(255) DEFAULT NULL',
                'created_by_user_id INT UNSIGNED DEFAULT NULL',
                'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
                'updated_at DATETIME DEFAULT NULL',
            ],
            'keys' => ['idx_sse_id_briefs_user' => ['tenant_id', 'user_id']],
        ],
    ];
    foreach ($tables as $table => $spec) {
        if ($schema->tableExists($table)) {
            $log("  [OK] {$table} (déjà présente)\n");
            continue;
        }
        $lines = [$sqlite ? 'id INTEGER PRIMARY KEY AUTOINCREMENT' : 'id INT UNSIGNED NOT NULL AUTO_INCREMENT'];
        foreach ($spec['cols'] as $def) {
            $lines[] = $u($def);
        }
        if (!$sqlite) {
            $lines[] = 'PRIMARY KEY (id)';
            foreach ($spec['keys'] as $name => $cols) {
                $lines[] = 'KEY ' . $name . ' (' . implode(', ', $cols) . ')';
            }
            $lines[] = 'CONSTRAINT fk_' . $table . '_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE';
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
};
