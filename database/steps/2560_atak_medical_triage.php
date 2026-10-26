<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_medical_alert_triage (triage alertes médicales) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$atakMedicalTriageMigrate = require $root . '/bootstrap/atak_medical_triage_migration.php';
try {
    echo "Migration atak_medical_alert_triage (triage alertes médicales)...\n";
    $atakMedicalTriageMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_medical_alert_triage : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_medical_alert_triage', $e);
}
$migrationEnsurePdo();
