<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class TenantFunctionKitRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
    }

    public function tableExists(): bool
    {
        try {
            $st = $this->pdo->query(
                "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenant_function_kit_state' LIMIT 1"
            );

            return (bool) ($st && $st->fetchColumn());
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array{kit_ids: list<string>, reviewed_at: string, updated_by: int|null}|null
     */
    public function find(int $tenantId): ?array
    {
        if ($tenantId < 1 || !$this->tableExists()) {
            return null;
        }
        try {
            $st = $this->pdo->prepare(
                'SELECT kit_ids_json, reviewed_at, updated_by FROM tenant_function_kit_state WHERE tenant_id = ? LIMIT 1'
            );
            $st->execute([$tenantId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return null;
        }
        if (!is_array($row)) {
            return null;
        }
        $decoded = json_decode((string) ($row['kit_ids_json'] ?? '[]'), true);
        $ids = [];
        if (is_array($decoded)) {
            foreach ($decoded as $id) {
                $id = trim((string) $id);
                if ($id !== '') {
                    $ids[] = $id;
                }
            }
        }

        return [
            'kit_ids' => $ids,
            'reviewed_at' => (string) ($row['reviewed_at'] ?? ''),
            'updated_by' => isset($row['updated_by']) ? (int) $row['updated_by'] : null,
        ];
    }

    public function hasReviewed(int $tenantId): bool
    {
        $row = $this->find($tenantId);

        return $row !== null && trim($row['reviewed_at']) !== '' && $row['reviewed_at'] !== '0000-00-00 00:00:00';
    }

    /**
     * @param list<string> $kitIds
     */
    public function save(int $tenantId, array $kitIds, ?int $updatedBy): void
    {
        if ($tenantId < 1 || !$this->tableExists()) {
            return;
        }
        $encoded = json_encode(array_values($kitIds), JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            $encoded = '[]';
        }
        $st = $this->pdo->prepare(
            'INSERT INTO tenant_function_kit_state (tenant_id, kit_ids_json, reviewed_at, updated_by, updated_at)
             VALUES (?, ?, NOW(), ?, NOW())
             ON DUPLICATE KEY UPDATE
                kit_ids_json = VALUES(kit_ids_json),
                reviewed_at = VALUES(reviewed_at),
                updated_by = VALUES(updated_by),
                updated_at = NOW()'
        );
        $st->execute([$tenantId, $encoded, $updatedBy !== null && $updatedBy > 0 ? $updatedBy : null]);
    }
}
