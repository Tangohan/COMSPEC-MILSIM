<?php

declare(strict_types=1);

/**
 * Étape « Effectifs : historique des fiches sans compte » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : CREATE TABLE IF NOT EXISTS.
 * Une ligne par événement sur une personne sans compte (création, correction, invitation, rattachement, import).
 * Identifiants en INT UNSIGNED comme tenants.id et users.id ; toute lecture filtre sur tenant_id.
 */

try {
    echo "Effectifs : historique des fiches sans compte…\n";
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS roster_fiche_events (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            actor_user_id INT UNSIGNED NULL,
            event VARCHAR(24) NOT NULL,
            source VARCHAR(24) NOT NULL DEFAULT \'\',
            detail VARCHAR(255) NOT NULL DEFAULT \'\',
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY idx_rfe_user (tenant_id, user_id, id),
            KEY idx_rfe_tenant (tenant_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
} catch (Throwable $e) {
    echo '  [ATTENTION] roster_fiche_events : ' . $e->getMessage() . "\n";
    $migrationRunner?->warn('roster_fiche_events', $e);
}
$migrationEnsurePdo();
