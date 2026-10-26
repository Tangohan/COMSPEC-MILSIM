<?php

declare(strict_types=1);

/**
 * Étape « Enlistments : étape de pipeline explicite (submitted/interview_scheduled/on_hold/accepted/rejected/blocked), » (Schéma).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Enlistments : étape de pipeline explicite (submitted/interview_scheduled/on_hold/accepted/rejected/blocked),
// en complément de `status` — n'affecte aucune logique existante basée sur `status`.
$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'enlistments' AND COLUMN_NAME = 'pipeline_stage'");
if ($stmt && !$stmt->fetch()) {
    echo "Ajout de la colonne pipeline_stage à enlistments...\n";
    $pdo->exec("ALTER TABLE enlistments ADD COLUMN pipeline_stage varchar(30) DEFAULT NULL AFTER status");
    $pdo->exec("ALTER TABLE enlistments ADD INDEX idx_enlistments_tenant_stage (tenant_id, pipeline_stage)");
    echo "pipeline_stage OK.\n";
}
