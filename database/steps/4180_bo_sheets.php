<?php

declare(strict_types=1);

/**
 * Étape « Back-office : tableaux de l'accueil » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : CREATE TABLE IF NOT EXISTS.
 * Petit tableur créé depuis l'accueil du back-office (12 colonnes, 200 lignes au plus), lisible par la communauté
 * s'il est partagé. Identifiants en INT UNSIGNED comme tenants.id et users.id.
 */

try {
    echo "Back-office : tableaux de l'accueil…\n";
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS bo_sheets (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            owner_user_id INT UNSIGNED NOT NULL,
            title VARCHAR(120) NOT NULL,
            data_json MEDIUMTEXT NOT NULL,
            shared TINYINT(1) NOT NULL DEFAULT 0,
            version INT UNSIGNED NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY idx_bo_sheets_owner (tenant_id, owner_user_id),
            KEY idx_bo_sheets_shared (tenant_id, shared)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
} catch (Throwable $e) {
    echo '  [ATTENTION] bo_sheets : ' . $e->getMessage() . "\n";
    $migrationRunner?->warn('bo_sheets', $e);
}
$migrationEnsurePdo();
