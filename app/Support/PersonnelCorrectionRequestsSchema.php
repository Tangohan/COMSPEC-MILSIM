<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Database;
use PDO;
use Throwable;

final class PersonnelCorrectionRequestsSchema
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
            $st = $pdo->query(
                "SELECT 1 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personnel_correction_requests' LIMIT 1"
            );
            if ($st && $st->fetchColumn()) {
                return;
            }
            $path = base_path('bootstrap/personnel_correction_requests_migration.php');
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
