<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Outbox sync + conflits + verrous (LOT 7).
 */
final class SseSyncRepository
{
    public function __construct(private ?Database $db = null)
    {
        $this->db ??= Database::getInstance();
        $this->ensureSchema();
    }

    private function ensureSchema(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $path = base_path('bootstrap/atak_sse_robustness_lot7_migration.php');
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

    /**
     * @param array<string,mixed> $payload
     * @return array{ok:bool,id?:int,uuid?:string,created?:bool,error?:string}
     */
    public function enqueue(int $tenantId, string $idempotencyKey, array $payload, string $channel = 'arma'): array
    {
        $idem = mb_substr(trim($idempotencyKey), 0, 120);
        if ($idem === '') {
            return ['ok' => false, 'error' => 'Clé d’idempotence requise.'];
        }
        $existing = $this->findByIdempotency($tenantId, $idem);
        if ($existing !== null) {
            $newJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
            $oldPayload = $existing['payload'] ?? null;
            $oldJson = is_array($oldPayload)
                ? (json_encode($oldPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}')
                : '{}';
            $conflict = hash('sha256', $newJson) !== hash('sha256', $oldJson);
            $out = [
                'ok' => true,
                'id' => (int) $existing['id'],
                'uuid' => (string) $existing['uuid'],
                'created' => false,
            ];
            if ($conflict) {
                $out['conflict'] = true;
                $out['payload_mismatch'] = true;
            }

            return $out;
        }

        $uuid = SseEntityIndexRepository::newUuid();
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        try {
            $id = (int) $this->db->insert(
                'INSERT INTO sse_sync_outbox (uuid, tenant_id, idempotency_key, channel, payload_json, status)
                 VALUES (:uuid, :t, :idem, :ch, :p, \'pending\')',
                [
                    'uuid' => $uuid,
                    't' => $tenantId,
                    'idem' => $idem,
                    'ch' => mb_substr($channel, 0, 32),
                    'p' => $json,
                ]
            );
        } catch (\Throwable) {
            $again = $this->findByIdempotency($tenantId, $idem);
            if ($again !== null) {
                return [
                    'ok' => true,
                    'id' => (int) $again['id'],
                    'uuid' => (string) $again['uuid'],
                    'created' => false,
                ];
            }

            return ['ok' => false, 'error' => 'File d’attente indisponible.'];
        }

        return ['ok' => true, 'id' => $id, 'uuid' => $uuid, 'created' => true];
    }

    public function findByIdempotency(int $tenantId, string $key): ?array
    {
        try {
            $row = $this->db->fetchOne(
                'SELECT * FROM sse_sync_outbox WHERE tenant_id = :t AND idempotency_key = :k LIMIT 1',
                ['t' => $tenantId, 'k' => $key]
            );
        } catch (\Throwable) {
            return null;
        }

        return $row ? $this->hydrateOutbox($row) : null;
    }

    /** @return list<array<string,mixed>> */
    public function listPending(int $tenantId, int $limit = 40): array
    {
        try {
            $rows = $this->db->fetchAll(
                "SELECT * FROM sse_sync_outbox
                  WHERE tenant_id = :t AND status IN ('pending','failed')
                  ORDER BY created_at ASC LIMIT " . max(1, min(100, $limit)),
                ['t' => $tenantId]
            );
        } catch (\Throwable) {
            return [];
        }

        return array_map([$this, 'hydrateOutbox'], $rows);
    }

    public function ack(int $tenantId, int $id): bool
    {
        try {
            $this->db->execute(
                "UPDATE sse_sync_outbox
                    SET status = 'acked', acked_at = UTC_TIMESTAMP()
                  WHERE id = :id AND tenant_id = :t AND status <> 'acked'",
                ['id' => $id, 't' => $tenantId]
            );

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function markFailed(int $tenantId, int $id, string $error): bool
    {
        try {
            $this->db->execute(
                "UPDATE sse_sync_outbox
                    SET status = 'failed', attempts = attempts + 1, last_error = :err
                  WHERE id = :id AND tenant_id = :t",
                [
                    'err' => mb_substr($error, 0, 500),
                    'id' => $id,
                    't' => $tenantId,
                ]
            );

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param array<string,mixed> $versionA
     * @param array<string,mixed> $versionB
     * @return array{ok:bool,id?:int,uuid?:string,error?:string}
     */
    public function registerConflict(
        int $tenantId,
        string $objectType,
        string $objectRef,
        array $versionA,
        array $versionB
    ): array {
        $uuid = SseEntityIndexRepository::newUuid();
        try {
            $id = (int) $this->db->insert(
                'INSERT INTO sse_sync_conflicts (
                    uuid, tenant_id, object_type, object_ref, status, version_a_json, version_b_json
                ) VALUES (:uuid, :t, :ot, :or, \'ouvert\', :a, :b)',
                [
                    'uuid' => $uuid,
                    't' => $tenantId,
                    'ot' => mb_substr($objectType, 0, 48),
                    'or' => mb_substr($objectRef, 0, 120),
                    'a' => json_encode($versionA, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
                    'b' => json_encode($versionB, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
                ]
            );
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'Conflit non enregistré.'];
        }

        return ['ok' => true, 'id' => $id, 'uuid' => $uuid];
    }

    /** @return list<array<string,mixed>> */
    public function listConflicts(int $tenantId, string $status = 'ouvert', int $limit = 30): array
    {
        try {
            $rows = $this->db->fetchAll(
                'SELECT * FROM sse_sync_conflicts
                  WHERE tenant_id = :t AND status = :st
                  ORDER BY created_at DESC LIMIT ' . max(1, min(100, $limit)),
                ['t' => $tenantId, 'st' => $status]
            );
        } catch (\Throwable) {
            return [];
        }

        return array_map([$this, 'hydrateConflict'], $rows);
    }

    public function resolveConflict(int $tenantId, int $id, string $note, string $author): bool
    {
        try {
            $this->db->execute(
                "UPDATE sse_sync_conflicts
                    SET status = 'resolu', resolution_note = :n, resolved_by_label = :a, resolved_at = UTC_TIMESTAMP()
                  WHERE id = :id AND tenant_id = :t AND status = 'ouvert'",
                [
                    'n' => $note,
                    'a' => mb_substr($author, 0, 160),
                    'id' => $id,
                    't' => $tenantId,
                ]
            );

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function tryLock(string $lockKey, int $tenantId, string $owner, int $ttlSeconds = 120): bool
    {
        $until = gmdate('Y-m-d H:i:s', time() + max(5, $ttlSeconds));
        try {
            $this->db->execute(
                'DELETE FROM sse_job_locks WHERE locked_until < UTC_TIMESTAMP()'
            );
            $this->db->insert(
                'INSERT INTO sse_job_locks (lock_key, tenant_id, owner_label, locked_until)
                 VALUES (:k, :t, :o, :u)',
                [
                    'k' => mb_substr($lockKey, 0, 80),
                    't' => $tenantId,
                    'o' => mb_substr($owner, 0, 120),
                    'u' => $until,
                ]
            );

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function releaseLock(string $lockKey, int $tenantId): void
    {
        try {
            $this->db->execute(
                'DELETE FROM sse_job_locks WHERE lock_key = :k AND tenant_id = :t',
                ['k' => $lockKey, 't' => $tenantId]
            );
        } catch (\Throwable) {
        }
    }

    /**
     * @return array{
     *   outbox_pending:int,
     *   outbox_failed:int,
     *   outbox_acked:int,
     *   conflicts_open:int,
     *   ok:bool
     * }
     */
    public function healthCounts(int $tenantId): array
    {
        $pending = 0;
        $failed = 0;
        $acked = 0;
        $conflicts = 0;
        try {
            $row = $this->db->fetchOne(
                "SELECT
                    SUM(status IN ('pending')) AS pending_n,
                    SUM(status IN ('failed')) AS failed_n,
                    SUM(status IN ('acked')) AS acked_n
                 FROM sse_sync_outbox WHERE tenant_id = :t",
                ['t' => $tenantId]
            );
            $pending = (int) ($row['pending_n'] ?? 0);
            $failed = (int) ($row['failed_n'] ?? 0);
            $acked = (int) ($row['acked_n'] ?? 0);
            $row2 = $this->db->fetchOne(
                "SELECT COUNT(*) AS n FROM sse_sync_conflicts WHERE tenant_id = :t AND status = 'ouvert'",
                ['t' => $tenantId]
            );
            $conflicts = (int) ($row2['n'] ?? 0);
        } catch (\Throwable) {
            return [
                'outbox_pending' => 0,
                'outbox_failed' => 0,
                'outbox_acked' => 0,
                'conflicts_open' => 0,
                'ok' => false,
            ];
        }

        return [
            'outbox_pending' => $pending + $failed,
            'outbox_failed' => $failed,
            'outbox_acked' => $acked,
            'conflicts_open' => $conflicts,
            'ok' => true,
        ];
    }

    /**
     * Purge des accusés anciens (optimisation stockage).
     *
     * @return array{deleted:int}
     */
    public function compactAcked(int $tenantId = 0, int $olderThanDays = 7): array
    {
        $days = max(1, min(90, $olderThanDays));
        try {
            if ($tenantId > 0) {
                $this->db->execute(
                    "DELETE FROM sse_sync_outbox
                      WHERE tenant_id = :t AND status = 'acked'
                        AND acked_at IS NOT NULL
                        AND acked_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL {$days} DAY)",
                    ['t' => $tenantId]
                );
            } else {
                $this->db->execute(
                    "DELETE FROM sse_sync_outbox
                      WHERE status = 'acked'
                        AND acked_at IS NOT NULL
                        AND acked_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL {$days} DAY)"
                );
            }
            // Database::execute may not return rowCount — best effort
            return ['deleted' => 1];
        } catch (\Throwable) {
            return ['deleted' => 0];
        }
    }

    /**
     * Expire les verrous périmés.
     */
    public function purgeExpiredLocks(): int
    {
        try {
            $this->db->execute('DELETE FROM sse_job_locks WHERE locked_until < UTC_TIMESTAMP()');

            return 1;
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function hydrateOutbox(array $row): array
    {
        $payload = null;
        if (!empty($row['payload_json'])) {
            $decoded = json_decode((string) $row['payload_json'], true);
            $payload = is_array($decoded) ? $decoded : null;
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'uuid' => (string) ($row['uuid'] ?? ''),
            'idempotency_key' => (string) ($row['idempotency_key'] ?? ''),
            'channel' => (string) ($row['channel'] ?? ''),
            'status' => (string) ($row['status'] ?? ''),
            'attempts' => (int) ($row['attempts'] ?? 0),
            'last_error' => $row['last_error'] ?? null,
            'payload' => $payload,
            'acked_at' => $row['acked_at'] ?? null,
            'created_at' => $row['created_at'] ?? null,
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function hydrateConflict(array $row): array
    {
        $a = json_decode((string) ($row['version_a_json'] ?? '{}'), true);
        $b = json_decode((string) ($row['version_b_json'] ?? '{}'), true);

        return [
            'id' => (int) ($row['id'] ?? 0),
            'uuid' => (string) ($row['uuid'] ?? ''),
            'object_type' => (string) ($row['object_type'] ?? ''),
            'object_ref' => (string) ($row['object_ref'] ?? ''),
            'status' => (string) ($row['status'] ?? ''),
            'version_a' => is_array($a) ? $a : [],
            'version_b' => is_array($b) ? $b : [],
            'resolution_note' => $row['resolution_note'] ?? null,
            'resolved_by_label' => $row['resolved_by_label'] ?? null,
            'resolved_at' => $row['resolved_at'] ?? null,
            'created_at' => $row['created_at'] ?? null,
        ];
    }
}
