<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class TenantApiKeyRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function tableExists(): bool
    {
        try {
            $stmt = $this->pdo->query(
                "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenant_api_keys' LIMIT 1"
            );

            return (bool) $stmt?->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param list<string> $scopes
     * @return array{plain_key: string, id: int, prefix: string}|null
     */
    public function createKey(int $tenantId, string $name, array $scopes = ['events:read'], int $quotaPerDay = 10000): ?array
    {
        if (!$this->tableExists()) {
            return null;
        }
        $plain = 'cs_' . bin2hex(random_bytes(24));
        $prefix = substr($plain, 0, 12);
        $hash = password_hash($plain, PASSWORD_DEFAULT);
        $scopesJson = json_encode(array_values($scopes), JSON_UNESCAPED_UNICODE);
        $stmt = $this->pdo->prepare(
            'INSERT INTO tenant_api_keys (tenant_id, name, key_prefix, key_hash, scopes_json, quota_per_day, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$tenantId, $name, $prefix, $hash, $scopesJson, $quotaPerDay]);
        $id = (int) $this->pdo->lastInsertId();

        return ['plain_key' => $plain, 'id' => $id, 'prefix' => $prefix];
    }

    /** @return list<array<string, mixed>> */
    public function listForTenant(int $tenantId): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        $stmt = $this->pdo->prepare(
            'SELECT id, name, key_prefix, quota_per_day, created_at, revoked_at, last_used_at FROM tenant_api_keys WHERE tenant_id = ? ORDER BY id DESC'
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function revoke(int $id, int $tenantId): void
    {
        if (!$this->tableExists()) {
            return;
        }
        $stmt = $this->pdo->prepare(
            'UPDATE tenant_api_keys SET revoked_at = NOW() WHERE id = ? AND tenant_id = ? AND revoked_at IS NULL'
        );
        $stmt->execute([$id, $tenantId]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findActiveByPlainKey(string $plainKey): ?array
    {
        if (!$this->tableExists() || strlen($plainKey) < 16) {
            return null;
        }
        $prefix = substr($plainKey, 0, 12);
        $stmt = $this->pdo->prepare(
            'SELECT * FROM tenant_api_keys WHERE key_prefix = ? AND revoked_at IS NULL LIMIT 1'
        );
        $stmt->execute([$prefix]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        if (!password_verify($plainKey, (string) $row['key_hash'])) {
            return null;
        }

        return $row;
    }

    public function touchUsed(int $id): void
    {
        if (!$this->tableExists()) {
            return;
        }
        $this->pdo->prepare('UPDATE tenant_api_keys SET last_used_at = NOW() WHERE id = ?')->execute([$id]);
    }

    public function incrementDailyUsage(int $apiKeyId): void
    {
        if (!$this->tableExists()) {
            return;
        }
        $day = date('Y-m-d');
        $stmt = $this->pdo->prepare(
            'INSERT INTO tenant_api_key_daily_usage (api_key_id, usage_day, request_count) VALUES (?, ?, 1)
             ON DUPLICATE KEY UPDATE request_count = request_count + 1'
        );
        $stmt->execute([$apiKeyId, $day]);
    }

    public function countToday(int $apiKeyId): int
    {
        if (!$this->tableExists()) {
            return 0;
        }
        $stmt = $this->pdo->prepare(
            'SELECT request_count FROM tenant_api_key_daily_usage WHERE api_key_id = ? AND usage_day = CURDATE() LIMIT 1'
        );
        $stmt->execute([$apiKeyId]);
        $n = $stmt->fetchColumn();

        return $n !== false ? (int) $n : 0;
    }
}
