<?php

declare(strict_types=1);

/**
 * Mode maintenance ATAK / Tacmap par communauté.
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
        echo "  [ATTENTION] tenant_atak_config absente — maintenance ATAK non ajoutée\n";

        return;
    }

    if (!$hasColumn($pdo, 'tenant_atak_config', 'maintenance_enabled')) {
        try {
            $pdo->exec(
                'ALTER TABLE tenant_atak_config
                 ADD COLUMN maintenance_enabled TINYINT(1) NOT NULL DEFAULT 0,
                 ADD COLUMN maintenance_message TEXT DEFAULT NULL'
            );
            echo "  [OK] tenant_atak_config.maintenance_enabled (+ maintenance_message)\n";
        } catch (Throwable $e) {
            echo '  [ATTENTION] tenant_atak_config.maintenance : ' . $e->getMessage() . "\n";
        }
    } else {
        echo "  [SKIP] tenant_atak_config.maintenance_enabled déjà présent\n";
    }
};
