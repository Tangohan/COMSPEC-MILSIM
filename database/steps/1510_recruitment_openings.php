<?php

declare(strict_types=1);

/**
 * Étape « Migration recruitment_openings » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$recruitmentOpeningsMigrate = require $root . '/bootstrap/recruitment_openings_migration.php';
try {
    $recruitmentOpeningsMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] recruitment_openings : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('recruitment_openings', $e);
}
