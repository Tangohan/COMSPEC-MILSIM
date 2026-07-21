<?php

declare(strict_types=1);

namespace App\Services\Replay;

use App\Repositories\ReplayRepository;
use DateInterval;
use DateTimeImmutable;

class ReplayService
{
    public function __construct(
        private ReplayRepository $repository
    ) {
    }

    /**
     * Build timeline grouped by timestamp for GET /api/replay/mission/{missionId}.
     */
    public function getTimeline(string $missionId, ?string $from, ?string $to): array
    {
        $rows = $this->repository->getTimeline($missionId, $from, $to);
        $byTime = [];
        foreach ($rows as $r) {
            $ts = $r['logged_at'];
            if (!isset($byTime[$ts])) {
                $byTime[$ts] = ['timestamp' => $ts, 'units' => []];
            }
            $byTime[$ts]['units'][] = [
                'unitId' => $r['unit_id'],
                'callsign' => $r['callsign'],
                'x' => (float) $r['pos_x'],
                'y' => (float) $r['pos_y'],
                'z' => (float) ($r['pos_z'] ?? 0),
                'heading' => $r['heading'] !== null ? (float) $r['heading'] : null,
            ];
        }
        return [
            'missionId' => $missionId,
            'timeline' => array_values($byTime),
        ];
    }

    public function logPosition(string $missionId, string $unitId, string $callsign, float $posX, float $posY, ?float $posZ = null, ?float $heading = null, ?string $unitType = null, ?string $side = null, ?float $speed = null, ?array $state = null): void
    {
        $this->repository->insertLog(
            $missionId,
            $unitId,
            $callsign,
            $unitType,
            $side,
            $posX,
            $posY,
            $posZ,
            $heading,
            $speed,
            $state !== null ? json_encode($state) : null
        );
    }

    /**
     * Chronologie intel dédiée au replay (AAR).
     */
    public function getEvents(string $missionId, ?string $from, ?string $to): array
    {
        $rows = $this->repository->getIntelEvents($missionId, $from, $to);
        $events = [];
        foreach ($rows as $row) {
            $events[] = [
                'type' => 'intel',
                'id' => (int) $row['id'],
                'timestamp' => $row['last_seen_at'],
                'source' => $row['source_callsign'],
                'reportType' => $row['report_type'],
                'targetType' => $row['target_type'],
                'x' => (float) $row['pos_x'],
                'y' => (float) $row['pos_y'],
                'z' => $row['pos_z'] !== null ? (float) $row['pos_z'] : null,
                'confidence' => (int) ($row['confidence_score'] ?? 0),
                'status' => $row['status'] ?? 'TEMPORARY',
                'mergedCount' => (int) ($row['merged_count'] ?? 1),
            ];
        }

        return [
            'missionId' => $missionId,
            'events' => $events,
        ];
    }

