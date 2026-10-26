<?php

declare(strict_types=1);

/**
 * Étape « Migration alerts » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$alertsMigrate = require $root . '/bootstrap/alerts_migration.php';
$migrationRunner->step('alertsMigrate', static fn () => $alertsMigrate($pdo));
