<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseDigitalLabRepository;
use App\Support\SseDigitalTerrainDetail;
use App\Support\SseDomexContract;

/**
 * Orchestration du laboratoire numérique : saisie, acquisition simulée, extraction de profil, signaux.
 * Les détections restent des propositions — jamais des conclusions.
 */
final class SseDigitalLabService
{
    /** kind de POST /api/sse/identity/checks : contenu d'un support relevé en jeu (voir ingestDetailPayload). */
    public const DETAIL_KIND = 'digital_detail';

    public function __construct(
        private ?SseDigitalLabRepository $repo = null,
        private ?SseTerrainService $terrain = null,
    ) {
        $this->repo ??= new SseDigitalLabRepository();
        $this->terrain ??= new SseTerrainService();
    }

    public function repository(): SseDigitalLabRepository
    {
        return $this->repo;
    }

    /**
     * Enregistre un support + saisine minimale.
     *
     * @param array<string, mixed> $input
     * @return array{device_id:int,seizure_id:int}
     */
    public function registerDevice(int $tenantId, array $input, ?int $userId = null): array
    {
        $status = (string) ($input['status'] ?? 'seized');
        $deviceId = $this->repo->createDevice($tenantId, $input, $userId);
        $seizureId = $this->repo->createSeizure($tenantId, $deviceId, [
            'mission_id' => $input['mission_id'] ?? null,
            'case_id' => $input['case_id'] ?? null,
            'seal_label' => $input['seal_label'] ?? null,
            'packaging' => $input['packaging_notes'] ?? null,
            'discovered_at' => $input['discovered_at'] ?? null,
            'seized_at' => $input['seized_at'] ?? date('Y-m-d H:i:s'),
            'observations' => $input['observations'] ?? null,
            'handlers' => array_values(array_filter([
                $input['seized_by_label'] ?? null,
            ])),
            'classification' => $input['classification'] ?? 'confidentiel',
        ], $userId);

        $this->repo->createTimelineEvent($tenantId, [
            'device_id' => $deviceId,
            'case_id' => $input['case_id'] ?? null,
            'event_type' => 'seizure',
            'event_at' => $input['seized_at'] ?? date('Y-m-d H:i:s'),
            'title' => 'Saisie du support',
            'detail' => 'Support enregistré dans le laboratoire numérique.',
            'interest_level' => 'a_surveiller',
        ], $userId);

        if ($status === 'discovered') {
            $this->repo->updateDeviceStatus($deviceId, $tenantId, 'seized');
        }

        return ['device_id' => $deviceId, 'seizure_id' => $seizureId];
    }

