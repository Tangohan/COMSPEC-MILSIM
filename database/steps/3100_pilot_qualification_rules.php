<?php

declare(strict_types=1);

/**
 * Étape « Cursus pilote : règles d'attribution automatique des qualifications de pilote » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Cursus pilote : qualifications attribuées d'après les heures de vol relevées par le mod.
$pilotQualificationRules = require $root . '/bootstrap/pilot_qualification_rules_migration.php';
$migrationRunner->step('pilot_qualification_rules', static function () use ($pilotQualificationRules, $pdo): void {
    $pilotQualificationRules($pdo);
});
