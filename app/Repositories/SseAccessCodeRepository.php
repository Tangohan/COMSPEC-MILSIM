<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class SseAccessCodeRepository
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
        static $done = false;
        if (!$done) {
            $path = base_path('bootstrap/atak_sse_portal_migration.php');
            if (is_file($path)) {
                $migrate = require $path;
                if (is_callable($migrate)) {
                    try {
                        $migrate(Database::getPdo());
                    } catch (\Throwable) {
                    }
                }
            }
            $done = true;
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        return (int) $this->db->insert(
            'INSERT INTO sse_access_codes (
                tenant_id, code_hash, code_hint, label, grant_type, case_id, created_by,
                expires_at, session_ttl_minutes, max_uses
            ) VALUES (
                :tenant_id, :code_hash, :code_hint, :label, :grant_type, :case_id, :created_by,
                :expires_at, :session_ttl_minutes, :max_uses
            )',
            [
                'tenant_id' => (int) $data['tenant_id'],
                'code_hash' => (string) $data['code_hash'],
                'code_hint' => (string) ($data['code_hint'] ?? ''),
                'label' => trim((string) ($data['label'] ?? '')),
                'grant_type' => in_array(($data['grant_type'] ?? ''), ['member', 'guest'], true)
                    ? $data['grant_type'] : 'member',
                'case_id' => isset($data['case_id']) && (int) $data['case_id'] > 0 ? (int) $data['case_id'] : null,
                'created_by' => isset($data['created_by']) ? (int) $data['created_by'] : null,
                'expires_at' => (string) $data['expires_at'],
                'session_ttl_minutes' => max(30, min(72 * 60, (int) ($data['session_ttl_minutes'] ?? 240))),
                'max_uses' => max(1, min(100, (int) ($data['max_uses'] ?? 1))),
            ]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findValidByPlainCode(string $plain, int $tenantId = 0): ?array
    {
        $hash = hash('sha256', strtoupper(trim($plain)));
        $sql = 'SELECT * FROM sse_access_codes WHERE code_hash = :h AND revoked_at IS NULL AND expires_at > NOW()';
        $params = ['h' => $hash];
        if ($tenantId > 0) {
            $sql .= ' AND tenant_id = :t';
            $params['t'] = $tenantId;
        }
        $sql .= ' LIMIT 1';
        $row = $this->db->fetchOne($sql, $params);
        if (!$row) {
            return null;
        }
        if ((int) ($row['uses_count'] ?? 0) >= (int) ($row['max_uses'] ?? 1)) {
            return null;
        }

        return $row;
    }

    public function incrementUse(int $id): void
    {
        $this->db->execute(
            'UPDATE sse_access_codes SET uses_count = uses_count + 1 WHERE id = :id',
            ['id' => $id]
        );
    }

    public function revoke(int $id, int $tenantId): bool
    {
        return $this->db->execute(
            'UPDATE sse_access_codes SET revoked_at = NOW() WHERE id = :id AND tenant_id = :t AND revoked_at IS NULL',
            ['id' => $id, 't' => $tenantId]
        ) > 0;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listActiveForTenant(int $tenantId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT id, label, grant_type, case_id, code_hint, expires_at, session_ttl_minutes,
                    max_uses, uses_count, created_at, revoked_at
             FROM sse_access_codes
             WHERE tenant_id = :t
             ORDER BY id DESC
             LIMIT 100',
            ['t' => $tenantId]
        );
        $out = [];
        foreach ($rows as $row) {
            $revoked = !empty($row['revoked_at']);
            $expired = strtotime((string) ($row['expires_at'] ?? '')) < time();
            $exhausted = (int) ($row['uses_count'] ?? 0) >= (int) ($row['max_uses'] ?? 1);
            $out[] = [
                'id' => (int) $row['id'],
                'label' => (string) ($row['label'] ?? ''),
                'grant_type' => (string) ($row['grant_type'] ?? 'member'),
                'grant_type_label' => (($row['grant_type'] ?? '') === 'guest')
                    ? 'Invité (sans compte)'
                    : 'Membre habilité',
                'case_id' => isset($row['case_id']) ? (int) $row['case_id'] : null,
                'code_hint' => (string) ($row['code_hint'] ?? ''),
                'expires_at' => $row['expires_at'] ?? null,
                'session_ttl_minutes' => (int) ($row['session_ttl_minutes'] ?? 240),
                'max_uses' => (int) ($row['max_uses'] ?? 1),
                'uses_count' => (int) ($row['uses_count'] ?? 0),
                'created_at' => $row['created_at'] ?? null,
                'active' => !$revoked && !$expired && !$exhausted,
                'status_label' => $revoked ? 'Révoqué' : ($expired ? 'Expiré' : ($exhausted ? 'Épuisé' : 'Actif')),
            ];
        }

        return $out;
    }

    public function logEvent(
        int $tenantId,
        string $eventType,
        ?int $codeId,
        ?int $caseId,
        ?int $userId,
        ?string $label,
        ?string $detail = null
    ): void {
        try {
            $this->db->insert(
                'INSERT INTO sse_access_grants_log (
                    tenant_id, access_code_id, case_id, actor_user_id, actor_label, event_type, detail
                ) VALUES (
                    :t, :c, :case_id, :u, :l, :e, :d
                )',
                [
                    't' => $tenantId,
                    'c' => $codeId,
                    'case_id' => $caseId,
                    'u' => $userId,
                    'l' => $label,
                    'e' => $eventType,
                    'd' => $detail,
                ]
            );
        } catch (\Throwable) {
        }
    }
}
