<?php

declare(strict_types=1);

/**
 * Étape « Migration training_lesson_player_mode » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$trainingLessonPlayerModeMigrate = require $root . '/bootstrap/training_lesson_player_mode_migration.php';
try {
    $trainingLessonPlayerModeMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] training_lesson_player_mode : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('training_lesson_player_mode', $e);
}
