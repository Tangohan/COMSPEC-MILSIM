<?php

declare(strict_types=1);

/**
 * Étape « COMSPEC C2/CAS : colonnes atak_nine_line (mission_id, assigned_aircraft, lines_checked) » (Schéma).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// COMSPEC C2/CAS : colonnes atak_nine_line (mission_id, assigned_aircraft, lines_checked)
foreach (['mission_id' => "ADD COLUMN mission_id varchar(128) DEFAULT NULL AFTER map_id", 'assigned_aircraft' => "ADD COLUMN assigned_aircraft varchar(128) DEFAULT NULL AFTER author", 'lines_checked' => "ADD COLUMN lines_checked json DEFAULT NULL AFTER line9"] as $col => $alterFrag) {
    $stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'atak_nine_line' AND COLUMN_NAME = '$col'");
    if ($stmt && !$stmt->fetch()) {
        echo "Ajout atak_nine_line.$col...\n";
        $pdo->exec("ALTER TABLE atak_nine_line $alterFrag");
    }
}
