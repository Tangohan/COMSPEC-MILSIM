<?php

declare(strict_types=1);

namespace App\Services\Tactical;

use App\Repositories\AtakDataRepository;
use App\Repositories\AtakVehicleTrackingRepository;

/**
 * Ingestion d’un lot de télémétrie (Phase A + B + C + D).
 * Phase D : obs / recon / salute / bda / bda_confirm (+ SIGINT → tracks)
 */
final class AtakTelemetryBatchIngest
{
    public const MAX_EVENTS_PER_BATCH = 40;

    public function __construct(
        private AtakDataRepository $atak,
        private AtakTelemetryJournalService $journal,
        private ?MissionWeatherService $weather = null,
        private ?AtakVehicleTrackingRepository $vehicles = null,
        private ?AtakActivityLogService $activityLog = null,
        private ?AtakTelemetryMedicalStore $medicalStore = null,
        private ?AtakTelemetryCommsJournal $commsJournal = null,
        private ?\App\Repositories\AssetLogisticsRepository $logistics = null,
        private ?\App\Services\Logistics\AssetLogisticsEvaluator $logisticsEval = null,
        private ?TacticalTrackService $trackService = null,
    ) {
        $this->weather ??= new MissionWeatherService();
        $this->vehicles ??= new AtakVehicleTrackingRepository();
        $this->activityLog ??= new AtakActivityLogService();
        $this->medicalStore ??= new AtakTelemetryMedicalStore();
        $this->commsJournal ??= new AtakTelemetryCommsJournal();
        try {
            $this->logistics ??= new \App\Repositories\AssetLogisticsRepository();
            $this->logisticsEval ??= new \App\Services\Logistics\AssetLogisticsEvaluator();
        } catch (\Throwable) {
            $this->logistics = null;
            $this->logisticsEval = null;
        }
        try {
            $this->trackService ??= new TacticalTrackService(
                new \App\Repositories\TacticalTrackRepository(),
                $this->atak,
                $this->activityLog
            );
        } catch (\Throwable) {
            $this->trackService = null;
        }
    }

