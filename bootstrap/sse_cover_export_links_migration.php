<?php

declare(strict_types=1);

use App\Support\Migrations\SchemaInspector;

/**
 * Couvertures bâties sur une identité et ses papiers (Outils opérateur › Mon identité › Couvertures) :
 *  - sse_cover_export_links : pour une couverture générée (sse_cover_exports), l'identité du membre sur laquelle elle est
 *    bâtie (sse_identities) et les papiers de cette identité qu'elle emporte (identifiants de sse_id_documents, en JSON).
 *
 * Une couverture sans ligne ici est « non rattachée » (personne fictive tirée au hasard, couverture d'avant ce lien).
 * Le fichier « comspec-cover » ne change pas : le lien ne vit que sur Athena. Table à part : sse_cover_exports n'est
 * pas modifiée. Idempotente ; MySQL, MariaDB et sqlite (tests). Le rétro-remplissage des couvertures existantes est
 * fait par l'étape database/steps/4440 (App\Support\SseCoverLinkMatcher::backfill), pas ici.
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
