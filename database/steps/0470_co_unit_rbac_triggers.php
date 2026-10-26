<?php

declare(strict_types=1);

/**
 * Étape « Migration co_unit_rbac_triggers » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

try {
    run_co_unit_rbac_triggers_migration($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] co_unit_rbac_triggers : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('co_unit_rbac_triggers', $e);
}
