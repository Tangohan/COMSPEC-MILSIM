<?php

declare(strict_types=1);

/**
 * Historique des changements visibles sur la fiche (rôle principal, grade, statut du compte, etc.).
 * Idempotent.
 */
function run_personnel_org_history_migration(PDO $pdo): void
{
    $hasTable = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if ($hasTable('personnel_org_history')) {
        return;
    }

    $pdo->exec(
        "CREATE TABLE personnel_org_history (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            actor_user_id INT UNSIGNED DEFAULT NULL,
            summary VARCHAR(600) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_poh_tenant_user_created (tenant_id, user_id, created_at),
            CONSTRAINT fk_poh_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_poh_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_poh_actor FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}
