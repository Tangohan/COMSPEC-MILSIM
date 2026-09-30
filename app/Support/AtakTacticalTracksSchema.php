<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Database;
use PDO;
use Throwable;

final class AtakTacticalTracksSchema
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
            $path = dirname(__DIR__, 2) . '/bootstrap/tactical_tracks_migration.php';
            if (!is_file($path)) {
                return;
            }
            $migrate = require $path;
            if (is_callable($migrate)) {
                $migrate($pdo);
            }
        } catch (Throwable) {
        }
    }
}