    /**
     * @param array<string, mixed> $batch
     * @param callable(array<string, mixed>): array{ok:bool, error?:string, http?:int} $ingestPosition
     * @return array{
     *   ok: bool,
     *   accepted: int,
     *   rejected: int,
     *   last_id: int,
     *   results: list<array<string, mixed>>,
     *   seq?: int|null
     * }
     */
    public function ingest(
        int $tenantId,
        int $mapId,
        array $batch,
        callable $ingestPosition,
        ?string $actorCallSign = null
    ): array {
        $events = $batch['events'] ?? null;
        if (!is_array($events)) {
            return [
                'ok' => false,
                'accepted' => 0,
                'rejected' => 0,
                'last_id' => $this->journal->latestId($tenantId, $mapId),
                'results' => [['error' => 'events_required']],
                'seq' => isset($batch['seq']) ? (int) $batch['seq'] : null,
            ];
        }

        $events = array_values(array_filter($events, static fn ($e) => is_array($e)));
        if (count($events) > self::MAX_EVENTS_PER_BATCH) {
            $events = array_slice($events, 0, self::MAX_EVENTS_PER_BATCH);
        }

        $accepted = 0;
        $rejected = 0;
        $results = [];
        $lastId = $this->journal->latestId($tenantId, $mapId);
        $batchTs = isset($batch['ts']) && is_numeric($batch['ts']) ? (int) $batch['ts'] : time();
        $seq = isset($batch['seq']) && is_numeric($batch['seq']) ? (int) $batch['seq'] : null;

        foreach ($events as $idx => $ev) {
            $type = strtolower(trim((string) ($ev['t'] ?? $ev['type'] ?? '')));
            if ($type === '') {
                $rejected++;
                $results[] = ['i' => $idx, 'ok' => false, 'error' => 'missing_type'];
                continue;
            }

            try {
                $outcome = match ($type) {
                    'pos', 'position', 'heartbeat' => $this->handlePosition($tenantId, $mapId, $ev, $batch, $ingestPosition),
                    'veh', 'vehicle', 'vehicles' => $this->handleVehicle($tenantId, $mapId, $ev),
                    'weather', 'wx' => $this->handleWeather($tenantId, $mapId, $ev),
                    'sigint' => $this->handleSigint($tenantId, $mapId, $ev, $actorCallSign),
                    'med', 'medical', 'medical_alert' => $this->handleMedical($tenantId, $mapId, $ev, $actorCallSign),
                    'med_clear', 'medical_clear', 'medical_resolved' => $this->handleMedicalClear($tenantId, $mapId, $ev, $actorCallSign),
                    'unit', 'unit_event' => $this->handleUnitEvent($tenantId, $mapId, $ev, $actorCallSign),
                    'combat', 'fire' => $this->handleCombat($tenantId, $mapId, $ev, $actorCallSign),
                    'logstat', 'logistics' => $this->handleLogstat($tenantId, $mapId, $ev, $actorCallSign),
                    'comms', 'acre', 'radio' => $this->handleComms($tenantId, $mapId, $ev, $actorCallSign),
                    'state' => $this->handleState($tenantId, $mapId, $ev, $actorCallSign),
                    'flight', 'flight_manifest' => $this->handleFlight($tenantId, $mapId, $ev, $actorCallSign),
                    'obs', 'observation', 'recon' => $this->handleTrackEvent($tenantId, $mapId, $ev, $actorCallSign, 'recon'),
                    'salute' => $this->handleTrackEvent($tenantId, $mapId, $ev, $actorCallSign, 'salute'),
                    'bda' => $this->handleTrackEvent($tenantId, $mapId, $ev, $actorCallSign, 'bda'),
                    'bda_confirm' => $this->handleTrackEvent($tenantId, $mapId, $ev, $actorCallSign, 'bda_confirm'),
                    default => ['ok' => false, 'error' => 'unsupported_type', 'http' => 422],
                };
            } catch (\Throwable $e) {
                $outcome = ['ok' => false, 'error' => 'exception', 'http' => 500];
            }

            if (!empty($outcome['ok'])) {
                $accepted++;
                $jid = $this->journal->append($tenantId, $mapId, $type, [
                    'seq' => $seq,
                    'batch_ts' => $batchTs,
                    'event_index' => $idx,
                    'call_sign' => $outcome['call_sign'] ?? ($ev['call_sign'] ?? $ev['cs'] ?? null),
                    'priority' => $ev['p'] ?? $ev['priority'] ?? null,
                ]);
                if ($jid > 0) {
                    $lastId = $jid;
                }
                $results[] = ['i' => $idx, 'ok' => true, 't' => $type, 'id' => $jid];
            } else {
                $rejected++;
                $results[] = [
                    'i' => $idx,
                    'ok' => false,
                    't' => $type,
                    'error' => (string) ($outcome['error'] ?? 'rejected'),
                    'http' => (int) ($outcome['http'] ?? 400),
                ];
            }
        }

        return [
            'ok' => $rejected === 0,
            'accepted' => $accepted,
            'rejected' => $rejected,
            'last_id' => $lastId,
            'results' => $results,
            'seq' => $seq,
        ];
    }

