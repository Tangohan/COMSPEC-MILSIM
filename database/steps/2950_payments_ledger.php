<?php

declare(strict_types=1);

/**
 * Étape « Migration suivi des paiements et dons (payment_events, platform_donations) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Suivi des paiements et dons de la plateforme (bootstrap/payments_ledger_migration.php, livré avec les paiements).

$paymentsLedgerPath = $root . '/bootstrap/payments_ledger_migration.php';
if (is_file($paymentsLedgerPath)) {
    $paymentsLedgerMigrate = require $paymentsLedgerPath;
    try {
        echo "Migration suivi des paiements et dons (payment_events, platform_donations)...\n";
        $paymentsLedgerMigrate($pdo);
    } catch (Throwable $e) {
        echo '  [ATTENTION] payments_ledger : ' . $e->getMessage() . "\n";
        $migrationRunner->warn('payments_ledger', $e);
    }
    $migrationEnsurePdo();
}
