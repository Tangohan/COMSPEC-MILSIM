<?php

declare(strict_types=1);

/**
 * Étape « Migration user_totp » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$userTotpMigrate = require $root . '/bootstrap/user_totp_migration.php';
try {
    $userTotpMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] user_totp : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('user_totp', $e);
}
