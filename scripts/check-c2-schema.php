<?php

declare(strict_types=1);

/**
 * Vérifie que toutes les tables et colonnes du schéma C2/CAS (et piliers C2) sont présentes.
 * Usage : php scripts/check-c2-schema.php
 * Depuis la racine du projet.
 */

$root = dirname(__DIR__);

// Charger .env
if (is_file($root . '/.env')) {
    $lines = file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) continue;
        [$name, $value] = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value, " \t\"'");
        putenv(trim($name) . '=' . trim($value, " \t\"'"));
    }
}

$host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: '127.0.0.1';
$name = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: '';
$user = $_ENV['DB_USER'] ?? getenv('DB_USER') ?: '';
$pass = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: '';
$charset = $_ENV['DB_CHARSET'] ?? getenv('DB_CHARSET') ?: 'utf8mb4';

if (empty($name) || empty($user)) {
    echo "Erreur : définir DB_NAME et DB_USER (.env ou variables d'environnement).\n";
    exit(1);
}

$dsn = "mysql:host=$host;dbname=$name;charset=$charset";
try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    echo "Connexion impossible : " . $e->getMessage() . "\n";
    exit(1);
}

echo "=== Vérification schéma C2/CAS et piliers — base : $name ===\n\n";

// Définition attendue : table => [colonnes]
$expected = [
    // C2/CAS (plan + schema.sql)
    'atak_nine_line' => [
        'id', 'tenant_id', 'map_id', 'mission_id', 'author', 'assigned_aircraft',
        'line1', 'line2', 'line3', 'line4', 'line5', 'line6', 'line7', 'line8', 'line9',
        'lines_checked', 'status', 'created_at', 'updated_at',
    ],
    'atak_air_assets' => [
        'id', 'tenant_id', 'map_id', 'mission_id', 'callsign', 'model', 'aircraft_type',
        'freq', 'radio_main', 'radio_aux', 'laser', 'auth', 'auth_code', 'pilot', 'crew',
        'fuel_pct', 'ordnance', 'station', 'eta_minutes', 'bingo_fuel', 'checklist',
        'pos_x', 'pos_y', 'pos_z', 'alt', 'heading', 'side', 'status', 'pilot_status',
        'aircraft_count', 'last_update', 'source', 'vehicle_id', 'updated_at',
    ],
    'recon_images' => [
        'id', 'tenant_id', 'mission_id', 'author_callsign', 'unit_name', 'side',
        'image_path', 'thumb_path', 'caption', 'pos_x', 'pos_y', 'pos_z', 'grid_ref',
        'heading', 'altitude', 'device_type', 'captured_at', 'atak_cas_id', 'created_at',
    ],
    'atak_map_shapes' => [
        'id', 'tenant_id', 'map_id', 'mission_id', 'shape_uid', 'type', 'label', 'color',
        'stroke', 'fill_opacity', 'created_by', 'visible_to', 'geometry', 'meta',
        'created_at', 'updated_at',
    ],
    'atak_laser_codes' => [
        'id', 'tenant_id', 'map_id', 'call_sign', 'laser_code', 'pos_x', 'pos_y',
        'status', 'last_update', 'updated_at',
    ],
    // Piliers C2 (c2_pillars.sql)
    'fire_units' => [
        'id', 'mission_id', 'callsign', 'vehicle_class', 'weapon_system', 'pos_x', 'pos_y', 'pos_z',
        'heading', 'side', 'status', 'last_update_at', 'created_at',
    ],
    'fire_tables' => [
        'id', 'weapon_system', 'ammo_type', 'min_range', 'max_range', 'table_json', 'created_at',
    ],
    'danger_zones' => [
        'id', 'mission_id', 'zone_type', 'label', 'color', 'fill_opacity', 'stroke_width',
        'geometry_type', 'geometry_json', 'side_visibility_json', 'threat_level', 'active',
        'created_by', 'created_at', 'updated_at',
    ],
    'asset_logistics_status' => [
        'id', 'mission_id', 'asset_id', 'callsign', 'vehicle_class', 'fuel_ratio', 'ammo_state_json',
        'damage_ratio', 'crew_count', 'cargo_slots_free', 'slingload_capable', 'last_update_at', 'created_at',
    ],
    'asset_logistics_status_history' => [
        'id', 'mission_id', 'asset_id', 'callsign', 'fuel_ratio', 'damage_ratio', 'snapshot_json', 'logged_at',
    ],
    'intel_reports' => [
        'id', 'mission_id', 'source_callsign', 'report_type', 'target_type', 'pos_x', 'pos_y', 'pos_z',
        'confidence_score', 'raw_payload_json', 'first_seen_at', 'last_seen_at', 'merged_count', 'status',
        'created_at', 'updated_at',
    ],
    'intel_reports_events' => [
        'id', 'intel_report_id', 'source_callsign', 'payload_json', 'created_at',
    ],
    'logs_positions' => [
        'id', 'mission_id', 'unit_id', 'callsign', 'unit_type', 'side', 'pos_x', 'pos_y', 'pos_z',
        'heading', 'speed', 'state_json', 'logged_at',
    ],
    'iff_challenges' => [
        'id', 'mission_id', 'code', 'valid_from', 'valid_until', 'created_at',
    ],
    'iff_asset_status' => [
        'id', 'mission_id', 'asset_id', 'callsign', 'platform_type', 'current_challenge_id',
        'response_code', 'response_status', 'responded_at', 'grace_until', 'created_at', 'updated_at',
    ],
];

$stmtTables = $pdo->prepare(
    "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
);
$stmtCols = $pdo->prepare(
    "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION"
);

$ok = true;
$missingTables = [];
$missingCols = [];

foreach ($expected as $table => $columns) {
    $stmtTables->execute([$table]);
    if (!$stmtTables->fetch()) {
        $missingTables[] = $table;
        $ok = false;
        echo "[MANQUANT] Table : $table\n";
        continue;
    }

    $stmtCols->execute([$table]);
    $existing = array_column($stmtCols->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME');
    $missing = array_diff($columns, $existing);
    if (!empty($missing)) {
        $ok = false;
        $missingCols[$table] = array_values($missing);
        echo "[COLONNES MANQUANTES] $table : " . implode(', ', $missing) . "\n";
    } else {
        echo "[OK] $table (" . count($columns) . " colonnes)\n";
    }
}

echo "\n--- Résumé ---\n";
if ($ok) {
    echo "Toutes les tables et colonnes C2/CAS et piliers sont présentes.\n";
    exit(0);
}

if (!empty($missingTables)) {
    echo "Tables manquantes : " . implode(', ', $missingTables) . "\n";
}
if (!empty($missingCols)) {
    echo "Colonnes manquantes (par table) :\n";
    foreach ($missingCols as $t => $cols) {
        echo "  - $t : " . implode(', ', $cols) . "\n";
    }
}
echo "\nExécutez les migrations : php setup-database.php\n";
echo "Et si besoin : mysql ... < migrations/c2_pillars.sql\n";
exit(1);
