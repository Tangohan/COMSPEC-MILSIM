<?php

declare(strict_types=1);

/**
 * Étape « Double vérification : codes de secours et appareils de confiance » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$authRecoveryTrusted = require $root . '/bootstrap/auth_recovery_trusted_devices_migration.php';
$migrationRunner->step('auth_recovery_trusted_devices', static function () use ($authRecoveryTrusted, $pdo): void {
    $authRecoveryTrusted($pdo);
});
