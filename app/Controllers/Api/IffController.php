<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\IffAssetStatusRepository;
use App\Services\Iff\IffChallengeService;
use App\Services\Iff\IffValidationService;

class IffController
{
    private const DEFAULT_MAP_ID = 1;

    public function __construct(
        private IffChallengeService $challengeService,
        private IffValidationService $validationService,
        private IffAssetStatusRepository $assetRepository
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
        $mapId = $body['mapId'] ?? $body['map_id'] ?? $request->query('mapId') ?? $request->query('map_id');
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

    private function generateCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $out = '';
        for ($i = 0; $i < 6; $i++) {
            $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        return $out;
    }

    public function current(Request $request, array $params = []): Response
    {
        $body = $this->jsonBody($request);
        $missionId = $this->missionId($request, $body);
        $challenge = $this->challengeService->getCurrent($missionId);
        return Response::json($challenge ?? ['code' => null, 'valid_until' => null]);
    }

    /**
     * Publie un nouveau défi IFF (TOC).
     * POST /api/iff/challenge
     */
    public function challenge(Request $request, array $params = []): Response
    {
        $body = $this->jsonBody($request);
        $missionId = $this->missionId($request, $body);
        $code = strtoupper(trim((string) ($body['code'] ?? '')));
        if ($code === '') {
            $code = $this->generateCode();
        }
        if (strlen($code) > 64) {
            return Response::json([
                'error' => 'code_too_long',
                'message' => 'Le code de défi est trop long (64 caractères maximum).',
            ], 400);
        }
        if (!preg_match('/^[A-Z0-9\-_.]+$/', $code)) {
            return Response::json([
                'error' => 'invalid_code',
                'message' => 'Le code ne peut contenir que des lettres, chiffres et - _ .',
            ], 400);
        }
        $validMinutes = (int) ($body['validMinutes'] ?? $body['valid_minutes'] ?? 30);
        if ($validMinutes < 5) {
            $validMinutes = 5;
        }
        if ($validMinutes > 240) {
            $validMinutes = 240;
        }
        $id = $this->challengeService->create($missionId, $code, $validMinutes);
        $challenge = $this->challengeService->getCurrent($missionId);

        $synced = 0;
        $syncUnits = !empty($body['syncUnits']) || !empty($body['sync_units']);
        $assets = $body['assets'] ?? null;
        if (is_array($assets)) {
            $synced = $this->syncAssetList($missionId, $assets, $id);
        } elseif ($syncUnits) {
            // Les clients ATAK envoient souvent les unités via /api/iff/assets/sync juste après.
            $synced = 0;
        }

        return Response::json([
            'id' => $id,
            'code' => $code,
            'valid_until' => $challenge['valid_until'] ?? null,
            'challenge' => $challenge,
            'synced' => $synced,
        ], 201);
    }

    /**
     * Inscrit des unités sur le défi courant.
     * POST /api/iff/assets/sync
     */
    public function syncAssets(Request $request, array $params = []): Response
    {
        $body = $this->jsonBody($request);
        $missionId = $this->missionId($request, $body);
        $challenge = $this->challengeService->getCurrent($missionId);
        $challengeId = $challenge ? (int) $challenge['id'] : null;
        $assets = $body['assets'] ?? [];
        if (!is_array($assets)) {
            return Response::json([
                'error' => 'invalid_assets',
                'message' => 'La liste des unités est invalide.',
            ], 400);
        }
        $count = $this->syncAssetList($missionId, $assets, $challengeId);
        return Response::json(['ok' => true, 'count' => $count]);
    }

    /**
     * @param list<array<string,mixed>> $assets
     */
    private function syncAssetList(string $missionId, array $assets, ?int $challengeId): int
    {
        $count = 0;
        foreach ($assets as $a) {
            if (!is_array($a)) {
                continue;
            }
            $assetId = trim((string) ($a['assetId'] ?? $a['asset_id'] ?? $a['id'] ?? ''));
            $callsign = trim((string) ($a['callsign'] ?? $a['call_sign'] ?? $assetId));
            if ($assetId === '') {
                continue;
            }
            $platform = $a['platformType'] ?? $a['platform_type'] ?? null;
            $this->assetRepository->upsert(
                $missionId,
                $assetId,
                $callsign !== '' ? $callsign : $assetId,
                $platform !== null ? (string) $platform : null,
                $challengeId
            );
            $count++;
        }
        return $count;
    }

    public function respond(Request $request, array $params = []): Response
    {
        $body = $this->jsonBody($request);
        $missionId = $this->missionId($request, $body);
        $assetId = trim((string) ($body['assetId'] ?? $body['asset_id'] ?? ''));
        $responseCode = trim((string) ($body['responseCode'] ?? $body['response_code'] ?? ''));
        if ($assetId === '' || $responseCode === '') {
            return Response::json([
                'error' => 'missing_fields',
                'message' => 'Indiquez l’unité et le code de réponse.',
            ], 400);
        }
        $callsign = trim((string) ($body['callsign'] ?? $body['call_sign'] ?? $assetId));
        $challenge = $this->challengeService->getCurrent($missionId);
        $challengeId = $challenge ? (int) $challenge['id'] : null;
        $this->assetRepository->upsert(
            $missionId,
            $assetId,
            $callsign !== '' ? $callsign : $assetId,
            isset($body['platformType']) ? (string) $body['platformType'] : null,
            $challengeId
        );
        $result = $this->validationService->respond($missionId, $assetId, $responseCode);
        if (($result['status'] ?? '') === 'EXPIRED' && isset($result['message'])) {
            $result['message'] = 'Aucun défi actif, ou le défi a expiré.';
        }
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
