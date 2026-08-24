<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AssetLogisticsRepository;
use App\Repositories\AtakDataRepository;
use App\Services\Intel\IntelFusionService;
use App\Services\Logistics\AssetLogisticsEvaluator;
use App\Services\Replay\ReplayAarPdfService;
use App\Services\Replay\ReplayService;
use App\Support\MedicalAlertParser;

class OperationsApiController
{
    private const DEFAULT_MAP_ID = 1;

    public function __construct(
        private ReplayService $replayService,
        private IntelFusionService $intelFusion,
        private AssetLogisticsRepository $assetLogisticsRepository,
        private AssetLogisticsEvaluator $assetLogisticsEvaluator,
        private ?AtakDataRepository $atakDataRepository = null,
    ) {
    }

    private function atakData(): AtakDataRepository
    {
        return $this->atakDataRepository ??= new AtakDataRepository();
    }

    public function missionsTimeline(Request $request, array $params = []): Response
    {
        $missionId = $this->resolveMissionId($request, $params);
        if ($missionId === null) {
            return $this->error(
                'operations.missions.mission_id_required',
                'missionId requis.',
                400,
                ['domain' => 'operations.missions', 'param' => 'missionId']
            );
        }

        $from = $this->nullableQuery($request, 'from');
        $to = $this->nullableQuery($request, 'to');

        return $this->success([
            'domain' => 'operations.missions',
            'missionId' => $missionId,
            'timeline' => $this->replayService->getTimeline($missionId, $from, $to)['timeline'] ?? [],
        ]);
    }

    public function missionsEvents(Request $request, array $params = []): Response
    {
        $missionId = $this->resolveMissionId($request, $params);
        if ($missionId === null) {
            return $this->error(
                'operations.missions.mission_id_required',
                'missionId requis.',
                400,
                ['domain' => 'operations.missions', 'param' => 'missionId']
            );
        }

        $from = $this->nullableQuery($request, 'from');
        $to = $this->nullableQuery($request, 'to');
        $events = $this->replayService->getEvents($missionId, $from, $to);

        return $this->success([
            'domain' => 'operations.missions',
            'missionId' => $missionId,
            'events' => $events['events'] ?? [],
        ]);
    }

    public function sitrepReport(Request $request, array $params = []): Response
    {
        $body = $this->jsonBody();
        $missionId = $this->missionIdFromInput($request, $body);
        if ($missionId === null) {
            return $this->error(
                'operations.sitrep.mission_id_required',
                'missionId requis.',
                400,
                ['domain' => 'operations.sitrep', 'param' => 'missionId']
            );
        }

        $report = $this->intelFusion->ingestReport($missionId, $body);
        if ($report === []) {
            return $this->error(
                'operations.sitrep.invalid_report',
                'Le SITREP est invalide (target_type non supporté ou payload rejeté).',
                422,
                ['domain' => 'operations.sitrep', 'target_type' => $body['target_type'] ?? $body['targetType'] ?? null]
            );
        }

        return $this->success([
            'domain' => 'operations.sitrep',
            'missionId' => $missionId,
            'report' => $report,
            'status' => ((int) ($report['merged_count'] ?? 1)) > 1 ? 'merged' : 'ok',
        ], 201);
    }

    public function sitrepFused(Request $request, array $params = []): Response
    {
        $missionId = $this->missionIdFromInput($request, $this->jsonBody()) ?? $this->defaultMissionId($request);
        $status = $this->nullableQuery($request, 'status');

        return $this->success([
            'domain' => 'operations.sitrep',
            'missionId' => $missionId,
            'reports' => $this->intelFusion->listFused($missionId, $status),
        ]);
    }

    public function aar(Request $request, array $params = []): Response
    {
        $missionId = $this->resolveMissionId($request, $params);
        if ($missionId === null) {
            return $this->error(
                'operations.aar.mission_id_required',
                'missionId requis.',
                400,
                ['domain' => 'operations.aar', 'param' => 'missionId']
            );
        }

        $aar = $this->replayService->buildAfterActionReview($missionId, $this->nullableQuery($request, 'from'), $this->nullableQuery($request, 'to'));

        return $this->success([
            'domain' => 'operations.aar',
            'missionId' => $missionId,
            'aar' => $aar,
        ]);
    }

    public function aarExportJson(Request $request, array $params = []): Response
    {
        return $this->aar($request, $params);
    }

