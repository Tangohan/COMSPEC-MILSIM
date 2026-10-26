<?php

declare(strict_types=1);

/**
 * Étape « Migration user_email_login_otp » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$userEmailLoginOtpMigrate = require $root . '/bootstrap/user_email_login_otp_migration.php';
try {
    $userEmailLoginOtpMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] user_email_login_otp : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('user_email_login_otp', $e);
}
