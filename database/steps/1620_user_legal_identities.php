<?php

declare(strict_types=1);

/**
 * Étape « Migration user_legal_identities » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$userLegalIdentitiesMigrate = require $root . '/bootstrap/user_legal_identities_migration.php';
try {
    $userLegalIdentitiesMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] user_legal_identities : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('user_legal_identities', $e);
}
