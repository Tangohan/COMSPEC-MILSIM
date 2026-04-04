<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Iff\IffChallengeService;
use App\Services\Iff\IffValidationService;

class IffController
{
    private const DEFAULT_MAP_ID = 1;

    public function __construct(
        private IffChallengeService $challengeService,
        private IffValidationService $validationService
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

    public function current(Request $request, array $params = []): Response
    {
        $body = $this->jsonBody($request);
        $missionId = $this->missionId($request, $body);
        $challenge = $this->challengeService->getCurrent($missionId);
        return Response::json($challenge ?? ['code' => null, 'valid_until' => null]);
    }

    public function respond(Request $request, array $params = []): Response
    {
        $body = $this->jsonBody($request);
        $missionId = $this->missionId($request, $body);
        $assetId = $body['assetId'] ?? $body['asset_id'] ?? '';
        $responseCode = $body['responseCode'] ?? $body['response_code'] ?? '';
        if ($assetId === '' || $responseCode === '') {
            return Response::json(['error' => 'assetId and responseCode required'], 400);
        }
        $result = $this->validationService->respond($missionId, $assetId, $responseCode);
        return Response::json($result);
    }

    public function assets(Request $request, array $params = []): Response
    {
        $body = $this->jsonBody($request);
        $missionId = $this->missionId($request, $body);
        $list = $this->validationService->listAssets($missionId);
        return Response::json($list);
    }
}
