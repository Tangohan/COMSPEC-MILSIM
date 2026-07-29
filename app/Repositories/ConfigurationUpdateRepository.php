<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class ConfigurationUpdateRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
    }

    public function tablesExist(): bool
    {
        $st = $this->pdo->query(
            "SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME IN ('system_configuration_updates','tenant_configuration_updates')"
        );

        return $st !== false && (int) $st->fetchColumn() === 2;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listActiveSystemUpdates(): array
    {
        if (!$this->tablesExist()) {
            return [];
        }
        $st = $this->pdo->query(
            'SELECT * FROM system_configuration_updates WHERE active = 1 ORDER BY sort_order ASC, id ASC'
        );

        return $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    public function findSystemByCode(string $code): ?array
    {
        if (!$this->tablesExist()) {
            return null;
        }
        $st = $this->pdo->prepare('SELECT * FROM system_configuration_updates WHERE code = ? LIMIT 1');
        $st->execute([$code]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findTenantState(int $tenantId, int $updateId): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM tenant_configuration_updates WHERE tenant_id = ? AND update_id = ? LIMIT 1'
        );
        $st->execute([$tenantId, $updateId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @return array<int, array<string, mixed>> keyed by update_id
     */
    public function mapTenantStates(int $tenantId): array
    {
        if (!$this->tablesExist()) {
            return [];
        }
        $st = $this->pdo->prepare('SELECT * FROM tenant_configuration_updates WHERE tenant_id = ?');
        $st->execute([$tenantId]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[(int) $row['update_id']] = $row;
        }

        return $out;
    }

    /**
     * @param array<string, mixed>|null $metadata
     * @param array<string, mixed>|null $progressData
     */
    public function upsertTenantState(
        int $tenantId,
        int $updateId,
        string $status,
        ?int $userId = null,
        ?array $metadata = null,
        ?string $progressStep = null,
        ?array $progressData = null,
        ?string $remindAt = null,
    ): void {
        $existing = $this->findTenantState($tenantId, $updateId);
        $metaJson = $metadata !== null ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null;
        $progressJson = $progressData !== null ? json_encode($progressData, JSON_UNESCAPED_UNICODE) : null;

        if ($existing === null) {
            $st = $this->pdo->prepare(
                'INSERT INTO tenant_configuration_updates
                    (tenant_id, update_id, status, progress_step, progress_data, metadata,
                     started_at, completed_at, dismissed_at, remind_at, completed_by_user_id, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
            );
            $st->execute([
                $tenantId,
                $updateId,
                $status,
                $progressStep,
                $progressJson,
                $metaJson,
                in_array($status, ['IN_PROGRESS', 'COMPLETED'], true) ? date('Y-m-d H:i:s') : null,
                $status === 'COMPLETED' ? date('Y-m-d H:i:s') : null,
                $status === 'DISMISSED' ? date('Y-m-d H:i:s') : null,
                $remindAt,
                $status === 'COMPLETED' ? $userId : null,
            ]);

            return;
        }

        $sets = ['status = ?', 'updated_at = NOW()'];
        $params = [$status];

        if ($progressStep !== null) {
            $sets[] = 'progress_step = ?';
            $params[] = $progressStep;
        }
        if ($progressData !== null) {
            $sets[] = 'progress_data = ?';
            $params[] = $progressJson;
        }
        if ($metadata !== null) {
            $sets[] = 'metadata = ?';
            $params[] = $metaJson;
        }
        if ($remindAt !== null) {
            $sets[] = 'remind_at = ?';
            $params[] = $remindAt;
        }

        if ($status === 'SEEN' && empty($existing['started_at'])) {
            // SEEN only — no start stamp
        }
        if ($status === 'IN_PROGRESS') {
            $sets[] = 'started_at = COALESCE(started_at, NOW())';
            $sets[] = 'dismissed_at = NULL';
            $sets[] = 'remind_at = NULL';
        }
        if ($status === 'COMPLETED') {
            $sets[] = 'completed_at = NOW()';
            $sets[] = 'dismissed_at = NULL';
            $sets[] = 'remind_at = NULL';
            $sets[] = 'completed_by_user_id = ?';
            $params[] = $userId;
        }
        if ($status === 'DISMISSED') {
            $sets[] = 'dismissed_at = NOW()';
            $sets[] = 'remind_at = NULL';
        }
        if ($status === 'PENDING' || $status === 'SEEN') {
            // reopen path clears terminal stamps when going back
            if (($existing['status'] ?? '') === 'DISMISSED' || ($existing['status'] ?? '') === 'COMPLETED') {
                $sets[] = 'completed_at = NULL';
                $sets[] = 'dismissed_at = NULL';
                $sets[] = 'completed_by_user_id = NULL';
            }
        }

        $params[] = $tenantId;
        $params[] = $updateId;
        $sql = 'UPDATE tenant_configuration_updates SET ' . implode(', ', $sets)
            . ' WHERE tenant_id = ? AND update_id = ?';
        $this->pdo->prepare($sql)->execute($params);
    }
}
