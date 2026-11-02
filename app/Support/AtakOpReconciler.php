<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Rapprochement d'une partie détectée en jeu avec ce qui était prévu : opération planifiée (mission_plans), cycle de
 * mission (theatre_mission_cycles) ou événement (community_events). Calcul pur.
 *
 * Le créneau est obligatoire (sans heure proche, pas de proposition) ; la carte et les joueurs prévus présents
 * renforcent ou affaiblissent la proposition. Au-dessus de AUTO_MIN points, la partie est proposée comme rattachée ;
 * le gestionnaire confirme, choisit une autre opération ou refuse (« aucune »).
 */
final class AtakOpReconciler
{
    /** Score minimal d'une proposition automatique. */
    public const AUTO_MIN = 45;

    /** Écart toléré entre le lancement de la partie et l'heure prévue. */
    public const WINDOW_MINUTES = 240;

    /** Marge autour d'une fenêtre connue (début - 60 min, fin + 30 min). */
    public const BEFORE_MINUTES = 60;
    public const AFTER_MINUTES = 30;

    /**
     * @param array{start_ts: ?int, map?: string, users?: list<int>} $op
     * @param array{start_ts: ?int, end_ts?: ?int, map?: string, users?: list<int>} $cand
     * @return array{score: int, signals: list<array{code: string, value?: int, total?: int}>}
     *   signals : window (dans la fenêtre), slot (value = minutes d'écart, signé), map, map_other, players (value/total)
     */
    public static function score(array $op, array $cand): array
    {
        $start = $op['start_ts'] ?? null;
        $ref = $cand['start_ts'] ?? null;
        if ($start === null || $ref === null) {
            return ['score' => 0, 'signals' => []];
        }
        $signals = [];
        $score = 0;
        $end = $cand['end_ts'] ?? null;
        if ($end !== null && $end > $ref && $start >= $ref - self::BEFORE_MINUTES * 60 && $start <= $end + self::AFTER_MINUTES * 60) {
            $score += 50;
            $signals[] = ['code' => 'window'];
        } else {
            $delta = intdiv($start - $ref, 60);
            if (abs($delta) > self::WINDOW_MINUTES) {
                return ['score' => 0, 'signals' => []];
            }
            $score += (int) round(45 * (1 - abs($delta) / self::WINDOW_MINUTES));
            $signals[] = ['code' => 'slot', 'value' => $delta];
        }
        $opMap = mb_strtolower(trim((string) ($op['map'] ?? '')));
        $candMap = mb_strtolower(trim((string) ($cand['map'] ?? '')));
        if ($opMap !== '' && $candMap !== '') {
            if ($opMap === $candMap) {
                $score += 15;
                $signals[] = ['code' => 'map'];
            } else {
                $score -= 10;
                $signals[] = ['code' => 'map_other'];
            }
        }
        $planned = array_values(array_unique(array_filter(array_map('intval', $cand['users'] ?? []))));
        $present = array_values(array_unique(array_filter(array_map('intval', $op['users'] ?? []))));
        if ($planned !== [] && $present !== []) {
            $hit = count(array_intersect($planned, $present));
            $score += (int) round(35 * $hit / count($planned));
            $signals[] = ['code' => 'players', 'value' => $hit, 'total' => count($planned)];
        }

        return ['score' => max(0, min(100, $score)), 'signals' => $signals];
    }

    /**
     * Meilleure proposition (score ≥ AUTO_MIN), à égalité la première.
     *
     * @param array<string, mixed> $op
     * @param list<array<string, mixed>> $cands chacun : kind, id, title, start_ts, end_ts, map, users
     * @return array{cand: array<string, mixed>, score: int, signals: list<array<string, mixed>>}|null
     */
    public static function best(array $op, array $cands): ?array
    {
        $best = null;
        foreach ($cands as $c) {
            $s = self::score($op, $c);
            if ($s['score'] >= self::AUTO_MIN && ($best === null || $s['score'] > $best['score'])) {
                $best = ['cand' => $c, 'score' => $s['score'], 'signals' => $s['signals']];
            }
        }

        return $best;
    }

    /** Valeur de rattachement saisie : « plan:12 », « cycle:3 », « event:7 », « none » ou « auto ». @return array{0: string, 1: int} */
    public static function parseLink(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === 'none' || $raw === 'auto') {
            return [$raw, 0];
        }
        if (preg_match('/^(plan|cycle|event):(\d{1,10})$/', $raw, $m) === 1 && (int) $m[2] > 0) {
            return [$m[1], (int) $m[2]];
        }

        return ['auto', 0];
    }
}
