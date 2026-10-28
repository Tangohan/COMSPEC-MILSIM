<?php

declare(strict_types=1);

/**
 * Étape « Aligner readiness_score depuis personnel_extras.readiness_percent quand le dossier est à 0 » (Maintenance).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Aligner readiness_score depuis personnel_extras.readiness_percent quand le dossier est à 0
$stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personnel_extras'");
if ($stmt && $stmt->fetch()) {
    echo "Backfill personnel_profiles.readiness_score depuis personnel_extras (si applicable)...\n";
    $pdo->exec(
        'UPDATE personnel_profiles pp
         INNER JOIN personnel_extras pe ON pe.user_id = pp.user_id
         SET pp.readiness_score = LEAST(100, GREATEST(COALESCE(pp.readiness_score, 0), COALESCE(pe.readiness_percent, 0)))
         WHERE pe.readiness_percent IS NOT NULL
           AND pe.readiness_percent > 0
           AND (pp.readiness_score IS NULL OR pp.readiness_score = 0)'
    );
    echo "Backfill personnel_profiles.clearance_level depuis personnel_extras (si dossier vide)...\n";
    $pdo->exec(
        'UPDATE personnel_profiles pp
         INNER JOIN personnel_extras pe ON pe.user_id = pp.user_id
         SET pp.clearance_level = pe.clearance_level
         WHERE (pp.clearance_level IS NULL OR TRIM(pp.clearance_level) = \'\')
           AND pe.clearance_level IS NOT NULL AND TRIM(pe.clearance_level) <> \'\''
    );
}
