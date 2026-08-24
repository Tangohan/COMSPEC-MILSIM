<?php

declare(strict_types=1);

namespace App\Services\MissionPlanning;

use App\Repositories\AtakDataRepository;
use App\Repositories\MissionPlanRepository;
use App\Services\Tactical\AtakUnitMotionService;
use App\Support\MissionPlanningLabels;
use Throwable;

/**
 * Vue opérationnelle du plan pour ATAK (carte, panneau, fiche unité, tableau de conduite).
 */
final class MissionPlanningAtakService
{
    public function __construct(
        private MissionPlanRepository $plans,
        private MissionPlanningService $planning,
        private ?AtakDataRepository $units = null,
        private ?AtakUnitMotionService $motion = null,
    ) {
        $this->units ??= new AtakDataRepository();
    }

    private function motion(): AtakUnitMotionService
    {
        return $this->motion ??= new AtakUnitMotionService();
    }

    /**
     * @return array<string,mixed>
     */
    public function snapshot(int $tenantId, int $mapId): array
    {
        $empty = [
            'ok' => true,
            'plan' => null,
            'overlay' => ['graphics' => [], 'routes' => []],
            'task_org' => [],
            'roster' => [],
            'timeline' => [],
            'next_events' => [],
            'unit_status' => [],
            'documents' => [],
            'slots' => [],
        ];
        try {
            if (!$this->plans->tablesReady()) {
                return $empty;
            }
            $plan = $this->plans->findActiveForAtak($tenantId, $mapId);
            if ($plan === null) {
                return $empty;
            }
            $planId = (int) ($plan['id'] ?? 0);
            if ($planId < 1) {
                return $empty;
            }
            $this->planning->seedControlMeasures($planId);
            $this->planning->seedPlannedTimeline($planId);

            $board = $this->planning->board($tenantId, $planId);
            if ($board === null) {
                return $empty;
            }
            /** @var array<string,mixed> $plan */
            $plan = $board['plan'];
            $roster = is_array($board['roster'] ?? null) ? $board['roster'] : [];
            $liveIndex = $this->liveIndex($tenantId, $mapId);
            $slots = $this->slotCards($plan, $roster, $liveIndex);
            $graphics = $this->serializeGraphics($this->plans->graphicsForPlan($planId));
            $timeline = $this->serializeTimeline($plan, $this->plans->timelineForPlan($planId));

            return [
                'ok' => true,
                'plan' => $this->planHead($plan, $board, $liveIndex, $roster),
                'overlay' => [
                    'graphics' => $graphics,
                    'routes' => $this->routes($graphics, $slots),
                ],
                'task_org' => $this->taskOrg($board['tree'] ?? [], $slots),
                'roster' => $this->tacticalRoster($roster, $slots),
                'timeline' => $timeline,
                'next_events' => $this->nextEvents($plan, $timeline),
                'unit_status' => $this->elementStatus($roster, $slots),
                'documents' => $this->documents($plan),
                'slots' => $slots,
            ];
        } catch (Throwable) {
            return $empty;
        }
    }

