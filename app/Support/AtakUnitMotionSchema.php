<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Database;
use PDO;
use Throwable;

/**
 * Tables de cinématique BFT et d’affectations de destination.
 * Filet pour les tenants historiques : la migration CLI peut ne pas avoir été relancée.
 */
final class AtakUnitMotionSchema
{
    private static bool $ensured = false;

    public static function ensure(): void
    {
        if (self::$ensured) {
            return;
        }
        self::$ensured = true;

        // Appelée à chaque position et à chaque GET /units : la migration (3 sondes information_schema)
        // n’est rejouée qu’en l’absence de trace de succès, ou si le fichier de migration a changé.
        $path = dirname(__DIR__, 2) . '/bootstrap/atak_unit_motion_migration.php';
        SchemaReadyCache::remember('atak_unit_motion_migration', static function () use ($path): bool {
            try {
                $pdo = Database::getPdo();
                if (!$pdo instanceof PDO || !is_file($path)) {
                    return false;
                }
                $migrate = require $path;
                if (!is_callable($migrate)) {
                    return false;
                }
                $migrate($pdo);
                // La migration avale ses erreurs : ne mémoriser le succès que si les tables sont là.
                foreach (['atak_unit_motion_samples', 'atak_unit_motion', 'atak_unit_assignments'] as $t) {
                    if (!schema_table_exists($pdo, $t)) {
                        return false;
                    }
                }

                return true;
            } catch (Throwable) {
                return false;
            }
        }, $path);
    }
}
