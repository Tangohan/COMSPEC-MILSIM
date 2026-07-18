<?php

declare(strict_types=1);

/**
 * Bilans d’étape du dossier personnel (RH / commandement / recrutement interne).
 * Idempotent.
 */
function run_personnel_stage_bilans_migration(PDO $pdo): void
{
    $hasTable = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if ($hasTable('personnel_stage_bilans')) {
        return;
    }

    $pdo->exec(
        "CREATE TABLE personnel_stage_bilans (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            bilan_kind ENUM('recrutement','rh','commandement') NOT NULL DEFAULT 'rh',
            stage_label VARCHAR(120) NOT NULL,
            title VARCHAR(180) NOT NULL,
            rating TINYINT UNSIGNED DEFAULT NULL,
            body TEXT NOT NULL,
            event_date DATE NOT NULL,
            created_by INT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_psb_tenant_user_date (tenant_id, user_id, event_date),
            KEY idx_psb_kind (tenant_id, bilan_kind),
            CONSTRAINT fk_psb_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_psb_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_psb_author FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}
