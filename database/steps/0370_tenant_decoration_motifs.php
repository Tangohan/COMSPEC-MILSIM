<?php

declare(strict_types=1);

/**
 * Étape « Migration tenant_decoration_motifs » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$migrationRunner->step('run_tenant_decoration_motifs_migration', static fn () => run_tenant_decoration_motifs_migration($pdo));
