<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseAnalysisFindingRepository;
use App\Repositories\SseEntityIndexRepository;
use App\Repositories\SseIntelEventRepository;
use App\Repositories\SseSuggestionQueueRepository;
use App\Support\SseAnalysisCatalog;

/**
 * LOT 6 — Analyse : Pattern of Life, heatmap, contradictions, rapprochements, anomalies.
 * Ne décide jamais ; produit des constats à arbitrage humain avec explication.
 */
final class SseAnalysisService
{
    private const SPATIAL_GAP_M = 500.0;
    private const TIME_WINDOW_SEC = 7200;

    public function __construct(
        private ?SseAnalysisFindingRepository $findings = null,
        private ?SseIntelEventRepository $events = null,
        private ?SseSuggestionQueueRepository $queue = null,
        private ?SseEntityIndexRepository $entities = null,
        private ?SseSyncService $sync = null,
    ) {
        $this->findings ??= new SseAnalysisFindingRepository();
        $this->events ??= new SseIntelEventRepository();
        $this->queue ??= new SseSuggestionQueueRepository();
        $this->entities ??= new SseEntityIndexRepository();
        $this->sync ??= new SseSyncService();
    }

    /**
     * Tableau de bord analyse pour le workspace.
     *
     * @return array<string,mixed>
     */
    public function analysisBoard(int $tenantId, ?int $caseId = null, ?string $entityUuid = null, int $windowDays = 14): array
    {
        $lockOk = $this->sync->tryLock('analysis_board', $tenantId, 'workspace', 90);
        try {
            $pol = $this->patternOfLife($tenantId, $caseId, $entityUuid, $windowDays, true);
            $heatmap = $this->heatmap($tenantId, $caseId, $entityUuid, $windowDays);
            $this->refreshFindings($tenantId, $caseId, $entityUuid, $pol, $windowDays);

            $findings = $this->findings->listFindings($tenantId, [
                'case_id' => $caseId,
                'entity_uuid' => $entityUuid,
                'limit' => 80,
            ]);

            $contradictions = array_values(array_filter(
                $findings,
                static fn (array $f): bool => ($f['finding_type'] ?? '') === 'contradiction'
            ));
            $anomalies = array_values(array_filter(
                $findings,
                static fn (array $f): bool => in_array(($f['finding_type'] ?? ''), ['anomaly', 'pol_gap'], true)
            ));
            $rapprochements = $this->rapprochements($tenantId, $caseId);

            return [
                'pattern_of_life' => $pol,
                'heatmap' => $heatmap,
                'contradictions' => $contradictions,
                'rapprochements' => $rapprochements,
                'anomalies' => $anomalies,
                'findings' => $findings,
                'counts' => [
                    'contradictions' => count($contradictions),
                    'rapprochements' => count($rapprochements),
                    'anomalies' => count($anomalies),
                    'findings_open' => count(array_filter(
                        $findings,
                        static fn (array $f): bool => ($f['status'] ?? '') === 'ouvert'
                    )),
                    'heatmap_cells' => count($heatmap['cells'] ?? []),
                    'events_sampled' => (int) ($pol['events_count'] ?? 0),
                ],
                'catalog' => [
                    'finding_types' => SseAnalysisCatalog::FINDING_TYPES,
                    'severities' => SseAnalysisCatalog::SEVERITIES,
                    'statuses' => SseAnalysisCatalog::STATUSES,
                    'confidence' => SseAnalysisCatalog::CONFIDENCE,
                ],
                'window_days' => $windowDays,
            ];
        } finally {
            if ($lockOk) {
                $this->sync->releaseLock('analysis_board', $tenantId);
            }
        }
    }

