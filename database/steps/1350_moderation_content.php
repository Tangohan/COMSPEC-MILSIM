<?php

declare(strict_types=1);

/**
 * Étape « Migration moderation_content » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$moderationContentMigrate = require $root . '/bootstrap/moderation_content_migration.php';
$migrationRunner->step('moderationContentMigrate', static fn () => $moderationContentMigrate($pdo));
