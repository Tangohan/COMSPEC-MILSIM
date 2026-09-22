<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\LazyDatabaseConnection;
use PDO;

final class AtakRelayRepository
{
    use LazyDatabaseConnection;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function upsert(int $tenantId, int $mapId, array $payload): array
    {
        $uid = trim((string) ($payload['relay_uid'] ?? $payload['uid'] ?? ''));
        if ($tenantId < 1 || $uid === '') {
            return [];
        }
        $x = (float) ($payload['pos_x'] ?? $payload['x'] ?? 0);
        $y = (float) ($payload['pos_y'] ?? $payload['y'] ?? 0);
        $z = (float) ($payload['pos_z'] ?? $payload['z'] ?? 0);
        $range = (float) ($payload['range_m'] ?? $payload['range'] ?? 2000);
        $alive = !empty($payload['alive']) || (($payload['alive'] ?? 1) === 1 || ($payload['alive'] ?? true) === true);
        if (array_key_exists('alive', $payload)) {
            $alive = (bool) $payload['alive'] && $payload['alive'] !== '0' && $payload['alive'] !== 0;
        }
        $name = trim((string) ($payload['name'] ?? $payload['display_name'] ?? ''));
        $identity = trim((string) ($payload['identity'] ?? ''));
        $ip = trim((string) ($payload['ip'] ?? $payload['ip_addr'] ?? ''));
        $gateway = trim((string) ($payload['gateway'] ?? ''));
        $certificate = trim((string) ($payload['certificate'] ?? ''));
        $slots = max(1, min(64, (int) ($payload['slots'] ?? 8)));
        $used = max(0, min($slots, (int) ($payload['used'] ?? $payload['slots_used'] ?? 0)));
        $power = max(0, min(999, (int) ($payload['power_w'] ?? 25)));
        $thru = max(0, min(999, (float) ($payload['throughput_mbps'] ?? 12)));
        $rel = max(0, min(100, (int) ($payload['reliability_pct'] ?? 92)));
        if (!$alive) {
            $power = 0;
            $thru = 0.0;
            $rel = 0;
            $used = 0;
        }

        $st = $this->pdo()->prepare(
            'INSERT INTO atak_relays (
                tenant_id, map_id, relay_uid, pos_x, pos_y, pos_z, range_m, alive, last_seen_at,
                display_name, identity, ip_addr, gateway, certificate, slots, slots_used, power_w, throughput_mbps, reliability_pct
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                pos_x = VALUES(pos_x),
                pos_y = VALUES(pos_y),
                pos_z = VALUES(pos_z),
                range_m = VALUES(range_m),
                alive = VALUES(alive),
                last_seen_at = NOW(),
                display_name = VALUES(display_name),
                identity = VALUES(identity),
                ip_addr = VALUES(ip_addr),
                gateway = VALUES(gateway),
                certificate = VALUES(certificate),
                slots = VALUES(slots),
                slots_used = VALUES(slots_used),
                power_w = VALUES(power_w),
                throughput_mbps = VALUES(throughput_mbps),
                reliability_pct = VALUES(reliability_pct)'
        );
        try {
            $st->execute([
                $tenantId, max(1, $mapId), $uid, $x, $y, $z, max(50, min(8000, $range)), $alive ? 1 : 0,
                $name, $identity, $ip, $gateway, $certificate, $slots, $used, $power, $thru, $rel,
            ]);
        } catch (\Throwable) {
            $st = $this->pdo()->prepare(
                'INSERT INTO atak_relays (tenant_id, map_id, relay_uid, pos_x, pos_y, pos_z, range_m, alive, last_seen_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE
                    pos_x = VALUES(pos_x),
                    pos_y = VALUES(pos_y),
                    pos_z = VALUES(pos_z),
                    range_m = VALUES(range_m),
                    alive = VALUES(alive),
                    last_seen_at = NOW()'
            );
            $st->execute([$tenantId, max(1, $mapId), $uid, $x, $y, $z, max(50, min(8000, $range)), $alive ? 1 : 0]);
        }

        return $this->getByUid($tenantId, $mapId, $uid) ?? [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForMap(int $tenantId, int $mapId): array
    {
        if ($tenantId < 1) {
            return [];
        }
        try {
            $st = $this->pdo()->prepare(
                'SELECT * FROM atak_relays WHERE tenant_id = ? AND map_id = ? ORDER BY last_seen_at DESC'
            );
            $st->execute([$tenantId, max(1, $mapId)]);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForTenant(int $tenantId): array
    {
        if ($tenantId < 1) {
            return [];
        }
        try {
            $st = $this->pdo()->prepare(
                'SELECT * FROM atak_relays WHERE tenant_id = ? ORDER BY last_seen_at DESC'
            );
            $st->execute([$tenantId]);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getByUid(int $tenantId, int $mapId, string $uid): ?array
    {
        $st = $this->pdo()->prepare(
            'SELECT * FROM atak_relays WHERE tenant_id = ? AND map_id = ? AND relay_uid = ? LIMIT 1'
        );
        $st->execute([$tenantId, $mapId, $uid]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function delete(int $tenantId, int $mapId, string $uid): bool
    {
        $uid = trim($uid);
        if ($tenantId < 1 || $uid === '') {
            return false;
        }
        try {
            $st = $this->pdo()->prepare(
                'DELETE FROM atak_relays WHERE tenant_id = ? AND map_id = ? AND relay_uid = ?'
            );
            $st->execute([$tenantId, max(1, $mapId), $uid]);

            return $st->rowCount() > 0;
        } catch (\Throwable) {
            return false;
        }
    }
}