    /**
     * Enregistre une acquisition transmise depuis le terminal SEEK (terrain).
     *
     * @param array<string, mixed> $body
     * @return array{
     *     ok: bool,
     *     duplicate: bool,
     *     device_id: int,
     *     seizure_id: int,
     *     acquisition_id: int,
     *     reference_code: string,
     *     packets_created: int,
     *     packets_total: int,
     *     detail: array<string, int>
     * }
     */
    public function ingestFromTerrain(int $tenantId, array $body, ?int $userId = null): array
    {
        $armaId = trim((string) ($body['record_id'] ?? $body['sse_uid'] ?? $body['arma_object_id'] ?? ''));
        $idem = trim((string) ($body['idempotency_key'] ?? ''));
        if ($armaId === '' && $idem !== '') {
            $armaId = $idem;
        }

        $existing = $armaId !== '' ? $this->repo->findDeviceByArmaObjectId($tenantId, $armaId) : null;
        if ($existing === null) {
            $domexPreview = $this->extractDomex($body);
            $nodeKey = (string) ($domexPreview['node']['node_id'] ?? '');
            if ($nodeKey !== '') {
                $existing = $this->repo->findDeviceByNodeKey($tenantId, $nodeKey);
            }
        }
        if ($existing !== null) {
            $acqs = $this->repo->listAcquisitions($tenantId, [
                'device_id' => (int) $existing['id'],
                'limit' => 1,
            ]);
            $acq = $acqs[0] ?? null;
            $packets = $this->ingestDomexPayload(
                $tenantId,
                (int) $existing['id'],
                isset($acq['id']) ? (int) $acq['id'] : null,
                $body,
                $userId
            );
            // Support déjà connu (envoi du résumé d'abord, puis du détail relevé) : le contenu s'ajoute une seule fois.
            $detailCounts = [];
            $detail = $this->terrainDetail($body);
            if (!SseDigitalTerrainDetail::isEmpty($detail) && isset($acq['id'])) {
                $detailCounts = $this->storeDetail($tenantId, (int) $existing['id'], (int) $acq['id'], $detail, $userId);
            }

            return [
                'ok' => true,
                'duplicate' => true,
                'device_id' => (int) $existing['id'],
                'seizure_id' => (int) ($existing['seizure_id'] ?? 0),
                'acquisition_id' => (int) ($acq['id'] ?? 0),
                'reference_code' => (string) ($acq['reference_code'] ?? $existing['reference_code'] ?? ''),
                'packets_created' => $packets['created'],
                'packets_total' => $packets['total'],
                'detail' => $detailCounts,
            ];
        }

        $phone = is_array($body['phone_summary'] ?? null) ? $body['phone_summary'] : [];
        $computer = is_array($body['computer_summary'] ?? null) ? $body['computer_summary'] : [];
        $sourceType = (string) ($body['source_type'] ?? $phone['deviceType'] ?? $computer['deviceType'] ?? '');
        $deviceType = self::mapTerrainDeviceType($sourceType, $phone, $computer);

        $lines = $body['extraction_lines'] ?? [];
        if (!is_array($lines)) {
            $lines = $lines === '' || $lines === null ? [] : [(string) $lines];
        }
        $lineTexts = [];
        foreach ($lines as $line) {
            if (is_array($line)) {
                $line = implode(' — ', array_map(static fn ($v) => is_scalar($v) ? (string) $v : '', $line));
            }
            $t = trim((string) $line);
            if ($t !== '') {
                $lineTexts[] = $t;
            }
        }

        $obsParts = [];
        $grid = trim((string) ($body['grid_reference'] ?? ''));
        if ($grid !== '') {
            $obsParts[] = 'Découvert en ' . $grid;
        }
        $model = trim((string) ($phone['model'] ?? $computer['model'] ?? ''));
        if ($model !== '') {
            $obsParts[] = 'Modèle : ' . $model;
        }
        $owner = trim((string) ($phone['owner'] ?? $computer['owner'] ?? ''));
        if ($owner !== '') {
            $obsParts[] = 'Titulaire apparent : ' . $owner;
        }
        foreach (array_slice($lineTexts, 0, 12) as $line) {
            $obsParts[] = $line;
        }

        $counts = [];
        foreach (['contacts' => 'contacts', 'messages' => 'messages', 'calls' => 'appels', 'images' => 'images'] as $key => $label) {
            $n = (int) ($phone[$key] ?? 0);
            if ($n > 0) {
                $counts[] = $n . ' ' . $label;
            }
        }
        if ($counts !== []) {
            $obsParts[] = 'Volume relevé : ' . implode(', ', $counts);
        }

        $personId = (int) ($body['person_id'] ?? $body['athena_person_id'] ?? 0);
        $domex = $this->extractDomex($body);
        $node = $domex['node'];
        if ($node['node_id'] !== '' && $node['device_type'] !== '') {
            $deviceType = $node['device_type'];
        }
        $deviceId = $this->repo->createDevice($tenantId, [
            'device_type' => $deviceType,
            'model' => $model !== '' ? $model : null,
            'serial_number' => $this->firstNonEmpty([
                $phone['imei'] ?? null,
                $phone['sim'] ?? null,
                $computer['serial'] ?? null,
            ]),
            'discovery_place' => $grid !== '' ? $grid : null,
            'person_id' => $personId > 0 ? $personId : null,
            'mission_label' => $this->firstNonEmpty([$body['mission_id'] ?? null, $body['case_reference'] ?? null]),
            'seized_by_label' => $this->firstNonEmpty([$body['collector'] ?? null, $body['submitter_callsign'] ?? null]),
            'observations' => $obsParts !== [] ? implode("\n", $obsParts) : 'Acquisition transmise depuis le terrain.',
            'data_profile' => 'terrain_seek',
            'arma_object_id' => $armaId !== '' ? $armaId : null,
            'status' => 'seized',
            'has_sim' => !empty($phone['sim']),
        ], $userId);

        $registered = $this->repo->createSeizure($tenantId, $deviceId, [
            'seized_at' => date('Y-m-d H:i:s'),
            'observations' => $obsParts !== [] ? implode("\n", $obsParts) : null,
            'handlers' => array_values(array_filter([
                $body['collector'] ?? null,
                $body['submitter_callsign'] ?? null,
            ])),
        ], $userId);

        $now = date('Y-m-d H:i:s');
        $acqId = $this->repo->createAcquisition($tenantId, $deviceId, [
            'seizure_id' => $registered,
            'method' => 'logical',
            'operator_label' => $this->firstNonEmpty([$body['collector'] ?? null, $body['submitter_callsign'] ?? null, 'Terrain']),
            'started_at' => $now,
            'ended_at' => $now,
            'status' => 'completed',
            'file_count' => count($lineTexts),
            'artifact_count' => min(40, count($lineTexts)),
            'data_profile' => 'terrain_seek',
            'tool_name' => 'Terminal SEEK',
            'tool_version' => '1.0',
            'reserves' => null,
        ], $userId);

        $this->repo->addAcquisitionLog($tenantId, $acqId, 'Acquisition reçue du terrain.', 'info', $userId);
        $nArt = 0;
        foreach (array_slice($lineTexts, 0, 40) as $i => $line) {
            $this->repo->createArtifact($tenantId, $deviceId, $acqId, [
                'name' => 'Extrait terrain #' . ($i + 1),
                'category' => 'document',
                'payload' => ['text' => $line],
                'interest_level' => 'a_surveiller',
                'status' => 'unexamined',
            ], $userId);
            $nArt++;
        }
        if ($nArt === 0 && $obsParts !== []) {
            $this->repo->createArtifact($tenantId, $deviceId, $acqId, [
                'name' => 'Synthèse d’extraction',
                'category' => 'document',
                'payload' => ['text' => implode("\n", $obsParts)],
                'interest_level' => 'courant',
                'status' => 'unexamined',
            ], $userId);
        }

        // Contenu relevé en jeu (contacts, messages, appels, images, positions, comptes, applications, pièces) : c'est lui
        // qui remplit les écrans téléphone, ordinateur, communications, artefacts et chronologie. Sans contenu (ancien
        // mod, support seulement identifié), l'acquisition le dit : réserves, jamais de données inventées.
        $detail = $this->terrainDetail($body);
        $detailCounts = [];
        if (!SseDigitalTerrainDetail::isEmpty($detail)) {
            $detailCounts = $this->storeDetail($tenantId, $deviceId, $acqId, $detail, $userId);
        } else {
            $totals = [];
            foreach (['contacts' => 'contacts', 'messages' => 'messages', 'calls' => 'appels', 'images' => 'images', 'locations' => 'positions'] as $key => $label) {
                if ((int) ($phone[$key] ?? 0) > 0) {
                    $totals[] = (int) $phone[$key] . ' ' . $label;
                }
            }
            $this->repo->updateAcquisition($acqId, $tenantId, [
                'status' => 'completed_with_reserves',
                'is_partial' => 1,
                'reserves' => 'Support identifié, contenu non relevé : le terrain n’a transmis que '
                    . ($totals !== [] ? 'les totaux (' . implode(', ', $totals) . ')' : 'son identification')
                    . '. Extraire le support en jeu (exploitation complète) puis le retransmettre pour remplir les contacts, messages, appels et images.',
            ]);
        }

        $this->repo->updateDeviceStatus($deviceId, $tenantId, 'acquired');
        $packets = $this->ingestDomexPayload($tenantId, $deviceId, $acqId, $body, $userId);
        $acq = $this->repo->findAcquisition($acqId, $tenantId);
        $this->repo->createTimelineEvent($tenantId, [
            'device_id' => $deviceId,
            'acquisition_id' => $acqId,
            'event_type' => 'acquisition',
            'event_at' => $now,
            'title' => 'Acquisition terrain',
            'detail' => 'Reçue depuis le terminal SEEK.',
            'interest_level' => 'a_surveiller',
        ], $userId);

        return [
            'ok' => true,
            'duplicate' => false,
            'device_id' => $deviceId,
            'seizure_id' => $registered,
            'acquisition_id' => $acqId,
            'reference_code' => (string) ($acq['reference_code'] ?? ''),
            'packets_created' => $packets['created'],
            'packets_total' => $packets['total'],
            'detail' => $detailCounts,
        ];
    }

