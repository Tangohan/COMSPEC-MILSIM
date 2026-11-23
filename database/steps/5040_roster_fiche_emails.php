<?php

declare(strict_types=1);

/**
 * Étape « Effectifs : adresse e-mail rattachée à une fiche sans compte » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : CREATE TABLE IF NOT EXISTS, aucune
 * donnée lue ni modifiée. Une fiche sans compte garde son adresse réservée dans users.email ; l'adresse réelle, saisie
 * par l'état-major, vit ici jusqu'à l'activation du compte. Une adresse ne peut figurer qu'une fois par communauté.
 * Identifiants en INT UNSIGNED comme tenants.id et users.id. Sans cette étape, les fiches se gèrent comme avant, sans
 * adresse rattachée.
 */

try {
    echo "Effectifs : adresse e-mail des fiches sans compte…\n";
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS roster_fiche_emails (
            user_id INT UNSIGNED NOT NULL,
            tenant_id INT UNSIGNED NOT NULL,
            email VARCHAR(190) NOT NULL,
            updated_by INT UNSIGNED NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (user_id),
            UNIQUE KEY uq_rfe_mail (tenant_id, email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
} catch (Throwable $e) {
    echo '  [ATTENTION] roster_fiche_emails : ' . $e->getMessage() . "\n";
    $migrationRunner?->warn('roster_fiche_emails', $e);
}
$migrationEnsurePdo();
