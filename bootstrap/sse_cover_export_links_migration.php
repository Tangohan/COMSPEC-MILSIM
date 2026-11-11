<?php

declare(strict_types=1);

use App\Support\Migrations\SchemaInspector;

/**
 * Table dépréciée, gardée pour ne rien perdre : sse_cover_export_links liait une couverture générée (sse_cover_exports) à
 * l'identité du membre (sse_identities) et aux papiers qu'elle emportait. Le site n'a plus de couverture à part : l'étape
 * 4450 (bootstrap/sse_identities_unified_migration.php) lit ces liens une dernière fois pour fondre chaque couverture dans
 * son identité. Plus aucun code ne lit ni n'écrit cette table ; elle sera supprimée par une étape ultérieure.
 * Idempotente ; MySQL, MariaDB et sqlite (tests).
 */
return static function (PDO $pdo, ?callable $log = null): void {
    $log ??= static function (string $msg): void {
        // Silence web.
    };
    $schema = new SchemaInspector($pdo);
    $sqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    if ($schema->tableExists('sse_cover_export_links')) {
        $log("  [OK] sse_cover_export_links (déjà présente)\n");

        return;
    }
    $lines = [
        $sqlite ? 'id INTEGER PRIMARY KEY AUTOINCREMENT' : 'id INT UNSIGNED NOT NULL AUTO_INCREMENT',
        'tenant_id INT UNSIGNED NOT NULL',
        'export_id INT UNSIGNED NOT NULL',
        'user_id INT UNSIGNED NOT NULL',
        'identity_id INT UNSIGNED NOT NULL',
        'document_ids TEXT NOT NULL',
        'created_at DATETIME NOT NULL',
        'updated_at DATETIME NOT NULL',
    ];
    if (!$sqlite) {
        $lines = array_merge($lines, [
            'PRIMARY KEY (id)',
            'UNIQUE KEY uq_sse_cover_export_links (tenant_id, export_id)',
            'KEY idx_sse_cover_export_links_user (tenant_id, user_id)',
            'KEY idx_sse_cover_export_links_identity (tenant_id, identity_id)',
            'CONSTRAINT fk_sse_cover_export_links_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE',
        ]);
    }
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS sse_cover_export_links (\n    " . implode(",\n    ", $lines) . "\n)"
        . ($sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci')
    );
    if ($sqlite) {
        $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS uq_sse_cover_export_links ON sse_cover_export_links (tenant_id, export_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_sse_cover_export_links_user ON sse_cover_export_links (tenant_id, user_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_sse_cover_export_links_identity ON sse_cover_export_links (tenant_id, identity_id)');
    }
    $log("  [OK] sse_cover_export_links\n");
};
