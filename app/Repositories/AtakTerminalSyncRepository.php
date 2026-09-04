<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\LazyDatabaseConnection;
use PDO;

final class AtakTerminalSyncRepository
{
    use LazyDatabaseConnection;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    /** @param array<string, mixed> $row */
    public function upsert(int $tenantId, string $terminalUid, array $row): void
    {
        $uid = strtoupper(trim($terminalUid));
        if ($tenantId < 1 || $uid === '') {
            return;
        }
        $sql = 'INSERT INTO atak_terminal_sync
                (tenant_id, terminal_uid, callsign, pending, markers, drawings, routes, intel, tiles, last_at, reported_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())
                ON DUPLICATE KEY UPDATE
                  callsign = VALUES(callsign),
                  pending = VALUES(pending),
                  markers = VALUES(markers),
                  drawings = VALUES(drawings),
                  routes = VALUES(routes),
                  intel = VALUES(intel),
                  tiles = VALUES(tiles),
                  last_at = VALUES(last_at),
                  reported_at = UTC_TIMESTAMP()';
        $this->pdo()->prepare($sql)->execute([
            $tenantId,
            substr($uid, 0, 128),
            substr(trim((string) ($row['callsign'] ?? '')), 0, 64) ?: null,
            max(0, (int) ($row['pending'] ?? 0)),
            max(0, (int) ($row['markers'] ?? 0)),
            max(0, (int) ($row['drawings'] ?? 0)),
            max(0, (int) ($row['routes'] ?? 0)),
            max(0, (int) ($row['intel'] ?? 0)),
            max(0, (int) ($row['tiles'] ?? 0)),
            $this->stamp($row['last_at'] ?? null),
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function listForTenant(int $tenantId): array
    {
        if ($tenantId < 1) {
            return [];
        }
        try {
            $st = $this->pdo()->prepare(
                'SELECT terminal_uid, callsign, pending, markers, drawings, routes, intel, tiles, last_at, reported_at
                 FROM atak_terminal_sync WHERE tenant_id = ?'
            );
            $st->execute([$tenantId]);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function stamp(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        $ts = strtotime($value);

        return $ts === false ? null : gmdate('Y-m-d H:i:s', $ts);
    }
}
