<?php

declare(strict_types=1);

use App\Support\Migrations\SchemaInspector;

/**
 * Lien Athena des couvertures (COMSPEC IDENTITY) :
 *  - sse_id_game_covers : couverture née en jeu, remontée par le poste du joueur (une par compte et par portée) ;
 *  - sse_editor_item_assignments : contenu de la bibliothèque de l'éditeur attribué à un joueur.
 *
 * Idempotente ; MySQL, MariaDB et sqlite (tests). La clé étrangère vers tenants n'existe pas en sqlite.
 */
return static function (PDO $pdo, ?callable $log = null): void {
    $log ??= static function (string $msg): void {
        // Silence web.
    };
    $schema = new SchemaInspector($pdo);
    $sqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    $create = static function (string $table, array $columns, array $keys, array $sqliteIndexes) use ($pdo, $schema, $sqlite, $log): void {
        if ($schema->tableExists($table)) {
            $log("  [OK] {$table} (déjà présente)\n");

            return;
        }
        $lines = array_merge([$sqlite ? 'id INTEGER PRIMARY KEY AUTOINCREMENT' : 'id INT UNSIGNED NOT NULL AUTO_INCREMENT'], $columns);
        if (!$sqlite) {
            $lines = array_merge($lines, ['PRIMARY KEY (id)'], $keys);
        }
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS {$table} (\n    " . implode(",\n    ", $lines) . "\n)"
            . ($sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci')
        );
        if ($sqlite) {
            foreach ($sqliteIndexes as $index) {
                $pdo->exec($index);
            }
        }
        $log("  [OK] {$table}\n");
    };

    $create('sse_id_game_covers', [
        'tenant_id INT UNSIGNED NOT NULL',
        'user_id INT UNSIGNED NOT NULL',
        'scope_key VARCHAR(90) NOT NULL',
        'context_id INT UNSIGNED DEFAULT NULL',
        'op_key VARCHAR(64) DEFAULT NULL',
        'content_hash CHAR(40) NOT NULL',
        'cover_json TEXT NOT NULL',
        'created_at DATETIME NOT NULL',
        'updated_at DATETIME NOT NULL',
    ], [
        'UNIQUE KEY uq_sse_id_game_covers (tenant_id, user_id, scope_key)',
        'CONSTRAINT fk_sse_id_game_covers_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE',
    ], [
        'CREATE UNIQUE INDEX IF NOT EXISTS uq_sse_id_game_covers ON sse_id_game_covers (tenant_id, user_id, scope_key)',
    ]);

    $create('sse_editor_item_assignments', [
        'tenant_id INT UNSIGNED NOT NULL',
        'item_id INT UNSIGNED NOT NULL',
        'user_id INT UNSIGNED NOT NULL',
        'scope_key VARCHAR(90) NOT NULL',
        'context_id INT UNSIGNED DEFAULT NULL',
        'op_key VARCHAR(64) DEFAULT NULL',
        'identity_id INT UNSIGNED NOT NULL',
        'document_ids TEXT NOT NULL',
        'item_version INT NOT NULL DEFAULT 1',
        'created_by_user_id INT UNSIGNED DEFAULT NULL',
        'created_at DATETIME NOT NULL',
        'updated_at DATETIME NOT NULL',
    ], [
        'UNIQUE KEY uq_sse_editor_item_assignments (tenant_id, item_id, user_id, scope_key)',
        'KEY idx_sse_editor_item_assignments_item (tenant_id, item_id)',
        'CONSTRAINT fk_sse_editor_item_assignments_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE',
    ], [
        'CREATE UNIQUE INDEX IF NOT EXISTS uq_sse_editor_item_assignments ON sse_editor_item_assignments (tenant_id, item_id, user_id, scope_key)',
    ]);
};
