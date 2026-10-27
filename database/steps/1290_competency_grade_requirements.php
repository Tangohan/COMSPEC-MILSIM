<?php

declare(strict_types=1);

/**
 * Étape « Migration competency_grade_requirements (matrice compétences × grades) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$competencyGradeRequirementsMigrate = require $root . '/bootstrap/competency_grade_requirements_migration.php';
try {
    echo "Migration competency_grade_requirements (matrice compétences × grades)...\n";
    $competencyGradeRequirementsMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] competency_grade_requirements : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('competency_grade_requirements', $e);
}
