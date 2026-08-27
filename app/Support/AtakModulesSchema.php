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

            $have = self::existingNames($pdo, [
                'atak_poi',
                'atak_vehicle_tracking',
                'atak_vehicle_service_requests',
                'v_atak_active_vehicles',
            ]);

            // atak_poi seul ne suffit pas : une base déjà migrée partiellement
            // sautait la pose des véhicules, et chaque relève carte échouait.
            if (!isset($have['atak_poi']) || !isset($have['atak_vehicle_tracking'])) {
                $migrate = require base_path('bootstrap/atak_modules_schema_migration.php');
                if (is_callable($migrate)) {
                    ob_start();
                    try {
                        $migrate($pdo);
                    } finally {
                        ob_end_clean();
                    }
                }
                $have = self::existingNames($pdo, [
                    'atak_poi',
                    'atak_vehicle_tracking',
                    'atak_vehicle_service_requests',
                    'v_atak_active_vehicles',
                ]);
            }

            if (isset($have['atak_vehicle_tracking']) && !isset($have['v_atak_active_vehicles'])) {
                self::createActiveVehiclesView($pdo, isset($have['atak_vehicle_service_requests']));
            }
        } catch (Throwable) {
            // L’appelant gère l’erreur SQL si la création échoue encore.
        }
    }

    /**
     * @param list<string> $names
     * @return array<string, true>
     */
    private static function existingNames(PDO $pdo, array $names): array
    {
        $have = [];
        if ($names === []) {
            return $have;
        }
        $placeholders = implode(',', array_fill(0, count($names), '?'));
        $st = $pdo->prepare(
            "SELECT TABLE_NAME FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ({$placeholders})"
        );
        $st->execute(array_values($names));
        while ($row = $st->fetchColumn()) {
            $have[(string) $row] = true;
        }

        return $have;
    }

    private static function createActiveVehiclesView(PDO $pdo, bool $hasServiceRequests): void
    {
        $pending = $hasServiceRequests
            ? "(SELECT COUNT(*) FROM atak_vehicle_service_requests
                WHERE vehicle_tracking_id = v.id
                  AND status IN ('REQUESTED', 'ACKNOWLEDGED', 'ENROUTE', 'IN_PROGRESS'))"
            : '0';
        $sql = "CREATE OR REPLACE VIEW v_atak_active_vehicles AS
                SELECT v.*,
                       commander_u.email AS crew_commander_username,
                       {$pending} AS pending_service_requests
                FROM atak_vehicle_tracking v
                LEFT JOIN users commander_u ON v.crew_commander_user_id = commander_u.id
                WHERE v.status <> 'DESTROYED'";
        try {
            $pdo->exec($sql);
        } catch (Throwable) {
            // Lecture carte : la relève utilise la table, pas cette vue.
        }
    }
}
