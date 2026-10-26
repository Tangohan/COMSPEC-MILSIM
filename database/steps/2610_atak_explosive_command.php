<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_explosive_command (déclenchement TOC des charges ACE) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$atakExplosiveCommandMigrate = require $root . '/bootstrap/atak_explosive_command_migration.php';
try {
    echo "Migration atak_explosive_command (déclenchement TOC des charges ACE)...\n";
    $atakExplosiveCommandMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_explosive_command : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_explosive_command', $e);
}
$migrationEnsurePdo();
