<?php

declare(strict_types=1);

/**
 * Clé d’accès Overwatch / ATAK par communauté (générée depuis l’admin ATAK).
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
        echo "  [ATTENTION] tenant_atak_config absente — clé d’accès ATAK non ajoutée\n";

        return;
    }

    if (!$hasColumn($pdo, 'tenant_atak_config', 'access_key')) {
        try {
            // Pas de AFTER figé : l’ordre des colonnes varie selon les migrations (default_map_slug, etc.).
            $pdo->exec(
                'ALTER TABLE tenant_atak_config
                 ADD COLUMN access_key VARCHAR(128) DEFAULT NULL,
                 ADD COLUMN access_key_prefix VARCHAR(16) DEFAULT NULL,
                 ADD COLUMN access_key_generated_at DATETIME DEFAULT NULL'
            );
            echo "  [OK] tenant_atak_config.access_key (+ prefix, generated_at)\n";
        } catch (Throwable $e) {
            echo '  [ATTENTION] tenant_atak_config.access_key : ' . $e->getMessage() . "\n";
        }
    } else {
        echo "  [SKIP] tenant_atak_config.access_key déjà présent\n";
    }
};
