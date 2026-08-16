<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SilentSchemaMigration;

/**
 * Timeline d’événements SSE normalisés (idempotente).
 */
final class SseIntelEventRepository
{
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
        SilentSchemaMigration::run(base_path('bootstrap/atak_sse_intel_foundation_migration.php'));
        $done = true;
    }

    /**
     * @param array<string, mixed> $data
     * @return array{id:int,event_uuid:string,created:bool,row:?array<string,mixed>}
     */
    public function append(array $data): array
    {
        $tenantId = (int) ($data['tenant_id'] ?? 0);
        if ($tenantId < 1) {
            throw new \InvalidArgumentException('tenant_id requis.');
        }

        $idem = trim((string) ($data['idempotency_key'] ?? ''));
        if ($idem !== '') {
            $existing = $this->findByIdempotency($tenantId, $idem);
            if ($existing !== null) {
                return [
                    'id' => (int) $existing['id'],
                    'event_uuid' => (string) $existing['event_uuid'],
                    'created' => false,
                    'row' => $existing,
                ];
            }
        }

        $eventUuid = trim((string) ($data['event_uuid'] ?? ''));
        if ($eventUuid === '') {
            $eventUuid = SseEntityIndexRepository::newUuid();
        }

        $payload = $data['payload'] ?? $data['payload_json'] ?? null;
        if (is_array($payload)) {
            $payload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $eventTime = (string) ($data['event_time'] ?? gmdate('Y-m-d H:i:s'));
        if ($eventTime === '') {
            $eventTime = gmdate('Y-m-d H:i:s');
        }

        try {
            $id = (int) $this->db->insert(
                'INSERT INTO sse_intel_events (
                    event_uuid, tenant_id, context_id, case_id, interest_case_id, entity_uuid,
                    event_type, source_system, raw_source_id, identity_tier, event_time,
                    author_label, unit_label, lat, lng, source_reliability, info_credibility,
                    summary, payload_json, idempotency_key
                ) VALUES (
                    :event_uuid, :tenant_id, :context_id, :case_id, :interest_case_id, :entity_uuid,
                    :event_type, :source_system, :raw_source_id, :identity_tier, :event_time,
                    :author_label, :unit_label, :lat, :lng, :source_reliability, :info_credibility,
                    :summary, :payload_json, :idempotency_key
                )',
                [
                    'event_uuid' => $eventUuid,
                    'tenant_id' => $tenantId,
                    'context_id' => (int) ($data['context_id'] ?? 1),
                    'case_id' => isset($data['case_id']) && (int) $data['case_id'] > 0 ? (int) $data['case_id'] : null,
                    'interest_case_id' => isset($data['interest_case_id']) && (int) $data['interest_case_id'] > 0
                        ? (int) $data['interest_case_id'] : null,
                    'entity_uuid' => $this->nullIfEmpty($data['entity_uuid'] ?? null),
                    'event_type' => strtoupper(trim((string) ($data['event_type'] ?? 'OBSERVED'))),
                    'source_system' => strtoupper(trim((string) ($data['source_system'] ?? 'ARMA_SSE'))),
                    'raw_source_id' => $this->nullIfEmpty($data['raw_source_id'] ?? null),
                    'identity_tier' => $this->nullIfEmpty($data['identity_tier'] ?? null),
                    'event_time' => $eventTime,
                    'author_label' => $this->nullIfEmpty($data['author_label'] ?? null),
                    'unit_label' => $this->nullIfEmpty($data['unit_label'] ?? null),
                    'lat' => isset($data['lat']) && $data['lat'] !== '' && $data['lat'] !== null ? (float) $data['lat'] : null,
                    'lng' => isset($data['lng']) && $data['lng'] !== '' && $data['lng'] !== null ? (float) $data['lng'] : null,
                    'source_reliability' => $this->reliability($data['source_reliability'] ?? 'F'),
                    'info_credibility' => $this->credibility($data['info_credibility'] ?? 6),
                    'summary' => mb_substr(trim((string) ($data['summary'] ?? '')), 0, 500),
                    'payload_json' => is_string($payload) ? $payload : null,
                    'idempotency_key' => $idem !== '' ? $idem : null,
                ]
            );
        } catch (\Throwable $e) {
            // Course idempotence
            if ($idem !== '') {
                $existing = $this->findByIdempotency($tenantId, $idem);
                if ($existing !== null) {
                    return [
                        'id' => (int) $existing['id'],
                        'event_uuid' => (string) $existing['event_uuid'],
                        'created' => false,
                        'row' => $existing,
                    ];
                }
            }
            throw $e;
        }

        $row = $this->findById($tenantId, $id);

        return [
            'id' => $id,
            'event_uuid' => $eventUuid,
            'created' => true,
            'row' => $row,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $tenantId, int $id): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM sse_intel_events WHERE tenant_id = :t AND id = :id LIMIT 1',
            ['t' => $tenantId, 'id' => $id]
        );

        return $row ? $this->hydrate($row) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByIdempotency(int $tenantId, string $key): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM sse_intel_events WHERE tenant_id = :t AND idempotency_key = :k LIMIT 1',
            ['t' => $tenantId, 'k' => $key]
        );

        return $row ? $this->hydrate($row) : null;
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function listForTenant(int $tenantId, array $filters = []): array
    {
        $sql = 'SELECT * FROM sse_intel_events WHERE tenant_id = :t';
        $params = ['t' => $tenantId];

        if (!empty($filters['case_id'])) {
            $sql .= ' AND case_id = :case_id';
            $params['case_id'] = (int) $filters['case_id'];
        }
        if (!empty($filters['entity_uuid'])) {
            $sql .= ' AND entity_uuid = :eu';
            $params['eu'] = (string) $filters['entity_uuid'];
        }
        if (!empty($filters['event_type'])) {
            $sql .= ' AND event_type = :etype';
            $params['etype'] = strtoupper((string) $filters['event_type']);
        }
        if (!empty($filters['source_system'])) {
            $sql .= ' AND source_system = :src';
            $params['src'] = strtoupper((string) $filters['source_system']);
        } elseif (!empty($filters['source_systems']) && is_array($filters['source_systems'])) {
            $in = [];
            foreach (array_values($filters['source_systems']) as $i => $src) {
                $key = 'src' . $i;
                $in[] = ':' . $key;
                $params[$key] = strtoupper(trim((string) $src));
            }
            if ($in !== []) {
                $sql .= ' AND source_system IN (' . implode(', ', $in) . ')';
            }
        }
        if (!empty($filters['q'])) {
            $sql .= ' AND (summary LIKE :q OR author_label LIKE :q OR unit_label LIKE :q OR raw_source_id LIKE :q)';
            $params['q'] = '%' . trim((string) $filters['q']) . '%';
        }
        if (!empty($filters['since'])) {
            $sql .= ' AND event_time >= :since';
            $params['since'] = (string) $filters['since'];
        }
        if (!empty($filters['until'])) {
            $sql .= ' AND event_time <= :until';
            $params['until'] = (string) $filters['until'];
        }

        $limit = min(200, max(1, (int) ($filters['limit'] ?? 40)));
        $offset = max(0, (int) ($filters['offset'] ?? 0));
        $sql .= ' ORDER BY event_time DESC, id DESC LIMIT ' . $limit . ' OFFSET ' . $offset;

        $rows = $this->db->fetchAll($sql, $params);
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->hydrate($row);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        $rel = strtoupper((string) ($row['source_reliability'] ?? 'F'));
        $cred = (int) ($row['info_credibility'] ?? 6);
        $payload = null;
        if (!empty($row['payload_json'])) {
            $decoded = json_decode((string) $row['payload_json'], true);
            $payload = is_array($decoded) ? $decoded : null;
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'event_uuid' => (string) ($row['event_uuid'] ?? ''),
            'tenant_id' => (int) ($row['tenant_id'] ?? 0),
            'context_id' => (int) ($row['context_id'] ?? 1),
            'case_id' => isset($row['case_id']) ? (int) $row['case_id'] : null,
            'interest_case_id' => isset($row['interest_case_id']) ? (int) $row['interest_case_id'] : null,
            'entity_uuid' => $row['entity_uuid'] ?? null,
            'event_type' => (string) ($row['event_type'] ?? ''),
            'event_type_label' => $this->eventTypeLabel((string) ($row['event_type'] ?? '')),
            'source_system' => (string) ($row['source_system'] ?? ''),
            'source_system_label' => $this->sourceSystemLabel((string) ($row['source_system'] ?? '')),
            'raw_source_id' => $row['raw_source_id'] ?? null,
            'identity_tier' => $row['identity_tier'] ?? null,
            'event_time' => (string) ($row['event_time'] ?? ''),
            'author_label' => $row['author_label'] ?? null,
            'unit_label' => $row['unit_label'] ?? null,
            'lat' => isset($row['lat']) ? (float) $row['lat'] : null,
            'lng' => isset($row['lng']) ? (float) $row['lng'] : null,
            'pos_x' => isset($row['pos_x']) && $row['pos_x'] !== null ? (float) $row['pos_x'] : null,
            'pos_y' => isset($row['pos_y']) && $row['pos_y'] !== null ? (float) $row['pos_y'] : null,
            'source_reliability' => $rel,
            'info_credibility' => $cred,
            'confidence_code' => $rel . (string) $cred,
            'summary' => (string) ($row['summary'] ?? ''),
            'payload' => $payload,
            'idempotency_key' => $row['idempotency_key'] ?? null,
            'created_at' => $row['created_at'] ?? null,
        ];
    }

    /**
     * Sources typiques d’une transmission depuis Arma 3 / mods terrain.
     *
     * @return list<string>
     */
    public static function armaTerrainSourceSystems(): array
    {
        return [
            'ARMA_SSE',
            'ACE',
            'ACE_DOGTAG',
            'BII_IDENTIFI',
            'ZEUS',
            'EDEN',
            'CTAB',
            'TFAR',
            'ACRE',
            'UAV',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function eventTypeOptions(): array
    {
        return [
            'OBSERVED' => 'Observation',
            'IDENTIFIED' => 'Identification',
            'PHOTOGRAPHED' => 'Photographie',
            'BIOMETRIC_CAPTURE' => 'Acquisition biométrique',
            'DEVICE_SEIZED' => 'Appareil saisi',
            'DEVICE_EXPLOITED' => 'Appareil exploité',
            'SITE_SEARCHED' => 'Site fouillé',
            'VEHICLE_OBSERVED' => 'Véhicule observé',
            'RELATION_CREATED' => 'Relation créée',
            'REPORT_RECEIVED' => 'Rapport reçu',
            'INTEL_VALIDATED' => 'Renseignement validé',
            'INTEL_DISSEMINATED' => 'Renseignement diffusé',
            'EXPLOSIVE_COMPONENT_FOUND' => 'Composant explosif trouvé',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function sourceSystemOptions(): array
    {
        $out = [];
        foreach (array_merge(self::armaTerrainSourceSystems(), ['MANUAL']) as $code) {
            $out[$code] = self::labelForSourceSystem($code);
        }

        return $out;
    }

    public static function labelForEventType(string $type): string
    {
        $options = self::eventTypeOptions();
        $key = strtoupper($type);
        if ($key === 'BIOMETRIC_SCAN') {
            return $options['BIOMETRIC_CAPTURE'];
        }

        return $options[$key] ?? ($type !== '' ? $type : 'Événement');
    }

    public static function labelForSourceSystem(string $src): string
    {
        return match (strtoupper($src)) {
            'ACE' => 'ACE',
            'ACE_DOGTAG' => 'Plaque d’identité ACE',
            'BII_IDENTIFI' => 'BII Identifi',
            'ARMA_SSE' => 'Terminal SSE (Arma)',
            'ZEUS' => 'Zeus',
            'EDEN' => 'Éditeur de mission',
            'MANUAL' => 'Saisie analyste',
            'CTAB' => 'cTAB',
            'TFAR' => 'TFAR',
            'ACRE' => 'ACRE',
            'UAV' => 'Drone / UAV',
            default => $src !== '' ? $src : 'Source inconnue',
        };
    }

    private function eventTypeLabel(string $type): string
    {
        return self::labelForEventType($type);
    }

    private function sourceSystemLabel(string $src): string
    {
        return self::labelForSourceSystem($src);
    }

    private function reliability(mixed $v): string
    {
        $c = strtoupper(substr(trim((string) $v), 0, 1));

        return in_array($c, ['A', 'B', 'C', 'D', 'E', 'F'], true) ? $c : 'F';
    }

    private function credibility(mixed $v): int
    {
        $n = (int) $v;

        return max(1, min(6, $n > 0 ? $n : 6));
    }

    private function nullIfEmpty(mixed $v): ?string
    {
        $s = trim((string) ($v ?? ''));

        return $s !== '' ? $s : null;
    }
}