    /**
     * Synthèse automatique AAR (erreurs + délais de réaction + chronologie intel).
     */
    public function buildAfterActionReview(string $missionId, ?string $from, ?string $to): array
    {
        $timelineRows = $this->repository->getTimeline($missionId, $from, $to, 12000);
        $intelRows = $this->repository->getIntelEvents($missionId, $from, $to, 4000);

        $unitTracks = [];
        $timeline = [];
        $lowFuelAlerts = 0;
        $healthAlerts = 0;

        foreach ($timelineRows as $row) {
            $unitId = (string) $row['unit_id'];
            $pt = [
                'timestamp' => (string) $row['logged_at'],
                'x' => (float) $row['pos_x'],
                'y' => (float) $row['pos_y'],
                'callsign' => (string) $row['callsign'],
            ];
            $timeline[] = $pt;
            if (!isset($unitTracks[$unitId])) {
                $unitTracks[$unitId] = [
                    'unitId' => $unitId,
                    'callsign' => (string) $row['callsign'],
                    'samples' => 0,
                    'distance' => 0.0,
                    'firstSeen' => (string) $row['logged_at'],
                    'lastSeen' => (string) $row['logged_at'],
                    'lastPoint' => null,
                ];
            }
            $unitTracks[$unitId]['samples']++;
            $unitTracks[$unitId]['lastSeen'] = (string) $row['logged_at'];

            $lp = $unitTracks[$unitId]['lastPoint'];
            if (is_array($lp)) {
                $dx = (float) $pt['x'] - (float) $lp['x'];
                $dy = (float) $pt['y'] - (float) $lp['y'];
                $unitTracks[$unitId]['distance'] += sqrt(($dx * $dx) + ($dy * $dy));
            }
            $unitTracks[$unitId]['lastPoint'] = $pt;

            $state = $row['state_json'] ?? null;
            if (is_string($state) && $state !== '') {
                $decoded = json_decode($state, true);
                if (is_array($decoded)) {
                    $fuel = $decoded['fuel'] ?? null;
                    if (is_numeric($fuel) && (float) $fuel < 0.20) {
                        $lowFuelAlerts++;
                    }
                    $health = strtolower((string) ($decoded['health'] ?? ''));
                    if (in_array($health, ['critical', 'incapacitated', 'down', 'wounded', 'unconscious', 'cardiac_arrest', 'cardiac-arrest', 'dead', 'kia'], true)) {
                        $healthAlerts++;
                    }
                }
            }
        }

        $intelTimeline = [];
        foreach ($intelRows as $row) {
            $intelTimeline[] = [
                'timestamp' => (string) $row['last_seen_at'],
                'source' => (string) ($row['source_callsign'] ?? ''),
                'reportType' => (string) ($row['report_type'] ?? ''),
                'targetType' => (string) ($row['target_type'] ?? ''),
                'x' => (float) $row['pos_x'],
                'y' => (float) $row['pos_y'],
                'confidence' => (int) ($row['confidence_score'] ?? 0),
                'status' => (string) ($row['status'] ?? 'TEMPORARY'),
            ];
        }

        usort($timeline, static fn (array $a, array $b): int => strcmp((string) $a['timestamp'], (string) $b['timestamp']));
        usort($intelTimeline, static fn (array $a, array $b): int => strcmp((string) $a['timestamp'], (string) $b['timestamp']));

        $reactionSamples = [];
        foreach ($intelTimeline as $intel) {
            $detectedAt = new DateTimeImmutable((string) $intel['timestamp']);
            $limitAt = $detectedAt->add(new DateInterval('PT15M'));
            $closestReactionDelay = null;

            foreach ($timeline as $pt) {
                $at = new DateTimeImmutable((string) $pt['timestamp']);
                if ($at < $detectedAt) {
                    continue;
                }
                if ($at > $limitAt) {
                    break;
                }
                $dx = (float) $pt['x'] - (float) $intel['x'];
                $dy = (float) $pt['y'] - (float) $intel['y'];
                $distance = sqrt(($dx * $dx) + ($dy * $dy));
                if ($distance <= 300.0) {
                    $delay = $at->getTimestamp() - $detectedAt->getTimestamp();
                    if ($closestReactionDelay === null || $delay < $closestReactionDelay) {
                        $closestReactionDelay = $delay;
                    }
                }
            }

            if ($closestReactionDelay !== null) {
                $reactionSamples[] = $closestReactionDelay;
            }
        }

        sort($reactionSamples);
        $medianReaction = null;
        if ($reactionSamples !== []) {
            $count = count($reactionSamples);
            $mid = (int) floor($count / 2);
            $medianReaction = $count % 2 === 0
                ? (int) floor(($reactionSamples[$mid - 1] + $reactionSamples[$mid]) / 2)
                : (int) $reactionSamples[$mid];
        }

        $errors = [];
        if ($lowFuelAlerts > 0) {
            $errors[] = [
                'code' => 'LOW_FUEL',
                'label' => 'Niveaux carburant critiques détectés',
                'count' => $lowFuelAlerts,
            ];
        }
        if ($healthAlerts > 0) {
            $errors[] = [
                'code' => 'HEALTH_CRITICAL',
                'label' => 'États santé critiques détectés',
                'count' => $healthAlerts,
            ];
        }
        $isolatedUnits = array_values(array_filter($unitTracks, static fn (array $track): bool => (int) $track['samples'] < 3));
        if ($isolatedUnits !== []) {
            $errors[] = [
                'code' => 'ISOLATED_UNITS',
                'label' => 'Unités potentiellement isolées (traces faibles)',
                'count' => count($isolatedUnits),
            ];
        }
        if ($medianReaction !== null && $medianReaction > 300) {
            $errors[] = [
                'code' => 'REACTION_DELAY',
                'label' => 'Délai de réaction médian au-dessus de 5 minutes',
                'count' => 1,
            ];
        }

        $missionStart = $timeline[0]['timestamp'] ?? null;
        $missionEnd = $timeline !== [] ? $timeline[count($timeline) - 1]['timestamp'] : null;

        $unitSummaries = [];
        foreach ($unitTracks as $track) {
            $unitSummaries[] = [
                'unitId' => $track['unitId'],
                'callsign' => $track['callsign'],
                'samples' => (int) $track['samples'],
                'distance' => round((float) $track['distance'], 2),
                'firstSeen' => $track['firstSeen'],
                'lastSeen' => $track['lastSeen'],
            ];
        }
        usort($unitSummaries, static fn (array $a, array $b): int => strcmp((string) $a['callsign'], (string) $b['callsign']));

        return [
            'missionId' => $missionId,
            'window' => ['from' => $from, 'to' => $to],
            'summary' => [
                'missionStart' => $missionStart,
                'missionEnd' => $missionEnd,
                'unitCount' => count($unitSummaries),
                'positionSamples' => count($timelineRows),
                'intelEvents' => count($intelTimeline),
                'medianReactionDelaySeconds' => $medianReaction,
            ],
            'unitTracks' => $unitSummaries,
            'intelTimeline' => $intelTimeline,
            'errors' => $errors,
        ];
    }
}
