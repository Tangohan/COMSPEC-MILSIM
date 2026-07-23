<?php

declare(strict_types=1);

/**
 * Absences déclarées par le personnel (période datée ou durée non précisée).
 * Idempotent.
 */
function run_personnel_absences_migration(PDO $pdo): void
{
    $hasTable = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if ($hasTable('personnel_absences')) {
        return;
    }

    $pdo->exec(
        "CREATE TABLE personnel_absences (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            starts_on DATE NOT NULL,
            ends_on DATE DEFAULT NULL,
            reason VARCHAR(40) NOT NULL DEFAULT 'autre',
            note VARCHAR(500) DEFAULT NULL,
            status ENUM('active','cancelled') NOT NULL DEFAULT 'active',
            created_by INT UNSIGNED DEFAULT NULL,
            cancelled_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_pa_tenant_user_status (tenant_id, user_id, status),
            KEY idx_pa_tenant_dates (tenant_id, starts_on, ends_on),
            CONSTRAINT fk_pa_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_pa_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_pa_author FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}
