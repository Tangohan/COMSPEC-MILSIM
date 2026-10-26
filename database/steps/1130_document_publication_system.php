<?php

declare(strict_types=1);

/**
 * Étape « Migration document_publication_system » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$documentPublicationMigrate = require $root . '/bootstrap/document_publication_system_migration.php';
$migrationRunner->step('documentPublicationMigrate', static fn () => $documentPublicationMigrate($pdo));
