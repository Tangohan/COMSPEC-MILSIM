<?php

declare(strict_types=1);

/**
 * Étape « Migration roleplay_deadlines_medical_rotation (groupe sanguin + type de rotation) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$roleplayDeadlinesMedicalRotationMigrate = require $root . '/bootstrap/roleplay_deadlines_medical_rotation_migration.php';
try {
    echo "Migration roleplay_deadlines_medical_rotation (groupe sanguin + type de rotation)...\n";
    $roleplayDeadlinesMedicalRotationMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] roleplay_deadlines_medical_rotation : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('roleplay_deadlines_medical_rotation', $e);
}
