<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Database;
use PDO;
use Throwable;

/**
 * Filet de sécurité : pose le schéma modules ATAK si les migrations n’ont pas encore tourné.
 */
final class AtakModulesSchema
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
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'atak_poi' LIMIT 1"
            );
            if ($st && $st->fetchColumn()) {
                return;
            }

            $migrate = require base_path('bootstrap/atak_modules_schema_migration.php');
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
