<?php

declare(strict_types=1);

/**
 * Étape « Migration unit_identity_enrichment » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$migrationRunner->step('run_unit_identity_enrichment_migration', static fn () => run_unit_identity_enrichment_migration($pdo));
