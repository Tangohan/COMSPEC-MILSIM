<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Passerelles carte ATAK entre communautés (code + acceptations bilatérales).
 */
class AtakMapGatewayRepository
{
    public const STATUS_OPEN = 'open';
    public const STATUS_PENDING = 'pending_validation';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_REVOKED = 'revoked';
    public const STATUS_EXPIRED = 'expired';

    private const CODE_TTL_HOURS = 48;
    private const ACTIVE_TTL_DAYS = 14;

    private PDO $pdo;

    private static ?bool $ready = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
    }

    public function schemaReady(): bool
    {
        if (self::$ready === null) {
            try {
                $st = $this->pdo->query("SHOW TABLES LIKE 'atak_map_gateways'");
                self::$ready = (bool) ($st && $st->fetchColumn());
            } catch (\Throwable) {
                self::$ready = false;
            }
        }

        return self::$ready;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        if (!$this->schemaReady() || $id < 1) {
            return null;
        }
        $st = $this->pdo->prepare('SELECT * FROM atak_map_gateways WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByCode(string $code): ?array
    {
        if (!$this->schemaReady()) {
            return null;
        }
        $code = strtoupper(trim($code));
        if ($code === '') {
            return null;
        }
        $st = $this->pdo->prepare('SELECT * FROM atak_map_gateways WHERE join_code = ? LIMIT 1');
        $st->execute([$code]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForTenant(int $tenantId, int $limit = 40): array
    {
        if (!$this->schemaReady() || $tenantId < 1) {
            return [];
        }
        $limit = max(1, min(100, $limit));
        $st = $this->pdo->prepare(
            "SELECT * FROM atak_map_gateways
             WHERE host_tenant_id = ? OR partner_tenant_id = ?
             ORDER BY
                FIELD(status, 'active', 'pending_validation', 'open', 'expired', 'revoked'),
                updated_at DESC
             LIMIT {$limit}"
        );
        $st->execute([$tenantId, $tenantId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listActiveForTenant(int $tenantId): array
    {
        if (!$this->schemaReady() || $tenantId < 1) {
            return [];
        }
        $this->expireStale();
        $st = $this->pdo->prepare(
            "SELECT * FROM atak_map_gateways
             WHERE status = 'active'
               AND (host_tenant_id = ? OR partner_tenant_id = ?)
               AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP())
             ORDER BY activated_at DESC, id DESC"
        );
        $st->execute([$tenantId, $tenantId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array{id: int, code: string, expires_at: string}|null
     */
    public function createOpen(
        int $hostTenantId,
        int $createdByUserId,
        string $label,
        bool $shareUnits,
        bool $shareMarkers,
        int $hostMapId = 1
    ): ?array {
        if (!$this->schemaReady() || $hostTenantId < 1 || $createdByUserId < 1) {
            return null;
        }
        $code = $this->generateUniqueCode();
        $ttl = (int) self::CODE_TTL_HOURS;
        $label = trim($label);
        if (mb_strlen($label) > 160) {
            $label = mb_substr($label, 0, 160);
        }
        $st = $this->pdo->prepare(
            "INSERT INTO atak_map_gateways (
                host_tenant_id, partner_tenant_id, join_code, status, label,
                share_units, share_markers, host_map_id, partner_map_id,
                created_by_user_id, expires_at, created_at, updated_at
             ) VALUES (
                ?, NULL, ?, 'open', ?,
                ?, ?, ?, NULL,
                ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL {$ttl} HOUR), UTC_TIMESTAMP(), UTC_TIMESTAMP()
             )"
        );
        $st->execute([
            $hostTenantId,
            $code,
            $label !== '' ? $label : null,
            $shareUnits ? 1 : 0,
            $shareMarkers ? 1 : 0,
            max(1, $hostMapId),
            $createdByUserId,
        ]);
        $id = (int) $this->pdo->lastInsertId();
        $row = $this->findById($id);
        if ($row === null) {
            return null;
        }

        return [
            'id' => $id,
            'code' => (string) $row['join_code'],
            'expires_at' => (string) $row['expires_at'],
        ];
    }

    public function attachPartner(int $gatewayId, int $partnerTenantId, int $partnerMapId = 1): bool
    {
        if (!$this->schemaReady() || $gatewayId < 1 || $partnerTenantId < 1) {
            return false;
        }
        $st = $this->pdo->prepare(
            "UPDATE atak_map_gateways
             SET partner_tenant_id = ?,
                 partner_map_id = ?,
                 status = 'pending_validation',
                 updated_at = UTC_TIMESTAMP()
             WHERE id = ? AND status = 'open' AND partner_tenant_id IS NULL
               AND expires_at > UTC_TIMESTAMP()"
        );
        $st->execute([$partnerTenantId, max(1, $partnerMapId), $gatewayId]);

        return $st->rowCount() > 0;
    }

    public function recordAcceptance(int $gatewayId, int $tenantId, int $userId): bool
    {
        if (!$this->schemaReady() || $gatewayId < 1 || $tenantId < 1 || $userId < 1) {
            return false;
        }
        $st = $this->pdo->prepare(
            'INSERT INTO atak_map_gateway_acceptances (gateway_id, tenant_id, user_id, accepted_at)
             VALUES (?, ?, ?, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), accepted_at = UTC_TIMESTAMP()'
        );
        $st->execute([$gatewayId, $tenantId, $userId]);

        return true;
    }

    /**
     * @return list<int>
     */
    public function acceptedTenantIds(int $gatewayId): array
    {
        if (!$this->schemaReady() || $gatewayId < 1) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT tenant_id FROM atak_map_gateway_acceptances WHERE gateway_id = ?'
        );
        $st->execute([$gatewayId]);
        $ids = [];
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $id = (int) ($row['tenant_id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function activateIfBilateral(int $gatewayId): bool
    {
        $gw = $this->findById($gatewayId);
        if ($gw === null) {
            return false;
        }
        $host = (int) ($gw['host_tenant_id'] ?? 0);
        $partner = (int) ($gw['partner_tenant_id'] ?? 0);
        if ($host < 1 || $partner < 1) {
            return false;
        }
        $accepted = $this->acceptedTenantIds($gatewayId);
        if (!in_array($host, $accepted, true) || !in_array($partner, $accepted, true)) {
            return false;
        }
        if ((string) ($gw['status'] ?? '') === self::STATUS_ACTIVE) {
            return true;
        }
        $days = (int) self::ACTIVE_TTL_DAYS;
        $st = $this->pdo->prepare(
            "UPDATE atak_map_gateways
             SET status = 'active',
                 activated_at = UTC_TIMESTAMP(),
                 expires_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL {$days} DAY),
                 updated_at = UTC_TIMESTAMP()
             WHERE id = ? AND status = 'pending_validation'
               AND partner_tenant_id IS NOT NULL"
        );
        $st->execute([$gatewayId]);

        return $st->rowCount() > 0 || (string) ($this->findById($gatewayId)['status'] ?? '') === self::STATUS_ACTIVE;
    }

    public function revoke(int $gatewayId, int $tenantId, ?string $reason = null): bool
    {
        if (!$this->schemaReady() || $gatewayId < 1 || $tenantId < 1) {
            return false;
        }
        $reason = $reason !== null ? trim($reason) : null;
        if ($reason !== null && mb_strlen($reason) > 255) {
            $reason = mb_substr($reason, 0, 255);
        }
        $st = $this->pdo->prepare(
            "UPDATE atak_map_gateways
             SET status = 'revoked',
                 revoked_at = UTC_TIMESTAMP(),
                 revoke_reason = ?,
                 updated_at = UTC_TIMESTAMP()
             WHERE id = ?
               AND status IN ('open', 'pending_validation', 'active')
               AND (host_tenant_id = ? OR partner_tenant_id = ?)"
        );
        $st->execute([$reason, $gatewayId, $tenantId, $tenantId]);

        return $st->rowCount() > 0;
    }

    public function expireStale(): int
    {
        if (!$this->schemaReady()) {
            return 0;
        }
        $st = $this->pdo->prepare(
            "UPDATE atak_map_gateways
             SET status = 'expired', updated_at = UTC_TIMESTAMP()
             WHERE status IN ('open', 'pending_validation', 'active')
               AND expires_at <= UTC_TIMESTAMP()"
        );
        $st->execute();

        return $st->rowCount();
    }

    public function tenantIsParty(array $gateway, int $tenantId): bool
    {
        return $tenantId > 0 && (
            (int) ($gateway['host_tenant_id'] ?? 0) === $tenantId
            || (int) ($gateway['partner_tenant_id'] ?? 0) === $tenantId
        );
    }

    public function peerTenantId(array $gateway, int $tenantId): int
    {
        $host = (int) ($gateway['host_tenant_id'] ?? 0);
        $partner = (int) ($gateway['partner_tenant_id'] ?? 0);
        if ($tenantId === $host) {
            return $partner;
        }
        if ($tenantId === $partner) {
            return $host;
        }

        return 0;
    }

    public function peerMapId(array $gateway, int $tenantId): int
    {
        $host = (int) ($gateway['host_tenant_id'] ?? 0);
        if ($tenantId === $host) {
            return max(1, (int) ($gateway['partner_map_id'] ?? $gateway['host_map_id'] ?? 1));
        }

        return max(1, (int) ($gateway['host_map_id'] ?? 1));
    }

    private function generateUniqueCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        for ($attempt = 0; $attempt < 16; $attempt++) {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $st = $this->pdo->prepare(
                "SELECT 1 FROM atak_map_gateways
                 WHERE join_code = ?
                   AND status IN ('open', 'pending_validation', 'active')
                   AND expires_at > UTC_TIMESTAMP()
                 LIMIT 1"
            );
            $st->execute([$code]);
            if (!$st->fetchColumn()) {
                return $code;
            }
        }

        return strtoupper(bin2hex(random_bytes(4)));
    }
}
