<?php

declare(strict_types=1);

/**
 * Étape « Migration doctrine_referential » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$doctrineReferentialMigrate = require $root . '/bootstrap/doctrine_referential_migration.php';
$migrationRunner->step('doctrineReferentialMigrate', static fn () => $doctrineReferentialMigrate($pdo));
