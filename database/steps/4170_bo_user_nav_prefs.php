<?php

declare(strict_types=1);

/**
 * Étape « Back-office : favoris du menu, pages nouvelles vues et accueil personnalisé » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : CREATE TABLE IF NOT EXISTS.
 * Une ligne par communauté et par compte ; identifiants en INT UNSIGNED comme tenants.id et users.id.
 */

try {
    echo "Back-office : préférences du menu et de l'accueil par compte…\n";
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS bo_user_nav_prefs (
            tenant_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            favorites_json TEXT NULL,
            seen_new_json TEXT NULL,
            home_json MEDIUMTEXT NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY (tenant_id, user_id),
            KEY idx_bo_nav_prefs_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
} catch (Throwable $e) {
    echo '  [ATTENTION] bo_user_nav_prefs : ' . $e->getMessage() . "\n";
    $migrationRunner?->warn('bo_user_nav_prefs', $e);
}
$migrationEnsurePdo();
