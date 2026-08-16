<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Database;
use App\Repositories\SseIntelEventRepository;
use App\Repositories\SseSyncRepository;

/**
 * LOT 7 — Robustesse : offline sync serveur, idempotence, conflits, monitoring, optimisation.
 */
final class SseSyncService
{
    public function __construct(
        private ?SseSyncRepository $repo = null,
        private ?SseIntelEventRepository $events = null,
        private ?Database $db = null,
    ) {
        $this->repo ??= new SseSyncRepository();
        $this->events ??= new SseIntelEventRepository();
        $this->db ??= Database::getInstance();
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{ok:bool,id?:int,uuid?:string,created?:bool,conflict?:bool,conflict_id?:int,error?:string}
     */
    public function enqueue(int $tenantId, string $idempotencyKey, array $payload, string $channel = 'arma'): array
    {
        $result = $this->repo->enqueue($tenantId, $idempotencyKey, $payload, $channel);
        if (!($result['ok'] ?? false)) {
            return $result;
        }
        if (!empty($result['payload_mismatch'])) {
            $existing = $this->repo->findByIdempotency($tenantId, mb_substr(trim($idempotencyKey), 0, 120));
            $conflict = $this->repo->registerConflict(
                $tenantId,
                'outbox',
                mb_substr(trim($idempotencyKey), 0, 120),
                is_array($existing['payload'] ?? null) ? $existing['payload'] : [],
                $payload
            );
            $result['conflict'] = true;
            if ($conflict['ok'] ?? false) {
                $result['conflict_id'] = (int) ($conflict['id'] ?? 0);
            }
        }

        return $result;
    }

    /** @return list<array<string,mixed>> */
    public function pending(int $tenantId, int $limit = 40): array
    {
        return $this->repo->listPending($tenantId, $limit);
    }

    public function ack(int $tenantId, int $id): array
    {
        return $this->repo->ack($tenantId, $id)
            ? ['ok' => true]
            : ['ok' => false, 'error' => 'Accusé non enregistré.'];
    }

    public function fail(int $tenantId, int $id, string $error): array
    {
        return $this->repo->markFailed($tenantId, $id, $error)
            ? ['ok' => true]
            : ['ok' => false, 'error' => 'Échec non enregistré.'];
    }

    /**
     * @param array<string,mixed> $a
     * @param array<string,mixed> $b
     */
    public function registerConflict(int $tenantId, string $type, string $ref, array $a, array $b): array
    {
        return $this->repo->registerConflict($tenantId, $type, $ref, $a, $b);
    }

    public function resolveConflict(int $tenantId, int $id, string $note, string $author): array
    {
        return $this->repo->resolveConflict($tenantId, $id, $note, $author)
            ? ['ok' => true]
            : ['ok' => false, 'error' => 'Résolution impossible.'];
    }

    /** @return list<array<string,mixed>> */
    public function openConflicts(int $tenantId): array
    {
        return $this->repo->listConflicts($tenantId, 'ouvert', 40);
    }

    public function tryLock(string $key, int $tenantId, string $owner, int $ttl = 120): bool
    {
        return $this->repo->tryLock($key, $tenantId, $owner, $ttl);
    }

    public function releaseLock(string $key, int $tenantId): void
    {
        $this->repo->releaseLock($key, $tenantId);
    }

    /**
     * Snapshot monitoring métier (sans jargon technique exposé côté UI).
     *
     * @return array<string,mixed>
     */
    public function monitorSnapshot(int $tenantId): array
    {
        $health = $this->health($tenantId);
        $pending = $this->pending($tenantId, 5);
        $conflicts = $this->openConflicts($tenantId);

        return [
            'status' => $health['status'],
            'status_label' => $health['status_label'],
            'liaison_label' => match ((string) $health['status']) {
                'nominal' => 'Liaison nominale',
                'dégradé' => 'Liaison dégradée',
                default => 'Liaison indisponible',
            },
            'file_attente' => (int) ($health['outbox_pending'] ?? 0),
            'echecs' => (int) ($health['outbox_failed'] ?? 0),
            'conflits' => (int) ($health['conflicts_open'] ?? 0),
            'idempotence_active' => true,
            'pending_preview' => array_map(static function (array $row): array {
                return [
                    'id' => (int) ($row['id'] ?? 0),
                    'channel' => (string) ($row['channel'] ?? ''),
                    'status' => (string) ($row['status'] ?? ''),
                    'created_at' => $row['created_at'] ?? null,
                ];
            }, $pending),
            'conflicts_preview' => array_map(static function (array $c): array {
                return [
                    'id' => (int) ($c['id'] ?? 0),
                    'object_type' => (string) ($c['object_type'] ?? ''),
                    'object_ref' => (string) ($c['object_ref'] ?? ''),
                    'created_at' => $c['created_at'] ?? null,
                ];
            }, array_slice($conflicts, 0, 5)),
            'checked_at' => $health['checked_at'] ?? gmdate('c'),
        ];
    }

    /**
     * Compaction + purge verrous (optimisation).
     *
     * @return array<string,mixed>
     */
    public function optimize(int $tenantId = 0, int $ackedRetentionDays = 7): array
    {
        $compact = $this->repo->compactAcked($tenantId, $ackedRetentionDays);
        $locks = $this->repo->purgeExpiredLocks();

        return [
            'ok' => true,
            'acked_purged' => (int) ($compact['deleted'] ?? 0),
            'locks_purged' => $locks,
            'retention_days' => $ackedRetentionDays,
        ];
    }

    /**
     * Santé SSE pour monitoring / dégradation gracieuse.
     *
     * @return array<string,mixed>
     */
    public function health(int $tenantId): array
    {
        $dbOk = false;
        try {
            $this->db->fetchOne('SELECT 1 AS ok');
            $dbOk = true;
        } catch (\Throwable) {
        }

        $counts = $this->repo->healthCounts($tenantId);
        $eventsOk = true;
        $eventsRecent = 0;
        try {
            $sample = $this->events->listForTenant($tenantId, ['limit' => 1]);
            $eventsRecent = count($sample);
        } catch (\Throwable) {
            $eventsOk = false;
        }

        $pending = (int) ($counts['outbox_pending'] ?? 0);
        $failed = (int) ($counts['outbox_failed'] ?? 0);
        $conflicts = (int) ($counts['conflicts_open'] ?? 0);

        $degraded = !$dbOk || !$counts['ok'] || !$eventsOk || $conflicts > 20 || $failed > 30 || $pending > 200;
        $status = !$dbOk ? 'indisponible' : ($degraded ? 'dégradé' : 'nominal');

        return [
            'status' => $status,
            'status_label' => match ($status) {
                'nominal' => 'Service nominal',
                'dégradé' => 'Service dégradé — certaines fonctions limitées',
                default => 'Service indisponible',
            },
            'database' => $dbOk ? 'ok' : 'ko',
            'events' => $eventsOk ? 'ok' : 'ko',
            'events_sample' => $eventsRecent,
            'outbox_pending' => $pending,
            'outbox_failed' => $failed,
            'outbox_acked' => (int) ($counts['outbox_acked'] ?? 0),
            'conflicts_open' => $conflicts,
            'idempotency' => 'active',
            'checked_at' => gmdate('c'),
        ];
    }
}
