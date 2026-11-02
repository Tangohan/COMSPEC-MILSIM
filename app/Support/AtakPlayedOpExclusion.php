<?php

declare(strict_types=1);

namespace App\Support;

use PDO;

/**
 * Retire des heures, de la présence et des statistiques les parties ignorées ou supprimées (atak_debrief_ops) et celles
 * visées par une règle « ignorer à l'avenir » active (atak_op_ignore_rules), sauf « compter quand même ».
 *
 * Fragment SQL « AND … » à coller dans un WHERE dont la ligne porte une clé de mission et une communauté. Tant que la
 * migration atak_played_operations n'est pas passée, le fragment est vide : rien n'est retiré, aucune requête ne casse.
 * Même lecture des motifs que AtakPlayedOpRules::matches (LIKE insensible à la casse, motif traduit par toLike).
 */
final class AtakPlayedOpExclusion
{
    private static ?bool $ready = null;

    /** Pour les tests : forcer l'état du schéma (null = relire). */
    public static function setReady(?bool $ready): void
    {
        self::$ready = $ready;
    }

    public static function ready(?PDO $pdo = null): bool
    {
        if (self::$ready !== null) {
            return self::$ready;
        }
        try {
            $pdo ??= \App\Core\Database::getPdo();
            $st = $pdo->query(
                "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
                 AND ((TABLE_NAME = 'atak_debrief_ops' AND COLUMN_NAME IN ('ignored_at', 'deleted_at', 'count_anyway'))
                   OR (TABLE_NAME = 'atak_op_ignore_rules' AND COLUMN_NAME = 'like_pattern'))"
            );
            self::$ready = $st !== false && (int) $st->fetchColumn() === 4;
        } catch (\Throwable) {
            self::$ready = false;
        }

        return self::$ready;
    }

    /**
     * « AND … » (ou '' si le schéma manque).
     *
     * @param string $missionKeyExpr colonne de la clé (ex. « t.mission_key »)
     * @param string $tenantExpr colonne de la communauté (ex. « t.tenant_id »)
     */
    public static function clause(string $missionKeyExpr, string $tenantExpr, ?PDO $pdo = null): string
    {
        return self::ready($pdo) ? ' AND ' . self::sql($missionKeyExpr, $tenantExpr) : '';
    }

    /** Condition « la partie compte » (vraie pour une ligne sans clé de mission). */
    public static function sql(string $mk, string $tenant): string
    {
        $mission = "SUBSTRING_INDEX({$mk}, '@', 1)";
        $map = "SUBSTRING_INDEX(SUBSTRING_INDEX({$mk}, '#', 1), '@', -1)";

        return "NOT EXISTS (SELECT 1 FROM atak_debrief_ops xo WHERE xo.tenant_id = {$tenant} AND xo.mission_key = {$mk}"
            . ' AND (xo.ignored_at IS NOT NULL OR xo.deleted_at IS NOT NULL))'
            . " AND NOT (COALESCE({$mk}, '') <> ''"
            . " AND EXISTS (SELECT 1 FROM atak_op_ignore_rules xr WHERE xr.tenant_id = {$tenant} AND xr.disabled_at IS NULL"
            . " AND (CASE xr.match_field WHEN 'map' THEN {$map} ELSE {$mission} END) LIKE xr.like_pattern)"
            . " AND NOT EXISTS (SELECT 1 FROM atak_debrief_ops xc WHERE xc.tenant_id = {$tenant} AND xc.mission_key = {$mk} AND xc.count_anyway = 1))";
    }
}
