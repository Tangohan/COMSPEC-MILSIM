<?php

declare(strict_types=1);

namespace App\Services\Cron\Jobs;

use App\Core\Database;
use App\Services\Cron\CronJobInterface;
use App\Services\Sse\SseSyncService;

/**
 * LOT 7 — Maintenance sync SSE : compaction outbox + purge verrous.
 */
final class SseSyncMaintenanceCronJob implements CronJobInterface
{
    public function __construct(
        private ?SseSyncService $sync = null,
        private ?Database $db = null,
    ) {
        $this->sync ??= new SseSyncService();
        $this->db ??= Database::getInstance();
    }

    public function key(): string
    {
        return 'sse_sync_maintenance';
    }

    public function label(): string
    {
        return 'Maintenance synchronisation SSE';
    }

    public function description(): string
    {
        return 'Purge les accusés anciens et les verrous expirés (file d’attente / conflits).';
    }

    public function run(): array
    {
        $global = $this->sync->optimize(0, 7);
        $tenantCount = 0;
        try {
            $rows = $this->db->fetchAll(
                "SELECT DISTINCT tenant_id AS id FROM sse_sync_outbox WHERE tenant_id > 0 LIMIT 200"
            );
            foreach ($rows as $row) {
                $this->sync->optimize((int) $row['id'], 7);
                $tenantCount++;
            }
        } catch (\Throwable) {
        }

        return [
            'ok' => (bool) ($global['ok'] ?? false),
            'summary' => sprintf(
                'Compaction globale · %d communauté(s) traitée(s) · verrous purgés=%d',
                $tenantCount,
                (int) ($global['locks_purged'] ?? 0)
            ),
            'details' => $global + ['tenants' => $tenantCount],
        ];
    }
}
