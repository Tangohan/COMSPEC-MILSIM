<?php

declare(strict_types=1);

/**
 * Étape « Grades sur mesure : systèmes, catégories et grades créés par une communauté (SCP, Star Wars…) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Grades sur mesure : systèmes, catégories et grades créés par une communauté (SCP, Star Wars…).
$customGradeSystems = require $root . '/bootstrap/custom_grade_systems_migration.php';
$migrationRunner->step('custom_grade_systems', static function () use ($customGradeSystems, $pdo): void {
    $customGradeSystems($pdo);
});
