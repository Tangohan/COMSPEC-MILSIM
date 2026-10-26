<?php

declare(strict_types=1);

/**
 * Étape « Migration system_moderator_account » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$systemModeratorMigrate = require $root . '/bootstrap/system_moderator_account_migration.php';
$migrationRunner->step('systemModeratorMigrate', static fn () => $systemModeratorMigrate($pdo));
