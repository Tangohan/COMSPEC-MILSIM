<?php

declare(strict_types=1);

/**
 * Étape « Migration advancement_grade » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$migrationRunner->step('run_advancement_grade_migration', static fn () => run_advancement_grade_migration($pdo));
