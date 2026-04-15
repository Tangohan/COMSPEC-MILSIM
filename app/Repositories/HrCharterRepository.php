<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class HrCharterRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function tableExists(): bool
    {
        $stmt = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'hr_charter_versions' LIMIT 1");

        return (bool) ($stmt && $stmt->fetchColumn());
    }

    public function acceptanceTableExists(): bool
    {
        $stmt = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'hr_charter_acceptances' LIMIT 1");

        return (bool) ($stmt && $stmt->fetchColumn());
    }

    public function findActiveVersion(): ?array
    {
        if (!$this->tableExists()) {
            return null;
        }
        $stmt = $this->pdo->query('SELECT * FROM hr_charter_versions WHERE is_active = 1 ORDER BY effective_at DESC, id DESC LIMIT 1');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findLatestAcceptance(int $tenantId, int $userId): ?array
    {
        if (!$this->acceptanceTableExists()) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM hr_charter_acceptances WHERE tenant_id = ? AND user_id = ? ORDER BY accepted_at DESC, id DESC LIMIT 1');
        $stmt->execute([$tenantId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function hasAcceptedVersion(int $tenantId, int $userId, int $charterVersionId): bool
    {
        if (!$this->acceptanceTableExists()) {
            return false;
        }
        $stmt = $this->pdo->prepare('SELECT 1 FROM hr_charter_acceptances WHERE tenant_id = ? AND user_id = ? AND charter_version_id = ? LIMIT 1');
        $stmt->execute([$tenantId, $userId, $charterVersionId]);

        return (bool) $stmt->fetchColumn();
    }

    public function storeAcceptance(int $tenantId, int $userId, int $charterVersionId, ?string $ipAddress, ?string $userAgent): void
    {
        if (!$this->acceptanceTableExists()) {
            return;
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO hr_charter_acceptances (tenant_id, user_id, charter_version_id, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE accepted_at = CURRENT_TIMESTAMP, ip_address = VALUES(ip_address), user_agent = VALUES(user_agent)'
        );
        $stmt->execute([$tenantId, $userId, $charterVersionId, $ipAddress, $userAgent]);
    }
}
