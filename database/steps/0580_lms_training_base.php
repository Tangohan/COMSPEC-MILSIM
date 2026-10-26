<?php

declare(strict_types=1);

/**
 * Étape « Migration lms_training_base » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Socle LMS moderne (training_courses, lessons, enrollments, quizzes…) : créé à partir de
// migrations/lms_training.sql si absent. Sans cette étape, une installation neuve n'a pas de
// table training_courses et le tableau de bord / la brique Formations plantent.
echo "Socle LMS moderne (training_courses, lessons, enrollments…)...\n";
$migrationFlush();
$lmsTrainingBaseMigrate = require $root . '/bootstrap/lms_training_base_migration.php';
try {
    $lmsTrainingBaseMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] socle LMS moderne : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('socle LMS moderne', $e);
}