    /**
     * @return array<string,mixed>
     */
    public function patternOfLife(
        int $tenantId,
        ?int $caseId = null,
        ?string $entityUuid = null,
        int $windowDays = 14,
        bool $persist = false
    ): array {
        $windowDays = max(1, min(90, $windowDays));
        $events = $this->loadEvents($tenantId, $caseId, $entityUuid, $windowDays, 200);

        $byHour = array_fill(0, 24, 0);
        $byWeekday = array_fill(1, 7, 0);
        $cells = [];
        $entityBuckets = [];

        foreach ($events as $ev) {
            $ts = strtotime((string) ($ev['event_time'] ?? '')) ?: 0;
            if ($ts > 0) {
                $h = (int) gmdate('G', $ts);
                $d = (int) gmdate('N', $ts);
                $byHour[$h]++;
                $byWeekday[$d]++;
            }
            $xy = $this->eventXY($ev);
            if ($xy !== null) {
                $key = $this->cellKey($xy[0], $xy[1], 100.0);
                if (!isset($cells[$key])) {
                    $cells[$key] = ['x' => $xy[0], 'y' => $xy[1], 'count' => 0];
                }
                $cells[$key]['count']++;
            }
            $eu = trim((string) ($ev['entity_uuid'] ?? ''));
            if ($eu !== '') {
                $entityBuckets[$eu] = ($entityBuckets[$eu] ?? 0) + 1;
            }
        }

        $peakHours = $this->topIndexed($byHour, 3);
        $peakDays = $this->topIndexed($byWeekday, 3);
        uasort($cells, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);
        $topLocations = array_values(array_slice($cells, 0, 8));

        $profile = [
            'window_days' => $windowDays,
            'events_count' => count($events),
            'by_hour' => $byHour,
            'by_weekday' => $byWeekday,
            'peak_hours' => $peakHours,
            'peak_weekdays' => array_map(static function (array $p): array {
                $labels = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'];

                return [
                    'weekday' => $p['index'],
                    'label' => $labels[$p['index']] ?? (string) $p['index'],
                    'count' => $p['count'],
                ];
            }, $peakDays),
            'top_locations' => $topLocations,
            'entities_active' => count($entityBuckets),
            'summary' => $this->polSummary(count($events), $peakHours, $topLocations),
        ];

        if ($persist && count($events) > 0) {
            $this->findings->savePolSnapshot($tenantId, $caseId, $entityUuid, $windowDays, $profile);
        }

        return $profile;
    }

