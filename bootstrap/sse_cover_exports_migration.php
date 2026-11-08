<?php

declare(strict_types=1);

use App\Support\Migrations\SchemaInspector;

/**
 * Couvertures générées sur Athena puis téléchargées pour le jeu (COMSPEC IDENTITY) :
 *  - sse_cover_exports : une ligne par couverture générée (historique, limite de régénération, re-téléchargement).
 *
 * Personnes fictives seulement. Ces lignes restent à part du registre des identités. Idempotente ; MySQL, MariaDB et
 * sqlite (tests).
 */
return static function (PDO $pdo, ?callable $log = null): void {
    $log ??= static function (string $msg): void {
        // Silence web.
    };
    $schema = new SchemaInspector($pdo);
    $sqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    if ($schema->tableExists('sse_cover_exports')) {
        $log("  [OK] sse_cover_exports (déjà présente)\n");

        return;
    }
    $lines = [
        $sqlite ? 'id INTEGER PRIMARY KEY AUTOINCREMENT' : 'id INT UNSIGNED NOT NULL AUTO_INCREMENT',
        'tenant_id INT UNSIGNED NOT NULL',
        'user_id INT UNSIGNED NOT NULL',
        'created_by_user_id INT UNSIGNED DEFAULT NULL',
        'ref VARCHAR(24) NOT NULL',
        'seed INT UNSIGNED NOT NULL',
        'country_code VARCHAR(16) NOT NULL',
        'cover_name VARCHAR(190) NOT NULL',
        'doc_number VARCHAR(60) NOT NULL DEFAULT \'\'',
        'content_sha CHAR(64) NOT NULL',
        'payload_json ' . ($sqlite ? 'TEXT' : 'MEDIUMTEXT') . ' NOT NULL',
        'created_at DATETIME NOT NULL',
        'downloaded_at DATETIME DEFAULT NULL',
    ];
    if (!$sqlite) {
        $lines = array_merge($lines, [
            'PRIMARY KEY (id)',
            'UNIQUE KEY uq_sse_cover_exports_ref (tenant_id, ref)',
            'KEY idx_sse_cover_exports_user (tenant_id, user_id, created_at)',
            'KEY idx_sse_cover_exports_name (tenant_id, cover_name)',
            'CONSTRAINT fk_sse_cover_exports_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE',
        ]);
    }
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS sse_cover_exports (\n    " . implode(",\n    ", $lines) . "\n)"
        . ($sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci')
    );
    if ($sqlite) {
        $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS uq_sse_cover_exports_ref ON sse_cover_exports (tenant_id, ref)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_sse_cover_exports_user ON sse_cover_exports (tenant_id, user_id, created_at)');
    }
    $log("  [OK] sse_cover_exports\n");
};
