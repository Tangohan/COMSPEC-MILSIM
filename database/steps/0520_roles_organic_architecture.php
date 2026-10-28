<?php

declare(strict_types=1);

/**
 * Étape « Migration roles_organic_architecture » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

try {
    run_roles_organic_architecture_migration($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] roles_organic_architecture : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('roles_organic_architecture', $e);
}
