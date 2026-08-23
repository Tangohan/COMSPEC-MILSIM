<?php

declare(strict_types=1);

namespace App\Services\Tactical;

use App\Repositories\AtakDataRepository;
use App\Repositories\AtakTerrainRepository;
use App\Repositories\AtakUnitAssignmentRepository;
use App\Repositories\AtakWaypointRepository;
use App\Support\AtakUnitMotionSchema;
use App\Support\LazyDatabaseConnection;
use PDO;
use Throwable;

/**
 * Enrichit le flux BFT existant : historique court, snapshot motion, destinations.
 */
final class AtakUnitMotionService
{
    use LazyDatabaseConnection;

    private const SAMPLE_KEEP = 24;

    private AtakUnitAssignmentRepository $assignments;
    private AtakDataRepository $units;
    private AtakCommandIntelService $intel;
    private AtakTerrainRepository $terrain;

    /** @var array<string, array<string, mixed>|null> */
    private array $terrainGridCache = [];

    public function __construct(
        ?PDO $pdo = null,
        ?AtakUnitAssignmentRepository $assignments = null,
        ?AtakDataRepository $units = null,
        ?AtakCommandIntelService $intel = null,
        ?AtakTerrainRepository $terrain = null
    ) {
        AtakUnitMotionSchema::ensure();
        $this->pdo = $pdo;
        $this->assignments = $assignments ?? new AtakUnitAssignmentRepository($pdo);
        $this->units = $units ?? new AtakDataRepository($pdo);
        $this->intel = $intel ?? new AtakCommandIntelService();
        $this->terrain = $terrain ?? new AtakTerrainRepository($pdo);
    }

