<?php

declare(strict_types=1);

/**
 * Étape « Migration training_course_lms_theme » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// LMS formations : colonnes training_courses + tables engagement — exécuté tôt (idempotent). Anciennement en fin de fichier :
// si le script s’arrêtait avant (timeout, erreur), colonnes comme enrollment_policy_json manquaient en prod.
echo "Migrations LMS formation (training_courses, politique d’inscription, vitrine)...\n";
$migrationFlush();
$trainingCourseLmsThemeMigrateEarly = require $root . '/bootstrap/training_course_lms_theme_migration.php';
$migrationRunner->step('trainingCourseLmsThemeMigrateEarly', static fn () => $trainingCourseLmsThemeMigrateEarly($pdo));