    /**
     * @return array{cells:list<array<string,mixed>>,max:int,cell_size:float,summary:string}
     */
    public function heatmap(
        int $tenantId,
        ?int $caseId = null,
        ?string $entityUuid = null,
        int $windowDays = 14,
        float $cellSize = 100.0
    ): array {
        $events = $this->loadEvents($tenantId, $caseId, $entityUuid, $windowDays, 200);
        $cells = [];
        $max = 0;
        foreach ($events as $ev) {
            $xy = $this->eventXY($ev);
            if ($xy === null) {
                continue;
            }
            $gx = floor($xy[0] / $cellSize) * $cellSize;
            $gy = floor($xy[1] / $cellSize) * $cellSize;
            $key = $gx . ':' . $gy;
            if (!isset($cells[$key])) {
                $cells[$key] = ['x' => $gx + $cellSize / 2, 'y' => $gy + $cellSize / 2, 'count' => 0];
            }
            $cells[$key]['count']++;
            $max = max($max, $cells[$key]['count']);
        }

        $out = [];
        foreach ($cells as $c) {
            $out[] = [
                'x' => $c['x'],
                'y' => $c['y'],
                'count' => $c['count'],
                'intensity' => $max > 0 ? round($c['count'] / $max, 3) : 0.0,
            ];
        }
        usort($out, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return [
            'cells' => array_slice($out, 0, 120),
            'max' => $max,
            'cell_size' => $cellSize,
            'summary' => count($out) === 0
                ? 'Aucune position exploitable pour une carte de densité sur la période.'
                : sprintf('%d zones actives — pic de densité %d observation(s).', count($out), $max),
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function rapprochements(int $tenantId, ?int $caseId = null): array
    {
        $filters = ['status' => 'pending', 'limit' => 40];
        if ($caseId !== null && $caseId > 0) {
            $filters['case_id'] = $caseId;
        }
        $suggestions = $this->queue->listSuggestions($tenantId, $filters);
        $out = [];
        foreach ($suggestions as $s) {
            $kind = (string) ($s['kind'] ?? '');
            if ($kind === 'contradiction') {
                continue;
            }
            $out[] = [
                'id' => (int) ($s['id'] ?? 0),
                'source' => 'suggestion',
                'kind' => $kind,
                'kind_label' => (string) ($s['kind_label'] ?? 'Rapprochement'),
                'title' => (string) ($s['title'] ?? 'Rapprochement proposé'),
                'explanation' => (string) ($s['reason'] ?? 'Proposition automatique à confirmer ou écarter.'),
                'confidence_label' => (string) ($s['confidence_label'] ?? $s['confidence'] ?? ''),
                'score' => (int) ($s['score'] ?? 0),
                'case_id' => isset($s['case_id']) ? (int) $s['case_id'] : null,
                'status' => 'ouvert',
                'status_label' => 'À examiner',
                'href' => url('atak/sse/rapprochements'),
            ];
        }

        $fromFindings = $this->findings->listFindings($tenantId, [
            'case_id' => $caseId,
            'finding_type' => 'rapprochement',
            'limit' => 20,
        ]);
        foreach ($fromFindings as $f) {
            $out[] = [
                'id' => (int) ($f['id'] ?? 0),
                'source' => 'finding',
                'kind' => 'rapprochement',
                'kind_label' => 'Rapprochement',
                'title' => (string) ($f['title'] ?? ''),
                'explanation' => (string) ($f['explanation'] ?? ''),
                'confidence_label' => (string) ($f['confidence_label_fr'] ?? ''),
                'score' => 0,
                'case_id' => $f['case_id'] ?? null,
                'status' => (string) ($f['status'] ?? ''),
                'status_label' => (string) ($f['status_label'] ?? ''),
                'href' => url('atak/sse/workspace') . '#analyse',
            ];
        }

        return array_slice($out, 0, 40);
    }

    /**
     * @return array{ok:bool,error?:string}
     */
    public function decideFinding(int $tenantId, int $id, string $status, string $author = ''): array
    {
        if (!isset(SseAnalysisCatalog::STATUSES[$status]) || $status === 'ouvert') {
            return ['ok' => false, 'error' => 'Décision non reconnue.'];
        }
        $row = $this->findings->findById($tenantId, $id);
        if ($row === null) {
            return ['ok' => false, 'error' => 'Constat introuvable.'];
        }
        if (($row['status'] ?? '') !== 'ouvert') {
            return ['ok' => false, 'error' => 'Ce constat a déjà été tranché.'];
        }
        if (!$this->findings->decide($tenantId, $id, $status, $author)) {
            return ['ok' => false, 'error' => 'Enregistrement impossible.'];
        }

        return ['ok' => true];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function openContradictionsForInbox(int $tenantId): array
    {
        return $this->findings->listFindings($tenantId, [
            'finding_type' => 'contradiction',
            'status' => 'ouvert',
            'limit' => 8,
        ]);
    }

    /**
     * @param array<string,mixed> $pol
     */
    private function refreshFindings(
        int $tenantId,
        ?int $caseId,
        ?string $entityUuid,
        array $pol,
        int $windowDays
    ): void {
        $events = $this->loadEvents($tenantId, $caseId, $entityUuid, $windowDays, 200);
        $this->detectSpatialContradictions($tenantId, $caseId, $events);
        $this->importQueueContradictions($tenantId, $caseId);
        $this->detectAnomalies($tenantId, $caseId, $events, $pol);
    }

    /**
     * @param list<array<string,mixed>> $events
     */
    private function detectSpatialContradictions(int $tenantId, ?int $caseId, array $events): void
    {
        $byEntity = [];
        foreach ($events as $ev) {
            $eu = trim((string) ($ev['entity_uuid'] ?? ''));
            if ($eu === '') {
                continue;
            }
            $xy = $this->eventXY($ev);
            if ($xy === null) {
                continue;
            }
            $byEntity[$eu][] = [
                'id' => (int) ($ev['id'] ?? 0),
                'time' => strtotime((string) ($ev['event_time'] ?? '')) ?: 0,
                'x' => $xy[0],
                'y' => $xy[1],
                'summary' => (string) ($ev['summary'] ?? ''),
                'case_id' => isset($ev['case_id']) ? (int) $ev['case_id'] : $caseId,
            ];
        }

        foreach ($byEntity as $eu => $rows) {
            usort($rows, static fn (array $a, array $b): int => $a['time'] <=> $b['time']);
            $n = count($rows);
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $dt = $rows[$j]['time'] - $rows[$i]['time'];
                    if ($dt > self::TIME_WINDOW_SEC) {
                        break;
                    }
                    $dist = hypot($rows[$j]['x'] - $rows[$i]['x'], $rows[$j]['y'] - $rows[$i]['y']);
                    if ($dist < self::SPATIAL_GAP_M) {
                        continue;
                    }
                    $fp = 'geo:' . $eu . ':' . min($rows[$i]['id'], $rows[$j]['id']) . ':' . max($rows[$i]['id'], $rows[$j]['id']);
                    if ($this->findings->findOpenByEvidenceFingerprint($tenantId, $fp) !== null) {
                        continue;
                    }
                    $label = $this->entityLabel($tenantId, $eu);
                    $this->findings->createFinding($tenantId, [
                        'case_id' => $rows[$i]['case_id'] ?: $caseId,
                        'entity_uuid' => $eu,
                        'finding_type' => 'contradiction',
                        'severity' => $dist > 2000 ? 'haute' : 'normale',
                        'confidence_label' => 'OBSERVE',
                        'title' => 'Présence impossible au même moment',
                        'explanation' => sprintf(
                            '%s apparaît à environ %.0f m d’écart en %d minutes. '
                            . 'Soit erreur de position / horodatage, soit confusion d’identité — à arbitrer.',
                            $label,
                            $dist,
                            (int) max(1, round($dt / 60))
                        ),
                        'evidence' => [
                            'fingerprint' => $fp,
                            'event_ids' => [$rows[$i]['id'], $rows[$j]['id']],
                            'distance_m' => round($dist, 1),
                            'delta_minutes' => (int) round($dt / 60),
                        ],
                    ]);
                }
            }
        }
    }

    private function importQueueContradictions(int $tenantId, ?int $caseId): void
    {
        $filters = ['status' => 'pending', 'limit' => 30];
        if ($caseId !== null && $caseId > 0) {
            $filters['case_id'] = $caseId;
        }
        foreach ($this->queue->listSuggestions($tenantId, $filters) as $s) {
            if (($s['kind'] ?? '') !== 'contradiction') {
                continue;
            }
            $fp = 'sug:' . (int) ($s['id'] ?? 0);
            if ($this->findings->findOpenByEvidenceFingerprint($tenantId, $fp) !== null) {
                continue;
            }
            $this->findings->createFinding($tenantId, [
                'case_id' => isset($s['case_id']) ? (int) $s['case_id'] : $caseId,
                'finding_type' => 'contradiction',
                'severity' => 'haute',
                'confidence_label' => 'PROBABLE',
                'title' => (string) ($s['title'] ?? 'Contradiction signalée'),
                'explanation' => (string) ($s['reason'] ?? 'Deux éléments ne peuvent être simultanément retenus.'),
                'evidence' => [
                    'fingerprint' => $fp,
                    'suggestion_id' => (int) ($s['id'] ?? 0),
                ],
            ]);
        }
    }

    /**
     * @param list<array<string,mixed>> $events
     * @param array<string,mixed> $pol
     */
    private function detectAnomalies(int $tenantId, ?int $caseId, array $events, array $pol): void
    {
        if (count($events) < 4) {
            return;
        }

        $peakHours = array_column($pol['peak_hours'] ?? [], 'index');
        $topLocs = $pol['top_locations'] ?? [];
        $bySource = [];

        foreach ($events as $ev) {
            $ts = strtotime((string) ($ev['event_time'] ?? '')) ?: 0;
            $hour = $ts > 0 ? (int) gmdate('G', $ts) : -1;
            $src = (string) ($ev['source_system'] ?? 'inconnu');
            $bySource[$src] = ($bySource[$src] ?? 0) + 1;

            if ($hour >= 0 && $peakHours !== [] && count($events) >= 8 && !in_array($hour, $peakHours, true)) {
                $offPeak = true;
                foreach ($peakHours as $ph) {
                    if (abs($hour - (int) $ph) <= 2) {
                        $offPeak = false;
                        break;
                    }
                }
                if ($offPeak) {
                    $eu = trim((string) ($ev['entity_uuid'] ?? '')) ?: null;
                    $fp = 'polh:' . (int) ($ev['id'] ?? 0);
                    if ($this->findings->findOpenByEvidenceFingerprint($tenantId, $fp) === null) {
                        $this->findings->createFinding($tenantId, [
                            'case_id' => isset($ev['case_id']) ? (int) $ev['case_id'] : $caseId,
                            'entity_uuid' => $eu,
                            'finding_type' => 'pol_gap',
                            'severity' => 'basse',
                            'confidence_label' => 'ESTIME',
                            'title' => 'Activité hors rythme habituel',
                            'explanation' => sprintf(
                                'Observation vers %02dh00 UTC, hors des plages les plus fréquentes (%s). '
                                . 'Peut indiquer un changement de comportement, une opération ponctuelle, ou un biais de collecte.',
                                $hour,
                                implode(', ', array_map(static fn ($h) => sprintf('%02dh', (int) $h), $peakHours))
                            ),
                            'evidence' => [
                                'fingerprint' => $fp,
                                'event_id' => (int) ($ev['id'] ?? 0),
                                'hour_utc' => $hour,
                            ],
                        ]);
                    }
                }
            }

            $xy = $this->eventXY($ev);
            if ($xy !== null && $topLocs !== [] && count($events) >= 6) {
                $minDist = null;
                foreach ($topLocs as $loc) {
                    if (!is_array($loc)) {
                        continue;
                    }
                    $d = hypot($xy[0] - (float) ($loc['x'] ?? 0), $xy[1] - (float) ($loc['y'] ?? 0));
                    $minDist = $minDist === null ? $d : min($minDist, $d);
                }
                if ($minDist !== null && $minDist > 1500) {
                    $fp = 'poll:' . (int) ($ev['id'] ?? 0);
                    if ($this->findings->findOpenByEvidenceFingerprint($tenantId, $fp) === null) {
                        $this->findings->createFinding($tenantId, [
                            'case_id' => isset($ev['case_id']) ? (int) $ev['case_id'] : $caseId,
                            'entity_uuid' => trim((string) ($ev['entity_uuid'] ?? '')) ?: null,
                            'finding_type' => 'anomaly',
                            'severity' => 'normale',
                            'confidence_label' => 'PROBABLE',
                            'title' => 'Zone inhabituelle par rapport au rythme d’activité',
                            'explanation' => sprintf(
                                'Position à environ %.0f m des zones habituelles. '
                                . 'À vérifier : déplacement réel, erreur de coordonnées, ou nouvelle zone d’intérêt.',
                                $minDist
                            ),
                            'evidence' => [
                                'fingerprint' => $fp,
                                'event_id' => (int) ($ev['id'] ?? 0),
                                'distance_from_usual_m' => round($minDist, 1),
                            ],
                        ]);
                    }
                }
            }
        }

        $total = count($events);
        foreach ($bySource as $src => $n) {
            if ($total >= 6 && $n / $total >= 0.85) {
                $fp = 'src:' . $src . ':' . ($caseId ?? 0);
                if ($this->findings->findOpenByEvidenceFingerprint($tenantId, $fp) === null) {
                    $this->findings->createFinding($tenantId, [
                        'case_id' => $caseId,
                        'finding_type' => 'anomaly',
                        'severity' => 'basse',
                        'confidence_label' => 'ESTIME',
                        'title' => 'Concentration sur une seule origine',
                        'explanation' => sprintf(
                            'Environ %d %% des événements récents proviennent de la même origine. '
                            . 'Le tableau peut être biaisé ; croiser avec d’autres canaux si possible.',
                            (int) round(100 * $n / $total)
                        ),
                        'evidence' => [
                            'fingerprint' => $fp,
                            'source_system' => $src,
                            'share' => round($n / $total, 3),
                        ],
                    ]);
                }
            }
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function loadEvents(
        int $tenantId,
        ?int $caseId,
        ?string $entityUuid,
        int $windowDays,
        int $limit
    ): array {
        $filters = [
            'limit' => $limit,
            'since' => gmdate('Y-m-d H:i:s', time() - $windowDays * 86400),
        ];
        if ($caseId !== null && $caseId > 0) {
            $filters['case_id'] = $caseId;
        }
        if ($entityUuid !== null && $entityUuid !== '') {
            $filters['entity_uuid'] = $entityUuid;
        }

        try {
            return $this->events->listForTenant($tenantId, $filters);
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array{0:float,1:float}|null */
    private function eventXY(array $ev): ?array
    {
        if (isset($ev['pos_x'], $ev['pos_y']) && $ev['pos_x'] !== null && $ev['pos_y'] !== null) {
            return [(float) $ev['pos_x'], (float) $ev['pos_y']];
        }
        if (isset($ev['lng'], $ev['lat']) && $ev['lng'] !== null && $ev['lat'] !== null) {
            // Approximation locale en mètres pour lat/lng (échelle grossière)
            return [(float) $ev['lng'] * 111320.0, (float) $ev['lat'] * 110540.0];
        }
        $payload = is_array($ev['payload'] ?? null) ? $ev['payload'] : [];
        if (isset($payload['pos_x'], $payload['pos_y'])) {
            return [(float) $payload['pos_x'], (float) $payload['pos_y']];
        }

        return null;
    }

    private function cellKey(float $x, float $y, float $size): string
    {
        return (floor($x / $size) * $size) . ':' . (floor($y / $size) * $size);
    }

    /**
     * @param array<int,int> $indexed
     * @return list<array{index:int,count:int}>
     */
    private function topIndexed(array $indexed, int $n): array
    {
        $pairs = [];
        foreach ($indexed as $i => $c) {
            if ($c > 0) {
                $pairs[] = ['index' => (int) $i, 'count' => (int) $c];
            }
        }
        usort($pairs, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return array_slice($pairs, 0, $n);
    }

    /**
     * @param list<array{index:int,count:int}> $peakHours
     * @param list<array<string,mixed>> $topLocations
     */
    private function polSummary(int $eventsCount, array $peakHours, array $topLocations): string
    {
        if ($eventsCount === 0) {
            return 'Pas assez d’événements pour établir un rythme d’activité.';
        }
        $hours = array_map(
            static fn (array $p): string => sprintf('%02dh', (int) $p['index']),
            $peakHours
        );
        $loc = count($topLocations);

        return sprintf(
            '%d observation(s) sur la période. Plages les plus actives : %s. %s zone(s) récurrente(s).',
            $eventsCount,
            $hours === [] ? 'non déterminées' : implode(', ', $hours),
            $loc
        );
    }

    private function entityLabel(int $tenantId, string $uuid): string
    {
        try {
            $e = $this->entities->findByUuid($tenantId, $uuid);
            if ($e !== null) {
                $label = trim((string) ($e['display_label'] ?? $e['reference_code'] ?? ''));
                if ($label !== '') {
                    return $label;
                }
            }
        } catch (\Throwable) {
        }

        return 'L’entité suivie';
    }
}
