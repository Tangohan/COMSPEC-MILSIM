<?php

declare(strict_types=1);

/**
 * Étape « Migration pedagogy_chain » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$pedagogyChainMigrate = require $root . '/bootstrap/pedagogy_chain_migration.php';
try {
    $pedagogyChainMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] pedagogy_chain : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('pedagogy_chain', $e);
}
