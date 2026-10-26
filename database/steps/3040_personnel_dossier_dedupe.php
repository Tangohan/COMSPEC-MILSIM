<?php

declare(strict_types=1);

/**
 * Étape « Fiches dossier en double (lignes sans communauté recréées à chaque ouverture de son propre dossier) » (Maintenance).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Fiches dossier en double (lignes sans communauté recréées à chaque ouverture de son propre dossier).
$personnelDossierDedupe = require $root . '/bootstrap/personnel_dossier_dedupe_migration.php';
$migrationRunner->step('personnel_dossier_dedupe', static function () use ($personnelDossierDedupe, $pdo): void {
    echo "Nettoyage des fiches dossier en double (personnel_profiles, personnel_extras)...\n";
    $personnelDossierDedupe($pdo);
});
