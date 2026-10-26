<?php

declare(strict_types=1);

/**
 * Étape « Migration recon_pv_tammuc » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$migrationRunner->step('run_recon_pv_tammuc_migration', static fn () => run_recon_pv_tammuc_migration($pdo));