    /**
     * @param array<string,mixed> $geometry
     * @return array<string,mixed>|null
     */
    public function placeGraphic(int $tenantId, int $mapId, int $graphicId, array $geometry, ?int $actorId): ?array
    {
        $plan = $this->plans->findActiveForAtak($tenantId, $mapId);
        if ($plan === null) {
            return null;
        }
        $planId = (int) ($plan['id'] ?? 0);
        $row = $this->plans->findGraphicForPlan($planId, $graphicId);
        if ($row === null) {
            return null;
        }
        $planStatus = (string) ($plan['status'] ?? '');
        $hadCoords = $row['world_x'] !== null || $row['world_y'] !== null || trim((string) ($row['path_json'] ?? '')) !== '';
        $state = (string) ($row['draw_state'] ?? 'planned');
        if ($planStatus === 'live') {
            $state = $hadCoords ? 'modified' : 'current';
        }
        $patch = ['draw_state' => $state];
        $geomType = (string) ($row['geom_type'] ?? 'point');
        if ($geomType === 'line' && isset($geometry['path']) && is_array($geometry['path'])) {
            $pts = [];
            foreach ($geometry['path'] as $pt) {
                if (!is_array($pt)) {
                    continue;
                }
                $x = isset($pt['x']) ? (float) $pt['x'] : null;
                $y = isset($pt['y']) ? (float) $pt['y'] : null;
                if ($x === null || $y === null) {
                    continue;
                }
                $pts[] = ['x' => $x, 'y' => $y];
            }
            if (count($pts) >= 2) {
                $patch['path_json'] = json_encode($pts, JSON_UNESCAPED_UNICODE) ?: '[]';
                $patch['world_x'] = $pts[0]['x'];
                $patch['world_y'] = $pts[0]['y'];
            }
        } else {
            if (!isset($geometry['x'], $geometry['y'])) {
                return null;
            }
            $patch['world_x'] = (float) $geometry['x'];
            $patch['world_y'] = (float) $geometry['y'];
        }
        $this->plans->updateGraphic($planId, $graphicId, $patch);
        $code = (string) ($row['code'] ?? '');
        $this->plans->addLog($planId, $code . ' — position mise à jour sur la carte.', $actorId);

        return $this->snapshot($tenantId, $mapId);
    }

    public function setGraphicState(int $tenantId, int $mapId, int $graphicId, string $state, ?int $actorId): ?array
    {
        $allowed = ['planned', 'current', 'completed', 'modified'];
        if (!in_array($state, $allowed, true)) {
            return null;
        }
        $plan = $this->plans->findActiveForAtak($tenantId, $mapId);
        if ($plan === null) {
            return null;
        }
        $planId = (int) ($plan['id'] ?? 0);
        $row = $this->plans->findGraphicForPlan($planId, $graphicId);
        if ($row === null) {
            return null;
        }
        $this->plans->updateGraphic($planId, $graphicId, ['draw_state' => $state]);
        $this->plans->addLog(
            $planId,
            (string) ($row['code'] ?? '') . ' — ' . MissionPlanningLabels::drawState($state) . '.',
            $actorId
        );

        return $this->snapshot($tenantId, $mapId);
    }

    public function setPhase(int $tenantId, int $mapId, string $phase, ?int $actorId): ?array
    {
        $plan = $this->plans->findActiveForAtak($tenantId, $mapId);
        if ($plan === null) {
            return null;
        }
        $planId = (int) ($plan['id'] ?? 0);
        $this->plans->setPhase($tenantId, $planId, $phase);
        $this->plans->addLog($planId, 'Phase : ' . MissionPlanningLabels::phase($phase) . '.', $actorId);

        return $this->snapshot($tenantId, $mapId);
    }

    public function addTimelineEvent(int $tenantId, int $mapId, string $label, string $source = 'c2', ?int $actorId = null): ?array
    {
        $plan = $this->plans->findActiveForAtak($tenantId, $mapId);
        if ($plan === null) {
            return null;
        }
        $planId = (int) ($plan['id'] ?? 0);
        $label = trim($label);
        if ($label === '') {
            return null;
        }
        $src = in_array($source, ['planned', 'arma', 'c2'], true) ? $source : 'c2';
        $this->plans->insertTimeline($planId, $src, 'C2', $label, null, date('Y-m-d H:i:s'));
        $this->plans->addLog($planId, 'Chronologie : ' . $label, $actorId);

        return $this->snapshot($tenantId, $mapId);
    }

    public function recordArmaEvent(int $tenantId, string $label, string $eventCode = 'ARMA'): void
    {
        try {
            $plan = $this->plans->findLiveForTenant($tenantId);
            if ($plan === null) {
                return;
            }
            $planId = (int) ($plan['id'] ?? 0);
            if ($planId < 1 || !$this->plans->graphicsReady()) {
                return;
            }
            $this->plans->insertTimeline($planId, 'arma', $eventCode, $label, null, date('Y-m-d H:i:s'));
        } catch (Throwable) {
        }
    }

