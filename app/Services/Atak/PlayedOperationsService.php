<?php

declare(strict_types=1);

namespace App\Services\Atak;

use App\Repositories\AtakPlayedOpRepository;
use App\Support\AtakMissionKey;
use App\Support\AtakOpReconciler;
use App\Support\AtakPlayedOpRules;
use App\Support\UtcDateTime;

/**
 * Vue d'ensemble des parties détectées en jeu, pour le cycle de mission et le suivi des CR : nom, type et catégorie
 * (choisis ou détectés par règle), exclusion, rattachement (choisi, proposé ou aucun) et état de chaque étape du cycle.
 */
final class PlayedOperationsService
{
    /** Sans remontée depuis ce délai, la partie est considérée terminée (le serveur envoie toutes les 5 min). */
    public const LIVE_MINUTES = 20;

    /** Étapes du cycle, dans l'ordre. */
    public const STEPS = ['plan', 'brief', 'start', 'run', 'end', 'debrief', 'effects'];

    public function __construct(private ?AtakPlayedOpRepository $repo = null)
    {
        $this->repo ??= new AtakPlayedOpRepository();
    }

    public function repository(): AtakPlayedOpRepository
    {
        return $this->repo;
    }

    /**
     * @return array{ops: list<array<string, mixed>>, types: list<array<string, mixed>>, all_types: list<array<string, mixed>>, rules: list<array<string, mixed>>, categories: list<string>, candidates: list<array<string, mixed>>, deleted: list<array<string, mixed>>, unlinked: int, ready: bool}
     */
    public function overview(int $tenantId, int $days = 90, ?int $now = null): array
    {
        $now ??= time();
        $repo = $this->repo;
        $ready = $repo->ready();
        $detected = $repo->detected($tenantId, $days);
        $meta = $repo->meta($tenantId);
        $allTypes = $repo->types($tenantId, true);
        $types = array_values(array_filter($allTypes, static fn (array $t): bool => empty($t['archived_at'])));
        $rules = $repo->rules($tenantId, true);
        $activeRules = array_values(array_filter($rules, static fn (array $r): bool => empty($r['disabled_at'])));
        $cands = $repo->candidates($tenantId, $days + 10);
        $users = $repo->userIdsFor($tenantId, array_keys($detected));
        $debriefs = $repo->debriefCounts($tenantId, array_keys($detected));
        $aars = $repo->aarReports($tenantId);
        $flights = $repo->flightCounts($tenantId, array_keys($detected));
        $ops = [];
        $deleted = [];
        $unlinked = 0;
        foreach ($detected as $key => $d) {
            $m = $meta[$key] ?? [];
            $d['flights'] = $flights[$key]['n'] ?? 0;
            $d['flight_seconds'] = $flights[$key]['s'] ?? 0;
            $op = self::present($key, $d, $m, $types, $activeRules, $cands, $users[$key] ?? [], $debriefs[$key] ?? 0, $aars, $now);
            if (!empty($m['deleted_at'])) {
                $deleted[] = $op;
                continue;
            }
            if ($op['link'] === null && !$op['ignored']['ignored']) {
                $unlinked++;
            }
            $ops[] = $op;
        }

        return [
            'ops' => $ops,
            'types' => $types,
            'all_types' => $allTypes,
            'rules' => $rules,
            'categories' => $repo->categories($tenantId),
            'candidates' => $cands,
            'deleted' => $deleted,
            'unlinked' => $unlinked,
            'ready' => $ready,
        ];
    }

