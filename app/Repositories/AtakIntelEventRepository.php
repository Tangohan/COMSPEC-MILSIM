<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\AtakCopTerrainSchema;
use App\Support\LazyDatabaseConnection;
use PDO;
use Throwable;

final class AtakIntelEventRepository
{
    use LazyDatabaseConnection;

    public function __construct(?PDO $pdo = null)
    {
        AtakCopTerrainSchema::ensure();
        $this->pdo = $pdo;
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    public function insert(
        int $tenantId,
        int $mapId,
        string $unitKind,
        string $unitRef,
        string $eventType,
        string $message,
        string $source = 'athena',
        string $severity = 'info',
        ?array $payload = null
    ): void {
        $unitRef = trim($unitRef);
        $eventType = strtoupper(trim($eventType));
        if ($tenantId < 1 || $mapId < 1 || $unitRef === '' || $eventType === '') {
            return;
        }
        try {
            $this->pdo()->prepare(
                'INSERT INTO atak_unit_intel_events (
                    tenant_id, map_id, unit_kind, unit_ref, event_type, source, severity, message, payload_json
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $tenantId,
                $mapId,
                $unitKind === 'air' ? 'air' : 'ground',
                substr($unitRef, 0, 64),
                substr($eventType, 0, 40),
                $source === 'arma' ? 'arma' : 'athena',
                in_array($severity, ['info', 'warn', 'alert'], true) ? $severity : 'info',
                substr($message, 0, 280),
                $payload !== null ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            ]);
        } catch (Throwable) {
        }
    }

    public function lastOfType(int $tenantId, int $mapId, string $unitKind, string $unitRef, string $eventType): ?array
    {
        try {
            $st = $this->pdo()->prepare(
                'SELECT id, event_type, message, created_at, payload_json
                 FROM atak_unit_intel_events
                 WHERE tenant_id = ? AND map_id = ? AND unit_kind = ? AND unit_ref = ? AND event_type = ?
                 ORDER BY id DESC LIMIT 1'
            );
            $st->execute([$tenantId, $mapId, $unitKind, $unitRef, strtoupper($eventType)]);
            $row = $st->fetch(PDO::FETCH_ASSOC);

            return is_array($row) ? $row : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listRecent(int $tenantId, int $mapId, int $limit = 80, ?string $unitRef = null): array
    {
        $limit = max(1, min(200, $limit));
        try {
            if ($unitRef !== null && trim($unitRef) !== '') {
                $st = $this->pdo()->prepare(
                    'SELECT id, unit_kind, unit_ref, event_type, source, severity, message, payload_json, created_at
                     FROM atak_unit_intel_events
                     WHERE tenant_id = ? AND map_id = ? AND unit_ref = ?
                     ORDER BY id DESC LIMIT ' . $limit
                );
                $st->execute([$tenantId, $mapId, trim($unitRef)]);
            } else {
                $st = $this->pdo()->prepare(
                    'SELECT id, unit_kind, unit_ref, event_type, source, severity, message, payload_json, created_at
                     FROM atak_unit_intel_events
                     WHERE tenant_id = ? AND map_id = ?
                     ORDER BY id DESC LIMIT ' . $limit
                );
                $st->execute([$tenantId, $mapId]);
            }
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $out = [];
            foreach ($rows as $row) {
                if (!empty($row['payload_json']) && is_string($row['payload_json'])) {
                    $decoded = json_decode($row['payload_json'], true);
                    $row['payload'] = is_array($decoded) ? $decoded : null;
                    unset($row['payload_json']);
                } else {
                    $row['payload'] = is_array($row['payload_json'] ?? null) ? $row['payload_json'] : null;
                    unset($row['payload_json']);
                }
                $out[] = $row;
            }

            return $out;
        } catch (Throwable) {
            return [];
        }
    }
}
