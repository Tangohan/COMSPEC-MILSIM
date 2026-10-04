<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AssetLogisticsRepository;
use App\Services\Logistics\AssetLogisticsEvaluator;

class LogisticsController
{
    private const DEFAULT_MAP_ID = 1;

    public function __construct(
        private AssetLogisticsRepository $repository,
        private AssetLogisticsEvaluator $evaluator
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
        if ($raw === false || $raw === '') {
            return is_array($_POST ?? null) ? $_POST : [];
        }
        // Corps compressé par la DLL (Content-Encoding: gzip) quand il est gros.
        if (str_starts_with($raw, "\x1f\x8b") && function_exists('gzdecode')) {
            $raw = (string) @gzdecode($raw);
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            error_log('[logistics/update] JSON illisible (' . strlen($raw) . ' o) : ' . json_last_error_msg());
        }
        return is_array($decoded) ? $decoded : [];
    }

    public function update(Request $request, array $params = []): Response
    {
        $body = $this->jsonBody($request);
        $missionId = $body['missionId'] ?? $body['mission_id'] ?? $this->missionId($request, $body);
        // Le jeu envoie l'indicatif comme identifiant ; on le reprend s'il manque l'assetId.
        $assetId = trim((string) ($body['assetId'] ?? $body['asset_id'] ?? $body['callsign'] ?? $body['call_sign'] ?? ''));
        if ($assetId === '') {
            return Response::json(['error' => 'assetId required', 'message' => 'Identifiant de l’unité manquant (assetId ou callsign).'], 400);
        }
        $row = $this->repository->upsert($missionId, (string) $assetId, $body);
        $evaluated = $this->evaluator->evaluate(array_merge($row, ['asset_id' => $assetId]));
        return Response::json(array_merge($row, $evaluated));
    }

    public function assets(Request $request, array $params = []): Response
    {
        $body = $this->jsonBody($request);
        $missionId = $this->missionId($request, $body);
        $list = $this->repository->listByMission($missionId);
        $out = [];
        foreach ($list as $row) {
            $eval = $this->evaluator->evaluate($row);
            $out[] = array_merge($row, $eval);
        }
        return Response::json($out);
    }
}
