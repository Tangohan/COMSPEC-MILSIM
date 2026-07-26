<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Database;
use PDO;
use Throwable;

/**
 * Filet de sécurité : pose les tables des piliers C2 si les migrations n’ont pas encore tourné.
 */
final class C2PillarsSchema
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
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fire_units' LIMIT 1"
            );
            if ($st && $st->fetchColumn()) {
                return;
            }

            $migrate = require base_path('bootstrap/c2_pillars_migration.php');
            if (!is_callable($migrate)) {
                return;
            }
            ob_start();
            try {
                $migrate($pdo);
            } finally {
                ob_end_clean();
            }
        } catch (Throwable) {
            // L’appelant gère l’erreur SQL si la création échoue encore.
        }
    }
}
