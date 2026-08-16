<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SilentSchemaMigration;

/**
 * Journal d’audit SSE (append-only).
 */
final class SseAuditLogRepository
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
     */
    public function write(array $data): int
    {
        $tenantId = (int) ($data['tenant_id'] ?? 0);
        if ($tenantId < 1) {
            throw new \InvalidArgumentException('tenant_id requis.');
        }

        $before = $data['before'] ?? $data['before_json'] ?? null;
        $after = $data['after'] ?? $data['after_json'] ?? null;
        if (is_array($before)) {
            $before = json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        if (is_array($after)) {
            $after = json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return (int) $this->db->insert(
            'INSERT INTO sse_audit_log (
                tenant_id, actor_user_id, actor_label, action,
                object_type, object_id, object_uuid, reason, before_json, after_json
            ) VALUES (
                :tenant_id, :actor_user_id, :actor_label, :action,
                :object_type, :object_id, :object_uuid, :reason, :before_json, :after_json
            )',
            [
                'tenant_id' => $tenantId,
                'actor_user_id' => isset($data['actor_user_id']) && (int) $data['actor_user_id'] > 0
                    ? (int) $data['actor_user_id'] : null,
                'actor_label' => trim((string) ($data['actor_label'] ?? 'Système')),
                'action' => trim((string) ($data['action'] ?? 'unknown')),
                'object_type' => trim((string) ($data['object_type'] ?? '')),
                'object_id' => isset($data['object_id']) && (int) $data['object_id'] > 0
                    ? (int) $data['object_id'] : null,
                'object_uuid' => ($u = trim((string) ($data['object_uuid'] ?? ''))) !== '' ? $u : null,
                'reason' => ($r = trim((string) ($data['reason'] ?? ''))) !== '' ? mb_substr($r, 0, 500) : null,
                'before_json' => is_string($before) ? $before : null,
                'after_json' => is_string($after) ? $after : null,
            ]
        );
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function listForTenant(int $tenantId, array $filters = []): array
    {
        $sql = 'SELECT * FROM sse_audit_log WHERE tenant_id = :t';
        $params = ['t' => $tenantId];

        if (!empty($filters['object_type'])) {
            $sql .= ' AND object_type = :ot';
            $params['ot'] = (string) $filters['object_type'];
        }
        if (!empty($filters['object_id'])) {
            $sql .= ' AND object_id = :oid';
            $params['oid'] = (int) $filters['object_id'];
        }
        if (!empty($filters['action'])) {
            $sql .= ' AND action = :act';
            $params['act'] = (string) $filters['action'];
        }

        $limit = min(200, max(1, (int) ($filters['limit'] ?? 40)));
        $sql .= ' ORDER BY id DESC LIMIT ' . $limit;

        $rows = $this->db->fetchAll($sql, $params);
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => (int) ($row['id'] ?? 0),
                'tenant_id' => (int) ($row['tenant_id'] ?? 0),
                'actor_user_id' => isset($row['actor_user_id']) ? (int) $row['actor_user_id'] : null,
                'actor_label' => (string) ($row['actor_label'] ?? ''),
                'action' => (string) ($row['action'] ?? ''),
                'object_type' => (string) ($row['object_type'] ?? ''),
                'object_id' => isset($row['object_id']) ? (int) $row['object_id'] : null,
                'object_uuid' => $row['object_uuid'] ?? null,
                'reason' => $row['reason'] ?? null,
                'created_at' => $row['created_at'] ?? null,
            ];
        }

        return $out;
    }
}