    /**
     * @param array<string,mixed> $plan
     * @param array<string,mixed> $board
     * @param array<string,array<string,mixed>> $liveIndex
     * @param list<array<string,mixed>> $roster
     * @return array<string,mixed>
     */
    private function planHead(array $plan, array $board, array $liveIndex, array $roster): array
    {
        $hHour = (string) ($plan['h_hour_at'] ?? '');
        $clock = null;
        if ($hHour !== '') {
            $ts = strtotime($hHour);
            if ($ts !== false) {
                $clock = time() - $ts;
            }
        }
        $liveCount = 0;
        $offlineCount = 0;
        foreach ($liveIndex as $u) {
            $st = (string) ($u['status'] ?? '');
            if ($st === 'linked') {
                $liveCount++;
            } else {
                $offlineCount++;
            }
        }
        $counts = is_array($board['counts'] ?? null) ? $board['counts'] : [];
        $phaseRaw = (string) ($plan['phase_label'] ?? '');

        return [
            'id' => (int) ($plan['id'] ?? 0),
            'mission_code' => (string) ($plan['mission_code'] ?? ''),
            'title' => (string) ($plan['title'] ?? ''),
            'operation_name' => (string) ($plan['operation_name'] ?: $plan['title'] ?? ''),
            'task_force_name' => (string) ($plan['task_force_name'] ?? ''),
            'status' => (string) ($plan['status'] ?? 'draft'),
            'status_label' => MissionPlanningLabels::status((string) ($plan['status'] ?? 'draft')),
            'phase' => $phaseRaw,
            'phase_label' => MissionPlanningLabels::phase($phaseRaw),
            'opord_version' => (string) ($plan['opord_version'] ?? '1.0'),
            'h_hour_at' => $hHour !== '' ? $hHour : null,
            'clock_seconds' => $clock,
            'mission_sentence' => (string) ($board['mission_sentence'] ?? ''),
            'present' => (int) ($counts['present'] ?? 0),
            'auth' => (int) ($counts['auth'] ?? count($roster)),
            'live' => $liveCount,
            'offline' => $offlineCount,
        ];
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private function liveIndex(int $tenantId, int $mapId): array
    {
        try {
            $rows = $this->units->getUnits($tenantId, $mapId);
            $rows = $this->motion()->attachToUnits($tenantId, $mapId, $rows);
        } catch (Throwable) {
            $rows = [];
        }
        $out = [];
        foreach ($rows as $row) {
            $cs = strtoupper(trim((string) ($row['call_sign'] ?? $row['callsign'] ?? '')));
            if ($cs === '') {
                continue;
            }
            $out[$cs] = $row;
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $plan
     * @param list<array<string,mixed>> $roster
     * @param array<string,array<string,mixed>> $liveIndex
     * @return array<string,array<string,mixed>>
     */
    private function slotCards(array $plan, array $roster, array $liveIndex): array
    {
        $phase = MissionPlanningLabels::phase((string) ($plan['phase_label'] ?? ''));
        $out = [];
        foreach ($roster as $row) {
            $cs = strtoupper(trim((string) ($row['callsign'] ?? '')));
            if ($cs === '') {
                continue;
            }
            $live = $liveIndex[$cs] ?? null;
            $nav = is_array($live) ? ($live['navigation'] ?? $live['assignment'] ?? null) : null;
            $motion = is_array($live) ? ($live['motion'] ?? null) : null;
            $armaStatus = is_array($live) ? (string) ($live['status'] ?? 'offline') : 'offline';
            $task = $this->deriveTaskStatus($armaStatus, is_array($motion) ? $motion : [], is_array($nav) ? $nav : []);
            $etaSec = is_array($nav) && isset($nav['eta']['seconds']) && is_numeric($nav['eta']['seconds'])
                ? (int) $nav['eta']['seconds']
                : null;
            $heading = null;
            if (is_array($live)) {
                $heading = $live['movement_heading'] ?? $live['heading_object'] ?? $live['heading'] ?? null;
            }
            $speed = null;
            if (is_array($live) && isset($live['speed']) && is_numeric($live['speed'])) {
                $speed = (int) round(((float) $live['speed']) * 3.6);
            } elseif (is_array($motion) && isset($motion['eta_speed']) && is_numeric($motion['eta_speed'])) {
                $speed = (int) round(((float) $motion['eta_speed']) * 3.6);
            }
            $out[$cs] = [
                'slot_id' => (int) ($row['id'] ?? 0),
                'callsign' => (string) ($row['callsign'] ?? ''),
                'function_label' => (string) ($row['function_label'] ?? ''),
                'role_code' => (string) ($row['role_code'] ?? ''),
                'element_code' => (string) ($row['element_code'] ?? ''),
                'element_label' => (string) ($row['element_label'] ?? ''),
                'element_kind' => (string) ($row['element_kind'] ?? ''),
                'vehicle_label' => (string) ($row['vehicle_label'] ?? ''),
                'radio_primary' => (string) ($row['radio_primary'] ?? ''),
                'radio_secondary' => (string) ($row['radio_secondary'] ?? ''),
                'planned_name' => $this->person($row['planned_callsign'] ?? null, $row['planned_name'] ?? null),
                'current_name' => $this->person($row['current_callsign'] ?? null, $row['current_name'] ?? null),
                'presence' => (string) ($row['presence_status'] ?? 'vacant'),
                'presence_label' => MissionPlanningLabels::presence((string) ($row['presence_status'] ?? 'vacant')),
                'mode_label' => MissionPlanningLabels::mode((string) ($row['assignment_mode'] ?? '')),
                'arma_status' => $armaStatus,
                'arma_status_label' => MissionPlanningLabels::armaLink($armaStatus),
                'pos_x' => is_array($live) ? ($live['pos_x'] ?? null) : null,
                'pos_y' => is_array($live) ? ($live['pos_y'] ?? null) : null,
                'heading' => $heading !== null && $heading !== '' ? (int) round((float) $heading) : null,
                'speed_kmh' => $speed,
                'destination' => is_array($nav) ? (string) ($nav['destination_label'] ?? '') : '',
                'eta_seconds' => $etaSec,
                'task_status' => $task,
                'task_status_label' => MissionPlanningLabels::taskStatus($task),
                'phase_label' => $phase,
                'online' => $armaStatus === 'linked',
            ];
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $motion
     * @param array<string,mixed> $nav
     */
    private function deriveTaskStatus(string $armaStatus, array $motion, array $nav): string
    {
        $dest = trim((string) ($nav['destination_label'] ?? ''));
        $arrived = !empty($nav['eta']['arrived']);
        $st = strtoupper((string) ($motion['status'] ?? ''));
        $kind = strtoupper((string) ($motion['category'] ?? ''));
        if ($armaStatus !== 'linked' && $armaStatus !== 'delayed') {
            return 'standby';
        }
        if ($arrived) {
            return 'on_objective';
        }
        if ($kind === 'HELICOPTER' || $kind === 'FIXED_WING' || $kind === 'UAV') {
            if ($st === 'STATIC' || $st === '') {
                return 'on_station';
            }
        }
        if ($dest !== '' && in_array($st, ['MOVING', 'FAST', 'MANEUVERING'], true)) {
            return 'en_route';
        }
        if ($dest !== '' && $st === 'STATIC') {
            return 'holding';
        }
        if ($st === 'STATIC' || $st === '') {
            return 'ready';
        }

        return 'en_route';
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private function serializeGraphics(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $path = [];
            $raw = (string) ($row['path_json'] ?? '');
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $pt) {
                        if (!is_array($pt)) {
                            continue;
                        }
                        $path[] = [
                            'x' => isset($pt['x']) ? (float) $pt['x'] : null,
                            'y' => isset($pt['y']) ? (float) $pt['y'] : null,
                        ];
                    }
                }
            }
            $state = (string) ($row['draw_state'] ?? 'planned');
            $out[] = [
                'id' => (int) ($row['id'] ?? 0),
                'code' => (string) ($row['code'] ?? ''),
                'label' => (string) ($row['label'] ?? ''),
                'kind' => (string) ($row['kind'] ?? 'obj'),
                'kind_label' => MissionPlanningLabels::graphicKind((string) ($row['kind'] ?? '')),
                'geom_type' => (string) ($row['geom_type'] ?? 'point'),
                'draw_state' => $state,
                'draw_state_label' => MissionPlanningLabels::drawState($state),
                'element_code' => (string) ($row['element_code'] ?? ''),
                'x' => $row['world_x'] !== null && $row['world_x'] !== '' ? (float) $row['world_x'] : null,
                'y' => $row['world_y'] !== null && $row['world_y'] !== '' ? (float) $row['world_y'] : null,
                'path' => $path,
                'placed' => ($row['world_x'] !== null && $row['world_y'] !== null) || count($path) >= 2,
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $graphics
     * @param array<string,array<string,mixed>> $slots
     * @return list<array<string,mixed>>
     */
    private function routes(array $graphics, array $slots): array
    {
        $byEl = [];
        foreach ($graphics as $g) {
            $el = strtoupper(trim((string) ($g['element_code'] ?? '')));
            if ($el === '' || empty($g['placed'])) {
                continue;
            }
            $byEl[$el][] = $g;
        }
        $out = [];
        foreach ($byEl as $el => $items) {
            $planned = [];
            foreach ($items as $g) {
                if (($g['geom_type'] ?? '') === 'line' && !empty($g['path'])) {
                    foreach ($g['path'] as $pt) {
                        if ($pt['x'] === null || $pt['y'] === null) {
                            continue;
                        }
                        $planned[] = ['x' => $pt['x'], 'y' => $pt['y'], 'code' => $g['code']];
                    }
                } elseif ($g['x'] !== null && $g['y'] !== null) {
                    $planned[] = ['x' => $g['x'], 'y' => $g['y'], 'code' => $g['code']];
                }
            }
            $actual = [];
            foreach ($slots as $slot) {
                if (strtoupper((string) ($slot['element_code'] ?? '')) !== $el) {
                    continue;
                }
                if (empty($slot['online']) || $slot['pos_x'] === null || $slot['pos_y'] === null) {
                    continue;
                }
                $actual[] = [
                    'x' => (float) $slot['pos_x'],
                    'y' => (float) $slot['pos_y'],
                    'code' => (string) ($slot['callsign'] ?? ''),
                ];
            }
            if ($planned === [] && $actual === []) {
                continue;
            }
            $out[] = [
                'element_code' => $el,
                'planned' => $planned,
                'actual' => $actual,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $tree
     * @param array<string,array<string,mixed>> $slots
     * @return list<array<string,mixed>>
     */
    private function taskOrg(mixed $tree, array $slots): array
    {
        if (!is_array($tree)) {
            return [];
        }
        $walk = function (array $nodes) use (&$walk, $slots): array {
            $out = [];
            foreach ($nodes as $node) {
                if (!is_array($node)) {
                    continue;
                }
                $el = is_array($node['element'] ?? null) ? $node['element'] : [];
                $childrenSlots = [];
                foreach ($node['slots'] ?? [] as $slot) {
                    if (!is_array($slot)) {
                        continue;
                    }
                    $cs = strtoupper(trim((string) ($slot['callsign'] ?? '')));
                    $card = $slots[$cs] ?? null;
                    $childrenSlots[] = [
                        'callsign' => (string) ($slot['callsign'] ?? ''),
                        'function_label' => (string) ($slot['function_label'] ?? ''),
                        'planned_name' => $card['planned_name'] ?? 'Vacant',
                        'current_name' => $card['current_name'] ?? 'Vacant',
                        'presence_label' => $card['presence_label'] ?? MissionPlanningLabels::presence((string) ($slot['presence_status'] ?? '')),
                        'online' => !empty($card['online']),
                        'mismatch' => (string) ($slot['presence_status'] ?? '') === 'mismatch'
                            || (string) ($slot['presence_status'] ?? '') === 'temporary',
                    ];
                }
                $out[] = [
                    'code' => (string) ($el['code'] ?? ''),
                    'label' => (string) ($el['label'] ?? ''),
                    'kind_label' => MissionPlanningLabels::elementKind((string) ($el['kind'] ?? '')),
                    'slots' => $childrenSlots,
                    'children' => $walk(is_array($node['children'] ?? null) ? $node['children'] : []),
                ];
            }

            return $out;
        };

        return $walk($tree);
    }

    /**
     * @param list<array<string,mixed>> $roster
     * @param array<string,array<string,mixed>> $slots
     * @return list<array<string,mixed>>
     */
    private function tacticalRoster(array $roster, array $slots): array
    {
        $out = [];
        foreach ($roster as $row) {
            $cs = strtoupper(trim((string) ($row['callsign'] ?? '')));
            $card = $slots[$cs] ?? [];
            $out[] = [
                'callsign' => (string) ($row['callsign'] ?? ''),
                'player' => (string) ($card['current_name'] ?? $card['planned_name'] ?? 'Vacant'),
                'role' => (string) ($row['function_label'] ?? ''),
                'online' => !empty($card['online']),
                'online_label' => !empty($card['online']) ? 'En liaison' : 'Hors liaison',
                'position' => $this->fmtPos($card['pos_x'] ?? null, $card['pos_y'] ?? null),
                'task' => (string) ($card['task_status_label'] ?? ''),
                'destination' => (string) ($card['destination'] ?? ''),
                'eta_seconds' => $card['eta_seconds'] ?? null,
                'med' => (string) ($row['role_code'] ?? '') === 'medic' || (string) ($row['role_code'] ?? '') === 'medevac' ? 'Oui' : '',
                'comms' => trim((string) ($row['radio_primary'] ?? '')),
            ];
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $plan
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private function serializeTimeline(array $plan, array $rows): array
    {
        $hHour = (string) ($plan['h_hour_at'] ?? '');
        $hTs = $hHour !== '' ? strtotime($hHour) : false;
        $out = [];
        foreach ($rows as $row) {
            $offset = isset($row['scheduled_offset_sec']) && $row['scheduled_offset_sec'] !== null
                ? (int) $row['scheduled_offset_sec']
                : null;
            $occurred = (string) ($row['occurred_at'] ?? '');
            $clockLabel = '';
            if ($occurred !== '') {
                $clockLabel = date('H:i', strtotime($occurred) ?: time());
            } elseif ($offset !== null && $hTs !== false) {
                $clockLabel = date('H:i', $hTs + $offset);
            } elseif ($offset !== null) {
                $sign = $offset < 0 ? '−' : '+';
                $abs = abs($offset);
                $clockLabel = 'H' . $sign . sprintf('%02d:%02d', intdiv($abs, 3600), intdiv($abs % 3600, 60));
            }
            $src = (string) ($row['source'] ?? 'planned');
            $out[] = [
                'id' => (int) ($row['id'] ?? 0),
                'source' => $src,
                'source_label' => MissionPlanningLabels::timelineSource($src),
                'event_code' => (string) ($row['event_code'] ?? ''),
                'label' => (string) ($row['label'] ?? ''),
                'clock' => $clockLabel,
                'occurred' => $occurred !== '',
                'scheduled_offset_sec' => $offset,
            ];
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $plan
     * @param list<array<string,mixed>> $timeline
     * @return list<array<string,mixed>>
     */
    private function nextEvents(array $plan, array $timeline): array
    {
        $hHour = (string) ($plan['h_hour_at'] ?? '');
        $hTs = $hHour !== '' ? strtotime($hHour) : false;
        $now = time();
        $upcoming = [];
        foreach ($timeline as $ev) {
            if (!empty($ev['occurred'])) {
                continue;
            }
            $offset = $ev['scheduled_offset_sec'];
            if ($offset === null) {
                continue;
            }
            $when = $hTs !== false ? $hTs + (int) $offset : $now + (int) $offset;
            if ($when + 120 < $now) {
                continue;
            }
            $upcoming[] = [
                'clock' => (string) ($ev['clock'] ?? ''),
                'label' => (string) ($ev['label'] ?? ''),
                'at' => $when,
            ];
        }
        usort($upcoming, static fn (array $a, array $b): int => $a['at'] <=> $b['at']);

        return array_slice($upcoming, 0, 4);
    }

    /**
     * @param list<array<string,mixed>> $roster
     * @param array<string,array<string,mixed>> $slots
     * @return list<array<string,mixed>>
     */
    private function elementStatus(array $roster, array $slots): array
    {
        $grouped = [];
        foreach ($roster as $row) {
            $code = (string) ($row['element_code'] ?? '');
            if ($code === '') {
                continue;
            }
            $grouped[$code]['label'] = (string) ($row['element_label'] ?? $code);
            $grouped[$code]['kind'] = (string) ($row['element_kind'] ?? '');
            $cs = strtoupper(trim((string) ($row['callsign'] ?? '')));
            if ($cs !== '' && isset($slots[$cs])) {
                $grouped[$code]['slots'][] = $slots[$cs];
            }
        }
        $out = [];
        foreach ($grouped as $code => $g) {
            $priority = [
                'on_objective' => 5,
                'en_route' => 4,
                'holding' => 3,
                'on_station' => 3,
                'ready' => 2,
                'standby' => 1,
            ];
            $best = 'standby';
            $eta = null;
            foreach ($g['slots'] ?? [] as $slot) {
                $st = (string) ($slot['task_status'] ?? 'standby');
                if (($priority[$st] ?? 0) > ($priority[$best] ?? 0)) {
                    $best = $st;
                }
                if (isset($slot['eta_seconds']) && is_int($slot['eta_seconds'])) {
                    if ($eta === null || $slot['eta_seconds'] < $eta) {
                        $eta = $slot['eta_seconds'];
                    }
                }
            }
            $out[] = [
                'code' => $code,
                'label' => (string) ($g['label'] ?? $code),
                'status' => $best,
                'status_label' => MissionPlanningLabels::taskStatus($best),
                'eta_seconds' => $eta,
            ];
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $plan
     * @return list<array<string,mixed>>
     */
    private function documents(array $plan): array
    {
        $ver = (string) ($plan['opord_version'] ?? '1.0');

        return [
            ['code' => 'opord', 'label' => 'Ordre de mission v' . $ver],
            ['code' => 'taskorg', 'label' => 'Organisation de combat'],
            ['code' => 'roster', 'label' => 'Tableau des effectifs'],
            ['code' => 'comms', 'label' => 'Plan radio'],
            ['code' => 'timeline', 'label' => 'Chronologie'],
            ['code' => 'annexes', 'label' => 'Annexes'],
        ];
    }

    private function person(mixed $callsign, mixed $name): string
    {
        $c = trim((string) $callsign);
        $n = trim((string) $name);
        if ($c === '' && $n === '') {
            return 'Vacant';
        }
        if ($c !== '' && $n !== '') {
            return $c . ' · ' . $n;
        }

        return $c !== '' ? $c : $n;
    }

    private function fmtPos(mixed $x, mixed $y): string
    {
        if ($x === null || $y === null || $x === '' || $y === '') {
            return '';
        }

        return (string) (int) round((float) $x) . ' / ' . (string) (int) round((float) $y);
    }
}
