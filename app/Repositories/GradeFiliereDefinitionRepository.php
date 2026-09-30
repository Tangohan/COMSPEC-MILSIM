<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;
use Throwable;

final class GradeFiliereDefinitionRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function schemaReady(): bool
    {
        return $this->tableExists('grade_filiere_definitions');
    }

    /** @return list<array<string, mixed>> */
    public function listForTenant(int $tenantId): array
    {
        if (!$this->schemaReady() || $tenantId < 1) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT * FROM grade_filiere_definitions WHERE tenant_id = ? ORDER BY sort_order ASC, label ASC'
        );
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string, mixed>|null */
    public function find(int $tenantId, int $id): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM grade_filiere_definitions WHERE tenant_id = ? AND id = ? LIMIT 1'
        );
        $st->execute([$tenantId, $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findByCode(int $tenantId, string $code): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM grade_filiere_definitions WHERE tenant_id = ? AND code = ? LIMIT 1'
        );
        $st->execute([$tenantId, $code]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function create(int $tenantId, string $code, string $label, int $sortOrder = 0): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO grade_filiere_definitions (tenant_id, code, label, sort_order, created_at, updated_at)
             VALUES (?, ?, ?, ?, NOW(), NOW())'
        );
        $st->execute([$tenantId, strtoupper(trim($code)), trim($label), $sortOrder]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $tenantId, int $id, string $code, string $label, int $sortOrder): void
    {
        $st = $this->pdo->prepare(
            'UPDATE grade_filiere_definitions
             SET code = ?, label = ?, sort_order = ?, updated_at = NOW()
             WHERE tenant_id = ? AND id = ?'
        );
        $st->execute([strtoupper(trim($code)), trim($label), $sortOrder, $tenantId, $id]);
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