    /**
     * Une partie prête pour l'affichage. Calcul pur à partir des lignes lues (testé sans base).
     *
     * @param array<string, mixed> $d ligne de AtakPlayedOpRepository::detected
     * @param array<string, mixed> $m réglages (atak_debrief_ops)
     * @param list<array<string, mixed>> $types
     * @param list<array<string, mixed>> $rules règles actives
     * @param list<array<string, mixed>> $cands
     * @param list<int> $userIds
     * @param list<array<string, mixed>> $aars
     * @return array<string, mixed>
     */
    public static function present(string $key, array $d, array $m, array $types, array $rules, array $cands, array $userIds, int $debriefs, array $aars, int $now): array
    {
        $custom = trim((string) ($m['label'] ?? ''));
        $label = $custom !== '' ? $custom : AtakMissionKey::label($key);
        $startTs = self::keyStartTs($key) ?? UtcDateTime::timestamp(((string) ($d['day'] ?? '')) . ' 00:00:00');
        $lastTs = ($d['last_seen'] ?? '') !== '' ? (strtotime((string) $d['last_seen']) ?: null) : null;
        $live = $lastTs !== null && $now - $lastTs <= self::LIVE_MINUTES * 60;
        $duration = $startTs !== null && $lastTs !== null && $lastTs > $startTs ? $lastTs - $startTs : null;
        if (($d['max_play_seconds'] ?? 0) > 0) {
            $duration = max((int) $d['max_play_seconds'], (int) ($duration ?? 0));
        }
        $type = AtakPlayedOpRules::resolveType($key, $label, $types, (int) ($m['type_id'] ?? 0));
        $manualCat = trim((string) ($m['category'] ?? ''));
        $typeCat = $type !== null ? trim((string) ($type['type']['category'] ?? '')) : '';
        $category = $manualCat !== '' ? ['name' => $manualCat, 'source' => 'manual'] : ($typeCat !== '' ? ['name' => $typeCat, 'source' => 'type'] : null);
        $ignored = AtakPlayedOpRules::resolveIgnore($key, $m, $rules);
        $opForMatch = ['start_ts' => $startTs, 'map' => AtakPlayedOpRules::fieldValue($key, 'map'), 'users' => $userIds];

        // Rattachement : choisi à la main, refusé (« aucune »), sinon proposé par le rapprochement.
        $link = null;
        $mode = (string) ($m['link_mode'] ?? '');
        if ($mode === 'manual') {
            foreach (['plan' => 'plan_id', 'cycle' => 'cycle_id', 'event' => 'event_id'] as $kind => $col) {
                $id = (int) ($m[$col] ?? 0);
                if ($id < 1) {
                    continue;
                }
                $cand = self::findCand($cands, $kind, $id) ?? ['kind' => $kind, 'id' => $id, 'title' => '#' . $id, 'start_ts' => null, 'users' => []];
                $s = AtakOpReconciler::score($opForMatch, $cand);
                $link = ['cand' => $cand, 'source' => 'manual', 'score' => $s['score'], 'signals' => $s['signals']];
                break;
            }
        } elseif ($mode !== 'none') {
            $best = AtakOpReconciler::best($opForMatch, $cands);
            if ($best !== null) {
                $link = ['cand' => $best['cand'], 'source' => 'auto', 'score' => $best['score'], 'signals' => $best['signals']];
            }
        }
        $planId = $link !== null ? ($link['cand']['kind'] === 'plan' ? (int) $link['cand']['id'] : (int) ($link['cand']['plan_id'] ?? 0)) : 0;
        $cycleId = $link !== null ? ($link['cand']['kind'] === 'cycle' ? (int) $link['cand']['id'] : (int) ($link['cand']['cycle_id'] ?? 0)) : 0;
        $linkedAars = array_values(array_filter($aars, static fn (array $a): bool => ($planId > 0 && $a['plan_id'] === $planId) || ($cycleId > 0 && $a['cycle_id'] === $cycleId)));

        $players = (int) ($d['players'] ?? 0);
        $reports = (int) ($d['reports'] ?? 0);
        $steps = [
            'plan' => $link !== null ? ($link['source'] === 'manual' ? 'done' : 'auto') : 'none',
            'brief' => $link !== null && $link['cand']['kind'] === 'plan' ? (!empty($link['cand']['published']) ? 'done' : 'todo') : ($cycleId > 0 ? 'done' : 'none'),
            'start' => 'auto',
            'run' => $players > 0 || $reports > 0 ? 'auto' : 'none',
            'end' => $live ? 'live' : 'auto',
            'debrief' => $debriefs > 0 || $linkedAars !== [] ? 'done' : ($live ? 'none' : 'todo'),
            'effects' => !empty($m['deleted_at']) || $ignored['ignored'] ? 'off' : (((int) ($d['play_seconds'] ?? 0)) > 0 ? 'auto' : 'none'),
        ];

        return [
            'key' => $key,
            'label' => $label,
            'custom' => $custom !== '',
            'default_label' => AtakMissionKey::label($key),
            'mission' => AtakPlayedOpRules::fieldValue($key, 'mission'),
            'map' => AtakPlayedOpRules::fieldValue($key, 'map'),
            'start_ts' => $startTs,
            'last_ts' => $lastTs,
            'live' => $live,
            'duration' => $duration,
            'players' => $players,
            'reports' => $reports,
            'play_seconds' => (int) ($d['play_seconds'] ?? 0),
            'debriefs' => $debriefs,
            'flights' => (int) ($d['flights'] ?? 0),
            'flight_seconds' => (int) ($d['flight_seconds'] ?? 0),
            'aars' => $linkedAars,
            'type' => $type,
            'category' => $category,
            'suggestion' => $type === null ? AtakPlayedOpRules::suggest($key, $players, $duration) : null,
            'ignored' => $ignored,
            'deleted' => !empty($m['deleted_at']),
            'hidden' => !empty($m['hidden_at']),
            'link' => $link,
            'link_mode' => $mode,
            'steps' => $steps,
        ];
    }

    /** Horodatage du lancement lu dans la clé (« #AAAAMMJJHHMM », UTC), null sinon. */
    public static function keyStartTs(string $key): ?int
    {
        $parts = explode('#', $key, 2);
        if (!isset($parts[1]) || preg_match('/^(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})/', $parts[1], $m) !== 1) {
            return null;
        }

        return UtcDateTime::timestamp("{$m[1]}-{$m[2]}-{$m[3]} {$m[4]}:{$m[5]}:00");
    }

    /** @param list<array<string, mixed>> $cands @return array<string, mixed>|null */
    public static function findCand(array $cands, string $kind, int $id): ?array
    {
        foreach ($cands as $c) {
            if (($c['kind'] ?? '') === $kind && (int) ($c['id'] ?? 0) === $id) {
                return $c;
            }
        }

        return null;
    }
}
