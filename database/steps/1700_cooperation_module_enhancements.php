<?php

declare(strict_types=1);

/**
 * Étape « Migration cooperation_module_enhancements » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$cooperationEnhanceMigrate = require $root . '/bootstrap/cooperation_module_enhancements_migration.php';
try {
    $cooperationEnhanceMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] cooperation_module_enhancements : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('cooperation_module_enhancements', $e);
}