    /**
     * Détail d'un support envoyé à part (kind « digital_detail » de POST /api/sse/identity/checks, par la file du SSE qui
     * découpe les gros envois) : même identifiant de support (record_id) que l'acquisition. Si le support n'est pas encore
     * au laboratoire (détail arrivé avant l'acquisition), il est créé avec ce détail ; l'acquisition qui suivra le retrouvera.
     *
     * @param array<string, mixed> $body
     * @return array{ok: bool, duplicate: bool, device_id: int, acquisition_id: int, detail: array<string, int>}|array{error: string}
     */
    public function ingestDetailPayload(int $tenantId, array $body, ?int $userId = null): array
    {
        $armaId = trim((string) ($body['record_id'] ?? $body['sse_uid'] ?? ''));
        if ($armaId === '' || mb_strlen($armaId) > 120) {
            return ['error' => 'record_id_required'];
        }
        if (SseDigitalTerrainDetail::isEmpty($this->terrainDetail($body))) {
            return ['error' => 'detail_empty'];
        }
        $existing = $this->repo->findDeviceByArmaObjectId($tenantId, $armaId);
        $result = $this->ingestFromTerrain($tenantId, $body + ['record_id' => $armaId], $userId);
        $stored = array_sum($result['detail'] ?? []) > 0;

        return [
            'ok' => true,
            'duplicate' => $existing !== null && !$stored,
            'device_id' => (int) $result['device_id'],
            'acquisition_id' => (int) $result['acquisition_id'],
            'detail' => $result['detail'] ?? [],
        ];
    }

    /**
     * Contenu normalisé d'un envoi du terrain : phone_data / computer_data, sinon ce que disent les lignes d'extraction
     * des anciens mods (contacts et SMS cités). Jamais de données inventées.
     *
     * @param array<string, mixed> $body
     * @return array<string, list<array<string, mixed>>>
     */
    public function terrainDetail(array $body): array
    {
        $phone = is_array($body['phone_data'] ?? null) ? $body['phone_data'] : [];
        $computer = is_array($body['computer_data'] ?? null) ? $body['computer_data'] : [];
        if ($phone === [] && $computer === []) {
            $lines = [];
            foreach ((is_array($body['extraction_lines'] ?? null) ? $body['extraction_lines'] : []) as $line) {
                if (is_scalar($line)) {
                    $lines[] = (string) $line;
                }
            }
            $phone = SseDigitalTerrainDetail::fromLines($lines);
        }

        return SseDigitalTerrainDetail::normalize($phone, $computer);
    }

    /**
     * Écrit le contenu relevé en jeu dans les rubriques du laboratoire (contacts, messages, appels, médias, positions,
     * comptes, applications, réseaux, artefacts) et la chronologie (événements datés seulement).
     *
     * Idempotent par élément : un envoi rejoué, ou un détail découpé en plusieurs envois, n'écrit jamais deux fois la même
     * ligne (contact, message, appel, image, position, compte, application, réseau, artefact).
     *
     * @param array<string, list<array<string, mixed>>> $detail sortie de SseDigitalTerrainDetail::normalize
     * @return array<string, int> nombre d'éléments NOUVEAUX écrits par rubrique
     */
    public function storeDetail(int $tenantId, int $deviceId, int $acqId, array $detail, ?int $userId = null): array
    {
        $sig = static fn (array $r, array $keys): string => mb_strtolower(implode('|', array_map(static fn (string $k): string => (string) ($r[$k] ?? ''), $keys)));
        $existing = [
            'contacts' => [$this->repo->listContacts($deviceId, $tenantId), ['display_name', 'phone_number']],
            'messages' => [$this->repo->listMessages($deviceId, $tenantId), ['body', 'sent_at', 'sender_label']],
            'calls' => [$this->repo->listCalls($deviceId, $tenantId), ['peer_label', 'peer_number', 'started_at', 'duration_sec']],
            'media' => [$this->repo->listMedia($deviceId, $tenantId), ['name', 'captured_at']],
            'locations' => [$this->repo->listLocations($deviceId, $tenantId), ['label', 'source_label', 'observed_at']],
            'accounts' => [$this->repo->listAccounts($deviceId, $tenantId), ['service_label', 'username']],
            'applications' => [$this->repo->listApplications($deviceId, $tenantId), ['app_name']],
            'networks' => [$this->repo->listNetworks($deviceId, $tenantId), ['ssid_or_name']],
            'artifacts' => [$this->repo->listArtifacts($tenantId, ['device_id' => $deviceId, 'limit' => 500]), ['category', 'name']],
        ];
        /** @var array<string, array<string, true>> $seen */
        $seen = [];
        foreach ($existing as $k => [$rows, $keys]) {
            foreach ($rows as $r) {
                $seen[$k][$sig($r, $keys)] = true;
            }
        }
        $writers = [
            'contacts' => fn (array $r): int => $this->repo->createContact($tenantId, $deviceId, $acqId, $r),
            'messages' => fn (array $r): int => $this->repo->createMessage($tenantId, $deviceId, $acqId, $r),
            'calls' => fn (array $r): int => $this->repo->createCall($tenantId, $deviceId, $acqId, $r),
            'media' => fn (array $r): int => $this->repo->createMedia($tenantId, $deviceId, $acqId, $r),
            'locations' => fn (array $r): int => $this->repo->createLocation($tenantId, $deviceId, $acqId, $r),
            'accounts' => fn (array $r): int => $this->repo->createAccount($tenantId, $deviceId, $acqId, $r),
            'applications' => fn (array $r): int => $this->repo->createApplication($tenantId, $deviceId, $acqId, $r),
            'networks' => fn (array $r): int => $this->repo->createNetwork($tenantId, $deviceId, $acqId, $r),
            'artifacts' => fn (array $r): int => $this->repo->createArtifact($tenantId, $deviceId, $acqId, $r + ['status' => 'unexamined'], $userId),
        ];
        $counts = array_fill_keys(array_keys($writers), 0);
        $written = [];
        foreach ($writers as $k => $write) {
            foreach ($detail[$k] ?? [] as $r) {
                $key = $sig($r, $existing[$k][1]);
                if (isset($seen[$k][$key])) {
                    continue;
                }
                $seen[$k][$key] = true;
                $write($r);
                $counts[$k]++;
                $written[$k][] = $r;
            }
        }

        // Chronologie : seulement ce qui porte une vraie date relevée sur le support.
        $events = 0;
        foreach ([
            ['messages', 'sent_at', 'message', static fn (array $r): string => 'Message ' . ($r['direction'] === 'inbound' ? 'reçu' : 'envoyé') . ($r['sender_label'] !== null ? ' — ' . $r['sender_label'] : '')],
            ['calls', 'started_at', 'call', static fn (array $r): string => 'Appel ' . ($r['direction'] === 'missed' ? 'manqué' : ($r['direction'] === 'inbound' ? 'reçu' : 'émis')) . ($r['peer_label'] !== null ? ' — ' . $r['peer_label'] : '')],
            ['media', 'captured_at', 'media', static fn (array $r): string => 'Image — ' . $r['name']],
            ['locations', 'observed_at', 'location', static fn (array $r): string => 'Position — ' . ($r['label'] ?? $r['source_label'] ?? '')],
        ] as [$key, $dateKey, $type, $title]) {
            foreach ($written[$key] ?? [] as $r) {
                if ($events >= 80 || ($r[$dateKey] ?? null) === null) {
                    continue;
                }
                $this->repo->createTimelineEvent($tenantId, [
                    'device_id' => $deviceId,
                    'acquisition_id' => $acqId,
                    'event_type' => $type,
                    'event_at' => $r[$dateKey],
                    'title' => mb_substr($title($r), 0, 160),
                    'detail' => isset($r['body']) ? mb_substr((string) $r['body'], 0, 300) : null,
                ], $userId);
                $events++;
            }
        }

        $total = array_sum($counts);
        if ($total > 0) {
            $acq = $this->repo->findAcquisition($acqId, $tenantId);
            $this->repo->updateAcquisition($acqId, $tenantId, [
                'artifact_count' => (int) ($acq['artifact_count'] ?? 0) + $counts['artifacts'],
                'file_count' => (int) ($acq['file_count'] ?? 0) + $total,
                // Le contenu est là : la réserve « seulement les totaux » posée à l'identification ne tient plus.
                'status' => 'completed',
                'is_partial' => 0,
                'reserves' => null,
            ]);
            $parts = [];
            foreach (['contacts' => 'contacts', 'messages' => 'messages', 'calls' => 'appels', 'media' => 'images', 'locations' => 'positions', 'accounts' => 'comptes', 'applications' => 'applications', 'networks' => 'réseaux', 'artifacts' => 'pièces'] as $k => $label) {
                if ($counts[$k] > 0) {
                    $parts[] = $counts[$k] . ' ' . $label;
                }
            }
            $this->repo->addAcquisitionLog($tenantId, $acqId, 'Contenu du support relevé en jeu : ' . implode(', ', $parts) . '.', 'info', $userId);
        }

        return $counts;
    }

