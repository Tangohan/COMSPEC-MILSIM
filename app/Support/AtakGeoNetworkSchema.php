<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Database;
use PDO;
use Throwable;

/**
 * Filet pour les tables du réseau géographique (lieux, routes).
 */
final class AtakGeoNetworkSchema
{
    private static bool $ensured = false;

    public static function ensure(): void
    {
        if (self::$ensured) {
            return;
        }
        self::$ensured = true;

        try {
            $pdo = Database::getPdo();
            if (!$pdo instanceof PDO) {
                return;
            }

            $root = dirname(__DIR__, 2);
            require_once $root . '/bootstrap/schema_ensure_column.php';

            $st = $pdo->query(
                "SELECT 1 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'atak_geo_places' LIMIT 1"
            );
            if (!$st || !$st->fetchColumn()) {
                $path = $root . '/bootstrap/atak_geo_network_migration.php';
                if (is_file($path)) {
                    $migrate = require $path;
                    if (is_callable($migrate)) {
                        $migrate($pdo);
                    }
                }
            }

            schema_ensure_column(
                $pdo,
                'atak_geo_road_segments',
                'operator_label',
                '`operator_label` VARCHAR(120) NULL AFTER `one_way`'
            );
        } catch (Throwable) {
        }
    }
}