    public function aarExportPdf(Request $request, array $params = []): Response
    {
        $missionId = $this->resolveMissionId($request, $params);
        if ($missionId === null) {
            return $this->error(
                'operations.aar.mission_id_required',
                'missionId requis.',
                400,
                ['domain' => 'operations.aar', 'param' => 'missionId']
            );
        }

        $aar = $this->replayService->buildAfterActionReview($missionId, $this->nullableQuery($request, 'from'), $this->nullableQuery($request, 'to'));
        $title = trim((string) $request->query('title'));
        $pdf = ReplayAarPdfService::response($aar, $title);
        if ($pdf->statusCode() === 503) {
            return $this->error(
                'operations.aar.pdf_engine_unavailable',
                'Moteur PDF indisponible.',
                503,
                ['domain' => 'operations.aar']
            );
        }

        return $pdf;
    }

    public function readiness(Request $request, array $params = []): Response
    {
        $missionId = $this->resolveMissionId($request, $params);
        if ($missionId === null) {
            return $this->error(
                'operations.readiness.mission_id_required',
                'missionId requis.',
                400,
                ['domain' => 'operations.readiness', 'param' => 'missionId']
            );
        }

        $aar = $this->replayService->buildAfterActionReview($missionId, $this->nullableQuery($request, 'from'), $this->nullableQuery($request, 'to'));
        $assets = $this->assetLogisticsRepository->listByMission($missionId);

        $sustainabilityCounts = ['FULL' => 0, 'LIMITED' => 0, 'CRITICAL' => 0, 'NONE' => 0];
        foreach ($assets as $asset) {
            $evaluation = $this->assetLogisticsEvaluator->evaluate($asset);
            $key = (string) ($evaluation['sustainability'] ?? 'FULL');
            if (!array_key_exists($key, $sustainabilityCounts)) {
                $sustainabilityCounts[$key] = 0;
            }
            $sustainabilityCounts[$key]++;
        }

        return $this->success([
            'domain' => 'operations.readiness',
            'missionId' => $missionId,
            'summary' => $aar['summary'] ?? [],
            'sustainability' => $sustainabilityCounts,
            'errors' => $aar['errors'] ?? [],
        ]);
    }

    public function medical(Request $request, array $params = []): Response
    {
        $missionId = $this->resolveMissionId($request, $params);
        if ($missionId === null) {
            return $this->error(
                'operations.medical.mission_id_required',
                'missionId requis.',
                400,
                ['domain' => 'operations.medical', 'param' => 'missionId']
            );
        }

        $aar = $this->replayService->buildAfterActionReview($missionId, $this->nullableQuery($request, 'from'), $this->nullableQuery($request, 'to'));
        $healthError = null;
        foreach (($aar['errors'] ?? []) as $error) {
            if (($error['code'] ?? null) === 'HEALTH_CRITICAL') {
                $healthError = $error;
                break;
            }
        }

        $mapId = self::DEFAULT_MAP_ID;
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        if (preg_match('/^mission_(\d+)_map_(\d+)$/', $missionId, $m)) {
            $tenantId = (int) $m[1];
            $mapId = (int) $m[2];
        }

        $liveAlerts = [];
        $criticalUnits = [];
        if ($tenantId > 0) {
            $liveAlerts = $this->atakData()->getMedicalAlertsFromChat($tenantId, $mapId, 40);
            $criticalUnits = $this->atakData()->getUnitsWithCriticalHealth($tenantId, $mapId);
        }

        $medicalSignals = [];
        if ($healthError !== null) {
            $medicalSignals[] = $healthError;
        }
        foreach ($liveAlerts as $alert) {
            $medicalSignals[] = [
                'code' => 'LIVE_' . strtoupper((string) ($alert['kind'] ?? 'MEDICAL')),
                'label' => (string) ($alert['summary'] ?? $alert['label'] ?? 'Assistance médicale'),
                'severity' => (string) ($alert['severity'] ?? 'urgent'),
                'callSign' => (string) ($alert['call_sign'] ?? ''),
                'createdAt' => (string) ($alert['created_at'] ?? ''),
                'source' => 'chat',
            ];
        }
        foreach ($criticalUnits as $unit) {
            $medicalSignals[] = [
                'code' => 'UNIT_HEALTH_' . strtoupper((string) ($unit['health'] ?? 'CRITICAL')),
                'label' => MedicalAlertParser::healthLabelFr((string) ($unit['health'] ?? '')) . ' — ' . (string) ($unit['call_sign'] ?? ''),
                'severity' => (string) ($unit['severity'] ?? 'attention'),
                'callSign' => (string) ($unit['call_sign'] ?? ''),
                'createdAt' => (string) ($unit['updated_at'] ?? ''),
                'source' => 'position',
            ];
        }

        return $this->success([
            'domain' => 'operations.medical',
            'missionId' => $missionId,
            'criticalHealthAlerts' => (int) ($healthError['count'] ?? 0) + count($liveAlerts) + count($criticalUnits),
            'medicalSignals' => $medicalSignals,
            'liveAlerts' => $liveAlerts,
            'criticalUnits' => $criticalUnits,
        ]);
    }

