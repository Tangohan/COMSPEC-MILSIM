<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Services\DangerZone\DangerZoneService;
use App\Support\TacticalMissionScope;

class DangerZoneController
{
    private const DEFAULT_MAP_ID = 1;

    public function __construct(
        private DangerZoneService $dangerZoneService
    ) {
    }

    /**
     * Bac de mission de la communauté appelante (voir TacticalMissionScope) : un missionId
     * étranger, vide ou numérique n’est jamais retenu tel quel ; non scalaire → 422.
     */
    private function missionId(Request $request, array $body = []): string|Response
    {
        $tenantId = TacticalMissionScope::tenantId();
        if ($tenantId === null) {
            return TacticalMissionScope::tenantRequiredResponse();
        }
        $missionId = $body['missionId'] ?? $body['mission_id'] ?? $request->query('missionId') ?? $request->query('mission_id');
        if (!TacticalMissionScope::isAcceptableRaw($missionId)) {
            return TacticalMissionScope::invalidMissionIdResponse();
        }
        $mapId = $body['mapId'] ?? $request->query('mapId');

        return TacticalMissionScope::resolve($missionId, $mapId, $tenantId, self::DEFAULT_MAP_ID);
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
        if ($missionId instanceof Response) {
            return $missionId;
        }
        $activeOnly = $request->query('active') !== '0';
        $list = $this->dangerZoneService->listForMission($missionId, $activeOnly);
        return Response::json($list);
    }

    public function store(Request $request, array $params = []): Response
    {
        $body = $this->jsonBody($request);
        $missionId = $this->missionId($request, $body);
        if ($missionId instanceof Response) {
            return $missionId;
        }
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
        if ($missionId instanceof Response) {
            return $missionId;
        }
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
        $missionId = $this->missionId($request, $body);
        if ($missionId instanceof Response) {
            return $missionId;
        }
        $ok = $this->dangerZoneService->delete($id, $missionId);
        if (!$ok) {
            return Response::json(['error' => 'Not found'], 404);
        }
        return Response::json(['ok' => true]);
    }
}
