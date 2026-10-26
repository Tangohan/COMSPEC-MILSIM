<?php

declare(strict_types=1);

/**
 * Étape « Affectations : compléter personnel_assignments depuis user_units lorsque la ligne manque (personnel_assignments = source métier riche ; use… » (Maintenance).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Affectations : compléter personnel_assignments depuis user_units lorsque la ligne manque (personnel_assignments = source métier riche ; user_units = compat / historique)
$stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personnel_assignments'");
if ($stmt && $stmt->fetch()) {
    echo "Complément personnel_assignments depuis user_units (lignes manquantes)...\n";
    $pdo->exec(
        'INSERT INTO personnel_assignments (user_id, unit_id, role_name, is_primary, started_at, ended_at, status, created_at)
         SELECT uu.user_id, uu.unit_id,
                COALESCE(NULLIF(TRIM(uu.assignment_type), \'\'), \'Membre\'),
                COALESCE(uu.is_primary, 0),
                CASE WHEN uu.assigned_at IS NULL THEN CURDATE() ELSE DATE(uu.assigned_at) END,
                CASE WHEN uu.ended_at IS NULL THEN NULL ELSE DATE(uu.ended_at) END,
                CASE WHEN uu.ended_at IS NULL OR uu.ended_at > NOW() THEN \'active\' ELSE \'inactive\' END,
                NOW()
         FROM user_units uu
         WHERE NOT EXISTS (
           SELECT 1 FROM personnel_assignments pa
           WHERE pa.user_id = uu.user_id AND pa.unit_id = uu.unit_id
         )'
    );
}
