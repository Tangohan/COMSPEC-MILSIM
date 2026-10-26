<?php

declare(strict_types=1);

/**
 * Étape « Migration qualification_referentiel » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$migrationRunner->step('run_qualification_referentiel_migration', static fn () => run_qualification_referentiel_migration($pdo));
