<?php

declare(strict_types=1);

/**
 * Colonnes pos_x / pos_y sur atak_units (positions carte, au-delà du seul grid_ref texte).
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

    if (!$tableExists($pdo, 'atak_units')) {
        echo "  [ATTENTION] atak_units absente — positions unités non ajoutées\n";

        return;
    }

    if (!$hasColumn($pdo, 'atak_units', 'pos_x')) {
        try {
            $pdo->exec(
                'ALTER TABLE atak_units
                 ADD COLUMN pos_x DECIMAL(15,4) DEFAULT NULL AFTER heading,
                 ADD COLUMN pos_y DECIMAL(15,4) DEFAULT NULL AFTER pos_x'
            );
            echo "  [OK] atak_units.pos_x / pos_y\n";
        } catch (Throwable $e) {
            echo '  [ATTENTION] atak_units pos_x/pos_y : ' . $e->getMessage() . "\n";
        }
    } else {
        echo "  [OK] atak_units.pos_x déjà présent\n";
    }

    // Backfill depuis grid_ref « X Y » quand les colonnes sont vides.
    try {
        $pdo->exec(
            "UPDATE atak_units
             SET pos_x = CAST(SUBSTRING_INDEX(TRIM(grid_ref), ' ', 1) AS DECIMAL(15,4)),
                 pos_y = CAST(SUBSTRING_INDEX(TRIM(grid_ref), ' ', -1) AS DECIMAL(15,4))
             WHERE (pos_x IS NULL OR pos_y IS NULL)
               AND grid_ref IS NOT NULL
               AND TRIM(grid_ref) REGEXP '^-?[0-9]+(\\.[0-9]+)?[[:space:]]+-?[0-9]+(\\.[0-9]+)?$'"
        );
    } catch (Throwable $e) {
        echo '  [ATTENTION] backfill grid_ref → pos : ' . $e->getMessage() . "\n";
    }
};
