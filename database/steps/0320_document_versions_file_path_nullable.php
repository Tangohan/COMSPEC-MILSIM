<?php

declare(strict_types=1);

/**
 * Étape « Migration document_versions_file_path_nullable » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$migrationRunner->step('run_document_versions_file_path_nullable_migration', static fn () => run_document_versions_file_path_nullable_migration($pdo));