    /**
     * @param array<string, mixed> $body
     * @return array{created:int,total:int}
     */
    public function ingestDomexPayload(
        int $tenantId,
        int $deviceId,
        ?int $acquisitionId,
        array $body,
        ?int $userId = null
    ): array {
        $extracted = $this->extractDomex($body);
        $node = $extracted['node'];
        if ($node['node_id'] !== '') {
            $this->repo->applyDeviceDomexMeta($deviceId, $tenantId, $node);
            $this->repo->promotePacketsForStage($tenantId, $node['node_id'], (string) ($node['terrain_stage'] ?? ''));
        }

        $created = 0;
        $total = 0;
        $collector = $this->firstNonEmpty([$body['collector'] ?? null, $body['submitter_callsign'] ?? null]);
        $grid = trim((string) ($body['grid_reference'] ?? ''));
        $originHint = (string) ($body['origin'] ?? $extracted['origin'] ?? 'terrain');
        $liveCreated = 0;

        foreach ($extracted['packets'] as $i => $raw) {
            if (!is_array($raw)) {
                continue;
            }
            $packet = SseDomexContract::normalizePacket($raw, $node['node_id'], $i + 1);
            if ($packet === null) {
                continue;
            }
            $total++;
            if (($packet['origin'] ?? '') === 'scenario' && $originHint === 'terrain') {
                $packet['origin'] = 'terrain';
            }
            $pktGrid = trim((string) ($packet['grid_reference'] ?? ''));
            $result = $this->repo->upsertPacket($tenantId, [
                'device_id' => $deviceId,
                'acquisition_id' => $acquisitionId,
                'node_key' => $node['node_id'] !== '' ? $node['node_id'] : (string) ($body['record_id'] ?? 'SUPPORT'),
                'packet_uid' => $packet['packet_uid'],
                'packet_type' => $packet['packet_type'],
                'title' => $packet['title'],
                'body_text' => $packet['body_text'],
                'occurred_at_label' => $packet['occurred_at_label'],
                'quality' => $packet['quality'],
                'is_decoy' => $packet['is_decoy'],
                'is_fragment' => $packet['is_fragment'],
                'is_complete' => $packet['is_complete'],
                'channel' => $packet['channel'],
                'reveal_after' => $packet['reveal_after'],
                'delay_seconds' => $packet['delay_seconds'],
                'origin' => $packet['origin'],
                'confidence' => $packet['confidence'],
                'status' => $packet['status'],
                'linked_entities' => $packet['linked_entities'],
                'collector_label' => $collector,
                'grid_reference' => $pktGrid !== '' ? $pktGrid : ($grid !== '' ? $grid : null),
                'pos_x' => $packet['pos_x'],
                'pos_y' => $packet['pos_y'],
                'pos_z' => $packet['pos_z'],
                'show_on_map' => $packet['show_on_map'],
            ], $userId);
            if (!empty($result['created'])) {
                $created++;
                if (($packet['origin'] ?? '') === 'zeus_live') {
                    $liveCreated++;
                }
            }
        }

        if ($created > 0) {
            $this->repo->createTimelineEvent($tenantId, [
                'device_id' => $deviceId,
                'acquisition_id' => $acquisitionId,
                'event_type' => 'domex_packet',
                'event_at' => date('Y-m-d H:i:s'),
                'title' => $liveCreated > 0
                    ? 'Renseignement ajouté en cours de mission'
                    : 'Renseignement numérique à exploiter',
                'detail' => $liveCreated > 0
                    ? ($liveCreated === 1
                        ? 'Le chef de mission a ajouté un renseignement dans la file du laboratoire.'
                        : $liveCreated . ' renseignements ont été ajoutés en cours de mission.')
                    : ($created === 1
                        ? 'Un paquet scénarisé a été déposé dans la file du laboratoire.'
                        : $created . ' paquets scénarisés ont été déposés dans la file du laboratoire.'),
                'interest_level' => 'a_surveiller',
            ], $userId);
        }

        return ['created' => $created, 'total' => $total];
    }

    /**
     * @param array<string, mixed> $body
     * @return array{node: array<string, mixed>, packets: list<mixed>, origin: string}
     */
    public function extractDomex(array $body): array
    {
        $block = is_array($body['domex'] ?? null) ? $body['domex'] : $body;
        $nodeRaw = [];
        if (is_array($block['node'] ?? null)) {
            $nodeRaw = $block['node'];
        } elseif (is_array($body['domex'] ?? null)) {
            $nodeRaw = $body['domex'];
        }
        $packets = $block['packets'] ?? $body['packets'] ?? [];
        if (!is_array($packets)) {
            $packets = [];
        }

        return [
            'node' => SseDomexContract::normalizeNode($nodeRaw),
            'packets' => array_values($packets),
            'origin' => (string) ($block['origin'] ?? $body['origin'] ?? 'terrain'),
        ];
    }

