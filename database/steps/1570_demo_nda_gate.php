<?php

declare(strict_types=1);

/**
 * Étape « Migration demo_nda_gate » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$demoNdaGateMigrate = require $root . '/bootstrap/demo_nda_gate_migration.php';
try {
    $demoNdaGateMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] demo_nda_gate : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('demo_nda_gate', $e);
}
