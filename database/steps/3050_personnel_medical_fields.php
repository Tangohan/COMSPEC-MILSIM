<?php

declare(strict_types=1);

/**
 * Étape « Dossier médical : taille, intolérances médicamenteuses, allergies (vue « Médical » du tableur des effectifs) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Dossier médical : taille, intolérances médicamenteuses, allergies (vue « Médical » du tableur des effectifs).
$personnelMedicalFields = require $root . '/bootstrap/personnel_medical_fields_migration.php';
$migrationRunner->step('personnel_medical_fields', static function () use ($personnelMedicalFields, $pdo): void {
    $personnelMedicalFields($pdo);
});