    /**
     * @param array<string, mixed> $input
     */
    public function reviewPacket(
        int $tenantId,
        int $packetId,
        string $decision,
        ?int $caseId,
        ?int $userId = null
    ): bool {
        $packet = $this->repo->findPacket($packetId, $tenantId);
        if ($packet === null) {
            return false;
        }
        $status = SseDomexContract::pickKey($decision, SseDomexContract::PACKET_STATUSES, '');
        if (!in_array($status, ['rattache', 'ecarte', 'a_exploiter'], true)) {
            return false;
        }
        $linkCase = $status === 'rattache' ? ($caseId ?? 0) : null;
        if ($status === 'rattache' && ($linkCase === null || $linkCase < 1)) {
            return false;
        }
        $ok = $this->repo->updatePacketStatus($packetId, $tenantId, $status, $linkCase);
        if ($ok) {
            $this->repo->createTimelineEvent($tenantId, [
                'device_id' => $packet['device_id'] ?? null,
                'case_id' => $linkCase,
                'event_type' => 'domex_review',
                'event_at' => date('Y-m-d H:i:s'),
                'title' => $status === 'rattache' ? 'Paquet rattaché au dossier' : 'Paquet écarté',
                'detail' => 'Décision humaine. Aucun lien n’a été consolidé automatiquement.',
                'interest_level' => 'courant',
                'is_validated' => 1,
            ], $userId);
        }

        return $ok;
    }

    /**
     * @param array<string, mixed> $phone
     * @param array<string, mixed> $computer
     */
    public static function mapTerrainDeviceType(string $sourceType, array $phone = [], array $computer = []): string
    {
        $s = strtolower(trim($sourceType));
        $s = str_replace(['-', ' '], '_', $s);
        $map = [
            'phone' => 'telephone',
            'telephone' => 'telephone',
            'smartphone' => 'telephone',
            'mobile' => 'telephone',
            'cell' => 'telephone',
            'device' => 'telephone',
            'computer' => 'ordinateur',
            'ordinateur' => 'ordinateur',
            'laptop' => 'ordinateur',
            'pc' => 'ordinateur',
            'desktop' => 'ordinateur',
            'tablet' => 'tablette',
            'tablette' => 'tablette',
            'usb' => 'cle_usb',
            'cle_usb' => 'cle_usb',
            'key' => 'cle_usb',
            'stick' => 'cle_usb',
            'radio' => 'radio_numerique',
            'radio_numerique' => 'radio_numerique',
            'gps' => 'gps',
            'camera' => 'appareil_photo',
            'photo' => 'appareil_photo',
            'appareil_photo' => 'appareil_photo',
            'zeus_live' => 'gps',
            'map_point' => 'gps',
        ];
        if (isset($map[$s])) {
            return $map[$s];
        }
        $hint = strtolower((string) ($phone['deviceType'] ?? $computer['deviceType'] ?? ''));
        if (isset($map[$hint])) {
            return $map[$hint];
        }
        if ($phone !== [] && empty($phone['ok']) === false && (int) ($phone['contacts'] ?? 0) + (int) ($phone['messages'] ?? 0) > 0) {
            return 'telephone';
        }
        if ($computer !== [] && $computer !== ['ok' => false]) {
            return 'ordinateur';
        }

        return 'telephone';
    }

    private function firstNonEmpty(array $values): ?string
    {
        foreach ($values as $v) {
            if ($v === null) {
                continue;
            }
            $s = trim((string) $v);
            if ($s !== '') {
                return $s;
            }
        }

        return null;
    }

    /**
     * Planifie puis exécute une acquisition simulée (profil de données).
     *
     * @param array<string, mixed> $input
     */
    public function runSimulatedAcquisition(int $tenantId, int $deviceId, array $input, ?int $userId = null): int
    {
        $device = $this->repo->findDevice($deviceId, $tenantId);
        if ($device === null) {
            throw new \InvalidArgumentException('Support introuvable.');
        }

        $profile = (string) ($input['data_profile'] ?? $device['data_profile'] ?? '');
        if ($profile === '') {
            $profile = $this->defaultProfileForType((string) ($device['device_type'] ?? 'telephone'));
        }

        $this->repo->updateDeviceStatus($deviceId, $tenantId, 'acquiring');

        $acqId = $this->repo->createAcquisition($tenantId, $deviceId, [
            'mission_id' => $device['mission_id'] ?? null,
            'case_id' => $device['case_id'] ?? ($input['case_id'] ?? null),
            'method' => $input['method'] ?? 'logical',
            'operator_label' => $input['operator_label'] ?? ($device['seized_by_label'] ?? 'Laboratoire'),
            'started_at' => date('Y-m-d H:i:s'),
            'status' => 'in_progress',
            'data_profile' => $profile,
            'tool_name' => $input['tool_name'] ?? 'Athena LabNum Sim',
            'tool_version' => '1.0',
            'classification' => $device['classification'] ?? 'confidentiel',
        ], $userId);

        $this->repo->addAcquisitionLog($tenantId, $acqId, 'Démarrage de l’acquisition simulée.', 'info', $userId);
        $this->repo->addAcquisitionLog($tenantId, $acqId, 'Profil de données appliqué : ' . $profile, 'info', $userId);

        $seed = $this->seedProfile($tenantId, $deviceId, $acqId, $profile, $userId);

        $hash = hash('sha256', $tenantId . '|' . $acqId . '|' . $profile . '|' . microtime(true));
        $hasReserves = !empty($seed['reserves']);
        $status = $hasReserves ? 'completed_with_reserves' : 'completed';

        $this->repo->updateAcquisition($acqId, $tenantId, [
            'status' => $status,
            'ended_at' => date('Y-m-d H:i:s'),
            'volume_bytes' => $seed['volume_bytes'],
            'file_count' => $seed['file_count'],
            'artifact_count' => $seed['artifact_count'],
            'integrity_hash' => $hash,
            'is_partial' => $hasReserves ? 1 : 0,
            'reserves' => $seed['reserves'],
        ]);
        $this->repo->addIntegrityCheck($tenantId, $acqId, $hash, 'ok', $userId);
        $this->repo->addAcquisitionLog(
            $tenantId,
            $acqId,
            $hasReserves
                ? 'Acquisition terminée avec réserves : ' . $seed['reserves']
                : 'Acquisition terminée. Empreinte d’intégrité calculée.',
            $hasReserves ? 'warning' : 'info',
            $userId
        );

        $this->repo->updateDeviceStatus($deviceId, $tenantId, 'exploiting');
        $this->repo->createTimelineEvent($tenantId, [
            'device_id' => $deviceId,
            'acquisition_id' => $acqId,
            'case_id' => $device['case_id'] ?? null,
            'event_type' => 'acquisition',
            'event_at' => date('Y-m-d H:i:s'),
            'title' => 'Acquisition numérique',
            'detail' => 'Référence ' . ($this->repo->findAcquisition($acqId, $tenantId)['reference_code'] ?? ''),
            'interest_level' => 'a_surveiller',
        ], $userId);

        $this->generateFindings($tenantId, $deviceId, $acqId, $userId);

        return $acqId;
    }

