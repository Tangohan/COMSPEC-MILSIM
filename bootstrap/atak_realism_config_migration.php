<?php

declare(strict_types=1);

/**
 * Table centralisée des configurations réalisme ATAK.
 * Stocke l'ensemble des ~100 paramètres réalisme dans une colonne JSON unique.
 * Idempotent. Une seule config active par tenant à la fois (contrainte UNIQUE).
 */
function run_atak_realism_config_migration(PDO $pdo): void
{
    $hasTable = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if ($hasTable('atak_realism_config')) {
        return;
    }

    try {
        $pdo->exec("
            CREATE TABLE atak_realism_config (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                
                config_version VARCHAR(16) NOT NULL DEFAULT '1.0.0' COMMENT 'Version du schéma JSON',
                config_name VARCHAR(160) NOT NULL DEFAULT 'Configuration par défaut' COMMENT 'Nom descriptif de la configuration',
                is_active BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Une seule config active par tenant',
                
                config_json JSON NOT NULL COMMENT 'Tous les paramètres de réalisme structurés par domaine',
                
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_by INT UNSIGNED DEFAULT NULL COMMENT 'ID utilisateur qui a créé cette config',
                updated_by INT UNSIGNED DEFAULT NULL COMMENT 'ID utilisateur dernière modification',
                
                PRIMARY KEY (id),
                UNIQUE KEY uk_realism_tenant_active (tenant_id, is_active) COMMENT 'Une seule config active par tenant',
                KEY idx_realism_tenant_version (tenant_id, config_version),
                KEY idx_realism_updated (updated_at),
                
                CONSTRAINT fk_realism_tenant FOREIGN KEY (tenant_id) 
                    REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_realism_created_by FOREIGN KEY (created_by) 
                    REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT fk_realism_updated_by FOREIGN KEY (updated_by) 
                    REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Configuration centralisée réalisme ATAK (relais, certificats, dommages terminal, etc.)'
        ");
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

run_atak_realism_config_migration($pdo);
