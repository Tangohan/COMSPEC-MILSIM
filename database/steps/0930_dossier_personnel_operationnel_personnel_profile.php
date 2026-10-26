<?php

declare(strict_types=1);

/**
 * Étape « Migration données personnel OK » (Schéma).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Dossier personnel opérationnel (personnel_profiles, qualifications, assignments, service_history, media)
$stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personnel_profiles'");
if ($stmt && !$stmt->fetch()) {
    echo "Création tables dossier personnel (personnel_profiles, personnel_qualifications, etc.)...\n";
    $personnelDossierPath = $root . '/migrations/personnel_dossier.sql';
    if (is_file($personnelDossierPath)) {
        $sql = file_get_contents($personnelDossierPath);
        $sql = preg_replace('/--[^\r\n]*/s', '', $sql);
        $sql = preg_replace('/SET NAMES utf8mb4;|SET FOREIGN_KEY_CHECKS = \d+;/', '', $sql);
        $chunks = preg_split('/;\s*[\r\n]+/', trim($sql));
        foreach ($chunks as $stmtSql) {
            $stmtSql = trim($stmtSql);
            if ($stmtSql !== '') {
                $pdo->exec($stmtSql . (str_ends_with($stmtSql, ';') ? '' : ';'));
            }
        }
        echo "Dossier personnel OK.\n";
        // Migration des données existantes : personnel_extras -> personnel_profiles, user_units -> personnel_assignments
        $pdo->exec("INSERT IGNORE INTO personnel_profiles (user_id, matricule_internal, enlistment_date, clearance_level, readiness_score, command_notes, created_at, updated_at)
            SELECT user_id, service_number, date_of_enlistment, clearance_level, COALESCE(readiness_percent, 0), admin_notes, created_at, updated_at
            FROM personnel_extras");
        $pdo->exec("INSERT INTO personnel_assignments (user_id, unit_id, role_name, is_primary, started_at, ended_at, status)
            SELECT user_id, unit_id, COALESCE(NULLIF(TRIM(assignment_type), ''), 'Membre'), is_primary, assigned_at, ended_at,
            CASE WHEN ended_at IS NULL OR ended_at > NOW() THEN 'active' ELSE 'inactive' END
            FROM user_units");
        echo "Migration données personnel OK.\n";
    }
}
