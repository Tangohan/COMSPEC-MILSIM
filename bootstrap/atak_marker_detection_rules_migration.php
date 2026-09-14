<?php

declare(strict_types=1);

/**
 * Règles communautaires de détection des marqueurs posés en jeu.
 */
function run_atak_marker_detection_rules_migration(PDO $pdo): void
{
    $hasTable = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if ($hasTable('atak_marker_detection_rules')) {
        return;
    }

    $pdo->exec(
        "CREATE TABLE atak_marker_detection_rules (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            label VARCHAR(80) NOT NULL,
            match_mode VARCHAR(32) NOT NULL DEFAULT 'label_prefix',
            match_value VARCHAR(64) NOT NULL,
            radius_m SMALLINT UNSIGNED NOT NULL DEFAULT 20,
            confirm_arrival TINYINT(1) NOT NULL DEFAULT 1,
            notify_web TINYINT(1) NOT NULL DEFAULT 1,
            notify_atak TINYINT(1) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            position SMALLINT UNSIGNED NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_amdr_tenant_active (tenant_id, is_active, position),
            CONSTRAINT fk_amdr_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}
