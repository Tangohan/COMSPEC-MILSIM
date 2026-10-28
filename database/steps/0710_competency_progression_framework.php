<?php

declare(strict_types=1);

/**
 * Étape « Migration competency_progression_framework » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$competencyProgressionFrameworkMigrate = require $root . '/bootstrap/competency_progression_framework_migration.php';
try {
    $competencyProgressionFrameworkMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] competency_progression_framework : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('competency_progression_framework', $e);
}