    /**
     * @param array<string, mixed> $ev
     * @param array<string, mixed> $batch
     * @param callable(array<string, mixed>): array{ok:bool, error?:string, http?:int, call_sign?:string} $ingestPosition
     * @return array{ok:bool, error?:string, http?:int, call_sign?:string}
     */
    private function handlePosition(int $tenantId, int $mapId, array $ev, array $batch, callable $ingestPosition): array
    {
        $body = $this->normalizePositionEvent($mapId, $ev, $batch);
        $result = $ingestPosition($body);
        if (!is_array($result)) {
            return ['ok' => false, 'error' => 'bad_ingest_result', 'http' => 500];
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $ev
     * @param array<string, mixed> $batch
     * @return array<string, mixed>
     */
    private function normalizePositionEvent(int $mapId, array $ev, array $batch): array
    {
        $data = is_array($ev['data'] ?? null) ? $ev['data'] : [];
        $extra = $data['extra'] ?? $ev['extra'] ?? [];
        if (!is_array($extra)) {
            $extra = [];
        }

        $x = $ev['x'] ?? $ev['pos_x'] ?? $data['pos_x'] ?? $data['x'] ?? null;
        $y = $ev['y'] ?? $ev['pos_y'] ?? $data['pos_y'] ?? $data['y'] ?? null;
        $h = $ev['h'] ?? $ev['heading'] ?? $data['heading'] ?? null;
        $z = $ev['z'] ?? $ev['asl_z'] ?? $ev['pos_z'] ?? $data['asl_z'] ?? $data['pos_z'] ?? null;

        $callSign = trim((string) ($ev['call_sign'] ?? $ev['cs'] ?? $data['call_sign'] ?? $data['callsign'] ?? ''));
        $role = (string) ($ev['role'] ?? $data['role'] ?? '');
        $kind = strtolower(trim((string) ($ev['t'] ?? $ev['type'] ?? 'pos')));
        if ($kind === 'heartbeat') {
            $extra['telemetry_kind'] = 'heartbeat';
        } elseif (!isset($extra['telemetry_kind'])) {
            $extra['telemetry_kind'] = 'position';
        }

        $body = [
            'mapId' => (int) ($ev['mapId'] ?? $ev['map_id'] ?? $data['mapId'] ?? $data['map_id'] ?? $batch['mapId'] ?? $batch['map_id'] ?? $mapId),
            'call_sign' => $callSign,
            'pos_x' => $x,
            'pos_y' => $y,
            'heading' => $h,
            'role' => $role,
            'extra' => $extra,
        ];
        if ($z !== null && $z !== '') {
            $body['asl_z'] = $z;
            $body['pos_z'] = $z;
        }
        foreach (['steam_uid', 'session_token', 'mod_version', 'group_name', 'group_id', 'health', 'fuel', 'ammo'] as $k) {
            if (isset($ev[$k])) {
                $body[$k] = $ev[$k];
            } elseif (isset($data[$k])) {
                $body[$k] = $data[$k];
            }
        }
        // Forme compacte : champs médicaux / radio souvent au top-level événement.
        foreach (['health', 'fuel', 'ammo', 'radio', 'radioFreq', 'vehicle', 'speed'] as $k) {
            if (isset($ev[$k]) && !isset($extra[$k])) {
                $extra[$k] = $ev[$k];
            }
        }
        if (isset($ev['state']) && !isset($extra['health'])) {
            $extra['health'] = $ev['state'];
        }
        $body['extra'] = $extra;

        return $body;
    }

    /**
     * @param array<string, mixed> $ev
     * @return array{ok:bool, error?:string, http?:int, call_sign?:string}
     */
    private function handleVehicle(int $tenantId, int $mapId, array $ev): array
    {
        $data = is_array($ev['data'] ?? null) ? $ev['data'] : $ev;
        $payload = array_merge(is_array($data) ? $data : [], [
            'tenant_id' => $tenantId,
            'context_id' => $mapId,
            'mapId' => $mapId,
            'map_id' => $mapId,
        ]);
        if (isset($ev['id']) && !isset($payload['vehicle_id']) && !isset($payload['id'])) {
            $payload['vehicle_id'] = $ev['id'];
            $payload['id'] = $ev['id'];
        }
        if (isset($ev['state']) && !isset($payload['state'])) {
            $payload['state'] = $ev['state'];
        }
        try {
            $id = $this->vehicles->upsert($payload);
            return ['ok' => $id > 0, 'error' => $id > 0 ? null : 'vehicle_upsert_failed', 'http' => $id > 0 ? 200 : 422];
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'vehicle_exception', 'http' => 500];
        }
    }

    /**
     * @param array<string, mixed> $ev
     * @return array{ok:bool, error?:string, http?:int}
     */
    private function handleWeather(int $tenantId, int $mapId, array $ev): array
    {
        $data = is_array($ev['data'] ?? null) ? $ev['data'] : $ev;
        try {
            $this->weather->put($tenantId, $mapId, is_array($data) ? $data : []);
            return ['ok' => true];
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'weather_exception', 'http' => 500];
        }
    }

