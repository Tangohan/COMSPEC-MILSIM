<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseDigitalLabRepository;

/**
 * Orchestration du laboratoire numérique : saisie, acquisition simulée, extraction de profil, signaux.
 * Les détections restent des propositions — jamais des conclusions.
 */
final class SseDigitalLabService
{
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
     *     reference_code: string
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
        if ($existing !== null) {
            $acqs = $this->repo->listAcquisitions($tenantId, [
                'device_id' => (int) $existing['id'],
                'limit' => 1,
            ]);
            $acq = $acqs[0] ?? null;

            return [
                'ok' => true,
                'duplicate' => true,
                'device_id' => (int) $existing['id'],
                'seizure_id' => (int) ($existing['seizure_id'] ?? 0),
                'acquisition_id' => (int) ($acq['id'] ?? 0),
                'reference_code' => (string) ($acq['reference_code'] ?? $existing['reference_code'] ?? ''),
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

        $this->repo->updateDeviceStatus($deviceId, $tenantId, 'acquired');
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
        ];
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
