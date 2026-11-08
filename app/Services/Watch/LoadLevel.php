<?php

declare(strict_types=1);

namespace App\Services\Watch;

/**
 * État de charge à partir de ce que le site mesure lui-même (sans état, sans base, testable seul).
 *
 * Signaux : durée p95 des requêtes dynamiques, part de réponses 5xx, charge du processeur par cœur (Linux). Chacun est
 * rapporté à son seuil d'alerte ; le palier brut suit le plus élevé des rapports :
 *   moins de 1 : 0 (normal) ; de 1 à 1,5 : 1 ; de 1,5 à 2,5 : 2 ; au-delà : 3.
 *
 * Un signal est « fiable » seulement avec assez de requêtes dans la fenêtre (p95 et taux d'erreurs) ou une valeur lue
 * (charge). Sans aucun signal fiable, le palier est 0 et l'état dit pourquoi : un capteur muet ne ralentit personne.
 * Anti-bascule : monter demande deux mesures de suite au-dessus, redescendre trois mesures de suite en dessous (d'un
 * palier à la fois).
 */
final class LoadLevel
{
    /** Requêtes minimum dans la fenêtre pour croire le p95 et le taux d'erreurs. */
    public const MIN_REQUESTS = 30;

    public const UP_AFTER = 2;

    public const DOWN_AFTER = 3;

    /** Âge maximal d'une mesure (secondes) : au-delà, elle ne compte plus. */
    public const MAX_AGE_SECONDS = 120;

    /**
     * @param array{requests: int, p95_ms: ?int, error_pct: ?float, load_per_core: ?float} $signals
     * @param array<string, float|int> $settings seuils d'alerte (WatchSettings)
     * @return array{ratio: ?float, raw_level: int, reliable: bool, reason: string, used: list<string>}
     */
    public static function evaluate(array $signals, array $settings): array
    {
        $ratios = [];
        $enough = (int) ($signals['requests'] ?? 0) >= self::MIN_REQUESTS;
        $p95 = $signals['p95_ms'] ?? null;
        $err = $signals['error_pct'] ?? null;
        $load = $signals['load_per_core'] ?? null;
        if ($enough && $p95 !== null) {
            $ratios['p95'] = $p95 / max(1.0, (float) $settings[WatchSettings::ALERT_P95_MS]);
        }
        if ($enough && $err !== null) {
            $ratios['errors'] = $err / max(0.1, (float) $settings[WatchSettings::ALERT_ERROR_PCT]);
        }
        if ($load !== null) {
            $ratios['load'] = $load / max(0.1, (float) $settings[WatchSettings::ALERT_LOAD_PER_CORE]);
        }
        if ($ratios === []) {
            return [
                'ratio' => null,
                'raw_level' => 0,
                'reliable' => false,
                'reason' => $enough ? 'no_signal' : 'too_few_requests',
                'used' => [],
            ];
        }
        $ratio = max($ratios);
        $raw = $ratio < 1.0 ? 0 : ($ratio < 1.5 ? 1 : ($ratio < 2.5 ? 2 : 3));

        return ['ratio' => round($ratio, 2), 'raw_level' => $raw, 'reliable' => true, 'reason' => '', 'used' => array_keys($ratios)];
    }

    /**
     * Palier retenu après anti-bascule.
     *
     * @param array{level?: int, up?: int, down?: int} $previous
     * @return array{level: int, up: int, down: int}
     */
    public static function settle(int $rawLevel, bool $reliable, array $previous): array
    {
        $level = max(0, min(3, (int) ($previous['level'] ?? 0)));
        $up = (int) ($previous['up'] ?? 0);
        $down = (int) ($previous['down'] ?? 0);
        if (!$reliable) {
            // Mesure muette : retour immédiat à zéro, rien n'est ralenti sur une mesure qu'on ne peut pas croire.
            return ['level' => 0, 'up' => 0, 'down' => 0];
        }
        if ($rawLevel > $level) {
            $up++;
            $down = 0;
            if ($up >= self::UP_AFTER) {
                $level = $rawLevel;
                $up = 0;
            }
        } elseif ($rawLevel < $level) {
            $down++;
            $up = 0;
            if ($down >= self::DOWN_AFTER) {
                $level = max($rawLevel, $level - 1);
                $down = 0;
            }
        } else {
            $up = 0;
            $down = 0;
        }

        return ['level' => $level, 'up' => $up, 'down' => $down];
    }

    /** Percentile (méthode du rang le plus proche) d'une liste de durées ; null si vide. */
    public static function percentile(array $values, float $p): ?int
    {
        $n = count($values);
        if ($n === 0) {
            return null;
        }
        sort($values);
        $rank = (int) ceil($p / 100 * $n);

        return (int) $values[max(0, min($n - 1, $rank - 1))];
    }

    /**
     * Charge système par cœur (null si illisible : Windows, droits).
     *
     * @param array{0: float, 1: float, 2: float}|null $loadAvg sys_getloadavg()
     */
    public static function loadPerCore(?array $loadAvg, ?int $cores): ?float
    {
        if ($loadAvg === null || $cores === null || $cores < 1) {
            return null;
        }

        return round(((float) $loadAvg[0]) / $cores, 2);
    }

    /** Nombre de cœurs lus dans /proc/cpuinfo (null hors Linux). */
    public static function cpuCores(): ?int
    {
        $raw = @file_get_contents('/proc/cpuinfo');
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $n = preg_match_all('/^processor\s*:/m', $raw);

        return $n > 0 ? $n : null;
    }
}