    /**
     * @param array<string, mixed> $extra
     */
    public function ingestGround(
        int $tenantId,
        int $mapId,
        int $unitId,
        string $callSign,
        float $x,
        float $y,
        ?float $headingObject,
        array $extra
    ): void {
        $this->ingest($tenantId, $mapId, 'ground', $unitId > 0 ? $unitId : null, $callSign, $x, $y, $headingObject, $extra);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function ingestAir(int $tenantId, int $mapId, string $callsign, array $data): void
    {
        $x = $this->num($data['pos_x'] ?? null);
        $y = $this->num($data['pos_y'] ?? null);
        if ($x === null || $y === null) {
            return;
        }
        $extra = $data;
        $extra['unit_kind'] = 'air';
        $extra['platform'] = $extra['platform'] ?? AtakMotionMath::classifyCategory($data);
        $extra['asl_z'] = $extra['asl_z'] ?? $extra['alt'] ?? $extra['pos_z'] ?? null;
        $this->ingest(
            $tenantId,
            $mapId,
            'air',
            null,
            $callsign,
            $x,
            $y,
            $this->num($data['heading'] ?? null),
            $extra
        );
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public function attachToUnits(int $tenantId, int $mapId, array $rows): array
    {
        return $this->attach($tenantId, $mapId, 'ground', $rows, static function (array $row): string {
            return trim((string) ($row['call_sign'] ?? $row['callsign'] ?? ''));
        });
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public function attachToAir(int $tenantId, int $mapId, array $rows): array
    {
        return $this->attach($tenantId, $mapId, 'air', $rows, static function (array $row): string {
            return trim((string) ($row['callsign'] ?? $row['call_sign'] ?? ''));
        });
    }

    /**
     * @param list<array<string, mixed>> $liveUnits
     * @param list<array<string, mixed>> $liveAir
     * @return list<array<string, mixed>>
     */
    public function arrivalSequence(
        int $tenantId,
        int $mapId,
        string $destinationType,
        string $destinationId,
        array $liveUnits = [],
        array $liveAir = []
    ): array {
        $rows = $this->assignments->listByDestination($tenantId, $mapId, $destinationType, $destinationId);
        if ($rows === []) {
            return [];
        }
        $unitIndex = $this->indexByRef($liveUnits, 'call_sign');
        $airIndex = $this->indexByRef($liveAir, 'callsign');
        $out = [];
        foreach ($rows as $asg) {
            $kind = (string) ($asg['unit_kind'] ?? 'ground');
            $ref = trim((string) ($asg['unit_ref'] ?? ''));
            $live = $kind === 'air' ? ($airIndex[$ref] ?? null) : ($unitIndex[$ref] ?? null);
            $nav = is_array($live) ? ($live['navigation'] ?? null) : null;
            $etaSec = is_array($nav) ? ($nav['eta']['seconds'] ?? null) : null;
            $out[] = [
                'assignment_id' => (int) ($asg['id'] ?? 0),
                'unit_kind' => $kind,
                'unit_ref' => $ref,
                'call_sign' => $ref,
                'destination_label' => $asg['destination_label'] ?? '',
                'eta_seconds' => is_numeric($etaSec) ? (int) $etaSec : null,
                'course_status' => is_array($nav) ? ($nav['course_status'] ?? null) : null,
                'distance_m' => is_array($nav) ? ($nav['distance_m'] ?? null) : null,
                'status' => $asg['status'] ?? 'active',
            ];
        }
        usort($out, static function (array $a, array $b): int {
            $ea = $a['eta_seconds'];
            $eb = $b['eta_seconds'];
            if ($ea === null && $eb === null) {
                return strcmp((string) $a['unit_ref'], (string) $b['unit_ref']);
            }
            if ($ea === null) {
                return 1;
            }
            if ($eb === null) {
                return -1;
            }

            return $ea <=> $eb;
        });
        $rank = 1;
        foreach ($out as &$item) {
            $item['arrival_order'] = $rank++;
        }
        unset($item);

        return $out;
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function ingest(
        int $tenantId,
        int $mapId,
        string $unitKind,
        ?int $unitId,
        string $unitRef,
        float $x,
        float $y,
        ?float $headingObject,
        array $extra
    ): void {
        $unitRef = trim($unitRef);
        if ($tenantId < 1 || $mapId < 1 || $unitRef === '') {
            return;
        }
        if (!AtakDataRepository::isValidMapPosition($x, $y)) {
            return;
        }
        if ($this->shouldSkipHistory($tenantId, $mapId, $unitKind, $unitRef, $x, $y, $extra)) {
            return;
        }
        $vel = $this->velocityFromExtra($extra);
        $speed = $this->num($extra['speed'] ?? $extra['speed_ms'] ?? null);
        if ($speed === null && $vel[0] !== null && $vel[1] !== null) {
            $speed = hypot(hypot((float) $vel[0], (float) $vel[1]), (float) ($vel[2] ?? 0));
        }
        $z = $this->num($extra['asl_z'] ?? $extra['pos_z'] ?? $extra['alt'] ?? $extra['altitude'] ?? null);

        try {
            $this->pdo()->prepare(
                'INSERT INTO atak_unit_motion_samples (
                    tenant_id, map_id, unit_kind, unit_id, unit_ref,
                    pos_x, pos_y, pos_z, heading_object, speed_ms, vel_x, vel_y, vel_z, sampled_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
            )->execute([
                $tenantId, $mapId, $unitKind, $unitId, $unitRef,
                $x, $y, $z, $headingObject, $speed, $vel[0], $vel[1], $vel[2],
            ]);
            $this->pruneSamples($tenantId, $mapId, $unitKind, $unitRef);
        } catch (Throwable) {
            return;
        }

        $samples = $this->loadSamples($tenantId, $mapId, $unitKind, $unitRef);
        $previous = $this->loadSnapshot($tenantId, $mapId, $unitKind, $unitRef);
        $arma = $extra;
        $arma['heading_object'] = $headingObject;
        $arma['speed_ms'] = $speed;
        $arma['vel_x'] = $vel[0];
        $arma['vel_y'] = $vel[1];
        $arma['vel_z'] = $vel[2];
        $arma['asl_z'] = $z;
        $arma['unit_kind'] = $unitKind;
        $computed = AtakMotionMath::compute($samples, $arma, $previous);
        $computed['in_vehicle'] = $extra['in_vehicle'] ?? null;
        $computed['health'] = $extra['health'] ?? null;
        $this->saveSnapshot($tenantId, $mapId, $unitKind, $unitId, $unitRef, $computed);
        $this->refreshAssignmentArrival($tenantId, $mapId, $unitKind, $unitRef, $x, $y, $computed);
        try {
            $this->intel->observe($tenantId, $mapId, $unitKind, $unitRef, $x, $y, $computed, $previous, $extra);
        } catch (Throwable) {
        }
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param callable(array<string, mixed>): string $refOf
     * @return list<array<string, mixed>>
     */
    private function attach(int $tenantId, int $mapId, string $unitKind, array $rows, callable $refOf): array
    {
        if ($rows === []) {
            return $rows;
        }
        $motions = $this->loadSnapshots($tenantId, $mapId, $unitKind);
        $assignments = [];
        foreach ($this->assignments->listActive($tenantId, $mapId) as $asg) {
            if ((string) ($asg['unit_kind'] ?? 'ground') !== $unitKind) {
                continue;
            }
            $ref = trim((string) ($asg['unit_ref'] ?? ''));
            if ($ref !== '') {
                $assignments[$ref] = $asg;
            }
        }
        $liveIndex = [];
        foreach ($rows as $row) {
            $ref = $refOf($row);
            if ($ref !== '') {
                $liveIndex[$ref] = $row;
            }
        }

        foreach ($rows as &$row) {
            $ref = $refOf($row);
            if ($ref === '') {
                continue;
            }
            $motion = $motions[$ref] ?? null;
            $payload = $this->publicMotion($motion, $row);
            $row['heading_object'] = $payload['heading_object'];
            $row['movement_heading'] = $payload['movement_heading'];
            $row['speed'] = $payload['speed'];
            $row['velocity'] = $payload['velocity'];
            $row['motion'] = $payload['motion'];
            if (isset($payload['air'])) {
                $row['air'] = $payload['air'];
            }
            $asg = $assignments[$ref] ?? null;
            if (is_array($asg)) {
                $row['assignment'] = $this->decorateAssignment($tenantId, $mapId, $asg, $row, $payload, $liveIndex);
                $row['navigation'] = $row['assignment'];
            }
            $row = AtakOperationalStatusService::decorate($row, $payload);
        }
        unset($row);

        return $rows;
    }

    /**
     * @param array<string, mixed>|null $snapshot
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function publicMotion(?array $snapshot, array $row): array
    {
        $json = [];
        if (is_array($snapshot) && !empty($snapshot['motion_json'])) {
            $decoded = is_array($snapshot['motion_json'])
                ? $snapshot['motion_json']
                : json_decode((string) $snapshot['motion_json'], true);
            if (is_array($decoded)) {
                $json = $decoded;
            }
        }
        $headingObject = $this->num($snapshot['heading_object'] ?? $row['heading'] ?? null);
        $moveHeading = $this->num($snapshot['movement_heading'] ?? null);
        $speed = $this->num($snapshot['speed_ms'] ?? null);
        $status = (string) ($snapshot['motion_status'] ?? AtakMotionMath::STATUS_UNKNOWN);
        $confidence = $this->num($snapshot['confidence'] ?? 0) ?? 0.0;
        $trend = (string) ($snapshot['trend'] ?? AtakMotionMath::TREND_UNKNOWN);
        $category = (string) ($json['category'] ?? AtakMotionMath::CAT_INFANTRY);
        $vel = $json['velocity'] ?? ['x' => null, 'y' => null, 'z' => null];
        $motion = [
            'status' => $status,
            'confidence' => round($confidence, 3),
            'trend' => $trend,
            'category' => $category,
            'speed_current' => $speed,
            'speed_average_30s' => $this->num($snapshot['speed_avg_30'] ?? null),
            'speed_average_60s' => $this->num($snapshot['speed_avg_60'] ?? null),
            'eta_speed' => $this->num($snapshot['eta_speed_ms'] ?? null),
            'projection' => $json['projection'] ?? ['horizon_s' => 30, 'length_m' => 0, 'visible' => false],
            'trail' => $json['trail'] ?? [],
        ];
        $out = [
            'heading_object' => $headingObject,
            'movement_heading' => $moveHeading,
            'speed' => $speed,
            'velocity' => $vel,
            'motion' => $motion,
        ];
        if (!empty($json['air']) && is_array($json['air'])) {
            $out['air'] = $json['air'];
        } elseif (isset($row['alt']) || isset($row['aircraft_type'])) {
            $out['air'] = [
                'ground_speed' => $speed,
                'altitude' => $this->num($row['alt'] ?? null),
                'vertical_speed' => $this->num($snapshot['vertical_speed'] ?? null),
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $asg
     * @param array<string, mixed> $row
     * @param array<string, mixed> $payload
     * @param array<string, array<string, mixed>> $liveIndex
     * @return array<string, mixed>
     */
    private function decorateAssignment(
        int $tenantId,
        int $mapId,
        array $asg,
        array $row,
        array $payload,
        array $liveIndex
    ): array {
        $dest = $this->resolveDestination($tenantId, $mapId, $asg, $liveIndex, $row, $payload);
        $ux = $this->num($row['pos_x'] ?? null);
        $uy = $this->num($row['pos_y'] ?? null);
        $dx = $dest['x'];
        $dy = $dest['y'];
        $dist = ($ux !== null && $uy !== null && $dx !== null && $dy !== null)
            ? AtakMotionMath::distanceM($ux, $uy, $dx, $dy)
            : null;
        $bearing = ($ux !== null && $uy !== null && $dx !== null && $dy !== null)
            ? AtakMotionMath::bearingTo($ux, $uy, $dx, $dy)
            : null;
        $category = (string) ($payload['motion']['category'] ?? AtakMotionMath::CAT_INFANTRY);
        $speed = (float) ($payload['motion']['eta_speed'] ?? $payload['speed'] ?? 0);
        $moveH = $this->num($payload['movement_heading'] ?? null);
        $course = AtakMotionMath::COURSE_STATIC;
        if ($dist !== null) {
            $course = AtakMotionMath::courseStatus($moveH, $bearing, $speed, $dist, $category);
        }
        $prevEta = null;
        $eta = [
            'kind' => AtakMotionMath::ETA_DIRECT,
            'seconds' => null,
            'raw_seconds' => null,
            'speed_ms' => $speed,
            'arrived' => $course === AtakMotionMath::COURSE_ARRIVED,
        ];
        if ($dist !== null) {
            $eta = AtakMotionMath::etaDirect($dist, $speed > 0 ? $speed : null, $category, $prevEta);
        }
        if ($course === AtakMotionMath::COURSE_ARRIVED && (int) ($asg['id'] ?? 0) > 0) {
            try {
                $this->assignments->markArrived($tenantId, (int) $asg['id']);
            } catch (Throwable) {
            }
            $eta['arrived'] = true;
            $eta['seconds'] = 0;
        }

        $mode = strtoupper((string) ($asg['assignment_mode'] ?? 'DIRECT'));
        $intercept = null;
        if ($mode === AtakUnitAssignmentRepository::MODE_INTERCEPT && ($asg['destination_type'] ?? '') === 'unit') {
            $tgtRef = trim((string) ($asg['destination_id'] ?? ''));
            $tgt = $liveIndex[$tgtRef] ?? null;
            if (is_array($tgt) && $ux !== null && $uy !== null) {
                $tx = $this->num($tgt['pos_x'] ?? null);
                $ty = $this->num($tgt['pos_y'] ?? null);
                $th = $this->num($tgt['movement_heading'] ?? $tgt['heading'] ?? null);
                $ts = $this->num($tgt['speed'] ?? null);
                if ($tx !== null && $ty !== null) {
                    $intercept = AtakMotionMath::interceptPoint($tx, $ty, $th, $ts, $ux, $uy, $speed > 0 ? $speed : null);
                }
            }
        }

        return [
            'id' => (int) ($asg['id'] ?? 0),
            'destination_type' => $asg['destination_type'] ?? 'custom',
            'destination_id' => $asg['destination_id'] ?? null,
            'destination_label' => $dest['label'] ?? ($asg['destination_label'] ?? ''),
            'destination_x' => $dx,
            'destination_y' => $dy,
            'assignment_mode' => $mode,
            'status' => $course === AtakMotionMath::COURSE_ARRIVED ? 'arrived' : ($asg['status'] ?? 'active'),
            'distance_m' => $dist !== null ? round($dist, 1) : null,
            'bearing' => $bearing !== null ? round($bearing, 1) : null,
            'heading_delta' => ($moveH !== null && $bearing !== null)
                ? round(AtakMotionMath::circularDeltaDeg($moveH, $bearing), 1)
                : null,
            'course_status' => $course,
            'eta' => $eta,
            'eta_kinds' => [
                AtakMotionMath::ETA_DIRECT => $eta,
                AtakMotionMath::ETA_ROUTE => null,
                AtakMotionMath::ETA_PLANNED => null,
            ],
            'intercept' => $intercept,
            'assigned_at' => $asg['assigned_at'] ?? null,
            'assigned_by_label' => $asg['assigned_by_label'] ?? null,
            'terrain' => $this->terrainForPath($tenantId, $mapId, $ux, $uy, $dx, $dy, is_array($eta) ? $this->num($eta['seconds'] ?? null) : null),
        ];
    }

    /**
     * @param array<string, mixed> $asg
     * @param array<string, array<string, mixed>> $liveIndex
     * @param array<string, mixed> $row
     * @param array<string, mixed> $payload
     * @return array{x:?float,y:?float,label:?string}
     */
    private function resolveDestination(int $tenantId, int $mapId, array $asg, array $liveIndex, array $row, array $payload): array
    {
        $type = (string) ($asg['destination_type'] ?? 'custom');
        $label = (string) ($asg['destination_label'] ?? '');
        $x = $this->num($asg['destination_x'] ?? null);
        $y = $this->num($asg['destination_y'] ?? null);
        $destId = trim((string) ($asg['destination_id'] ?? ''));

        if ($type === AtakUnitAssignmentRepository::DEST_UNIT && $destId !== '') {
            $tgt = $liveIndex[$destId] ?? null;
            if (!is_array($tgt)) {
                try {
                    $found = $this->units->getUnitByCallSign($tenantId, $mapId, $destId);
                    $tgt = is_array($found) ? $found : null;
                } catch (Throwable) {
                    $tgt = null;
                }
            }
            if (is_array($tgt)) {
                $x = $this->num($tgt['pos_x'] ?? null) ?? $x;
                $y = $this->num($tgt['pos_y'] ?? null) ?? $y;
                $label = $label !== '' ? $label : (string) ($tgt['call_sign'] ?? $tgt['callsign'] ?? $destId);
            }
        } elseif (in_array($type, [AtakUnitAssignmentRepository::DEST_MARKER, AtakUnitAssignmentRepository::DEST_ARMA_MARKER], true) && $destId !== '' && ctype_digit($destId)) {
            try {
                $marker = $this->units->getMarkerById($tenantId, (int) $destId);
                if (is_array($marker)) {
                    $md = $marker['markerData'] ?? null;
                    $parsed = is_string($md) ? json_decode($md, true) : (is_array($md) ? $md : []);
                    if (is_array($parsed)) {
                        $mx = $this->num($parsed['x'] ?? $parsed['pos_x'] ?? $parsed['lng'] ?? null);
                        $my = $this->num($parsed['y'] ?? $parsed['pos_y'] ?? $parsed['lat'] ?? null);
                        if ($mx !== null && $my !== null) {
                            $x = $mx;
                            $y = $my;
                        }
                        if ($label === '') {
                            $label = (string) ($parsed['label'] ?? $parsed['text'] ?? $parsed['name'] ?? '');
                        }
                    }
                }
            } catch (Throwable) {
            }
        } elseif ($type === AtakUnitAssignmentRepository::DEST_WAYPOINT && $destId !== '' && ctype_digit($destId)) {
            try {
                $wp = (new AtakWaypointRepository())->findWaypoint($tenantId, (int) $destId);
                if (is_array($wp)) {
                    $x = $this->num($wp['pos_x'] ?? null) ?? $x;
                    $y = $this->num($wp['pos_y'] ?? null) ?? $y;
                    if ($label === '') {
                        $label = (string) ($wp['label'] ?? '');
                    }
                }
            } catch (Throwable) {
            }
        }

        return ['x' => $x, 'y' => $y, 'label' => $label !== '' ? $label : null];
    }

    /**
     * @param array<string, mixed> $computed
     */
    private function refreshAssignmentArrival(
        int $tenantId,
        int $mapId,
        string $unitKind,
        string $unitRef,
        float $x,
        float $y,
        array $computed
    ): void {
        try {
            $asg = $this->assignments->findActiveForUnit($tenantId, $mapId, $unitKind, $unitRef);
            if ($asg === null) {
                return;
            }
            $dx = $this->num($asg['destination_x'] ?? null);
            $dy = $this->num($asg['destination_y'] ?? null);
            if (($asg['destination_type'] ?? '') === 'unit') {
                $tgt = $this->units->getUnitByCallSign($tenantId, $mapId, (string) ($asg['destination_id'] ?? ''));
                if (is_array($tgt)) {
                    $dx = $this->num($tgt['pos_x'] ?? null) ?? $dx;
                    $dy = $this->num($tgt['pos_y'] ?? null) ?? $dy;
                }
            }
            if ($dx === null || $dy === null) {
                return;
            }
            $cat = (string) ($computed['category'] ?? AtakMotionMath::CAT_INFANTRY);
            $dist = AtakMotionMath::distanceM($x, $y, $dx, $dy);
            if ($dist <= AtakMotionMath::arrivalRadiusM($cat)) {
                $this->assignments->markArrived($tenantId, (int) $asg['id']);
            }
        } catch (Throwable) {
        }
    }

    /**
     * Heartbeat ou micro-déplacement : pas d’INSERT d’historique.
     * L’état courant (atak_units) est déjà mis à jour par l’upsert position.
     *
     * @param array<string, mixed> $extra
     */
    public static function shouldSkipHistorySample(
        ?float $lastX,
        ?float $lastY,
        ?int $lastAt,
        float $x,
        float $y,
        array $extra,
        int $now = 0
    ): bool {
        if ($lastX === null || $lastY === null || $lastAt === null) {
            return false;
        }
        $now = $now > 0 ? $now : time();
        $dist = hypot($x - $lastX, $y - $lastY);
        $kind = strtolower(trim((string) ($extra['telemetry_kind'] ?? '')));
        $minGap = (int) round((float) ($extra['history_sample_min'] ?? 15));
        $minGap = max(5, min(120, $minGap));
        $age = $now - $lastAt;
        if ($kind === 'heartbeat' && $dist < 8.0) {
            return true;
        }

        return $age < $minGap && $dist < 8.0;
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function shouldSkipHistory(
        int $tenantId,
        int $mapId,
        string $unitKind,
        string $unitRef,
        float $x,
        float $y,
        array $extra
    ): bool {
        try {
            $last = $this->loadLatestSample($tenantId, $mapId, $unitKind, $unitRef);
        } catch (Throwable) {
            return false;
        }
        if ($last === null) {
            return false;
        }

        return self::shouldSkipHistorySample(
            $last['x'],
            $last['y'],
            $last['t'],
            $x,
            $y,
            $extra
        );
    }

    /**
     * @return array{x: float, y: float, t: int}|null
     */
    private function loadLatestSample(int $tenantId, int $mapId, string $unitKind, string $unitRef): ?array
    {
        $st = $this->pdo()->prepare(
            'SELECT pos_x, pos_y, sampled_at
             FROM atak_unit_motion_samples
             WHERE tenant_id = ? AND map_id = ? AND unit_kind = ? AND unit_ref = ?
             ORDER BY sampled_at DESC, id DESC
             LIMIT 1'
        );
        $st->execute([$tenantId, $mapId, $unitKind, $unitRef]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        $t = strtotime((string) ($row['sampled_at'] ?? '')) ?: 0;
        if ($t < 1) {
            return null;
        }

        return [
            'x' => (float) $row['pos_x'],
            'y' => (float) $row['pos_y'],
            't' => $t,
        ];
    }

    /**
     * @return list<array{x:float,y:float,z:?float,t:float,heading_object:?float,speed_ms:?float,vel_x:?float,vel_y:?float,vel_z:?float}>
     */
    private function loadSamples(int $tenantId, int $mapId, string $unitKind, string $unitRef): array
    {
        $st = $this->pdo()->prepare(
            'SELECT pos_x, pos_y, pos_z, heading_object, speed_ms, vel_x, vel_y, vel_z, sampled_at
             FROM atak_unit_motion_samples
             WHERE tenant_id = ? AND map_id = ? AND unit_kind = ? AND unit_ref = ?
             ORDER BY sampled_at ASC, id ASC'
        );
        $st->execute([$tenantId, $mapId, $unitKind, $unitRef]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $t = strtotime((string) ($r['sampled_at'] ?? '')) ?: time();
            $out[] = [
                'x' => (float) $r['pos_x'],
                'y' => (float) $r['pos_y'],
                'z' => $this->num($r['pos_z'] ?? null),
                't' => (float) $t,
                'heading_object' => $this->num($r['heading_object'] ?? null),
                'speed_ms' => $this->num($r['speed_ms'] ?? null),
                'vel_x' => $this->num($r['vel_x'] ?? null),
                'vel_y' => $this->num($r['vel_y'] ?? null),
                'vel_z' => $this->num($r['vel_z'] ?? null),
            ];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadSnapshot(int $tenantId, int $mapId, string $unitKind, string $unitRef): ?array
    {
        $st = $this->pdo()->prepare(
            'SELECT * FROM atak_unit_motion
             WHERE tenant_id = ? AND map_id = ? AND unit_kind = ? AND unit_ref = ? LIMIT 1'
        );
        $st->execute([$tenantId, $mapId, $unitKind, $unitRef]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        if (isset($row['motion_json']) && is_string($row['motion_json'])) {
            $decoded = json_decode($row['motion_json'], true);
            if (is_array($decoded)) {
                $row['motion_json'] = $decoded;
            }
        }

        return $row;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function loadSnapshots(int $tenantId, int $mapId, string $unitKind): array
    {
        try {
            $st = $this->pdo()->prepare(
                'SELECT * FROM atak_unit_motion WHERE tenant_id = ? AND map_id = ? AND unit_kind = ?'
            );
            $st->execute([$tenantId, $mapId, $unitKind]);
        } catch (Throwable) {
            return [];
        }
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $ref = trim((string) ($row['unit_ref'] ?? ''));
            if ($ref === '') {
                continue;
            }
            $out[$ref] = $row;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $computed
     */
    private function saveSnapshot(
        int $tenantId,
        int $mapId,
        string $unitKind,
        ?int $unitId,
        string $unitRef,
        array $computed
    ): void {
        $json = json_encode([
            'category' => $computed['category'] ?? null,
            'velocity' => $computed['velocity'] ?? null,
            'projection' => $computed['projection'] ?? null,
            'trail' => $computed['trail'] ?? [],
            'air' => $computed['air'] ?? null,
            'sample_count' => $computed['sample_count'] ?? 0,
            'in_vehicle' => $computed['in_vehicle'] ?? null,
            'health' => $computed['health'] ?? null,
        ], JSON_UNESCAPED_UNICODE);
        $this->pdo()->prepare(
            'INSERT INTO atak_unit_motion (
                tenant_id, map_id, unit_kind, unit_id, unit_ref,
                heading_object, movement_heading, speed_ms, speed_avg_30, speed_avg_60, eta_speed_ms,
                motion_status, confidence, trend, alt_msl, vertical_speed, alt_trend, motion_json, computed_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                unit_id = VALUES(unit_id),
                heading_object = VALUES(heading_object),
                movement_heading = VALUES(movement_heading),
                speed_ms = VALUES(speed_ms),
                speed_avg_30 = VALUES(speed_avg_30),
                speed_avg_60 = VALUES(speed_avg_60),
                eta_speed_ms = VALUES(eta_speed_ms),
                motion_status = VALUES(motion_status),
                confidence = VALUES(confidence),
                trend = VALUES(trend),
                alt_msl = VALUES(alt_msl),
                vertical_speed = VALUES(vertical_speed),
                alt_trend = VALUES(alt_trend),
                motion_json = VALUES(motion_json),
                computed_at = VALUES(computed_at)'
        )->execute([
            $tenantId, $mapId, $unitKind, $unitId, $unitRef,
            $computed['heading_object'] ?? null,
            $computed['movement_heading'] ?? null,
            $computed['speed_ms'] ?? null,
            $computed['speed_avg_30'] ?? null,
            $computed['speed_avg_60'] ?? null,
            $computed['eta_speed_ms'] ?? null,
            $computed['motion_status'] ?? AtakMotionMath::STATUS_UNKNOWN,
            $computed['confidence'] ?? 0,
            $computed['trend'] ?? AtakMotionMath::TREND_UNKNOWN,
            $computed['alt_msl'] ?? null,
            $computed['vertical_speed'] ?? null,
            $computed['alt_trend'] ?? null,
            $json ?: '{}',
        ]);
    }

    private function pruneSamples(int $tenantId, int $mapId, string $unitKind, string $unitRef): void
    {
        $this->pdo()->prepare(
            'DELETE FROM atak_unit_motion_samples
             WHERE tenant_id = ? AND map_id = ? AND unit_kind = ? AND unit_ref = ?
               AND id NOT IN (
                 SELECT id FROM (
                   SELECT id FROM atak_unit_motion_samples
                   WHERE tenant_id = ? AND map_id = ? AND unit_kind = ? AND unit_ref = ?
                   ORDER BY sampled_at DESC, id DESC
                   LIMIT ' . self::SAMPLE_KEEP . '
                 ) keep_rows
               )'
        )->execute([$tenantId, $mapId, $unitKind, $unitRef, $tenantId, $mapId, $unitKind, $unitRef]);
    }

    /**
     * @param array<string, mixed> $extra
     * @return array{0:?float,1:?float,2:?float}
     */
    private function velocityFromExtra(array $extra): array
    {
        $v = $extra['velocity'] ?? null;
        if (is_array($v) && isset($v[0], $v[1])) {
            return [$this->num($v[0]), $this->num($v[1]), $this->num($v[2] ?? null)];
        }
        if (is_array($v) && (isset($v['x']) || isset($v['y']))) {
            return [$this->num($v['x'] ?? null), $this->num($v['y'] ?? null), $this->num($v['z'] ?? null)];
        }

        return [null, null, null];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, array<string, mixed>>
     */
    private function indexByRef(array $rows, string $key): array
    {
        $out = [];
        foreach ($rows as $row) {
            $ref = trim((string) ($row[$key] ?? $row['call_sign'] ?? $row['callsign'] ?? ''));
            if ($ref !== '') {
                $out[$ref] = $row;
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function terrainForPath(int $tenantId, int $mapId, ?float $x0, ?float $y0, ?float $x1, ?float $y1, ?float $etaSec): ?array
    {
        if ($x0 === null || $y0 === null || $x1 === null || $y1 === null) {
            return null;
        }
        $grid = $this->cachedTerrainGrid($tenantId, $mapId);
        if ($grid === null || (int) ($grid['ready'] ?? 0) !== 1) {
            return null;
        }
        try {
            return AtakTerrainMath::pathAnalysis($grid, $x0, $y0, $x1, $y1, $etaSec);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function cachedTerrainGrid(int $tenantId, int $mapId): ?array
    {
        $key = $tenantId . ':' . $mapId;
        if (array_key_exists($key, $this->terrainGridCache)) {
            return $this->terrainGridCache[$key];
        }
        try {
            $grid = $this->terrain->getGrid($tenantId, $mapId, true);
            $this->terrainGridCache[$key] = is_array($grid) ? $grid : null;
        } catch (Throwable) {
            $this->terrainGridCache[$key] = null;
        }

        return $this->terrainGridCache[$key];
    }

    private function num(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (!is_numeric($v)) {
            return null;
        }
        $f = (float) $v;

        return is_finite($f) ? $f : null;
    }
}
