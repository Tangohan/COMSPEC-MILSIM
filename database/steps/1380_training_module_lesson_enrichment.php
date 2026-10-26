<?php

declare(strict_types=1);

/**
 * Étape « training_showcase + training_course_lms_theme + training_lms_engagement : déjà exécutés après le bootstrap (début de fichier) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// training_showcase + training_course_lms_theme + training_lms_engagement : déjà exécutés après le bootstrap (début de fichier).

$trainingModuleLessonEnrichmentMigrate = require $root . '/bootstrap/training_module_lesson_enrichment_migration.php';
try {
    $trainingModuleLessonEnrichmentMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] training_module_lesson_enrichment : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('training_module_lesson_enrichment', $e);
}
