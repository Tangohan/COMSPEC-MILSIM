<?php

declare(strict_types=1);

/**
 * Étape « Migration positions_admin_category » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

try {
    run_positions_admin_category_migration($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] positions_admin_category : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('positions_admin_category', $e);
}
