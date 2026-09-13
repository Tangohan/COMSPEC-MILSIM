<?php

declare(strict_types=1);

/**
 * Hot-path / tenants historiques : colonne operator_label sur les segments routiers ATAK.
 * Idempotent via schema_ensure_column.
 */
return static function (PDO $pdo): void {
    $root = dirname(__DIR__);
    require_once $root . '/bootstrap/schema_ensure_column.php';

    schema_ensure_column(
        $pdo,
        'atak_geo_road_segments',
        'operator_label',
        '`operator_label` VARCHAR(120) NULL AFTER `one_way`'
    );

    if (PHP_SAPI === 'cli') {
        echo "  [OK] atak_geo_road_operator_label\n";
    }
};
