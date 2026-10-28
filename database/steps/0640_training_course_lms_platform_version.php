<?php

declare(strict_types=1);

/**
 * Étape « Migration training_course_lms_platform_version » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$trainingCourseLmsPlatformVersionMigrate = require $root . '/bootstrap/training_course_lms_platform_version_migration.php';
try {
    $trainingCourseLmsPlatformVersionMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] training_course_lms_platform_version : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('training_course_lms_platform_version', $e);
}
