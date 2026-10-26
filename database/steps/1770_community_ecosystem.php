<?php

declare(strict_types=1);

/**
 * Étape « Migration hub inter-communautés (recommandations, avis, bourse) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

require_once $root . '/bootstrap/community_ecosystem_migration.php';
try {
    echo "Migration hub inter-communautés (recommandations, avis, bourse)...\n";
    run_community_ecosystem_migration($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] community_ecosystem : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('community_ecosystem', $e);
}