    /**
     * @param array<string, mixed> $ev
     * @return array{ok:bool, error?:string, http?:int, call_sign?:string}
     */
    private function handleSigint(int $tenantId, int $mapId, array $ev, ?string $actorCallSign): array
    {
        $data = is_array($ev['data'] ?? null) ? $ev['data'] : [];
        $callSign = trim((string) ($ev['call_sign'] ?? $ev['cs'] ?? $data['call_sign'] ?? $actorCallSign ?? 'Unknown'));
        $posX = (float) ($ev['x'] ?? $ev['pos_x'] ?? $data['pos_x'] ?? 0);
        $posY = (float) ($ev['y'] ?? $ev['pos_y'] ?? $data['pos_y'] ?? 0);
        $bearing = isset($ev['bearing']) ? (float) $ev['bearing'] : (isset($data['bearing']) ? (float) $data['bearing'] : null);
        try {
            $this->atak->addSigint($tenantId, $mapId, $callSign !== '' ? $callSign : 'Unknown', $posX, $posY, $bearing);
            try {
                $this->activityLog->record(
                    $tenantId,
                    $mapId,
                    AtakActivityLogService::TYPE_SIGINT,
                    'Signalement radio — ' . ($callSign !== '' ? $callSign : 'Unknown'),
                    $callSign !== '' ? $callSign : null
                );
            } catch (\Throwable) {
            }
            if ($this->trackService !== null) {
                $sigEv = array_merge($data, $ev, [
                    't' => 'sigint',
                    'kind' => 'sigint',
                    'call_sign' => $callSign,
                    'x' => $posX,
                    'y' => $posY,
                    'bearing' => $bearing,
                ]);
                try {
                    $this->trackService->ingestObservation($tenantId, $mapId, $sigEv, $actorCallSign);
                } catch (\Throwable) {
                }
            }

            return ['ok' => true, 'call_sign' => $callSign];
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'sigint_exception', 'http' => 500];
        }
    }

    /**
     * @param array<string, mixed> $ev
     * @return array{ok:bool, error?:string, http?:int, call_sign?:string}
     */
    private function handleTrackEvent(int $tenantId, int $mapId, array $ev, ?string $actorCallSign, string $kind): array
    {
        if ($this->trackService === null) {
            return ['ok' => false, 'error' => 'tracks_unavailable', 'http' => 503];
        }
        $data = is_array($ev['data'] ?? null) ? $ev['data'] : [];
        $payload = array_merge(is_array($data) ? $data : [], $ev);
        $payload['t'] = $kind;
        $payload['kind'] = $kind;
        try {
            $result = $this->trackService->ingestObservation($tenantId, $mapId, $payload, $actorCallSign);
            if (empty($result['ok'])) {
                return ['ok' => false, 'error' => (string) ($result['error'] ?? 'track_rejected'), 'http' => 422];
            }
            $cs = trim((string) ($payload['call_sign'] ?? $actorCallSign ?? ''));

            return ['ok' => true, 'call_sign' => $cs];
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'track_exception', 'http' => 500];
        }
    }

    /**
     * @param array<string, mixed> $ev
     * @return array{ok:bool, error?:string, http?:int, call_sign?:string}
     */
    private function handleMedical(int $tenantId, int $mapId, array $ev, ?string $actorCallSign): array
    {
        $data = is_array($ev['data'] ?? null) ? $ev['data'] : [];
        $payload = array_merge(is_array($data) ? $data : [], $ev);
        if (!isset($payload['call_sign']) && $actorCallSign) {
            $payload['call_sign'] = $actorCallSign;
        }
        try {
            $alert = $this->medicalStore->upsertFromEvent($tenantId, $mapId, $payload);
            $cs = (string) ($alert['call_sign'] ?? '');
            try {
                $this->activityLog->record(
                    $tenantId,
                    $mapId,
                    AtakActivityLogService::TYPE_MEDEVAC,
                    'Alerte médicale — ' . ($cs !== '' ? $cs : 'opérateur') . ' — ' . (string) ($alert['label'] ?? ''),
                    $cs !== '' ? $cs : null,
                    ['source' => 'telemetry', 'kind' => $alert['kind'] ?? null]
                );
            } catch (\Throwable) {
            }

            return ['ok' => true, 'call_sign' => $cs];
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'medical_exception', 'http' => 500];
        }
    }

    /**
     * @param array<string, mixed> $ev
     * @return array{ok:bool, error?:string, http?:int, call_sign?:string}
     */
    private function handleMedicalClear(int $tenantId, int $mapId, array $ev, ?string $actorCallSign): array
    {
        $cs = trim((string) ($ev['call_sign'] ?? $ev['cs'] ?? $actorCallSign ?? ''));
        if ($cs === '') {
            return ['ok' => false, 'error' => 'call_sign_required', 'http' => 422];
        }
        $reason = strtolower(trim((string) ($ev['reason'] ?? $ev['status'] ?? 'annule')));
        if (!in_array($reason, ['annule', 'traite', 'kia'], true)) {
            $reason = 'annule';
        }
        try {
            $this->medicalStore->clearForCallSign($tenantId, $mapId, $cs, $reason);

            return ['ok' => true, 'call_sign' => $cs];
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'medical_clear_exception', 'http' => 500];
        }
    }

    /**
     * GetIn / GetOut / leader change, etc.
     *
     * @param array<string, mixed> $ev
     * @return array{ok:bool, error?:string, http?:int, call_sign?:string}
     */
    private function handleUnitEvent(int $tenantId, int $mapId, array $ev, ?string $actorCallSign): array
    {
        $data = is_array($ev['data'] ?? null) ? $ev['data'] : [];
        $action = strtolower(trim((string) ($ev['action'] ?? $data['action'] ?? $ev['state'] ?? '')));
        $cs = trim((string) ($ev['call_sign'] ?? $ev['cs'] ?? $data['call_sign'] ?? $actorCallSign ?? ''));
        $veh = trim((string) ($ev['vehicle'] ?? $ev['veh'] ?? $data['vehicle'] ?? $data['vehicle_callsign'] ?? ''));
        $role = trim((string) ($ev['role'] ?? $data['role'] ?? ''));

        $label = match ($action) {
            'enter', 'getin', 'mounted' => 'Embarquement' . ($veh !== '' ? ' — ' . $veh : ''),
            'exit', 'getout', 'dismounted' => 'Débarquement' . ($veh !== '' ? ' — ' . $veh : ''),
            'leader' => 'Changement de chef de groupe',
            default => 'Événement unité' . ($action !== '' ? ' — ' . $action : ''),
        };

        try {
            $this->activityLog->record(
                $tenantId,
                $mapId,
                AtakActivityLogService::TYPE_INGEST,
                ($cs !== '' ? $cs . ' — ' : '') . $label,
                $cs !== '' ? $cs : null,
                [
                    'source' => 'telemetry',
                    'unit_action' => $action,
                    'vehicle' => $veh,
                    'role' => $role,
                    'x' => $ev['x'] ?? $data['x'] ?? null,
                    'y' => $ev['y'] ?? $data['y'] ?? null,
                ]
            );

            return ['ok' => true, 'call_sign' => $cs];
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'unit_exception', 'http' => 500];
        }
    }

    /**
     * @param array<string, mixed> $ev
     * @return array{ok:bool, error?:string, http?:int, call_sign?:string}
     */
    private function handleCombat(int $tenantId, int $mapId, array $ev, ?string $actorCallSign): array
    {
        $data = is_array($ev['data'] ?? null) ? $ev['data'] : [];
        $kind = strtolower(trim((string) ($ev['kind'] ?? $data['kind'] ?? $ev['t'] ?? 'fire')));
        $cs = trim((string) ($ev['call_sign'] ?? $ev['cs'] ?? $actorCallSign ?? ''));
        $n = isset($ev['n']) && is_numeric($ev['n']) ? (int) $ev['n'] : (isset($data['n']) && is_numeric($data['n']) ? (int) $data['n'] : 0);
        $label = match ($kind) {
            'hit' => 'Impact reçu',
            'missile' => 'Tir missile',
            'exchange' => 'Échange de tirs',
            'fire' => $n > 1 ? ('Tirs — x' . $n) : 'Tir',
            default => 'Contact armé',
        };

        try {
            $this->activityLog->record(
                $tenantId,
                $mapId,
                AtakActivityLogService::TYPE_TACTICAL_ALERT,
                ($cs !== '' ? $cs . ' — ' : '') . $label,
                $cs !== '' ? $cs : null,
                [
                    'source' => 'telemetry',
                    'combat_kind' => $kind,
                    'n' => $n,
                    'x' => $ev['x'] ?? $data['x'] ?? null,
                    'y' => $ev['y'] ?? $data['y'] ?? null,
                ]
            );

            return ['ok' => true, 'call_sign' => $cs];
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'combat_exception', 'http' => 500];
        }
    }

    /**
     * @param array<string, mixed> $ev
     * @return array{ok:bool, error?:string, http?:int, call_sign?:string}
     */
    private function handleLogstat(int $tenantId, int $mapId, array $ev, ?string $actorCallSign): array
    {
        if ($this->logistics === null) {
            return ['ok' => false, 'error' => 'logistics_unavailable', 'http' => 503];
        }
        $data = is_array($ev['data'] ?? null) ? $ev['data'] : [];
        $payload = array_merge(is_array($data) ? $data : [], $ev);
        $cs = trim((string) ($payload['callsign'] ?? $payload['call_sign'] ?? $actorCallSign ?? ''));
        $assetId = trim((string) ($payload['assetId'] ?? $payload['asset_id'] ?? $cs));
        if ($assetId === '') {
            return ['ok' => false, 'error' => 'assetId_required', 'http' => 422];
        }
        $missionId = trim((string) ($payload['missionId'] ?? $payload['mission_id'] ?? ''));
        if ($missionId === '') {
            $missionId = 'mission_' . $tenantId . '_map_' . $mapId;
        }
        if (isset($payload['magazinesCount']) && !isset($payload['ammo_state_json'])) {
            $payload['ammo_state_json'] = [
                'magazinesCount' => (int) $payload['magazinesCount'],
                'weaponsOnline' => true,
                'pers_fit' => $payload['pers_fit'] ?? null,
                'pers_wia' => $payload['pers_wia'] ?? null,
                'pers_kia' => $payload['pers_kia'] ?? null,
            ];
        }
        try {
            $row = $this->logistics->upsert($missionId, $assetId, $payload);
            if ($this->logisticsEval !== null) {
                $this->logisticsEval->evaluate(array_merge($row, ['asset_id' => $assetId]));
            }
            try {
                $fuelPct = isset($payload['fuel_ratio']) ? (int) round(((float) $payload['fuel_ratio']) * 100) : null;
                $this->activityLog->record(
                    $tenantId,
                    $mapId,
                    AtakActivityLogService::TYPE_INGEST,
                    'LOGSTAT — ' . ($cs !== '' ? $cs : $assetId)
                        . ($fuelPct !== null ? (' — carburant ' . $fuelPct . ' %') : ''),
                    $cs !== '' ? $cs : null,
                    ['source' => 'telemetry', 'kind' => 'logstat', 'fuel_ratio' => $payload['fuel_ratio'] ?? null]
                );
            } catch (\Throwable) {
            }

            return ['ok' => true, 'call_sign' => $cs];
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'logstat_exception', 'http' => 500];
        }
    }

    /**
     * @param array<string, mixed> $ev
     * @return array{ok:bool, error?:string, http?:int, call_sign?:string}
     */
    private function handleComms(int $tenantId, int $mapId, array $ev, ?string $actorCallSign): array
    {
        $data = is_array($ev['data'] ?? null) ? $ev['data'] : [];
        $payload = array_merge(is_array($data) ? $data : [], $ev);
        if (!isset($payload['call_sign']) && $actorCallSign) {
            $payload['call_sign'] = $actorCallSign;
        }
        $cs = trim((string) ($payload['call_sign'] ?? ''));
        try {
            $id = $this->commsJournal->append($tenantId, $mapId, $payload);
            $action = strtolower(trim((string) ($payload['action'] ?? 'tx')));
            $label = match ($action) {
                'tx_start' => 'Radio — début émission',
                'tx_end' => 'Radio — fin émission'
                    . (isset($payload['duration_s']) ? (' (' . round((float) $payload['duration_s'], 1) . ' s)') : ''),
                default => 'Radio — activité',
            };
            try {
                $this->activityLog->record(
                    $tenantId,
                    $mapId,
                    AtakActivityLogService::TYPE_CHAT,
                    ($cs !== '' ? $cs . ' — ' : '') . $label,
                    $cs !== '' ? $cs : null,
                    [
                        'source' => 'telemetry',
                        'comms' => true,
                        'freq' => $payload['freq'] ?? null,
                        'channel' => $payload['channel'] ?? null,
                        'duration_s' => $payload['duration_s'] ?? null,
                        'journal_id' => $id,
                    ]
                );
            } catch (\Throwable) {
            }

            return ['ok' => true, 'call_sign' => $cs];
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'comms_exception', 'http' => 500];
        }
    }

    /**
     * @param array<string, mixed> $ev
     * @return array{ok:bool, error?:string, http?:int, call_sign?:string}
     */
    private function handleState(int $tenantId, int $mapId, array $ev, ?string $actorCallSign): array
    {
        $data = is_array($ev['data'] ?? null) ? $ev['data'] : [];
        $payload = array_merge(is_array($data) ? $data : [], $ev);
        $cs = trim((string) ($payload['call_sign'] ?? $actorCallSign ?? ''));
        // Journal uniquement (l’état live reste porté par la position BFT).
        try {
            $bits = [];
            if (isset($payload['health'])) {
                $bits[] = 'santé ' . (string) $payload['health'];
            }
            if (isset($payload['fuel']) && (string) $payload['fuel'] !== '') {
                $bits[] = 'carburant ' . (string) $payload['fuel'] . '%';
            }
            if (isset($payload['crew_count'])) {
                $bits[] = 'équipage ' . (int) $payload['crew_count'];
            }
            $this->activityLog->record(
                $tenantId,
                $mapId,
                AtakActivityLogService::TYPE_INGEST,
                ($cs !== '' ? $cs . ' — ' : '') . 'État'
                    . ($bits !== [] ? (' — ' . implode(', ', $bits)) : ''),
                $cs !== '' ? $cs : null,
                ['source' => 'telemetry', 'kind' => 'state', 'state' => $payload]
            );

            return ['ok' => true, 'call_sign' => $cs];
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'state_exception', 'http' => 500];
        }
    }

    /**
     * @param array<string, mixed> $ev
     * @return array{ok:bool, error?:string, http?:int, call_sign?:string}
     */
    private function handleFlight(int $tenantId, int $mapId, array $ev, ?string $actorCallSign): array
    {
        $data = is_array($ev['data'] ?? null) ? $ev['data'] : [];
        $payload = array_merge(is_array($data) ? $data : [], $ev);
        $callsign = trim((string) ($payload['callsign'] ?? $payload['call_sign'] ?? $actorCallSign ?? ''));
        if ($callsign === '') {
            $callsign = 'Aeronef';
        }
        $payload['mapId'] = $mapId;
        $payload['map_id'] = $mapId;
        $payload['callsign'] = $callsign;
        $payload['call_sign'] = $callsign;
        if (isset($payload['pos']) && is_array($payload['pos'])) {
            $payload['pos_x'] = $payload['pos'][0] ?? ($payload['pos_x'] ?? null);
            $payload['pos_y'] = $payload['pos'][1] ?? ($payload['pos_y'] ?? null);
        }
        try {
            $this->atak->upsertAirAsset($tenantId, $mapId, $callsign, $payload);

            return ['ok' => true, 'call_sign' => $callsign];
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'flight_exception', 'http' => 500];
        }
    }
}
