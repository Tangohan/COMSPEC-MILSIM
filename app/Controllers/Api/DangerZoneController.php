<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\DangerZone\DangerZoneService;

class DangerZoneController
{
    private const DEFAULT_MAP_ID = 1;

    public function __construct(
        private DangerZoneService $dangerZoneService
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

    public function index(Request $request, array $params = []): Response
    {
        $body = $this->jsonBody($request);
        $missionId = $this->missionId($request, $body);
        $activeOnly = $request->query('active') !== '0';
        $list = $this->dangerZoneService->listForMission($missionId, $activeOnly);
        return Response::json($list);
    }

    public function store(Request $request, array $params = []): Response
    {
        $body = $this->jsonBody($request);
        $missionId = $this->missionId($request, $body);
        $zone = $this->dangerZoneService->create($missionId, $body);
        return Response::json($zone, 201);
    }

    public function update(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            return Response::json(['error' => 'Invalid id'], 400);
        }
        $body = $this->jsonBody($request);
        $missionId = $this->missionId($request, $body);
        $ok = $this->dangerZoneService->update($id, $missionId, $body);
        if (!$ok) {
            return Response::json(['error' => 'Not found or unchanged'], 404);
        }
        $zone = $this->dangerZoneService->get($id, $missionId);
        return Response::json($zone ?? []);
    }

    public function delete(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            return Response::json(['error' => 'Invalid id'], 400);
        }
        $body = $this->jsonBody($request);
        $missionId = $body['missionId'] ?? $body['mission_id'] ?? $request->query('missionId') ?? $request->query('mission_id') ?? $this->missionId($request, $body);
        $ok = $this->dangerZoneService->delete($id, $missionId);
        if (!$ok) {
            return Response::json(['error' => 'Not found'], 404);
        }
        return Response::json(['ok' => true]);
    }
}
