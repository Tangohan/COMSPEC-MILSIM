<?php

declare(strict_types=1);

/**
 * Étape « COMSPEC C2/CAS : colonnes atak_air_assets (flight manifest enrichi) » (Schéma).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// COMSPEC C2/CAS : colonnes atak_air_assets (flight manifest enrichi)
$airAssetCols = [
    'mission_id' => "ADD COLUMN mission_id varchar(128) DEFAULT NULL AFTER map_id",
    'radio_main' => "ADD COLUMN radio_main varchar(64) DEFAULT NULL AFTER freq",
    'radio_aux' => "ADD COLUMN radio_aux varchar(64) DEFAULT NULL AFTER radio_main",
    'auth_code' => "ADD COLUMN auth_code varchar(128) DEFAULT NULL AFTER auth",
    'pilot' => "ADD COLUMN pilot varchar(255) DEFAULT NULL AFTER auth_code",
    'crew' => "ADD COLUMN crew json DEFAULT NULL AFTER pilot",
    'fuel_pct' => "ADD COLUMN fuel_pct int unsigned DEFAULT NULL AFTER crew",
    'ordnance' => "ADD COLUMN ordnance json DEFAULT NULL AFTER fuel_pct",
    'station' => "ADD COLUMN station varchar(128) DEFAULT NULL AFTER ordnance",
    'eta_minutes' => "ADD COLUMN eta_minutes int unsigned DEFAULT NULL AFTER station",
    'bingo_fuel' => "ADD COLUMN bingo_fuel varchar(32) DEFAULT NULL AFTER eta_minutes",
    'checklist' => "ADD COLUMN checklist json DEFAULT NULL AFTER bingo_fuel",
    'pos_z' => "ADD COLUMN pos_z decimal(15,4) DEFAULT NULL AFTER pos_y",
    'last_update' => "ADD COLUMN last_update bigint DEFAULT NULL AFTER aircraft_count",
    'source' => "ADD COLUMN source varchar(32) DEFAULT NULL AFTER last_update",
    'vehicle_id' => "ADD COLUMN vehicle_id varchar(64) DEFAULT NULL AFTER source",
];
foreach ($airAssetCols as $col => $alterFrag) {
    $stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'atak_air_assets' AND COLUMN_NAME = '$col'");
    if ($stmt && !$stmt->fetch()) {
        echo "Ajout atak_air_assets.$col...\n";
        $pdo->exec("ALTER TABLE atak_air_assets $alterFrag");
    }
}