    public function logisticsAssets(Request $request, array $params = []): Response
    {
        $missionId = $this->missionIdFromInput($request, $this->jsonBody()) ?? $this->defaultMissionId($request);
        $list = $this->assetLogisticsRepository->listByMission($missionId);
        $evaluated = [];
        foreach ($list as $row) {
            $evaluated[] = array_merge($row, $this->assetLogisticsEvaluator->evaluate($row));
        }

        return $this->success([
            'domain' => 'operations.logistics',
            'missionId' => $missionId,
            'assets' => $evaluated,
        ]);
    }

    public function logisticsUpdate(Request $request, array $params = []): Response
    {
        $body = $this->jsonBody();
        $missionId = $this->missionIdFromInput($request, $body) ?? $this->defaultMissionId($request);
        $assetId = trim((string) ($body['assetId'] ?? $body['asset_id'] ?? ''));
        if ($assetId === '') {
            return $this->error(
                'operations.logistics.asset_id_required',
                'assetId requis.',
                400,
                ['domain' => 'operations.logistics', 'param' => 'assetId']
            );
        }

        $row = $this->assetLogisticsRepository->upsert($missionId, $assetId, $body);

        return $this->success([
            'domain' => 'operations.logistics',
            'missionId' => $missionId,
            'asset' => array_merge($row, $this->assetLogisticsEvaluator->evaluate($row)),
        ]);
    }

    public function comms(Request $request, array $params = []): Response
    {
        $missionId = $this->resolveMissionId($request, $params);
        if ($missionId === null) {
            return $this->error(
                'operations.comms.mission_id_required',
                'missionId requis.',
                400,
                ['domain' => 'operations.comms', 'param' => 'missionId']
            );
        }

        $events = $this->replayService->getEvents($missionId, $this->nullableQuery($request, 'from'), $this->nullableQuery($request, 'to'))['events'] ?? [];
        $sources = [];
        foreach ($events as $event) {
            $source = trim((string) ($event['source'] ?? ''));
            if ($source !== '') {
                $sources[$source] = true;
            }
        }

        return $this->success([
            'domain' => 'operations.comms',
            'missionId' => $missionId,
            'eventsCount' => count($events),
            'activeSources' => array_values(array_keys($sources)),
        ]);
    }

    public function doctrine(Request $request, array $params = []): Response
    {
        return $this->success([
            'domain' => 'operations.doctrine',
            'permissionFamilies' => [
                'operations.missions.*',
                'operations.sitrep.*',
                'operations.aar.*',
                'operations.readiness.*',
                'operations.medical.*',
                'operations.logistics.*',
                'operations.comms.*',
                'operations.doctrine.*',
            ],
        ]);
    }

    private function resolveMissionId(Request $request, array $params): ?string
    {
        $missionId = $params['missionId'] ?? $request->query('missionId') ?? $request->query('mission_id') ?? null;
        if ($missionId === null) {
            return null;
        }
        $missionId = trim((string) $missionId);
        return $missionId !== '' ? $missionId : null;
    }

    private function jsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function missionIdFromInput(Request $request, array $body): ?string
    {
        $missionId = $body['missionId'] ?? $body['mission_id'] ?? $request->query('missionId') ?? $request->query('mission_id') ?? null;
        if ($missionId === null) {
            return null;
        }
        $missionId = trim((string) $missionId);
        return $missionId !== '' ? $missionId : null;
    }

    private function defaultMissionId(Request $request): string
    {
        $tenantId = (int) (Session::get('tenant_id') ?? 1);
        $mapId = (int) ($request->query('mapId') ?: self::DEFAULT_MAP_ID);
        return 'mission_' . $tenantId . '_map_' . $mapId;
    }

    private function nullableQuery(Request $request, string $key): ?string
    {
        $v = trim((string) ($request->query($key) ?? ''));
        return $v === '' ? null : $v;
    }

    private function success(array $payload, int $status = 200): Response
    {
        return Response::json(array_merge(['success' => true], $payload), $status);
    }

    private function error(string $code, string $message, int $status, array $context = []): Response
    {
        return Response::json([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'context' => $context,
            ],
        ], $status);
    }
}
