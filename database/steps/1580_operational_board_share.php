<?php

declare(strict_types=1);

/**
 * Étape « Migration operational_board_share » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$operationalBoardShareMigrate = require $root . '/bootstrap/operational_board_share_migration.php';
try {
    $operationalBoardShareMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] operational_board_share : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('operational_board_share', $e);
}
