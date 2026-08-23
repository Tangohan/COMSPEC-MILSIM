<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SilentSchemaMigration;

/**
 * Laboratoire numérique SSE (ATH-SSE-LABNUM).
 * Libellés métier hydratés au chargement — jamais de codes bruts exposés à l’UI.
 */
final class SseDigitalLabRepository
{
    public const DEVICE_TYPES = [
        'ordinateur' => 'Ordinateur',
        'disque_dur' => 'Disque dur',
        'ssd' => 'SSD',
        'cle_usb' => 'Clé USB',
        'carte_memoire' => 'Carte mémoire',
        'telephone' => 'Téléphone',
        'tablette' => 'Tablette',
        'appareil_photo' => 'Appareil photo',
        'gps' => 'GPS',
        'radio_numerique' => 'Radio numérique',
        'support_amovible' => 'Support amovible',
        'image_disque' => 'Image disque (simulation)',
        'compte_service' => 'Compte ou service numérique',
    ];

    public const DEVICE_STATUSES = [
        'discovered' => 'Découvert',
        'seized' => 'Saisi',
        'packaged' => 'Conditionné',
        'transmitted' => 'Transmis',
        'received_lab' => 'Reçu au laboratoire',
        'pending' => 'En attente',
        'acquiring' => 'En acquisition',
        'acquired' => 'Acquis',
        'exploiting' => 'En exploitation',
        'exploited' => 'Exploité',
        'returned' => 'Restitué',
        'archived' => 'Archivé',
    ];

    public const ACQUISITION_METHODS = [
        'logical' => 'Acquisition logique',
        'filesystem' => 'Acquisition système de fichiers',
        'full' => 'Acquisition complète',
        'disk_image' => 'Image disque',
        'selective' => 'Extraction sélective',
        'directory_copy' => 'Copie de répertoire',
        'app_export' => 'Export applicatif',
        'sim_extract' => 'Extraction de carte SIM',
        'memory_card_extract' => 'Extraction de carte mémoire',
        'memory_capture_simulated' => 'Capture mémoire simulée',
    ];

    public const ACQUISITION_STATUSES = [
        'planned' => 'Planifiée',
        'in_progress' => 'En cours',
        'suspended' => 'Suspendue',
        'completed' => 'Terminée',
        'completed_with_reserves' => 'Terminée avec réserves',
        'failed' => 'Échec',
        'cancelled' => 'Annulée',
    ];

    public const ARTIFACT_CATEGORIES = [
        'document' => 'Documents',
        'image' => 'Images',
        'video' => 'Vidéos',
        'audio' => 'Audios',
        'message' => 'Messages',
        'call' => 'Appels',
        'contact' => 'Contacts',
        'email' => 'Courriels',
        'navigation' => 'Navigation',
        'location' => 'Localisations',
        'application' => 'Applications',
        'archive' => 'Archives',
        'deleted' => 'Fichiers supprimés',
        'encrypted' => 'Fichiers chiffrés',
        'system' => 'Données système',
    ];

    public const ARTIFACT_STATUSES = [
        'unexamined' => 'Non examiné',
        'to_review' => 'À examiner',
        'relevant' => 'Pertinent',
        'not_relevant' => 'Non pertinent',
        'linked' => 'Rattaché',
        'validated' => 'Validé',
        'dismissed' => 'Écarté',
    ];

    public const FINDING_STATUSES = [
        'to_review' => 'À examiner',
        'accepted' => 'Accepté (proposition)',
        'rejected' => 'Rejeté',
        'needs_collection' => 'Besoin de collecte',
    ];

    public const INTEREST_LEVELS = [
        'courant' => 'Courant',
        'a_surveiller' => 'À surveiller',
        'prioritaire' => 'Prioritaire',
        'critique' => 'Critique',
    ];

    public const CONFIDENCE_LEVELS = [
        'faible' => 'Faible',
        'modere' => 'Modérée',
        'eleve' => 'Élevée',
        'tres_eleve' => 'Très élevée',
    ];

