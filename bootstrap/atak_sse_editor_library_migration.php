<?php

declare(strict_types=1);

use App\Support\Migrations\SchemaInspector;

/**
 * Bibliothèque de l'éditeur SSE (mod COMSPEC SSE) : table sse_editor_items, un contenu par communauté et par identifiant.
 *
 * Idempotente ; MySQL, MariaDB et sqlite (tests). La clé étrangère vers tenants n'existe pas en sqlite.
 */
return static function (PDO $pdo, ?callable $log = null): void {
    $log ??= static function (string $msg): void {
        // Silence web.
    };
    $schema = new SchemaInspector($pdo);
    if ($schema->tableExists('sse_editor_items')) {
        $log("  [OK] sse_editor_items (déjà présente)\n");

        return;
    }
    $sqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    $lines = [
        $sqlite ? 'id INTEGER PRIMARY KEY AUTOINCREMENT' : 'id INT UNSIGNED NOT NULL AUTO_INCREMENT',
        'tenant_id INT UNSIGNED NOT NULL',
        'item_uid VARCHAR(40) NOT NULL',
        'item_type VARCHAR(16) NOT NULL',
        'title VARCHAR(190) NOT NULL',
        'body TEXT NOT NULL',
        'marking TINYINT NOT NULL DEFAULT 0',
        'paper TINYINT NOT NULL DEFAULT 0',
        'nature TINYINT NOT NULL DEFAULT 0',
        'delivery TINYINT NOT NULL DEFAULT 0',
        'state VARCHAR(32) NOT NULL',
        'op_label VARCHAR(120) DEFAULT NULL',
        'link_label VARCHAR(190) DEFAULT NULL',
        'version INT NOT NULL DEFAULT 1',
        'history_json TEXT DEFAULT NULL',
        'found_by VARCHAR(80) DEFAULT NULL',
        'found_when VARCHAR(32) DEFAULT NULL',
        'author_user_id INT UNSIGNED DEFAULT NULL',
        'created_at DATETIME NOT NULL',
        'updated_at DATETIME NOT NULL',
    ];
    if (!$sqlite) {
        $lines[] = 'PRIMARY KEY (id)';
        $lines[] = 'UNIQUE KEY uq_sse_editor_items_uid (tenant_id, item_uid)';
        $lines[] = 'KEY idx_sse_editor_items_recent (tenant_id, updated_at)';
        $lines[] = 'CONSTRAINT fk_sse_editor_items_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE';
    }
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS sse_editor_items (\n    " . implode(",\n    ", $lines) . "\n)"
        . ($sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci')
    );
    if ($sqlite) {
        $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS uq_sse_editor_items_uid ON sse_editor_items (tenant_id, item_uid)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_sse_editor_items_recent ON sse_editor_items (tenant_id, updated_at)');
    }
    $log("  [OK] sse_editor_items\n");
};
