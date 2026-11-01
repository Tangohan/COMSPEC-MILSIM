<?php

declare(strict_types=1);

/**
 * Étape « Emplois : journal des reprises d’emplois (personnel_job_role_recovery_log) » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : l’état réel de la base
 * est vérifié avant toute modification, une relance ne change rien.
 * Source unique : PersonnelJobRoleRecoveryService::ensureAuditTable() (CREATE TABLE IF NOT EXISTS).
 */

$jobRecoverySchema = new \App\Support\Migrations\SchemaInspector($pdo);
if ($jobRecoverySchema->tableExists('personnel_job_role_recovery_log')) {
    echo "  [OK] Table personnel_job_role_recovery_log déjà présente.\n";
} else {
    (new \App\Services\Personnel\PersonnelJobRoleRecoveryService($pdo))->ensureAuditTable();
    echo "  [OK] Table personnel_job_role_recovery_log créée.\n";
}
