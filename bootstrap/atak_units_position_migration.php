<?php

declare(strict_types=1);

/**
 * Colonnes atak_units alignées sur migrations/schema.sql :
 * military_id, pos_x, pos_y (+ backfill grid_ref → pos).
 * Idempotent — appelée depuis run-migrations.php.
 * (ensure tôt dans le pipeline via schema_ensure_column ; ce bloc reste pour le backfill.)
 */
return static function (PDO $pdo): void {
    $root = dirname(__DIR__);
    require_once $root . '/bootstrap/schema_ensure_column.php';

    if (!schema_table_exists($pdo, 'atak_units')) {
        echo "  [ATTENTION] atak_units absente — positions unités non ajoutées\n";

        return;
    }

    $added = schema_ensure_columns($pdo, 'atak_units', [
        'military_id' => '`military_id` varchar(32) DEFAULT NULL AFTER `call_sign`',
        'pos_x' => '`pos_x` decimal(15,4) DEFAULT NULL AFTER `heading`',
        'pos_y' => '`pos_y` decimal(15,4) DEFAULT NULL AFTER `pos_x`',
    ]);
    if ($added === 0) {
        echo "  [OK] atak_units.military_id / pos_x / pos_y déjà présentes\n";
    }

    // Backfill depuis grid_ref « X Y » quand les colonnes sont vides.
    if (schema_column_exists($pdo, 'atak_units', 'pos_x') && schema_column_exists($pdo, 'atak_units', 'pos_y')) {
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
    }
};
