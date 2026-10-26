<?php

declare(strict_types=1);

/**
 * Étape « Migration training_formation_custom_page_feedback (avis lecteurs Documentations) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$trainingFormationCustomPageFeedbackMigrate = require $root . '/bootstrap/training_formation_custom_page_feedback_migration.php';
try {
    echo "Migration training_formation_custom_page_feedback (avis lecteurs Documentations)...\n";
    $trainingFormationCustomPageFeedbackMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] training_formation_custom_page_feedback : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('training_formation_custom_page_feedback', $e);
}
