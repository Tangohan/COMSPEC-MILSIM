<?php

declare(strict_types=1);

/**
 * Lieux nommés et segments routiers pour planification d’itinéraires (ingest mod Arma).
 * Idempotent — appelée depuis run-migrations.php et AtakGeoNetworkSchema::ensure().
 */
return static function (PDO $pdo): void {
    $root = dirname(__DIR__);
    require_once $root . '/bootstrap/schema_ensure_column.php';

    $path = $root . '/migrations/2026_08_28_001_atak_geo_network.sql';
    if (!is_file($path)) {
        return;
    }

    $cli = PHP_SAPI === 'cli';
    $sql = (string) file_get_contents($path);
    $sql = preg_replace('/--[^\n]*/', '', $sql) ?? $sql;

    foreach (explode(';', $sql) as $statement) {
        $statement = trim($statement);
        if ($statement === '') {
            continue;
        }
        try {
            $pdo->exec($statement);
        } catch (Throwable $e) {
            if ($cli) {
                echo '  [ATTENTION] atak_geo_network DDL : ' . $e->getMessage() . "\n";
            }
        }
    }

    if ($cli) {
        echo "  [OK] atak_geo_network (lieux + routes)\n";
    }
};
