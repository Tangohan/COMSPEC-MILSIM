<?php

declare(strict_types=1);

/**
 * Étape « Migration training_courses_lms_scope » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$trainingCoursesLmsScopeMigrate = require $root . '/bootstrap/training_courses_lms_scope_migration.php';
try {
    $trainingCoursesLmsScopeMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] training_courses_lms_scope : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('training_courses_lms_scope', $e);
}
