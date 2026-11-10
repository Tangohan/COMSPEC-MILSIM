<?php

declare(strict_types=1);

/**
 * Étape « Signalements du mod : intégrité du client » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente, rejouable sans effet :
 * colonne atak_mod_reports.client_attest (valid, absent, invalid, off) : preuve du module officiel vue par Athena
 * à la réception du rapport, affichée dans le ticket Discord (champ « Intégrité »). Les rapports déjà reçus gardent
 * NULL (« non vérifié »). Le même ajout est fait par bootstrap/atak_mod_reports_migration.php au premier usage.
 */

require_once $root . '/bootstrap/atak_mod_reports_migration.php';
try {
    run_atak_mod_reports_migration($pdo);
    echo "  [OK] atak_mod_reports.client_attest vérifiée.\n";
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_mod_reports.client_attest : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_mod_report_client_attest', $e);
}
