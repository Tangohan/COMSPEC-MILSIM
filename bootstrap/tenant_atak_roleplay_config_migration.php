<?php

declare(strict_types=1);

/**
 * Configuration roleplay ATAK — simulation réseau, défauts capteurs, dégradation liaison.
 * Idempotent — appelée depuis run-migrations.php.
 */
return static function (PDO $pdo): void {
    $hasColumn = static function (PDO $pdo, string $table, string $column): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };

    $tableExists = static function (PDO $pdo, string $table): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if (!$tableExists($pdo, 'tenant_atak_config')) {
        echo "  [ATTENTION] tenant_atak_config absente — roleplay ATAK non ajouté\n";

        return;
    }

    // Colonnes de configuration roleplay
    $roleplayColumns = [
        // Simulation réseau
        'roleplay_network_enabled' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'roleplay_network_mode' => "VARCHAR(20) DEFAULT 'normal'",
        'roleplay_latency_min_ms' => 'INT UNSIGNED DEFAULT 0',
        'roleplay_latency_max_ms' => 'INT UNSIGNED DEFAULT 0',
        'roleplay_packet_loss_percent' => 'DECIMAL(5,2) DEFAULT 0.00',
        'roleplay_disconnect_enabled' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'roleplay_disconnect_min_sec' => 'INT UNSIGNED DEFAULT 5',
        'roleplay_disconnect_max_sec' => 'INT UNSIGNED DEFAULT 30',
        'roleplay_disconnect_interval_sec' => 'INT UNSIGNED DEFAULT 600',

        // Défauts capteur cardiaque
        'roleplay_sensor_enabled' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'roleplay_sensor_failure_percent' => 'DECIMAL(5,2) DEFAULT 0.00',
        'roleplay_sensor_error_percent' => 'DECIMAL(5,2) DEFAULT 0.00',
        'roleplay_sensor_missing_percent' => 'DECIMAL(5,2) DEFAULT 0.00',

        // Dégradation liaison (zones géographiques)
        'roleplay_zones_enabled' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'roleplay_zones_config' => 'TEXT DEFAULT NULL',

        // Données chiffrées sans certificat / appareil compromis
        'roleplay_intel_scramble_enabled' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'roleplay_intel_scramble_reviewed' => 'TINYINT(1) NOT NULL DEFAULT 0',
    ];

    $columnsToAdd = [];
    foreach ($roleplayColumns as $column => $definition) {
        if (!$hasColumn($pdo, 'tenant_atak_config', $column)) {
            $columnsToAdd[$column] = $definition;
        }
    }

    if (empty($columnsToAdd)) {
        echo "  [SKIP] tenant_atak_config.roleplay_* déjà présents\n";

        return;
    }

    try {
        $alterStatements = [];
        foreach ($columnsToAdd as $column => $definition) {
            $alterStatements[] = "ADD COLUMN {$column} {$definition}";
        }

        $sql = 'ALTER TABLE tenant_atak_config ' . implode(', ', $alterStatements);
        $pdo->exec($sql);

        echo '  [OK] tenant_atak_config.roleplay_* ajoutés (' . count($columnsToAdd) . " colonnes)\n";
    } catch (Throwable $e) {
        echo '  [ATTENTION] tenant_atak_config.roleplay_* : ' . $e->getMessage() . "\n";
    }
};
