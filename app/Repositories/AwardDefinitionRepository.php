<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;
use Throwable;

final class AwardDefinitionRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function schemaReady(): bool
    {
        return $this->tableExists('award_definitions');
    }

    /** @return list<array<string, mixed>> */
    public function listForTenant(int $tenantId, bool $includeArchived = false): array
    {
        if (!$this->schemaReady()) {
            return [];
        }
        $sql = 'SELECT d.*,
                       (SELECT COUNT(*) FROM personnel_awards a WHERE a.definition_id = d.id) AS holders_count
                FROM award_definitions d WHERE d.tenant_id = ?';
        if (!$includeArchived) {
            $sql .= ' AND d.archived_at IS NULL';
        }
        $sql .= ' ORDER BY d.sort_order ASC, d.name ASC';
        $st = $this->pdo->prepare($sql);
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function find(int $tenantId, int $id): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM award_definitions WHERE tenant_id = ? AND id = ? LIMIT 1'
        );
        $st->execute([$tenantId, $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findByCode(int $tenantId, string $code): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM award_definitions WHERE tenant_id = ? AND code = ? LIMIT 1'
        );
        $st->execute([$tenantId, strtoupper(trim($code))]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @param array<string, mixed> $data */
    public function create(int $tenantId, array $data, ?int $actorId): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO award_definitions
                (tenant_id, code, name, decoration_grade, award_criterion, sort_order, created_at, updated_at, created_by)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW(), ?)'
        );
        $st->execute([
            $tenantId,
            strtoupper(trim((string) ($data['code'] ?? ''))),
            trim((string) ($data['name'] ?? '')),
            ($g = trim((string) ($data['decoration_grade'] ?? ''))) === '' ? null : $g,
            ($c = trim((string) ($data['award_criterion'] ?? ''))) === '' ? null : $c,
            (int) ($data['sort_order'] ?? 0),
            $actorId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $tenantId, int $id, array $data): void
    {
        $st = $this->pdo->prepare(
            'UPDATE award_definitions
             SET code = ?, name = ?, decoration_grade = ?, award_criterion = ?, sort_order = ?, updated_at = NOW()
             WHERE tenant_id = ? AND id = ? AND archived_at IS NULL'
        );
        $st->execute([
            strtoupper(trim((string) ($data['code'] ?? ''))),
            trim((string) ($data['name'] ?? '')),
            ($g = trim((string) ($data['decoration_grade'] ?? ''))) === '' ? null : $g,
            ($c = trim((string) ($data['award_criterion'] ?? ''))) === '' ? null : $c,
            (int) ($data['sort_order'] ?? 0),
            $tenantId,
            $id,
        ]);
    }

    public function archive(int $tenantId, int $id): void
    {
        $st = $this->pdo->prepare(
            'UPDATE award_definitions SET archived_at = NOW(), updated_at = NOW()
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
