<?php

declare(strict_types=1);

/**
 * Expérience Overwatch par communauté (réalisme, troll, guide).
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
        echo "  [ATTENTION] tenant_atak_config absente — expérience Overwatch non ajoutée\n";

        return;
    }

    if (!$hasColumn($pdo, 'tenant_atak_config', 'experience_config')) {
        try {
            $pdo->exec(
                'ALTER TABLE tenant_atak_config ADD COLUMN experience_config JSON DEFAULT NULL'
            );
            echo "  [OK] tenant_atak_config.experience_config\n";
        } catch (Throwable $e) {
            echo '  [ATTENTION] tenant_atak_config.experience_config : ' . $e->getMessage() . "\n";
        }
    } else {
        echo "  [SKIP] tenant_atak_config.experience_config déjà présent\n";
    }
};