    /**
     * @return array{volume_bytes:int,file_count:int,artifact_count:int,reserves:?string}
     */
    private function seedProfile(int $tenantId, int $deviceId, int $acqId, string $profile, ?int $userId): array
    {
        $reserves = null;
        $artifactCount = 0;
        $fileCount = 0;
        $volume = 0;

        $defs = match ($profile) {
            'CELLULE_LOGISTIQUE_03' => [
                'contacts' => [
                    ['display_name' => 'Farid Garage', 'phone_number' => '+33 6 12 44 87 03', 'alias_label' => 'Garage'],
                    ['display_name' => 'Amine Convoi', 'phone_number' => '+33 7 55 01 22 18'],
                    ['display_name' => 'Depot Nord', 'phone_number' => '+33 1 40 00 11 22', 'email' => 'depot.nord@mail.sim'],
                ],
                'messages' => 12,
                'calls' => 4,
                'photos' => 8,
                'locations' => 5,
                'accounts' => 2,
                'networks' => 3,
                'apps' => 6,
                'docs' => 3,
                'deleted' => 4,
                'encrypted' => true,
                'volume' => (int) (31.4 * 1073741824 / 10), // ~3.1 Go pour démo légère
            ],
            'POSTE_COMMANDES_01' => [
                'contacts' => [
                    ['display_name' => 'Chef de poste', 'phone_number' => '+33 6 98 11 00 01'],
                    ['display_name' => 'Liaison radio', 'phone_number' => '+33 6 98 11 00 02'],
                ],
                'messages' => 8,
                'calls' => 6,
                'photos' => 4,
                'locations' => 3,
                'accounts' => 1,
                'networks' => 2,
                'apps' => 4,
                'docs' => 5,
                'deleted' => 2,
                'encrypted' => true,
                'volume' => 2 * 1073741824,
            ],
            'CACHE_URBAINE_02' => [
                'contacts' => [
                    ['display_name' => 'Contact cache', 'phone_number' => '+33 6 22 33 44 55'],
                ],
                'messages' => 5,
                'calls' => 2,
                'photos' => 10,
                'locations' => 6,
                'accounts' => 1,
                'networks' => 4,
                'apps' => 3,
                'docs' => 2,
                'deleted' => 6,
                'encrypted' => false,
                'volume' => 800 * 1048576,
            ],
            'GENERIC_COMPUTER' => [
                'contacts' => [],
                'messages' => 0,
                'calls' => 0,
                'photos' => 3,
                'locations' => 1,
                'accounts' => 3,
                'networks' => 4,
                'apps' => 8,
                'docs' => 10,
                'deleted' => 5,
                'encrypted' => true,
                'volume' => 5 * 1073741824,
                'computer' => true,
            ],
            'GENERIC_USB' => [
                'contacts' => [],
                'messages' => 0,
                'calls' => 0,
                'photos' => 2,
                'locations' => 0,
                'accounts' => 0,
                'networks' => 0,
                'apps' => 0,
                'docs' => 6,
                'deleted' => 2,
                'encrypted' => false,
                'volume' => 256 * 1048576,
            ],
            default => [
                'contacts' => [
                    ['display_name' => 'Contact inconnu', 'phone_number' => '+33 6 00 00 00 01'],
                ],
                'messages' => 6,
                'calls' => 3,
                'photos' => 4,
                'locations' => 2,
                'accounts' => 1,
                'networks' => 2,
                'apps' => 4,
                'docs' => 2,
                'deleted' => 2,
                'encrypted' => false,
                'volume' => 512 * 1048576,
            ],
        };

        foreach ($defs['contacts'] as $c) {
            $this->repo->createContact($tenantId, $deviceId, $acqId, $c);
            $this->repo->createArtifact($tenantId, $deviceId, $acqId, [
                'name' => 'Contact — ' . $c['display_name'],
                'path' => '/Contacts/' . preg_replace('/\s+/', '_', $c['display_name']) . '.vcf',
                'category' => 'contact',
                'associated_identifiers' => $c['phone_number'] ?? ($c['email'] ?? null),
                'status' => 'to_review',
                'interest_level' => 'a_surveiller',
            ], $userId);
            $artifactCount++;
            $fileCount++;
        }

        $baseTs = time() - 86400 * 5;
        $thread = 'thread-farid';
        for ($i = 0; $i < (int) $defs['messages']; $i++) {
            $sent = date('Y-m-d H:i:s', $baseTs + $i * 3600);
            $body = match ($i % 4) {
                0 => 'Livraison prévue ce soir près du dépôt.',
                1 => 'Change de fréquence, même grille.',
                2 => 'Photo du convoi envoyée.',
                default => 'OK, on se retrouve demain.',
            };
            $this->repo->createMessage($tenantId, $deviceId, $acqId, [
                'thread_key' => $thread,
                'direction' => $i % 2 === 0 ? 'inbound' : 'outbound',
                'sender_label' => $i % 2 === 0 ? 'Farid Garage' : 'Appareil saisi',
                'recipient_label' => $i % 2 === 0 ? 'Appareil saisi' : 'Farid Garage',
                'body' => $body,
                'sent_at' => $sent,
                'app_label' => $i % 3 === 0 ? 'Signal' : 'SMS',
                'is_deleted' => $i === 3,
                'has_attachment' => $i === 2,
            ]);
            $this->repo->createArtifact($tenantId, $deviceId, $acqId, [
                'name' => 'Message ' . ($i + 1),
                'path' => '/Messages/' . $thread . '/' . ($i + 1) . '.msg',
                'category' => 'message',
                'created_at_device' => $sent,
                'source_app' => $i % 3 === 0 ? 'Signal' : 'SMS',
                'is_deleted' => $i === 3,
                'status' => $i === 2 ? 'to_review' : 'unexamined',
                'interest_level' => $i === 2 ? 'prioritaire' : 'courant',
            ], $userId);
            $this->repo->createTimelineEvent($tenantId, [
                'device_id' => $deviceId,
                'acquisition_id' => $acqId,
                'event_type' => 'message',
                'event_at' => $sent,
                'title' => 'Message — ' . ($i % 2 === 0 ? 'Farid Garage' : 'Appareil'),
                'detail' => $body,
                'interest_level' => $i === 2 ? 'prioritaire' : 'courant',
            ], $userId);
            $artifactCount++;
            $fileCount++;
        }

        for ($i = 0; $i < (int) $defs['calls']; $i++) {
            $started = date('Y-m-d H:i:s', $baseTs + 7200 + $i * 5400);
            $this->repo->createCall($tenantId, $deviceId, $acqId, [
                'direction' => $i % 2 === 0 ? 'inbound' : 'outbound',
                'peer_label' => 'Farid Garage',
                'peer_number' => '+33 6 12 44 87 03',
                'started_at' => $started,
                'duration_sec' => 60 + $i * 45,
            ]);
            $this->repo->createTimelineEvent($tenantId, [
                'device_id' => $deviceId,
                'acquisition_id' => $acqId,
                'event_type' => 'call',
                'event_at' => $started,
                'title' => 'Appel avec Farid Garage',
                'detail' => 'Durée ' . (60 + $i * 45) . ' s',
            ], $userId);
            $artifactCount++;
        }

        for ($i = 0; $i < (int) $defs['photos']; $i++) {
            $name = sprintf('IMG_%04d.JPG', 4800 + $i);
            $captured = date('Y-m-d H:i:s', $baseTs + 10000 + $i * 1800);
            $lat = 48.85 + ($i * 0.001);
            $lng = 2.35 + ($i * 0.001);
            $artId = $this->repo->createArtifact($tenantId, $deviceId, $acqId, [
                'name' => $name,
                'path' => '/DCIM/Camera/' . $name,
                'category' => 'image',
                'mime_label' => 'image/jpeg',
                'size_bytes' => 2_400_000 + $i * 10000,
                'created_at_device' => $captured,
                'geo_lat' => $lat,
                'geo_lng' => $lng,
                'status' => $i === 0 ? 'to_review' : 'unexamined',
                'interest_level' => $i === 0 ? 'prioritaire' : 'courant',
                'detected_persons' => $i === 0 ? 'Visage non consolidé — proposition en file' : null,
            ], $userId);
            $this->repo->createMedia($tenantId, $deviceId, $acqId, [
                'artifact_id' => $artId,
                'media_type' => 'image',
                'name' => $name,
                'captured_at' => $captured,
                'geo_lat' => $lat,
                'geo_lng' => $lng,
            ]);
            $this->repo->createTimelineEvent($tenantId, [
                'device_id' => $deviceId,
                'acquisition_id' => $acqId,
                'artifact_id' => $artId,
                'event_type' => 'photo',
                'event_at' => $captured,
                'title' => 'Photographie ' . $name,
                'detail' => 'Géolocalisation présente',
                'interest_level' => $i === 0 ? 'prioritaire' : 'courant',
            ], $userId);
            $artifactCount++;
            $fileCount++;
        }

        for ($i = 0; $i < (int) $defs['locations']; $i++) {
            $obs = date('Y-m-d H:i:s', $baseTs + 20000 + $i * 3600);
            $this->repo->createLocation($tenantId, $deviceId, $acqId, [
                'label' => 'Point ' . ($i + 1),
                'lat' => 48.86 + $i * 0.002,
                'lng' => 2.34 + $i * 0.002,
                'observed_at' => $obs,
                'source_label' => 'GPS / photos',
            ]);
            $this->repo->createArtifact($tenantId, $deviceId, $acqId, [
                'name' => 'Localisation ' . ($i + 1),
                'category' => 'location',
                'created_at_device' => $obs,
                'geo_lat' => 48.86 + $i * 0.002,
                'geo_lng' => 2.34 + $i * 0.002,
            ], $userId);
            $artifactCount++;
        }

        for ($i = 0; $i < (int) $defs['accounts']; $i++) {
            $this->repo->createAccount($tenantId, $deviceId, $acqId, [
                'service_label' => $i === 0 ? 'Messagerie' : 'Cloud',
                'username' => 'user' . ($i + 1) . '.sim',
                'email' => 'user' . ($i + 1) . '@mail.sim',
            ]);
            $artifactCount++;
        }

        $wifiNames = ['Depot_Nord_Guest', 'SITE_WIFI_09', 'Garage_Farid', 'Freebox-URBA'];
        for ($i = 0; $i < (int) $defs['networks']; $i++) {
            $this->repo->createNetwork($tenantId, $deviceId, $acqId, [
                'network_type' => 'wifi',
                'ssid_or_name' => $wifiNames[$i % count($wifiNames)],
                'observed_at' => date('Y-m-d H:i:s', $baseTs + 30000 + $i * 7200),
            ]);
            $this->repo->createTimelineEvent($tenantId, [
                'device_id' => $deviceId,
                'acquisition_id' => $acqId,
                'event_type' => 'wifi',
                'event_at' => date('Y-m-d H:i:s', $baseTs + 30000 + $i * 7200),
                'title' => 'Connexion Wi-Fi',
                'detail' => $wifiNames[$i % count($wifiNames)],
            ], $userId);
            $artifactCount++;
        }

        $apps = ['Messagerie', 'Cartes', 'Galerie', 'Notes', 'Navigateur', 'Chiffrement', 'Radio', 'Fichiers'];
        for ($i = 0; $i < (int) $defs['apps']; $i++) {
            $this->repo->createApplication($tenantId, $deviceId, $acqId, [
                'app_name' => $apps[$i % count($apps)],
                'version_label' => '1.' . $i,
            ]);
            $artifactCount++;
        }

        for ($i = 0; $i < (int) $defs['docs']; $i++) {
            $this->repo->createArtifact($tenantId, $deviceId, $acqId, [
                'name' => 'Document_' . ($i + 1) . '.pdf',
                'path' => '/Documents/Document_' . ($i + 1) . '.pdf',
                'category' => 'document',
                'size_bytes' => 120000 + $i * 5000,
                'presumed_author' => !empty($defs['computer']) ? 'Utilisateur local' : null,
                'interest_level' => $i === 0 ? 'a_surveiller' : 'courant',
            ], $userId);
            $artifactCount++;
            $fileCount++;
        }

        for ($i = 0; $i < (int) $defs['deleted']; $i++) {
            $this->repo->createArtifact($tenantId, $deviceId, $acqId, [
                'name' => 'deleted_' . ($i + 1) . '.dat',
                'path' => '/.trashed/deleted_' . ($i + 1) . '.dat',
                'category' => 'deleted',
                'is_deleted' => 1,
                'status' => 'to_review',
                'interest_level' => 'a_surveiller',
            ], $userId);
            $artifactCount++;
            $fileCount++;
        }

        if (!empty($defs['encrypted'])) {
            $this->repo->createArtifact($tenantId, $deviceId, $acqId, [
                'name' => 'archive_chiffree.vault',
                'path' => '/Secure/archive_chiffree.vault',
                'category' => 'encrypted',
                'is_encrypted' => 1,
                'status' => 'to_review',
                'interest_level' => 'prioritaire',
            ], $userId);
            $artifactCount++;
            $fileCount++;
            $reserves = 'Répertoire chiffré non accessible — contenu non extrait.';
        }

        if (!empty($defs['computer'])) {
            foreach (['Administrateur', 'Operateur', 'Invite'] as $user) {
                $this->repo->createArtifact($tenantId, $deviceId, $acqId, [
                    'name' => 'Compte local — ' . $user,
                    'path' => '/Users/' . $user,
                    'category' => 'system',
                    'account_label' => $user,
                ], $userId);
                $artifactCount++;
            }
            $this->repo->createArtifact($tenantId, $deviceId, $acqId, [
                'name' => 'Historique USB',
                'path' => '/System/USB_History',
                'category' => 'system',
                'status' => 'to_review',
            ], $userId);
            $artifactCount++;
        }

        $volume = (int) $defs['volume'];

        return [
            'volume_bytes' => $volume,
            'file_count' => max($fileCount, $artifactCount),
            'artifact_count' => $artifactCount,
            'reserves' => $reserves,
        ];
    }