    public const DATA_PROFILES = [
        'CELLULE_LOGISTIQUE_03' => 'Cellule logistique 03',
        'POSTE_COMMANDES_01' => 'Poste de commandement 01',
        'CACHE_URBAINE_02' => 'Cache urbaine 02',
        'GENERIC_PHONE' => 'Téléphone générique',
        'GENERIC_COMPUTER' => 'Ordinateur générique',
        'GENERIC_USB' => 'Clé USB générique',
        'terrain_seek' => 'Extraction terrain (SEEK)',
    ];

    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->ensureSchema();
    }

    private function ensureSchema(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        SilentSchemaMigration::run(base_path('bootstrap/atak_sse_digital_lab_migration.php'));
        $done = true;
    }

    public static function normalizeDeviceType(string $raw): string
    {
        $s = strtolower(trim($raw));

        return isset(self::DEVICE_TYPES[$s]) ? $s : 'telephone';
    }

    public static function normalizeDeviceStatus(string $raw): string
    {
        $s = strtolower(trim($raw));

        return isset(self::DEVICE_STATUSES[$s]) ? $s : 'discovered';
    }

    public static function normalizeAcquisitionMethod(string $raw): string
    {
        $s = strtolower(trim($raw));

        return isset(self::ACQUISITION_METHODS[$s]) ? $s : 'logical';
    }

    public static function normalizeAcquisitionStatus(string $raw): string
    {
        $s = strtolower(trim($raw));

        return isset(self::ACQUISITION_STATUSES[$s]) ? $s : 'planned';
    }

    public static function normalizeArtifactStatus(string $raw): string
    {
        $s = strtolower(trim($raw));

        return isset(self::ARTIFACT_STATUSES[$s]) ? $s : 'unexamined';
    }

    public static function normalizeCategory(string $raw): string
    {
        $s = strtolower(trim($raw));

        return isset(self::ARTIFACT_CATEGORIES[$s]) ? $s : 'document';
    }

    public function nextDeviceReference(int $tenantId): string
    {
        return $this->nextReference($tenantId, 'sse_digital_devices', 'SSE-DEV');
    }

    public function nextAcquisitionReference(int $tenantId): string
    {
        return $this->nextReference($tenantId, 'sse_digital_acquisitions', 'ACQ-NUM');
    }

    public function nextSeizureReference(int $tenantId): string
    {
        return $this->nextReference($tenantId, 'sse_digital_seizures', 'SAI-NUM');
    }

    private function nextReference(int $tenantId, string $table, string $prefix): string
    {
        $year = date('Y');
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS c FROM {$table} WHERE tenant_id = :t AND reference_code LIKE :p",
            ['t' => $tenantId, 'p' => $prefix . '-' . $year . '-%']
        );
        $n = (int) ($row['c'] ?? 0) + 1;

        return sprintf('%s-%s-%06d', $prefix, $year, $n);
    }

    public function countDevices(int $tenantId): int
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS c FROM sse_digital_devices WHERE tenant_id = :t AND deleted_at IS NULL',
            ['t' => $tenantId]
        );

        return (int) ($row['c'] ?? 0);
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function listDevices(int $tenantId, array $filters = []): array
    {
        $where = ['tenant_id = :tenant', 'deleted_at IS NULL'];
        $params = ['tenant' => $tenantId];
        if (isset(self::DEVICE_STATUSES[(string) ($filters['status'] ?? '')])) {
            $where[] = 'status = :status';
            $params['status'] = $filters['status'];
        }
        if (isset(self::DEVICE_TYPES[(string) ($filters['device_type'] ?? '')])) {
            $where[] = 'device_type = :dtype';
            $params['dtype'] = $filters['device_type'];
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . $q . '%';
            $where[] = '(reference_code LIKE :q_ref OR model LIKE :q_model OR manufacturer LIKE :q_mfr OR serial_number LIKE :q_serial OR mission_label LIKE :q_mission)';
            $params['q_ref'] = $like;
            $params['q_model'] = $like;
            $params['q_mfr'] = $like;
            $params['q_serial'] = $like;
            $params['q_mission'] = $like;
        }
        $limit = min(200, max(1, (int) ($filters['limit'] ?? 100)));
        $rows = $this->db->fetchAll(
            'SELECT * FROM sse_digital_devices WHERE ' . implode(' AND ', $where)
            . ' ORDER BY updated_at DESC LIMIT ' . $limit,
            $params
        );

        return array_map(fn (array $r): array => $this->hydrateDevice($r), $rows);
    }

    public function findDevice(int $id, int $tenantId): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM sse_digital_devices WHERE id = :id AND tenant_id = :tenant AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'tenant' => $tenantId]
        );

        return $row ? $this->hydrateDevice($row) : null;
    }

    public function findDeviceByArmaObjectId(int $tenantId, string $armaObjectId): ?array
    {
        $armaObjectId = trim($armaObjectId);
        if ($armaObjectId === '') {
            return null;
        }
        $row = $this->db->fetchOne(
            'SELECT * FROM sse_digital_devices
             WHERE tenant_id = :t AND arma_object_id = :a AND deleted_at IS NULL
             ORDER BY id DESC LIMIT 1',
            ['t' => $tenantId, 'a' => $armaObjectId]
        );

        return $row ? $this->hydrateDevice($row) : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createDevice(int $tenantId, array $data, ?int $userId = null): int
    {
        $ref = (string) ($data['reference_code'] ?? '');
        if ($ref === '') {
            $ref = $this->nextDeviceReference($tenantId);
        }
        $this->db->execute(
            'INSERT INTO sse_digital_devices (
                tenant_id, mission_id, case_id, reference_code, device_type, manufacturer, model,
                serial_number, color, capacity_label, apparent_condition, lock_state, has_sim, has_memory_card,
                has_battery, discovery_place, person_id, site_id, interest_case_id, mission_label, seized_by_label,
                power_state, locked, airplane_mode, network_connected, encryption_detected, presumed_os,
                displayed_time, language_label, damage_notes, accessories_notes, discovered_at, seized_at,
                packaging_notes, observations, data_profile, arma_object_id, status, classification, compartment, created_by
            ) VALUES (
                :tenant_id, :mission_id, :case_id, :reference_code, :device_type, :manufacturer, :model,
                :serial_number, :color, :capacity_label, :apparent_condition, :lock_state, :has_sim, :has_memory_card,
                :has_battery, :discovery_place, :person_id, :site_id, :interest_case_id, :mission_label, :seized_by_label,
                :power_state, :locked, :airplane_mode, :network_connected, :encryption_detected, :presumed_os,
                :displayed_time, :language_label, :damage_notes, :accessories_notes, :discovered_at, :seized_at,
                :packaging_notes, :observations, :data_profile, :arma_object_id, :status, :classification, :compartment, :created_by
            )',
            [
                'tenant_id' => $tenantId,
                'mission_id' => $data['mission_id'] ?? null,
                'case_id' => $data['case_id'] ?? null,
                'reference_code' => $ref,
                'device_type' => self::normalizeDeviceType((string) ($data['device_type'] ?? 'telephone')),
                'manufacturer' => $this->nullStr($data['manufacturer'] ?? null),
                'model' => $this->nullStr($data['model'] ?? null),
                'serial_number' => $this->nullStr($data['serial_number'] ?? null),
                'color' => $this->nullStr($data['color'] ?? null),
                'capacity_label' => $this->nullStr($data['capacity_label'] ?? null),
                'apparent_condition' => $this->nullStr($data['apparent_condition'] ?? null),
                'lock_state' => $this->nullStr($data['lock_state'] ?? null),
                'has_sim' => !empty($data['has_sim']) ? 1 : 0,
                'has_memory_card' => !empty($data['has_memory_card']) ? 1 : 0,
                'has_battery' => !isset($data['has_battery']) || !empty($data['has_battery']) ? 1 : 0,
                'discovery_place' => $this->nullStr($data['discovery_place'] ?? null),
                'person_id' => $data['person_id'] ?? null,
                'site_id' => $data['site_id'] ?? null,
                'interest_case_id' => $data['interest_case_id'] ?? null,
                'mission_label' => $this->nullStr($data['mission_label'] ?? null),
                'seized_by_label' => $this->nullStr($data['seized_by_label'] ?? null),
                'power_state' => $this->nullStr($data['power_state'] ?? null),
                'locked' => !empty($data['locked']) ? 1 : 0,
                'airplane_mode' => !empty($data['airplane_mode']) ? 1 : 0,
                'network_connected' => !empty($data['network_connected']) ? 1 : 0,
                'encryption_detected' => !empty($data['encryption_detected']) ? 1 : 0,
                'presumed_os' => $this->nullStr($data['presumed_os'] ?? null),
                'displayed_time' => $this->nullStr($data['displayed_time'] ?? null),
                'language_label' => $this->nullStr($data['language_label'] ?? null),
                'damage_notes' => $this->nullStr($data['damage_notes'] ?? null),
                'accessories_notes' => $this->nullStr($data['accessories_notes'] ?? null),
                'discovered_at' => $this->nullStr($data['discovered_at'] ?? null),
                'seized_at' => $this->nullStr($data['seized_at'] ?? null),
                'packaging_notes' => $this->nullStr($data['packaging_notes'] ?? null),
                'observations' => $this->nullStr($data['observations'] ?? null),
                'data_profile' => $this->nullStr($data['data_profile'] ?? null),
                'arma_object_id' => $this->nullStr($data['arma_object_id'] ?? null),
                'status' => self::normalizeDeviceStatus((string) ($data['status'] ?? 'seized')),
                'classification' => (string) ($data['classification'] ?? 'confidentiel'),
                'compartment' => $this->nullStr($data['compartment'] ?? null),
                'created_by' => $userId,
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    public function updateDeviceStatus(int $id, int $tenantId, string $status): bool
    {
        return $this->db->execute(
            'UPDATE sse_digital_devices SET status = :s WHERE id = :id AND tenant_id = :t AND deleted_at IS NULL',
            ['s' => self::normalizeDeviceStatus($status), 'id' => $id, 't' => $tenantId]
        ) > 0;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createSeizure(int $tenantId, int $deviceId, array $data, ?int $userId = null): int
    {
        $ref = (string) ($data['reference_code'] ?? '');
        if ($ref === '') {
            $ref = $this->nextSeizureReference($tenantId);
        }
        $handlers = $data['handlers'] ?? [];
        $this->db->execute(
            'INSERT INTO sse_digital_seizures (
                tenant_id, mission_id, case_id, device_id, reference_code, seal_label, packaging, photo_notes,
                handlers_json, discovered_at, seized_at, status, observations, classification, compartment, created_by
            ) VALUES (
                :tenant_id, :mission_id, :case_id, :device_id, :reference_code, :seal_label, :packaging, :photo_notes,
                :handlers_json, :discovered_at, :seized_at, :status, :observations, :classification, :compartment, :created_by
            )',
            [
                'tenant_id' => $tenantId,
                'mission_id' => $data['mission_id'] ?? null,
                'case_id' => $data['case_id'] ?? null,
                'device_id' => $deviceId,
                'reference_code' => $ref,
                'seal_label' => $this->nullStr($data['seal_label'] ?? null),
                'packaging' => $this->nullStr($data['packaging'] ?? null),
                'photo_notes' => $this->nullStr($data['photo_notes'] ?? null),
                'handlers_json' => is_array($handlers) ? json_encode($handlers, JSON_UNESCAPED_UNICODE) : null,
                'discovered_at' => $this->nullStr($data['discovered_at'] ?? null),
                'seized_at' => $this->nullStr($data['seized_at'] ?? date('Y-m-d H:i:s')),
                'status' => (string) ($data['status'] ?? 'seized'),
                'observations' => $this->nullStr($data['observations'] ?? null),
                'classification' => (string) ($data['classification'] ?? 'confidentiel'),
                'compartment' => $this->nullStr($data['compartment'] ?? null),
                'created_by' => $userId,
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function listSeizuresForDevice(int $deviceId, int $tenantId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM sse_digital_seizures WHERE device_id = :d AND tenant_id = :t ORDER BY created_at DESC',
            ['d' => $deviceId, 't' => $tenantId]
        );

        return $rows;
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function listAcquisitions(int $tenantId, array $filters = []): array
    {
        $where = ['a.tenant_id = :tenant'];
        $params = ['tenant' => $tenantId];
        if (!empty($filters['device_id'])) {
            $where[] = 'a.device_id = :device_id';
            $params['device_id'] = (int) $filters['device_id'];
        }
        if (isset(self::ACQUISITION_STATUSES[(string) ($filters['status'] ?? '')])) {
            $where[] = 'a.status = :status';
            $params['status'] = $filters['status'];
        }
        $limit = min(200, max(1, (int) ($filters['limit'] ?? 100)));
        $rows = $this->db->fetchAll(
            'SELECT a.*, d.reference_code AS device_reference, d.device_type
             FROM sse_digital_acquisitions a
             INNER JOIN sse_digital_devices d ON d.id = a.device_id AND d.tenant_id = a.tenant_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY a.updated_at DESC LIMIT ' . $limit,
            $params
        );

        return array_map(fn (array $r): array => $this->hydrateAcquisition($r), $rows);
    }

    public function findAcquisition(int $id, int $tenantId): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT a.*, d.reference_code AS device_reference, d.device_type, d.model AS device_model
             FROM sse_digital_acquisitions a
             INNER JOIN sse_digital_devices d ON d.id = a.device_id AND d.tenant_id = a.tenant_id
             WHERE a.id = :id AND a.tenant_id = :tenant LIMIT 1',
            ['id' => $id, 'tenant' => $tenantId]
        );

        return $row ? $this->hydrateAcquisition($row) : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createAcquisition(int $tenantId, int $deviceId, array $data, ?int $userId = null): int
    {
        $ref = (string) ($data['reference_code'] ?? '');
        if ($ref === '') {
            $ref = $this->nextAcquisitionReference($tenantId);
        }
        $this->db->execute(
            'INSERT INTO sse_digital_acquisitions (
                tenant_id, mission_id, case_id, device_id, seizure_id, reference_code, method, operator_label,
                started_at, ended_at, status, volume_bytes, file_count, artifact_count, integrity_algo, integrity_hash,
                tool_name, tool_version, is_partial, reserves, errors_text, data_profile, classification, compartment, created_by
            ) VALUES (
                :tenant_id, :mission_id, :case_id, :device_id, :seizure_id, :reference_code, :method, :operator_label,
                :started_at, :ended_at, :status, :volume_bytes, :file_count, :artifact_count, :integrity_algo, :integrity_hash,
                :tool_name, :tool_version, :is_partial, :reserves, :errors_text, :data_profile, :classification, :compartment, :created_by
            )',
            [
                'tenant_id' => $tenantId,
                'mission_id' => $data['mission_id'] ?? null,
                'case_id' => $data['case_id'] ?? null,
                'device_id' => $deviceId,
                'seizure_id' => $data['seizure_id'] ?? null,
                'reference_code' => $ref,
                'method' => self::normalizeAcquisitionMethod((string) ($data['method'] ?? 'logical')),
                'operator_label' => $this->nullStr($data['operator_label'] ?? null),
                'started_at' => $this->nullStr($data['started_at'] ?? null),
                'ended_at' => $this->nullStr($data['ended_at'] ?? null),
                'status' => self::normalizeAcquisitionStatus((string) ($data['status'] ?? 'planned')),
                'volume_bytes' => $data['volume_bytes'] ?? null,
                'file_count' => (int) ($data['file_count'] ?? 0),
                'artifact_count' => (int) ($data['artifact_count'] ?? 0),
                'integrity_algo' => (string) ($data['integrity_algo'] ?? 'SHA-256'),
                'integrity_hash' => $this->nullStr($data['integrity_hash'] ?? null),
                'tool_name' => $this->nullStr($data['tool_name'] ?? 'Athena LabNum Sim'),
                'tool_version' => $this->nullStr($data['tool_version'] ?? '1.0'),
                'is_partial' => !empty($data['is_partial']) ? 1 : 0,
                'reserves' => $this->nullStr($data['reserves'] ?? null),
                'errors_text' => $this->nullStr($data['errors_text'] ?? null),
                'data_profile' => $this->nullStr($data['data_profile'] ?? null),
                'classification' => (string) ($data['classification'] ?? 'confidentiel'),
                'compartment' => $this->nullStr($data['compartment'] ?? null),
                'created_by' => $userId,
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    public function updateAcquisition(int $id, int $tenantId, array $fields): bool
    {
        $allowed = [
            'status', 'ended_at', 'volume_bytes', 'file_count', 'artifact_count', 'integrity_hash',
            'is_partial', 'reserves', 'errors_text', 'started_at',
        ];
        $sets = [];
        $params = ['id' => $id, 'tenant' => $tenantId];
        foreach ($allowed as $col) {
            if (!array_key_exists($col, $fields)) {
                continue;
            }
            if ($col === 'status') {
                $fields[$col] = self::normalizeAcquisitionStatus((string) $fields[$col]);
            }
            $sets[] = "{$col} = :{$col}";
            $params[$col] = $fields[$col];
        }
        if ($sets === []) {
            return false;
        }

        return $this->db->execute(
            'UPDATE sse_digital_acquisitions SET ' . implode(', ', $sets) . ' WHERE id = :id AND tenant_id = :tenant',
            $params
        ) > 0;
    }

    public function addAcquisitionLog(int $tenantId, int $acquisitionId, string $message, string $level = 'info', ?int $userId = null): void
    {
        $this->db->execute(
            'INSERT INTO sse_digital_acquisition_logs (tenant_id, acquisition_id, level, message, created_by)
             VALUES (:t, :a, :l, :m, :u)',
            ['t' => $tenantId, 'a' => $acquisitionId, 'l' => $level, 'm' => $message, 'u' => $userId]
        );
    }

    /** @return list<array<string, mixed>> */
    public function listAcquisitionLogs(int $acquisitionId, int $tenantId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM sse_digital_acquisition_logs WHERE acquisition_id = :a AND tenant_id = :t ORDER BY logged_at ASC, id ASC',
            ['a' => $acquisitionId, 't' => $tenantId]
        );
    }

    public function addIntegrityCheck(int $tenantId, int $acquisitionId, string $hash, string $result = 'ok', ?int $userId = null): void
    {
        $this->db->execute(
            'INSERT INTO sse_digital_integrity_checks (tenant_id, acquisition_id, hash_value, result_status, created_by)
             VALUES (:t, :a, :h, :r, :u)',
            ['t' => $tenantId, 'a' => $acquisitionId, 'h' => $hash, 'r' => $result, 'u' => $userId]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createArtifact(int $tenantId, int $deviceId, int $acquisitionId, array $data, ?int $userId = null): int
    {
        $this->db->execute(
            'INSERT INTO sse_digital_artifacts (
                tenant_id, mission_id, case_id, device_id, acquisition_id, name, path, category, mime_label,
                size_bytes, created_at_device, modified_at_device, presumed_author, account_label, source_app,
                geo_lat, geo_lng, detected_persons, associated_identifiers, interest_level, status,
                is_deleted, is_hidden, is_encrypted, payload_json, classification, compartment, created_by
            ) VALUES (
                :tenant_id, :mission_id, :case_id, :device_id, :acquisition_id, :name, :path, :category, :mime_label,
                :size_bytes, :created_at_device, :modified_at_device, :presumed_author, :account_label, :source_app,
                :geo_lat, :geo_lng, :detected_persons, :associated_identifiers, :interest_level, :status,
                :is_deleted, :is_hidden, :is_encrypted, :payload_json, :classification, :compartment, :created_by
            )',
            [
                'tenant_id' => $tenantId,
                'mission_id' => $data['mission_id'] ?? null,
                'case_id' => $data['case_id'] ?? null,
                'device_id' => $deviceId,
                'acquisition_id' => $acquisitionId,
                'name' => (string) ($data['name'] ?? 'artefact'),
                'path' => $this->nullStr($data['path'] ?? null),
                'category' => self::normalizeCategory((string) ($data['category'] ?? 'document')),
                'mime_label' => $this->nullStr($data['mime_label'] ?? null),
                'size_bytes' => $data['size_bytes'] ?? null,
                'created_at_device' => $this->nullStr($data['created_at_device'] ?? null),
                'modified_at_device' => $this->nullStr($data['modified_at_device'] ?? null),
                'presumed_author' => $this->nullStr($data['presumed_author'] ?? null),
                'account_label' => $this->nullStr($data['account_label'] ?? null),
                'source_app' => $this->nullStr($data['source_app'] ?? null),
                'geo_lat' => $data['geo_lat'] ?? null,
                'geo_lng' => $data['geo_lng'] ?? null,
                'detected_persons' => $this->nullStr($data['detected_persons'] ?? null),
                'associated_identifiers' => $this->nullStr($data['associated_identifiers'] ?? null),
                'interest_level' => isset(self::INTEREST_LEVELS[(string) ($data['interest_level'] ?? '')])
                    ? $data['interest_level']
                    : 'courant',
                'status' => self::normalizeArtifactStatus((string) ($data['status'] ?? 'unexamined')),
                'is_deleted' => !empty($data['is_deleted']) ? 1 : 0,
                'is_hidden' => !empty($data['is_hidden']) ? 1 : 0,
                'is_encrypted' => !empty($data['is_encrypted']) ? 1 : 0,
                'payload_json' => isset($data['payload']) && is_array($data['payload'])
                    ? json_encode($data['payload'], JSON_UNESCAPED_UNICODE)
                    : $this->nullStr($data['payload_json'] ?? null),
                'classification' => (string) ($data['classification'] ?? 'confidentiel'),
                'compartment' => $this->nullStr($data['compartment'] ?? null),
                'created_by' => $userId,
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function listArtifacts(int $tenantId, array $filters = []): array
    {
        $where = ['tenant_id = :tenant', 'deleted_at IS NULL'];
        $params = ['tenant' => $tenantId];
        if (!empty($filters['device_id'])) {
            $where[] = 'device_id = :device_id';
            $params['device_id'] = (int) $filters['device_id'];
        }
        if (!empty($filters['acquisition_id'])) {
            $where[] = 'acquisition_id = :acquisition_id';
            $params['acquisition_id'] = (int) $filters['acquisition_id'];
        }
        if (isset(self::ARTIFACT_CATEGORIES[(string) ($filters['category'] ?? '')])) {
            $where[] = 'category = :category';
            $params['category'] = $filters['category'];
        }
        if (isset(self::ARTIFACT_STATUSES[(string) ($filters['status'] ?? '')])) {
            $where[] = 'status = :status';
            $params['status'] = $filters['status'];
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . $q . '%';
            $where[] = '(name LIKE :q_name OR path LIKE :q_path OR associated_identifiers LIKE :q_ids)';
            $params['q_name'] = $like;
            $params['q_path'] = $like;
            $params['q_ids'] = $like;
        }
        $limit = min(500, max(1, (int) ($filters['limit'] ?? 200)));
        $rows = $this->db->fetchAll(
            'SELECT * FROM sse_digital_artifacts WHERE ' . implode(' AND ', $where)
            . ' ORDER BY interest_level DESC, updated_at DESC LIMIT ' . $limit,
            $params
        );

        return array_map(fn (array $r): array => $this->hydrateArtifact($r), $rows);
    }

    public function findArtifact(int $id, int $tenantId): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM sse_digital_artifacts WHERE id = :id AND tenant_id = :t AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 't' => $tenantId]
        );

        return $row ? $this->hydrateArtifact($row) : null;
    }

    public function updateArtifactStatus(int $id, int $tenantId, string $status, ?string $comment = null): bool
    {
        return $this->db->execute(
            'UPDATE sse_digital_artifacts SET status = :s, analyst_comment = COALESCE(:c, analyst_comment)
             WHERE id = :id AND tenant_id = :t AND deleted_at IS NULL',
            [
                's' => self::normalizeArtifactStatus($status),
                'c' => $comment,
                'id' => $id,
                't' => $tenantId,
            ]
        ) > 0;
    }

    /** @param array<string, mixed> $data */
    public function createContact(int $tenantId, int $deviceId, int $acquisitionId, array $data): int
    {
        $this->db->execute(
            'INSERT INTO sse_digital_contacts (tenant_id, device_id, acquisition_id, display_name, phone_number, email, alias_label, notes)
             VALUES (:t, :d, :a, :n, :p, :e, :al, :notes)',
            [
                't' => $tenantId,
                'd' => $deviceId,
                'a' => $acquisitionId,
                'n' => (string) ($data['display_name'] ?? 'Contact'),
                'p' => $this->nullStr($data['phone_number'] ?? null),
                'e' => $this->nullStr($data['email'] ?? null),
                'al' => $this->nullStr($data['alias_label'] ?? null),
                'notes' => $this->nullStr($data['notes'] ?? null),
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function listContacts(int $deviceId, int $tenantId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM sse_digital_contacts WHERE device_id = :d AND tenant_id = :t ORDER BY display_name ASC',
            ['d' => $deviceId, 't' => $tenantId]
        );
    }

    /** @param array<string, mixed> $data */
    public function createMessage(int $tenantId, int $deviceId, int $acquisitionId, array $data): int
    {
        $this->db->execute(
            'INSERT INTO sse_digital_messages (
                tenant_id, device_id, acquisition_id, thread_key, direction, sender_label, recipient_label,
                body, sent_at, app_label, is_deleted, has_attachment
            ) VALUES (
                :t, :d, :a, :tk, :dir, :sender, :recipient, :body, :sent_at, :app, :del, :att
            )',
            [
                't' => $tenantId,
                'd' => $deviceId,
                'a' => $acquisitionId,
                'tk' => $this->nullStr($data['thread_key'] ?? null),
                'dir' => (string) ($data['direction'] ?? 'inbound'),
                'sender' => $this->nullStr($data['sender_label'] ?? null),
                'recipient' => $this->nullStr($data['recipient_label'] ?? null),
                'body' => $this->nullStr($data['body'] ?? null),
                'sent_at' => $this->nullStr($data['sent_at'] ?? null),
                'app' => $this->nullStr($data['app_label'] ?? 'SMS'),
                'del' => !empty($data['is_deleted']) ? 1 : 0,
                'att' => !empty($data['has_attachment']) ? 1 : 0,
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function listMessages(int $deviceId, int $tenantId, ?string $threadKey = null): array
    {
        if ($threadKey !== null && $threadKey !== '') {
            return $this->db->fetchAll(
                'SELECT * FROM sse_digital_messages WHERE device_id = :d AND tenant_id = :t AND thread_key = :tk ORDER BY sent_at ASC',
                ['d' => $deviceId, 't' => $tenantId, 'tk' => $threadKey]
            );
        }

        return $this->db->fetchAll(
            'SELECT * FROM sse_digital_messages WHERE device_id = :d AND tenant_id = :t ORDER BY sent_at DESC LIMIT 300',
            ['d' => $deviceId, 't' => $tenantId]
        );
    }

    /** @param array<string, mixed> $data */
    public function createCall(int $tenantId, int $deviceId, int $acquisitionId, array $data): int
    {
        $this->db->execute(
            'INSERT INTO sse_digital_calls (tenant_id, device_id, acquisition_id, direction, peer_label, peer_number, started_at, duration_sec)
             VALUES (:t, :d, :a, :dir, :pl, :pn, :st, :dur)',
            [
                't' => $tenantId,
                'd' => $deviceId,
                'a' => $acquisitionId,
                'dir' => (string) ($data['direction'] ?? 'inbound'),
                'pl' => $this->nullStr($data['peer_label'] ?? null),
                'pn' => $this->nullStr($data['peer_number'] ?? null),
                'st' => $this->nullStr($data['started_at'] ?? null),
                'dur' => (int) ($data['duration_sec'] ?? 0),
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function listCalls(int $deviceId, int $tenantId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM sse_digital_calls WHERE device_id = :d AND tenant_id = :t ORDER BY started_at DESC LIMIT 200',
            ['d' => $deviceId, 't' => $tenantId]
        );
    }

    /** @param array<string, mixed> $data */
    public function createAccount(int $tenantId, int $deviceId, int $acquisitionId, array $data): int
    {
        $this->db->execute(
            'INSERT INTO sse_digital_accounts (tenant_id, device_id, acquisition_id, service_label, username, email, notes)
             VALUES (:t, :d, :a, :s, :u, :e, :n)',
            [
                't' => $tenantId,
                'd' => $deviceId,
                'a' => $acquisitionId,
                's' => (string) ($data['service_label'] ?? 'Compte'),
                'u' => $this->nullStr($data['username'] ?? null),
                'e' => $this->nullStr($data['email'] ?? null),
                'n' => $this->nullStr($data['notes'] ?? null),
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function listAccounts(int $deviceId, int $tenantId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM sse_digital_accounts WHERE device_id = :d AND tenant_id = :t ORDER BY service_label ASC',
            ['d' => $deviceId, 't' => $tenantId]
        );
    }

    /** @param array<string, mixed> $data */
    public function createLocation(int $tenantId, int $deviceId, int $acquisitionId, array $data): int
    {
        $this->db->execute(
            'INSERT INTO sse_digital_locations (tenant_id, device_id, acquisition_id, label, lat, lng, observed_at, source_label)
             VALUES (:t, :d, :a, :l, :lat, :lng, :o, :s)',
            [
                't' => $tenantId,
                'd' => $deviceId,
                'a' => $acquisitionId,
                'l' => $this->nullStr($data['label'] ?? null),
                'lat' => $data['lat'] ?? null,
                'lng' => $data['lng'] ?? null,
                'o' => $this->nullStr($data['observed_at'] ?? null),
                's' => $this->nullStr($data['source_label'] ?? null),
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function listLocations(int $deviceId, int $tenantId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM sse_digital_locations WHERE device_id = :d AND tenant_id = :t ORDER BY observed_at DESC',
            ['d' => $deviceId, 't' => $tenantId]
        );
    }

    /** @param array<string, mixed> $data */
    public function createNetwork(int $tenantId, int $deviceId, int $acquisitionId, array $data): int
    {
        $this->db->execute(
            'INSERT INTO sse_digital_networks (tenant_id, device_id, acquisition_id, network_type, ssid_or_name, observed_at, notes)
             VALUES (:t, :d, :a, :nt, :n, :o, :notes)',
            [
                't' => $tenantId,
                'd' => $deviceId,
                'a' => $acquisitionId,
                'nt' => (string) ($data['network_type'] ?? 'wifi'),
                'n' => (string) ($data['ssid_or_name'] ?? 'Réseau'),
                'o' => $this->nullStr($data['observed_at'] ?? null),
                'notes' => $this->nullStr($data['notes'] ?? null),
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function listNetworks(int $deviceId, int $tenantId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM sse_digital_networks WHERE device_id = :d AND tenant_id = :t ORDER BY observed_at DESC',
            ['d' => $deviceId, 't' => $tenantId]
        );
    }

    /** @param array<string, mixed> $data */
    public function createApplication(int $tenantId, int $deviceId, int $acquisitionId, array $data): int
    {
        $this->db->execute(
            'INSERT INTO sse_digital_applications (tenant_id, device_id, acquisition_id, app_name, package_or_path, version_label)
             VALUES (:t, :d, :a, :n, :p, :v)',
            [
                't' => $tenantId,
                'd' => $deviceId,
                'a' => $acquisitionId,
                'n' => (string) ($data['app_name'] ?? 'Application'),
                'p' => $this->nullStr($data['package_or_path'] ?? null),
                'v' => $this->nullStr($data['version_label'] ?? null),
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function listApplications(int $deviceId, int $tenantId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM sse_digital_applications WHERE device_id = :d AND tenant_id = :t ORDER BY app_name ASC',
            ['d' => $deviceId, 't' => $tenantId]
        );
    }

    /** @param array<string, mixed> $data */
    public function createMedia(int $tenantId, int $deviceId, int $acquisitionId, array $data): int
    {
        $this->db->execute(
            'INSERT INTO sse_digital_media (tenant_id, device_id, acquisition_id, artifact_id, media_type, name, captured_at, geo_lat, geo_lng, integrity_hash)
             VALUES (:t, :d, :a, :art, :mt, :n, :c, :lat, :lng, :h)',
            [
                't' => $tenantId,
                'd' => $deviceId,
                'a' => $acquisitionId,
                'art' => $data['artifact_id'] ?? null,
                'mt' => (string) ($data['media_type'] ?? 'image'),
                'n' => (string) ($data['name'] ?? 'média'),
                'c' => $this->nullStr($data['captured_at'] ?? null),
                'lat' => $data['geo_lat'] ?? null,
                'lng' => $data['geo_lng'] ?? null,
                'h' => $this->nullStr($data['integrity_hash'] ?? null),
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function listMedia(int $deviceId, int $tenantId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM sse_digital_media WHERE device_id = :d AND tenant_id = :t ORDER BY captured_at DESC',
            ['d' => $deviceId, 't' => $tenantId]
        );
    }

    /** @param array<string, mixed> $data */
    public function createTimelineEvent(int $tenantId, array $data, ?int $userId = null): int
    {
        $this->db->execute(
            'INSERT INTO sse_digital_timelines (
                tenant_id, mission_id, case_id, device_id, acquisition_id, artifact_id, event_type, event_at,
                title, detail, interest_level, is_validated, classification, compartment, created_by
            ) VALUES (
                :tenant_id, :mission_id, :case_id, :device_id, :acquisition_id, :artifact_id, :event_type, :event_at,
                :title, :detail, :interest_level, :is_validated, :classification, :compartment, :created_by
            )',
            [
                'tenant_id' => $tenantId,
                'mission_id' => $data['mission_id'] ?? null,
                'case_id' => $data['case_id'] ?? null,
                'device_id' => $data['device_id'] ?? null,
                'acquisition_id' => $data['acquisition_id'] ?? null,
                'artifact_id' => $data['artifact_id'] ?? null,
                'event_type' => (string) ($data['event_type'] ?? 'other'),
                'event_at' => (string) ($data['event_at'] ?? date('Y-m-d H:i:s')),
                'title' => (string) ($data['title'] ?? 'Événement'),
                'detail' => $this->nullStr($data['detail'] ?? null),
                'interest_level' => isset(self::INTEREST_LEVELS[(string) ($data['interest_level'] ?? '')])
                    ? $data['interest_level']
                    : 'courant',
                'is_validated' => !empty($data['is_validated']) ? 1 : 0,
                'classification' => (string) ($data['classification'] ?? 'confidentiel'),
                'compartment' => $this->nullStr($data['compartment'] ?? null),
                'created_by' => $userId,
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function listTimeline(int $tenantId, array $filters = []): array
    {
        $where = ['tenant_id = :tenant'];
        $params = ['tenant' => $tenantId];
        if (!empty($filters['device_id'])) {
            $where[] = 'device_id = :device_id';
            $params['device_id'] = (int) $filters['device_id'];
        }
        if (!empty($filters['event_type'])) {
            $where[] = 'event_type = :event_type';
            $params['event_type'] = (string) $filters['event_type'];
        }
        if (isset($filters['validated']) && $filters['validated'] !== '') {
            $where[] = 'is_validated = :val';
            $params['val'] = (string) $filters['validated'] === '1' ? 1 : 0;
        }
        $limit = min(500, max(1, (int) ($filters['limit'] ?? 200)));
        $rows = $this->db->fetchAll(
            'SELECT * FROM sse_digital_timelines WHERE ' . implode(' AND ', $where)
            . ' ORDER BY event_at DESC LIMIT ' . $limit,
            $params
        );

        return array_map(function (array $r): array {
            $r['interest_level_label'] = self::INTEREST_LEVELS[(string) ($r['interest_level'] ?? '')] ?? 'Courant';
            $r['validated_label'] = !empty($r['is_validated']) ? 'Validé' : 'Non validé';

            return $r;
        }, $rows);
    }

    /** @param array<string, mixed> $data */
    public function createFinding(int $tenantId, array $data, ?int $userId = null): int
    {
        $this->db->execute(
            'INSERT INTO sse_digital_findings (
                tenant_id, mission_id, case_id, device_id, acquisition_id, artifact_id, finding_type, title, detail,
                confidence_level, score_pct, status, factors_json, proposed_relation_json, algorithm_version,
                classification, compartment, created_by
            ) VALUES (
                :tenant_id, :mission_id, :case_id, :device_id, :acquisition_id, :artifact_id, :finding_type, :title, :detail,
                :confidence_level, :score_pct, :status, :factors_json, :proposed_relation_json, :algorithm_version,
                :classification, :compartment, :created_by
            )',
            [
                'tenant_id' => $tenantId,
                'mission_id' => $data['mission_id'] ?? null,
                'case_id' => $data['case_id'] ?? null,
                'device_id' => $data['device_id'] ?? null,
                'acquisition_id' => $data['acquisition_id'] ?? null,
                'artifact_id' => $data['artifact_id'] ?? null,
                'finding_type' => (string) ($data['finding_type'] ?? 'signal'),
                'title' => (string) ($data['title'] ?? 'Signal analytique'),
                'detail' => $this->nullStr($data['detail'] ?? null),
                'confidence_level' => isset(self::CONFIDENCE_LEVELS[(string) ($data['confidence_level'] ?? '')])
                    ? $data['confidence_level']
                    : 'modere',
                'score_pct' => $data['score_pct'] ?? null,
                'status' => isset(self::FINDING_STATUSES[(string) ($data['status'] ?? '')])
                    ? $data['status']
                    : 'to_review',
                'factors_json' => isset($data['factors']) && is_array($data['factors'])
                    ? json_encode($data['factors'], JSON_UNESCAPED_UNICODE)
                    : null,
                'proposed_relation_json' => isset($data['proposed_relation']) && is_array($data['proposed_relation'])
                    ? json_encode($data['proposed_relation'], JSON_UNESCAPED_UNICODE)
                    : null,
                'algorithm_version' => (string) ($data['algorithm_version'] ?? 'labnum-1.0'),
                'classification' => (string) ($data['classification'] ?? 'confidentiel'),
                'compartment' => $this->nullStr($data['compartment'] ?? null),
                'created_by' => $userId,
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function listFindings(int $tenantId, array $filters = []): array
    {
        $where = ['tenant_id = :tenant'];
        $params = ['tenant' => $tenantId];
        if (!empty($filters['device_id'])) {
            $where[] = 'device_id = :device_id';
            $params['device_id'] = (int) $filters['device_id'];
        }
        if (isset(self::FINDING_STATUSES[(string) ($filters['status'] ?? '')])) {
            $where[] = 'status = :status';
            $params['status'] = $filters['status'];
        }
        $rows = $this->db->fetchAll(
            'SELECT * FROM sse_digital_findings WHERE ' . implode(' AND ', $where)
            . ' ORDER BY created_at DESC LIMIT 200',
            $params
        );

        return array_map(function (array $r): array {
            $r['status_label'] = self::FINDING_STATUSES[(string) ($r['status'] ?? '')] ?? (string) ($r['status'] ?? '');
            $r['confidence_label'] = self::CONFIDENCE_LEVELS[(string) ($r['confidence_level'] ?? '')] ?? 'Modérée';
            $r['factors'] = [];
            if (!empty($r['factors_json'])) {
                $decoded = json_decode((string) $r['factors_json'], true);
                $r['factors'] = is_array($decoded) ? $decoded : [];
            }
            $r['proposed_relation'] = null;
            if (!empty($r['proposed_relation_json'])) {
                $decoded = json_decode((string) $r['proposed_relation_json'], true);
                $r['proposed_relation'] = is_array($decoded) ? $decoded : null;
            }

            return $r;
        }, $rows);
    }

    public function findFinding(int $id, int $tenantId): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM sse_digital_findings WHERE id = :id AND tenant_id = :t LIMIT 1',
            ['id' => $id, 't' => $tenantId]
        );
        if (!$row) {
            return null;
        }
        $row['status_label'] = self::FINDING_STATUSES[(string) ($row['status'] ?? '')] ?? (string) ($row['status'] ?? '');
        $row['confidence_label'] = self::CONFIDENCE_LEVELS[(string) ($row['confidence_level'] ?? '')] ?? 'Modérée';
        $row['factors'] = [];
        if (!empty($row['factors_json'])) {
            $decoded = json_decode((string) $row['factors_json'], true);
            $row['factors'] = is_array($decoded) ? $decoded : [];
        }
        $row['proposed_relation'] = null;
        if (!empty($row['proposed_relation_json'])) {
            $decoded = json_decode((string) $row['proposed_relation_json'], true);
            $row['proposed_relation'] = is_array($decoded) ? $decoded : null;
        }

        return $row;
    }

    public function reviewFinding(int $id, int $tenantId, string $status, int $userId, ?string $comment = null): bool
    {
        if (!isset(self::FINDING_STATUSES[$status])) {
            return false;
        }

        return $this->db->execute(
            'UPDATE sse_digital_findings
             SET status = :s, reviewed_by = :u, reviewed_at = NOW(), review_comment = :c
             WHERE id = :id AND tenant_id = :t',
            ['s' => $status, 'u' => $userId, 'c' => $comment, 'id' => $id, 't' => $tenantId]
        ) > 0;
    }

    /** @return array{devices:int,acquisitions:int,artifacts:int,findings_pending:int} */
    public function hubCounts(int $tenantId): array
    {
        $devices = $this->countDevices($tenantId);
        $acq = $this->db->fetchOne(
            'SELECT COUNT(*) AS c FROM sse_digital_acquisitions WHERE tenant_id = :t',
            ['t' => $tenantId]
        );
        $art = $this->db->fetchOne(
            'SELECT COUNT(*) AS c FROM sse_digital_artifacts WHERE tenant_id = :t AND deleted_at IS NULL',
            ['t' => $tenantId]
        );
        $find = $this->db->fetchOne(
            "SELECT COUNT(*) AS c FROM sse_digital_findings WHERE tenant_id = :t AND status = 'to_review'",
            ['t' => $tenantId]
        );

        return [
            'devices' => $devices,
            'acquisitions' => (int) ($acq['c'] ?? 0),
            'artifacts' => (int) ($art['c'] ?? 0),
            'findings_pending' => (int) ($find['c'] ?? 0),
        ];
    }

    /** @param array<string, mixed> $row */
    private function hydrateDevice(array $row): array
    {
        $row['device_type_label'] = self::DEVICE_TYPES[(string) ($row['device_type'] ?? '')] ?? 'Support';
        $row['status_label'] = self::DEVICE_STATUSES[(string) ($row['status'] ?? '')] ?? 'Inconnu';
        $profile = (string) ($row['data_profile'] ?? '');
        $row['data_profile_label'] = self::DATA_PROFILES[$profile] ?? ($profile !== '' ? $profile : '—');
        $row['locked_label'] = !empty($row['locked']) ? 'Verrouillé' : 'Déverrouillé';
        $row['power_state_label'] = match ((string) ($row['power_state'] ?? '')) {
            'on' => 'Allumé',
            'off' => 'Éteint',
            default => (string) ($row['power_state'] ?? '—') ?: '—',
        };

        return $row;
    }

    /** @param array<string, mixed> $row */
    private function hydrateAcquisition(array $row): array
    {
        $row['method_label'] = self::ACQUISITION_METHODS[(string) ($row['method'] ?? '')] ?? 'Acquisition';
        $row['status_label'] = self::ACQUISITION_STATUSES[(string) ($row['status'] ?? '')] ?? 'Inconnu';
        $row['device_type_label'] = self::DEVICE_TYPES[(string) ($row['device_type'] ?? '')] ?? '';
        $bytes = (int) ($row['volume_bytes'] ?? 0);
        $row['volume_label'] = $bytes > 0 ? $this->formatBytes($bytes) : '—';
        $row['partial_label'] = !empty($row['is_partial']) ? 'Partielle' : 'Complète';

        return $row;
    }

    /** @param array<string, mixed> $row */
    private function hydrateArtifact(array $row): array
    {
        $row['category_label'] = self::ARTIFACT_CATEGORIES[(string) ($row['category'] ?? '')] ?? 'Autre';
        $row['status_label'] = self::ARTIFACT_STATUSES[(string) ($row['status'] ?? '')] ?? 'Inconnu';
        $row['interest_level_label'] = self::INTEREST_LEVELS[(string) ($row['interest_level'] ?? '')] ?? 'Courant';
        $bytes = (int) ($row['size_bytes'] ?? 0);
        $row['size_label'] = $bytes > 0 ? $this->formatBytes($bytes) : '—';

        return $row;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 1) . ' Go';
        }
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' Mo';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' Ko';
        }

        return $bytes . ' o';
    }

    private function nullStr(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $s = trim((string) $v);

        return $s === '' ? null : $s;
    }
}
