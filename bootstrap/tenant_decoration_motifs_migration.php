<?php

declare(strict_types=1);

/**
 * Motifs de placard / rubans personnalisés par communauté (éditeur + image optionnelle).
 */
function run_tenant_decoration_motifs_migration(PDO $pdo, ?callable $log = null): void
{
    $say = static function (string $message) use ($log): void {
        if ($log !== null) {
            $log($message);
        }
    };

    $tableExists = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if (!$tableExists('tenant_decoration_motifs')) {
        $pdo->exec(
            "CREATE TABLE tenant_decoration_motifs (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                name VARCHAR(120) NOT NULL,
                motif_type VARCHAR(16) NOT NULL DEFAULT 'ribbon',
                level_label VARCHAR(40) DEFAULT NULL,
                description VARCHAR(400) DEFAULT NULL,
                pattern_class VARCHAR(64) NOT NULL DEFAULT 'dk-rb-svc2',
                drop_class VARCHAR(64) DEFAULT NULL,
                disc_class VARCHAR(64) DEFAULT NULL,
                glyph VARCHAR(32) DEFAULT NULL,
                colors_json JSON DEFAULT NULL,
                image_path VARCHAR(255) DEFAULT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                archived_at DATETIME DEFAULT NULL,
                created_by INT UNSIGNED DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_tdm_tenant (tenant_id, archived_at, sort_order),
                CONSTRAINT fk_tdm_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $say('tenant_decoration_motifs created');
    }
}