    public function generateFindings(int $tenantId, int $deviceId, int $acqId, ?int $userId = null): void
    {
        $device = $this->repo->findDevice($deviceId, $tenantId);
        $contacts = $this->repo->listContacts($deviceId, $tenantId);
        $messages = $this->repo->listMessages($deviceId, $tenantId);
        $media = $this->repo->listMedia($deviceId, $tenantId);
        $networks = $this->repo->listNetworks($deviceId, $tenantId);

        foreach ($contacts as $c) {
            $phone = trim((string) ($c['phone_number'] ?? ''));
            if ($phone === '') {
                continue;
            }
            $this->repo->createFinding($tenantId, [
                'device_id' => $deviceId,
                'acquisition_id' => $acqId,
                'case_id' => $device['case_id'] ?? null,
                'finding_type' => 'identifier',
                'title' => 'Numéro à rapprocher — ' . ($c['display_name'] ?? 'Contact'),
                'detail' => 'Le contact « ' . ($c['display_name'] ?? '') . ' » (' . $phone
                    . ') peut correspondre à un identifiant déjà présent dans le SSE. Validation humaine requise.',
                'confidence_level' => 'modere',
                'score_pct' => 62,
                'status' => 'to_review',
                'factors' => [
                    'Numéro extrait de l’acquisition',
                    'Correspondance possible avec le registre SSE',
                ],
                'proposed_relation' => [
                    'from_label' => $device['reference_code'] ?? 'Support',
                    'relation' => 'CONTIENT LE CONTACT',
                    'to_label' => $c['display_name'] ?? $phone,
                ],
            ], $userId);
        }

        if (count($messages) >= 5) {
            $this->repo->createFinding($tenantId, [
                'device_id' => $deviceId,
                'acquisition_id' => $acqId,
                'case_id' => $device['case_id'] ?? null,
                'finding_type' => 'relation',
                'title' => 'Proposition de relation — communications',
                'detail' => count($messages) . ' messages et appels associés suggèrent une relation de communication. '
                    . 'Ceci est une proposition, pas une consolidation.',
                'confidence_level' => 'eleve',
                'score_pct' => 78,
                'status' => 'to_review',
                'factors' => [
                    count($messages) . ' messages',
                    'Période d’échange continue',
                    'Même interlocuteur récurrent',
                ],
                'proposed_relation' => [
                    'from_label' => 'Appareil saisi',
                    'relation' => 'COMMUNIQUE AVEC',
                    'to_label' => 'Farid Garage',
                    'confidence' => 'ÉLEVÉE',
                ],
            ], $userId);
        }

        if ($media !== []) {
            $first = $media[0];
            $this->repo->createFinding($tenantId, [
                'device_id' => $deviceId,
                'acquisition_id' => $acqId,
                'artifact_id' => $first['artifact_id'] ?? null,
                'case_id' => $device['case_id'] ?? null,
                'finding_type' => 'media',
                'title' => 'Signal analytique — ' . ($first['name'] ?? 'média'),
                'detail' => 'Photographie géolocalisée détectée. Lieu et visage éventuels à examiner manuellement.',
                'confidence_level' => 'modere',
                'score_pct' => 71,
                'status' => 'to_review',
                'factors' => [
                    'Géolocalisation présente',
                    'Date dans une fenêtre d’intérêt possible',
                    'Appareil source lié à l’acquisition',
                ],
            ], $userId);
        }

        foreach ($networks as $net) {
            $ssid = (string) ($net['ssid_or_name'] ?? '');
            $ssidUp = strtoupper($ssid);
            if ($ssid === '' || (!str_contains($ssidUp, 'SITE') && !str_contains($ssidUp, 'DEPOT'))) {
                continue;
            }
            $this->repo->createFinding($tenantId, [
                'device_id' => $deviceId,
                'acquisition_id' => $acqId,
                'case_id' => $device['case_id'] ?? null,
                'finding_type' => 'network',
                'title' => 'Wi-Fi compatible avec un site SSE',
                'detail' => 'Réseau « ' . $ssid . ' » observé — rapprochement possible avec un site exploité.',
                'confidence_level' => 'faible',
                'score_pct' => 48,
                'status' => 'to_review',
                'factors' => ['SSID suggestif', 'Horodatage disponible'],
            ], $userId);
        }

        if (is_array($device)) {
            try {
                $findings = $this->repo->listFindings($tenantId, [
                    'device_id' => $deviceId,
                    'status' => 'to_review',
                ]);
                foreach ($findings as $finding) {
                    $this->terrain->onDigitalFindingCreated($tenantId, $finding, $device);
                }
            } catch (\Throwable) {
                // Pont intel optionnel (LOT 3).
            }
        }
    }

    private function defaultProfileForType(string $type): string
    {
        return match ($type) {
            'ordinateur', 'disque_dur', 'ssd', 'image_disque' => 'GENERIC_COMPUTER',
            'cle_usb', 'carte_memoire', 'support_amovible' => 'GENERIC_USB',
            default => 'GENERIC_PHONE',
        };
    }
}
