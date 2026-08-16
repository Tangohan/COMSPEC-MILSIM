<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SilentSchemaMigration;

/**
 * Registre unifié d’entités SSE (projection vers persons/sites/cases/…).
 */
final class SseEntityIndexRepository
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

    public static function newUuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }

    /**
     * @param array<string, mixed> $data
     * @return array{id:int,uuid:string,created:bool}
     */
    public function upsert(array $data): array
    {
        $tenantId = (int) ($data['tenant_id'] ?? 0);
        $sourceTable = (string) ($data['source_table'] ?? '');
        $sourceId = (int) ($data['source_id'] ?? 0);
        if ($tenantId < 1 || $sourceTable === '' || $sourceId < 1) {
            throw new \InvalidArgumentException('tenant_id, source_table et source_id requis.');
        }

        $existing = $this->findBySource($tenantId, $sourceTable, $sourceId);
        $uuid = $existing['uuid'] ?? (string) ($data['uuid'] ?? self::newUuid());

        if ($existing !== null) {
            $this->db->execute(
                'UPDATE sse_entity_index SET
                    context_id = :context_id,
                    entity_type = :entity_type,
                    display_label = :display_label,
                    reference_code = :reference_code,
                    status = :status,
                    identity_tier = :identity_tier,
                    source_reliability = :source_reliability,
                    info_credibility = :info_credibility,
                    classification = :classification,
                    last_event_at = COALESCE(:last_event_at, last_event_at),
                    search_blob = :search_blob
                 WHERE tenant_id = :tenant_id AND id = :id',
                [
                    'id' => (int) $existing['id'],
                    'tenant_id' => $tenantId,
                    'context_id' => (int) ($data['context_id'] ?? $existing['context_id'] ?? 1),
                    'entity_type' => (string) ($data['entity_type'] ?? $existing['entity_type']),
                    'display_label' => (string) ($data['display_label'] ?? $existing['display_label']),
                    'reference_code' => $this->nullIfEmpty($data['reference_code'] ?? $existing['reference_code'] ?? null),
                    'status' => (string) ($data['status'] ?? $existing['status'] ?? ''),
                    'identity_tier' => $this->nullIfEmpty($data['identity_tier'] ?? $existing['identity_tier'] ?? null),
                    'source_reliability' => $this->reliability($data['source_reliability'] ?? $existing['source_reliability'] ?? 'F'),
                    'info_credibility' => $this->credibility($data['info_credibility'] ?? $existing['info_credibility'] ?? 6),
                    'classification' => (string) ($data['classification'] ?? $existing['classification'] ?? 'encadrement'),
                    'last_event_at' => $data['last_event_at'] ?? null,
                    'search_blob' => (string) ($data['search_blob'] ?? $existing['search_blob'] ?? ''),
                ]
            );

            return ['id' => (int) $existing['id'], 'uuid' => $uuid, 'created' => false];
        }

        $id = (int) $this->db->insert(
            'INSERT INTO sse_entity_index (
                uuid, tenant_id, context_id, entity_type, source_table, source_id,
                display_label, reference_code, status, identity_tier,
                source_reliability, info_credibility, classification,
                last_event_at, search_blob
            ) VALUES (
                :uuid, :tenant_id, :context_id, :entity_type, :source_table, :source_id,
                :display_label, :reference_code, :status, :identity_tier,
                :source_reliability, :info_credibility, :classification,
                :last_event_at, :search_blob
            )',
            [
                'uuid' => $uuid,
                'tenant_id' => $tenantId,
                'context_id' => (int) ($data['context_id'] ?? 1),
                'entity_type' => (string) ($data['entity_type'] ?? 'object'),
                'source_table' => $sourceTable,
                'source_id' => $sourceId,
                'display_label' => (string) ($data['display_label'] ?? ''),
                'reference_code' => $this->nullIfEmpty($data['reference_code'] ?? null),
                'status' => (string) ($data['status'] ?? ''),
                'identity_tier' => $this->nullIfEmpty($data['identity_tier'] ?? null),
                'source_reliability' => $this->reliability($data['source_reliability'] ?? 'F'),
                'info_credibility' => $this->credibility($data['info_credibility'] ?? 6),
                'classification' => (string) ($data['classification'] ?? 'encadrement'),
                'last_event_at' => $data['last_event_at'] ?? null,
                'search_blob' => (string) ($data['search_blob'] ?? ''),
            ]
        );

        return ['id' => $id, 'uuid' => $uuid, 'created' => true];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByUuid(int $tenantId, string $uuid): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM sse_entity_index WHERE tenant_id = :t AND uuid = :u LIMIT 1',
            ['t' => $tenantId, 'u' => $uuid]
        );

        return $row ? $this->hydrate($row) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findBySource(int $tenantId, string $sourceTable, int $sourceId): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM sse_entity_index
             WHERE tenant_id = :t AND source_table = :st AND source_id = :sid LIMIT 1',
            ['t' => $tenantId, 'st' => $sourceTable, 'sid' => $sourceId]
        );

        return $row ? $this->hydrate($row) : null;
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function search(int $tenantId, array $filters = []): array
    {
        $sql = 'SELECT * FROM sse_entity_index WHERE tenant_id = :t';
        $params = ['t' => $tenantId];

        if (!empty($filters['entity_type'])) {
            $sql .= ' AND entity_type = :etype';
            $params['etype'] = (string) $filters['entity_type'];
        }
        if (!empty($filters['q'])) {
            $sql .= ' AND (display_label LIKE :q OR reference_code LIKE :q2 OR search_blob LIKE :q3 OR uuid LIKE :q4)';
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], (string) $filters['q']) . '%';
            $params['q'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
            $params['q4'] = $like;
        }
        if (!empty($filters['context_id'])) {
            $sql .= ' AND context_id = :cid';
            $params['cid'] = (int) $filters['context_id'];
        }

        $limit = min(200, max(1, (int) ($filters['limit'] ?? 50)));
        $offset = max(0, (int) ($filters['offset'] ?? 0));
        $sql .= ' ORDER BY COALESCE(last_event_at, updated_at) DESC LIMIT ' . $limit . ' OFFSET ' . $offset;

        $rows = $this->db->fetchAll($sql, $params);
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->hydrate($row);
        }

        return $out;
    }

    public function touchLastEvent(int $tenantId, string $uuid, ?string $eventTime = null): void
    {
        $this->db->execute(
            'UPDATE sse_entity_index
             SET last_event_at = COALESCE(:et, UTC_TIMESTAMP())
             WHERE tenant_id = :t AND uuid = :u',
            ['t' => $tenantId, 'u' => $uuid, 'et' => $eventTime]
        );
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        $rel = strtoupper((string) ($row['source_reliability'] ?? 'F'));
        $cred = (int) ($row['info_credibility'] ?? 6);

        return [
            'id' => (int) ($row['id'] ?? 0),
            'uuid' => (string) ($row['uuid'] ?? ''),
            'tenant_id' => (int) ($row['tenant_id'] ?? 0),
            'context_id' => (int) ($row['context_id'] ?? 1),
            'entity_type' => (string) ($row['entity_type'] ?? ''),
            'source_table' => (string) ($row['source_table'] ?? ''),
            'source_id' => (int) ($row['source_id'] ?? 0),
            'display_label' => (string) ($row['display_label'] ?? ''),
            'reference_code' => $row['reference_code'] ?? null,
            'status' => (string) ($row['status'] ?? ''),
            'identity_tier' => $row['identity_tier'] ?? null,
            'identity_tier_label' => $this->identityTierLabel($row['identity_tier'] ?? null),
            'source_reliability' => $rel,
            'info_credibility' => $cred,
            'confidence_code' => $rel . (string) $cred,
            'confidence_label' => $this->confidenceLabel($rel, $cred),
            'classification' => (string) ($row['classification'] ?? ''),
            'last_event_at' => $row['last_event_at'] ?? null,
            'search_blob' => (string) ($row['search_blob'] ?? ''),
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    private function identityTierLabel(mixed $tier): string
    {
        return match (strtoupper(trim((string) ($tier ?? '')))) {
            'DECLARED' => 'Identité déclarée',
            'DOCUMENTARY' => 'Identité documentaire',
            'CONFIRMED' => 'Identité confirmée',
            'UNKNOWN' => 'Identité inconnue',
            default => 'Non précisée',
        };
    }

    private function confidenceLabel(string $rel, int $cred): string
    {
        $r = match ($rel) {
            'A' => 'Très fiable',
            'B' => 'Généralement fiable',
            'C' => 'Fiabilité moyenne',
            'D' => 'Peu fiable',
            'E' => 'Non fiable',
            default => 'Fiabilité inconnue',
        };
        $c = match ($cred) {
            1 => 'confirmée',
            2 => 'probablement vraie',
            3 => 'possiblement vraie',
            4 => 'douteuse',
            5 => 'improbable',
            default => 'impossible à apprécier',
        };

        return $rel . $cred . ' — Source ' . strtolower($r) . ', information ' . $c;
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
