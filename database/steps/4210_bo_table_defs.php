<?php

declare(strict_types=1);

/**
 * Étape « Back-office : tableaux de données enregistrés » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : CREATE TABLE IF NOT EXISTS.
 * Une définition = une source, des colonnes, des filtres, un tri et un regroupement choisis dans le constructeur
 * (jamais les données : elles sont relues à chaque ouverture, dans les droits de la personne qui ouvre).
 * Identifiants en INT UNSIGNED comme tenants.id et users.id.
 */

try {
    echo "Back-office : tableaux de données enregistrés…\n";
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS bo_table_defs (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            owner_user_id INT UNSIGNED NOT NULL,
            title VARCHAR(120) NOT NULL,
            source VARCHAR(32) NOT NULL,
            def_json TEXT NOT NULL,
            shared TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY idx_bo_table_defs_owner (tenant_id, owner_user_id),
            KEY idx_bo_table_defs_shared (tenant_id, shared)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
} catch (Throwable $e) {
    echo '  [ATTENTION] bo_table_defs : ' . $e->getMessage() . "\n";
    $migrationRunner?->warn('bo_table_defs', $e);
}
$migrationEnsurePdo();
