<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Repositories\PilotLogbookRepository;
use App\Support\PilotFleet;
use App\Support\PilotSyllabusCatalog as Cat;
use Throwable;

/**
 * Espace aérien (back-office) : vue d'ensemble de l'aviation de la communauté, tirée des seules données relevées —
 * ligne de vol (qui est qualifié et à jour sur quel appareil), propositions et vols à valider, cursus par filière,
 * pilotes (heures, paliers, prochaine étape), carnet de vol, flotte réellement pilotée.
 */
final class AirspaceOverviewService
{
    /** Jours avant l'échéance du maintien où un pilote passe « bientôt ». */
    public const SOON_DAYS = 15;

    public function __construct(
        private PilotQualificationService $pilot,
        private ?PilotLogbookRepository $logbook = null,
        private ?PilotLogbookService $logbookService = null,
        private ?PilotFleetService $fleet = null,
        private ?PilotSyllabusStepService $steps = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function overview(int $tenantId, int $actorId, string $logFilter = ''): array
    {
        $ready = $this->pilot->schemaReady();
        $board = $ready ? $this->pilot->board($tenantId) : ['rules' => [], 'members' => [], 'flown_families' => [], 'pending_total' => 0, 'eligible_total' => 0];
        $safe = static function (callable $fn, mixed $default): mixed {
            try {
                return $fn();
            } catch (Throwable) {
                return $default;
            }
        };
        $logReady = $this->logbook !== null && $this->logbook->schemaReady();
        $canReview = [];
        foreach ([Cat::TRACK_HELI, Cat::TRACK_PLANE] as $t) {
            $canReview[$t] = $this->logbookService !== null && $safe(fn (): bool => $this->logbookService->canReview($tenantId, $t, $actorId), false);
        }

        return self::compose([
            'board' => $board,
            'totals' => $ready ? $safe(fn (): array => $this->pilot->totalsByUser($tenantId), []) : [],
            'aircraft' => $this->fleet !== null ? $safe(fn (): array => $this->fleet->aircraft($tenantId), []) : [],
            'steps' => $this->steps !== null && $this->steps->schemaReady() ? $safe(fn (): array => $this->steps->board($tenantId), []) : [],
            'declared' => $logReady ? $safe(fn (): array => $this->logbook->listDeclared($tenantId), []) : [],
            'log' => $logReady ? $safe(fn (): array => $this->logbook->listRecent($tenantId, $logFilter), []) : [],
            'log_counts' => $logReady ? $safe(fn (): array => $this->logbook->countByStatus($tenantId), []) : [],
            'seconds_30' => $logReady ? $safe(fn (): int => $this->logbook->secondsSince($tenantId, 30), 0) : 0,
            'actor' => $actorId,
            'can_review' => $canReview,
            'now' => time(),
        ]) + ['schema_ready' => $ready, 'log_ready' => $logReady];
    }

    /**
     * Assemble la vue d'ensemble (pur, testable seul).
     *
     * @param array<string, mixed> $in
     * @return array<string, mixed>
     */
    public static function compose(array $in): array
    {
        $rules = $in['board']['rules'] ?? [];
        $members = $in['board']['members'] ?? [];
        $totals = $in['totals'] ?? [];
        $now = (int) ($in['now'] ?? time());
        $actor = (int) ($in['actor'] ?? 0);
        $tierOrder = array_flip(array_keys(Cat::tiers()));

        // État de chaque membre pour chaque palier (les membres sans heure sur le périmètre n'y figurent pas).
        $ev = [];
        foreach ($rules as $r) {
            foreach ($r['groups'] ?? [] as $state => $list) {
                foreach ($list as $it) {
                    $ev[(int) $r['id']][(int) $it['user_id']] = ['state' => (string) $state] + $it;
                }
            }
        }
        $labels = [];
        foreach ($in['aircraft'] ?? [] as $a) {
            $labels['family:' . $a['key']] = (string) $a['label'];
        }
        $byScope = [];
        foreach ($rules as $r) {
            $byScope[(string) $r['scope']][] = $r;
            if (str_starts_with((string) $r['scope'], 'family:') && trim((string) ($r['scope_label'] ?? '')) !== '') {
                $labels[(string) $r['scope']] ??= (string) $r['scope_label'];
            }
        }
        foreach ($byScope as $scope => $list) {
            usort($list, static fn (array $a, array $b): int => ($tierOrder[$a['tier']] ?? 99) <=> ($tierOrder[$b['tier']] ?? 99));
            $byScope[$scope] = $list;
        }
        $held = static fn (array $r, int $uid): bool => ($ev[(int) $r['id']][$uid]['state'] ?? '') === PilotQualificationEngine::HELD;
        $stateOf = static fn (array $r, int $uid): string => (string) ($ev[(int) $r['id']][$uid]['state'] ?? PilotQualificationEngine::PROGRESS);
        $short = static fn (array $r): string => (string) (Cat::tier((string) $r['tier'])['short'] ?? strtoupper((string) $r['tier']));
        $currency = static function (array $r, int $uid) use ($totals, $now): array {
            $days = Cat::tier((string) $r['tier'])['currency_days'] ?? null;
            $last = (string) ($totals[$uid][(string) $r['scope']]['last_flown_at'] ?? '');
            if ($days === null) {
                return ['s' => 'ok', 'left' => null, 'since' => null, 'permanent' => true];
            }
            if ($last === '') {
                return ['s' => 'exp', 'left' => null, 'since' => null, 'permanent' => false];
            }
            $since = max(0, (int) floor(($now - (int) strtotime($last)) / 86400));
            $left = (int) $days - $since;

            return ['s' => $left < 0 ? 'exp' : ($left <= self::SOON_DAYS ? 'soon' : 'ok'), 'left' => $left, 'since' => $since, 'permanent' => false];
        };

        // Ligne de vol : une colonne par appareil du cursus, les pilotes qualifiés sur le type et leur maintien.
        $line = [];
        $curOk = 0;
        $curTot = 0;
        foreach ($byScope as $scope => $list) {
            if (!str_starts_with($scope, 'family:')) {
                continue;
            }
            $qt = null;
            foreach ($list as $r) {
                if ($r['tier'] === 'type') {
                    $qt = $r;
                }
            }
            $qt ??= $list[0];
            $pilots = [];
            foreach (array_keys($members) as $uid) {
                if (!$held($qt, (int) $uid)) {
                    continue;
                }
                $top = $qt;
                foreach ($list as $r) {
                    if ($held($r, (int) $uid)) {
                        $top = $r;
                    }
                }
                $cur = $currency($qt, (int) $uid);
                $pilots[] = ['user_id' => (int) $uid, 'top' => $short($top), 'top_rank' => $tierOrder[$top['tier']] ?? 0, 'cur' => $cur];
                $curTot++;
                $curOk += $cur['s'] !== 'exp' ? 1 : 0;
            }
            usort($pilots, static fn (array $a, array $b): int => $b['top_rank'] <=> $a['top_rank'] ?: $a['user_id'] <=> $b['user_id']);
            $count = ['ok' => 0, 'soon' => 0, 'exp' => 0];
            foreach ($pilots as $p) {
                $count[$p['cur']['s']]++;
            }
            $line[] = ['scope' => $scope, 'label' => $labels[$scope] ?? substr($scope, 7), 'track' => (string) $qt['track'], 'rule_id' => (int) $qt['id'], 'pilots' => $pilots, 'count' => $count];
        }

        // À valider : paliers proposés (cursus modèle et paliers de la communauté) et vols déclarés au carnet.
        $queue = [];
        foreach ($rules as $r) {
            foreach ($r['groups'][PilotQualificationEngine::PENDING] ?? [] as $it) {
                $uid = (int) $it['user_id'];
                $validators = $r['validators'] ?? null;
                $queue[] = [
                    'kind' => 'rule', 'user_id' => $uid, 'code' => $short($r), 'rule' => $r, 'item' => $it,
                    'can' => $uid !== $actor && ($validators === null || in_array($actor, $validators, true)), 'mine' => $uid === $actor,
                ];
            }
        }
        foreach ($in['steps'] ?? [] as $s) {
            foreach ($s['summary']['pending'] ?? [] as $p) {
                $uid = (int) $p['member']['user_id'];
                $queue[] = ['kind' => 'step', 'user_id' => $uid, 'code' => (string) ($s['definition_short_name'] ?? '') ?: 'PAL', 'step' => $s, 'award_id' => (int) $p['award_id'], 'can' => $uid !== $actor, 'mine' => $uid === $actor, 'member' => $p['member']];
            }
        }
        foreach ($in['declared'] ?? [] as $f) {
            $uid = (int) $f['user_id'];
            $queue[] = ['kind' => 'flight', 'user_id' => $uid, 'flight' => $f, 'can' => $uid !== $actor && !empty($in['can_review'][(string) $f['vehicle_kind']]), 'mine' => $uid === $actor];
        }

        // Cursus : par filière, le tronc commun puis un rang par appareil, puis les paliers de la communauté.
        $tracks = [];
        foreach ($byScope as $scope => $list) {
            $track = (string) $list[0]['track'];
            $isFamily = str_starts_with($scope, 'family:');
            $hours = 0;
            $pilotCount = 0;
            foreach ($totals as $t) {
                $sec = (int) ($t[$isFamily ? $scope : 'kind:' . $track]['seconds'] ?? 0);
                $hours += $sec;
                $pilotCount += $sec > 0 ? 1 : 0;
            }
            $nodes = [];
            foreach ($list as $r) {
                $progress = [];
                foreach ($r['groups'][PilotQualificationEngine::PROGRESS] ?? [] as $it) {
                    $progress[] = (int) $it['user_id'];
                }
                $nodes[] = [
                    'rule' => $r,
                    'code' => $short($r),
                    'held' => count($r['groups'][PilotQualificationEngine::HELD] ?? []),
                    'pending' => count($r['groups'][PilotQualificationEngine::PENDING] ?? []),
                    'eligible' => count($r['groups'][PilotQualificationEngine::ELIGIBLE] ?? []),
                    'progress' => $progress,
                ];
            }
            $tracks[$track][$isFamily ? $scope : ' common'] = [
                'kind' => $isFamily ? 'family' : 'common',
                'label' => $isFamily ? ($labels[$scope] ?? substr($scope, 7)) : Cat::trackLabel($track),
                'hours' => round($hours / 3600, 2),
                'pilots' => $pilotCount,
                'nodes' => $nodes,
            ];
        }
        foreach ($tracks as $t => $rows) {
            ksort($rows);
            $tracks[$t] = $rows;
        }
        $customByTrack = [];
        foreach ($in['steps'] ?? [] as $s) {
            if (empty($s['archived'])) {
                $customByTrack[(string) $s['track']][] = $s;
            }
        }

        // Pilotes : tous les membres qui ont des heures, du plus expérimenté au moins expérimenté.
        $pilots = [];
        foreach ($totals as $uid => $t) {
            $uid = (int) $uid;
            $heli = (int) ($t['kind:heli']['seconds'] ?? 0);
            $plane = (int) ($t['kind:plane']['seconds'] ?? 0);
            if ($heli + $plane < 1 || !isset($members[$uid])) {
                continue;
            }
            $flownTracks = array_keys(array_filter([Cat::TRACK_HELI => $heli, Cat::TRACK_PLANE => $plane], static fn (int $s): bool => $s > 0));
            $common = [];
            foreach ($flownTracks as $tk) {
                foreach ($byScope['kind:' . $tk] ?? [] as $r) {
                    $common[] = ['code' => $short($r), 'state' => $stateOf($r, $uid), 'track' => $tk];
                }
            }
            $rows = [];
            foreach ($t as $scope => $v) {
                if (!str_starts_with((string) $scope, 'family:') || (int) $v['seconds'] < 60) {
                    continue;
                }
                $list = $byScope[$scope] ?? [];
                $ladder = [];
                $next = null;
                foreach ($list as $r) {
                    $st = $stateOf($r, $uid);
                    $ladder[] = ['code' => $short($r), 'state' => $st];
                    if ($next === null && $st !== PilotQualificationEngine::HELD) {
                        $e = $ev[(int) $r['id']][$uid] ?? null;
                        $next = [
                            'code' => $short($r),
                            'state' => $st,
                            'pct' => $st === PilotQualificationEngine::PENDING || $st === PilotQualificationEngine::ELIGIBLE ? 100 : (int) ($e['pct'] ?? 0),
                            'hours' => (float) ($e['scope_hours'] ?? round((int) $v['seconds'] / 3600, 2)),
                            'target' => (float) ($e['target_scope'] ?? $r['min_scope_hours']),
                            'prereq_ok' => (bool) ($e['prereq_ok'] ?? true),
                        ];
                    }
                }
                $qt = null;
                foreach ($list as $r) {
                    if ($r['tier'] === 'type' && $held($r, $uid)) {
                        $qt = $r;
                    }
                }
                $rows[] = [
                    'scope' => (string) $scope,
                    'label' => $labels[$scope] ?? substr((string) $scope, 7),
                    'hours' => round((int) $v['seconds'] / 3600, 2),
                    'installed' => $list !== [],
                    'ladder' => $ladder,
                    'next' => $next,
                    'done' => $list !== [] && $next === null,
                    'cur' => $qt !== null ? $currency($qt, $uid) : null,
                ];
            }
            usort($rows, static fn (array $a, array $b): int => $b['hours'] <=> $a['hours']);
            $pilots[] = [
                'user_id' => $uid,
                'tracks' => $flownTracks,
                'total' => round(($heli + $plane) / 3600, 2),
                'night' => round(((int) ($t['cond:night:heli']['seconds'] ?? 0) + (int) ($t['cond:night:plane']['seconds'] ?? 0)) / 3600, 2),
                'ifr' => round(((int) ($t['cond:ifr:heli']['seconds'] ?? 0) + (int) ($t['cond:ifr:plane']['seconds'] ?? 0)) / 3600, 2),
                'common' => $common,
                'rows' => array_slice($rows, 0, 6),
            ];
        }
        usort($pilots, static fn (array $a, array $b): int => $b['total'] <=> $a['total']);

        // Flotte : appareils du cursus et appareils réellement pilotés, par filière.
        $installed = [];
        foreach (array_keys($byScope) as $scope) {
            if (str_starts_with($scope, 'family:')) {
                $installed[substr($scope, 7)] = true;
            }
        }
        $fleet = [Cat::TRACK_HELI => [], Cat::TRACK_PLANE => []];
        foreach ($in['aircraft'] ?? [] as $a) {
            if (!empty($a['archived'])) {
                continue;
            }
            $tk = $a['track'] === PilotFleet::TRACK_OTHER ? Cat::TRACK_HELI : (string) $a['track'];
            if (!isset($fleet[$tk]) || (!$a['flown'] && !isset($installed[$a['key']]))) {
                continue;
            }
            $fleet[$tk][] = $a + ['installed' => isset($installed[$a['key']])];
        }

        $queueCount = count($queue);

        return [
            'members' => $members,
            'hud' => [
                'pilots' => count($pilots),
                'current_ok' => $curOk,
                'current_total' => $curTot,
                'hours_30' => round((int) ($in['seconds_30'] ?? 0) / 3600, 1),
                'queue' => $queueCount,
            ],
            'line' => $line,
            'queue' => $queue,
            'tracks' => $tracks,
            'custom' => $customByTrack,
            'pilots' => $pilots,
            'fleet' => $fleet,
            'log' => $in['log'] ?? [],
            'log_counts' => $in['log_counts'] ?? [],
            'can_review' => $in['can_review'] ?? [],
        ];
    }
}
