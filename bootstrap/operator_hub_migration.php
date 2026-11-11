<?php

declare(strict_types=1);

use App\Support\Migrations\SchemaInspector;

/**
 * Outils opérateur (/operateur) :
 *  - operator_tool_favorites : outils épinglés en tête de la page, par membre et par communauté ;
 *  - operator_identity_states : état posé par le membre sur ses identités d'emprunt (archivée) et sur ses couvertures
 *    générées (active, révoquée, modifiée). Les identités, papiers et couvertures restent dans leurs tables
 *    (sse_identities, sse_id_documents, sse_cover_exports) : rien n'y est dupliqué.
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

    $create = static function (string $table, array $lines, array $mysqlKeys, array $sqliteIndexes) use ($pdo, $schema, $sqlite, $engine, $log): void {
        if ($schema->tableExists($table)) {
            $log("  [OK] {$table} (déjà présente)\n");

            return;
        }
        if (!$sqlite) {
            $lines = array_merge($lines, $mysqlKeys);
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS {$table} (\n    " . implode(",\n    ", $lines) . "\n)" . $engine);
        if ($sqlite) {
            foreach ($sqliteIndexes as $sql) {
                $pdo->exec($sql);
            }
        }
        $log("  [OK] {$table}\n");
    };

    $create('operator_tool_favorites', [
        $id,
        'tenant_id INT UNSIGNED NOT NULL',
        'user_id INT UNSIGNED NOT NULL',
        'tool_key VARCHAR(48) NOT NULL',
        'created_at DATETIME NOT NULL',
    ], [
        'PRIMARY KEY (id)',
        'UNIQUE KEY uq_operator_tool_favorites (tenant_id, user_id, tool_key)',
        'CONSTRAINT fk_operator_tool_favorites_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE',
    ], [
        'CREATE UNIQUE INDEX IF NOT EXISTS uq_operator_tool_favorites ON operator_tool_favorites (tenant_id, user_id, tool_key)',
    ]);

    $create('operator_identity_states', [
        $id,
        'tenant_id INT UNSIGNED NOT NULL',
        'user_id INT UNSIGNED NOT NULL',
        'item_kind VARCHAR(12) NOT NULL',
        'item_id INT UNSIGNED NOT NULL',
        'is_active TINYINT(1) NOT NULL DEFAULT 0',
        'archived_at DATETIME DEFAULT NULL',
        'revoked_at DATETIME DEFAULT NULL',
        'edited_at DATETIME DEFAULT NULL',
        'actor_user_id INT UNSIGNED DEFAULT NULL',
        'updated_at DATETIME NOT NULL',
    ], [
        'PRIMARY KEY (id)',
        'UNIQUE KEY uq_operator_identity_states (tenant_id, item_kind, item_id)',
        'KEY idx_operator_identity_states_user (tenant_id, user_id, item_kind)',
        'CONSTRAINT fk_operator_identity_states_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE',
    ], [
        'CREATE UNIQUE INDEX IF NOT EXISTS uq_operator_identity_states ON operator_identity_states (tenant_id, item_kind, item_id)',
        'CREATE INDEX IF NOT EXISTS idx_operator_identity_states_user ON operator_identity_states (tenant_id, user_id, item_kind)',
    ]);
};
