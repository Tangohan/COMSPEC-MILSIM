<?php

declare(strict_types=1);

/**
 * Étape « Migration enlistment_canned_messages » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$enlistmentCannedMessagesMigrate = require $root . '/bootstrap/enlistment_canned_messages_migration.php';
$migrationRunner->step('enlistmentCannedMessagesMigrate', static fn () => $enlistmentCannedMessagesMigrate($pdo));
