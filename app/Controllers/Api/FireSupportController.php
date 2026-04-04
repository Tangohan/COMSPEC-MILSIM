<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\FireUnitRepository;
use App\Services\FireSupport\BallisticCalculatorService;

class FireSupportController
{
    private const DEFAULT_MAP_ID = 1;

    public function __construct(
        private FireUnitRepository $fireUnitRepository,
        private BallisticCalculatorService $ballisticCalculator
    ) {
    }

    private function missionId(Request $request, array $body = []): string
    {
        $missionId = $body['missionId'] ?? $body['mission_id'] ?? $request->query('missionId') ?? $request->query('mission_id');
        if ($missionId !== null && $missionId !== '') {
            return (string) $missionId;
        }
        $tenantId = Session::get('tenant_id');
        $tid = $tenantId !== null && $tenantId !== '' ? (int) $tenantId : 1;
        $mapId = $body['mapId'] ?? $request->query('mapId');
        $mid = $mapId !== null && $mapId !== '' ? (int) $mapId : self::DEFAULT_MAP_ID;
        return 'mission_' . $tid . '_map_' . $mid;
    }

    private function jsonBody(Request $request): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false) {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function units(Request $request, array $params = []): Response
    {
        $body = $this->jsonBody($request);
        $missionId = $this->missionId($request, $body);
        $list = $this->fireUnitRepository->listByMission($missionId);
        return Response::json($list);
    }

    public function calculate(Request $request, array $params = []): Response
    {
        $body = $this->jsonBody($request);
        $missionId = $this->missionId($request, $body);

        $fireUnitId = isset($body['fireUnitId']) ? (int) $body['fireUnitId'] : null;
        $gunX = isset($body['gun_x']) ? (float) $body['gun_x'] : null;
        $gunY = isset($body['gun_y']) ? (float) $body['gun_y'] : null;
        $gunZ = isset($body['gun_z']) ? (float) $body['gun_z'] : 0.0;

        $targetX = (float) ($body['target_x'] ?? $body['targetX'] ?? $body['x'] ?? 0);
        $targetY = (float) ($body['target_y'] ?? $body['targetY'] ?? $body['y'] ?? 0);
        $targetZ = (float) ($body['target_z'] ?? $body['targetZ'] ?? $body['z'] ?? 0);

        $weaponSystem = $body['weaponSystem'] ?? $body['weapon_system'] ?? '';
        $ammoType = $body['ammoType'] ?? $body['ammo_type'] ?? 'HE';

        if ($fireUnitId !== null && $fireUnitId > 0) {
            $unit = $this->fireUnitRepository->getByIdAndMission($fireUnitId, $missionId);
            if (!$unit) {
                return Response::json(['error' => 'Fire unit not found'], 404);
            }
            $gunX = (float) $unit['pos_x'];
            $gunY = (float) $unit['pos_y'];
            $gunZ = (float) ($unit['pos_z'] ?? 0);
            if ($weaponSystem === '') {
                $weaponSystem = (string) ($unit['weapon_system'] ?? 'MK6');
            }
        }

        if ($gunX === null || $gunY === null) {
            return Response::json(['error' => 'Gun position required (fireUnitId or gun_x, gun_y)'], 400);
        }

        $distance = $this->ballisticCalculator->calculateDistance($gunX, $gunY, $targetX, $targetY);
        $azimuthDeg = $this->ballisticCalculator->calculateAzimuth($gunX, $gunY, $targetX, $targetY);
        $azimuthMils = (int) round($this->ballisticCalculator->convertDegreesToMils($azimuthDeg));
        $deltaZ = $targetZ - $gunZ;

        $solution = $this->ballisticCalculator->resolveFiringSolution(
            $weaponSystem ?: 'MK6',
            $ammoType,
            $distance,
            $deltaZ
        );
        $solution['azimuth_deg'] = round($azimuthDeg, 1);
        $solution['azimuth_mils'] = $azimuthMils;

        $callsign = $body['fireUnitCallsign'] ?? 'GUN';
        if ($fireUnitId !== null && $fireUnitId > 0 && isset($unit)) {
            $callsign = $unit['callsign'] ?? $callsign;
        }

        $payload = [
            'type' => 'FIRE_SOLUTION',
            'missionId' => $missionId,
            'fireUnit' => $callsign,
            'weaponSystem' => $weaponSystem ?: 'MK6',
            'ammoType' => $ammoType,
            'target' => [
                'x' => $targetX,
                'y' => $targetY,
                'z' => $targetZ,
            ],
            'solution' => $solution,
            'computedAt' => date('Y-m-d H:i:s'),
        ];
        return Response::json($payload);
    }
}
