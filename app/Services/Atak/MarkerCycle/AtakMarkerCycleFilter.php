<?php

declare(strict_types=1);

namespace App\Services\Atak\MarkerCycle;

use App\Support\ShortCache;

/**
 * Filtre de lecture du cycle : partout où les marqueurs et tracés sont servis (carte web du poste TOC, ATAK web /
 * Overwatch, TACMAP, jeu), seuls ceux du cycle en cours et ceux épinglés sont renvoyés.
 *
 * cutoff() renvoie le début du cycle en cours (UTC, « Y-m-d H:i:s ») ou null quand il n'y a rien à filtrer : cycle
 * désactivé par la communauté, tables absentes (avant la migration), ou toute erreur de lecture : on retombe alors sur
 * l'affichage d'avant, jamais sur une carte vide. Le résultat est gardé 30 s au plus (et jamais au-delà de la bascule).
 */
final class AtakMarkerCycleFilter
{
    /**
     * Marqueur épinglé (« permanent » du poste) ou dans le cycle : fragment SQL, un paramètre (début du cycle).
     *
     * Pas de COALESCE avec un littéral de date : MySQL en fait une chaîne dont la collation diffère de celle du paramètre
     * lié (« Illegal mix of collations », erreur 1267). Une ligne sans aucune date reste visible, comme avant.
     */
    public const MARKER_SQL = " AND (COALESCE(updated_at, created_at) >= ? OR (updated_at IS NULL AND created_at IS NULL) OR marker_data LIKE '%\"web_permanent\":true%')";

    public const SHAPE_SQL = " AND (COALESCE(updated_at, created_at) >= ? OR (updated_at IS NULL AND created_at IS NULL) OR COALESCE(JSON_UNQUOTE(JSON_EXTRACT(meta, '\$.web_permanent')), '') = 'true')";

    private static ?AtakMarkerCycleService $service = null;

    /** @var array<int, array{0: ?string, 1: int}> */
    private static array $memo = [];

    /** Tests : service à utiliser (null : celui du site). */
    public static function useService(?AtakMarkerCycleService $service): void
    {
        self::$service = $service;
        self::$memo = [];
    }

    public static function cutoff(int $tenantId): ?string
    {
        if ($tenantId < 1) {
            return null;
        }
        $now = time();
        $hit = self::$memo[$tenantId] ?? null;
        if ($hit !== null && $now < $hit[1]) {
            return $hit[0];
        }
        try {
            $svc = self::$service ?? new AtakMarkerCycleService(new PdoMarkerCycleStore());
            $now = $svc->now();
            $cut = ShortCache::remember(
                'mcycle:' . $tenantId . ':' . ShortCache::gen('mcycle:' . $tenantId),
                30,
                static fn (): array => [$svc->readCutoff($tenantId, $now)]
            );
            $value = is_array($cut) ? ($cut[0] ?? null) : null;
        } catch (\Throwable) {
            return null;
        }
        // Valable jusqu'à la prochaine bascule possible : au plus 30 s, donc jamais en retard d'un cycle.
        self::$memo[$tenantId] = [is_string($value) ? $value : null, $now + 30];

        return is_string($value) ? $value : null;
    }
}
