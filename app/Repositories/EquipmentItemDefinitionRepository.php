<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\AdvancementCodes;
use PDO;
use Throwable;

final class EquipmentItemDefinitionRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function schemaReady(): bool
    {
        return $this->tableExists('equipment_item_definitions');
    }

    /** @return list<array<string, mixed>> */
    public function listForTenant(int $tenantId, bool $includeArchived = false): array
    {
        if (!$this->schemaReady()) {
            return [];
        }
        $sql = 'SELECT d.*,
                       (SELECT COUNT(*) FROM personnel_equipment_assignments a
                         WHERE a.definition_id = d.id AND a.status = \'' . AdvancementCodes::EQUIP_ISSUED . '\') AS issued_count
                FROM equipment_item_definitions d WHERE d.tenant_id = ?';
        if (!$includeArchived) {
            $sql .= ' AND d.archived_at IS NULL';
        }
        $sql .= ' ORDER BY d.category ASC, d.name ASC';
        $st = $this->pdo->prepare($sql);
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function find(int $tenantId, int $id): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM equipment_item_definitions WHERE tenant_id = ? AND id = ? LIMIT 1'
        );
        $st->execute([$tenantId, $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findByCode(int $tenantId, string $code): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM equipment_item_definitions WHERE tenant_id = ? AND code = ? LIMIT 1'
        );
        $st->execute([$tenantId, strtoupper(trim($code))]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @param array<string, mixed> $data */
    public function create(int $tenantId, array $data, ?int $actorId): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO equipment_item_definitions
                (tenant_id, code, name, category, description, created_at, updated_at, created_by)
             VALUES (?, ?, ?, ?, ?, NOW(), NOW(), ?)'
        );
        $st->execute([
            $tenantId,
            strtoupper(trim((string) ($data['code'] ?? ''))),
            trim((string) ($data['name'] ?? '')),
            ($c = trim((string) ($data['category'] ?? ''))) === '' ? null : $c,
            ($d = trim((string) ($data['description'] ?? ''))) === '' ? null : $d,
            $actorId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $tenantId, int $id, array $data): void
    {
        $st = $this->pdo->prepare(
            'UPDATE equipment_item_definitions
             SET code = ?, name = ?, category = ?, description = ?, updated_at = NOW()
             WHERE tenant_id = ? AND id = ? AND archived_at IS NULL'
        );
        $st->execute([
            strtoupper(trim((string) ($data['code'] ?? ''))),
            trim((string) ($data['name'] ?? '')),
            ($c = trim((string) ($data['category'] ?? ''))) === '' ? null : $c,
            ($d = trim((string) ($data['description'] ?? ''))) === '' ? null : $d,
            $tenantId,
            $id,
        ]);
    }

    public function archive(int $tenantId, int $id): void
    {
        $st = $this->pdo->prepare(
            'UPDATE equipment_item_definitions SET archived_at = NOW(), updated_at = NOW()
             WHERE tenant_id = ? AND id = ? AND archived_at IS NULL'
        );
        $st->execute([$tenantId, $id]);
    }

    private function tableExists(string $table): bool
    {
        try {
            $st = $this->pdo->prepare(
                'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
            );
            $st->execute([$table]);

            return (bool) $st->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }
}
