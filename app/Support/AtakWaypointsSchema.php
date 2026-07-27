<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Database;
use PDO;
use Throwable;

/**
 * Filet de sécurité pour les tables de waypoints.
 *
 * AtakModulesSchema::ensure() s’arrête dès que `atak_poi` existe : sur une base déjà
 * installée, elle ne poserait donc jamais les tables ajoutées après coup. D’où ce garde
 * dédié, qui teste sa propre table et exécute son propre fichier de migration.
 */
final class AtakWaypointsSchema
{
    private const MIGRATION = 'migrations/2026_07_27_001_atak_waypoints.sql';

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
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'atak_waypoints' LIMIT 1"
            );
            if ($st && $st->fetchColumn()) {
                return;
            }

            $path = base_path(self::MIGRATION);
            if (!is_file($path)) {
                return;
            }
            $sql = (string) file_get_contents($path);
            // Le fichier est écrit portable (pas de COMMENT, pas de FK) : un simple
            // découpage sur « ; » suffit, sans passer par le nettoyeur des modules.
            $sql = preg_replace('/--[^\n]*/', '', $sql) ?? $sql;
            foreach (explode(';', $sql) as $statement) {
                $statement = trim($statement);
                if ($statement === '') {
                    continue;
                }
                try {
                    $pdo->exec($statement);
                } catch (Throwable) {
                    // Table déjà présente ou moteur récalcitrant : l’appelant verra l’erreur SQL.
                }
            }
        } catch (Throwable) {
            // L’appelant gère l’erreur SQL si la création échoue encore.
        }
    }
}
