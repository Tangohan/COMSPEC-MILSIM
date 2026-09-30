<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;
use Throwable;

final class PersonnelAwardRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function schemaReady(): bool
    {
        return $this->tableExists('personnel_awards');
    }

    /** @return list<array<string, mixed>> */
    public function listForPersonnel(int $tenantId, int $personnelId): array
    {
        if (!$this->schemaReady()) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT a.*, d.name AS definition_name, d.code AS definition_code,
                    d.decoration_grade, d.award_criterion
             FROM personnel_awards a
             JOIN award_definitions d ON d.id = a.definition_id
             WHERE a.tenant_id = ? AND a.personnel_id = ?
             ORDER BY a.awarded_at DESC, a.id DESC'
        );
        $st->execute([$tenantId, $personnelId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function listHolders(int $tenantId, int $definitionId): array
    {
        $st = $this->pdo->prepare(
            'SELECT a.*, u.display_name, u.email
             FROM personnel_awards a
             JOIN users u ON u.id = a.personnel_id
             WHERE a.tenant_id = ? AND a.definition_id = ?
             ORDER BY a.awarded_at DESC'
        );
        $st->execute([$tenantId, $definitionId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @param array<string, mixed> $data */
    public function create(int $tenantId, array $data, ?int $actorId): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO personnel_awards
                (tenant_id, personnel_id, definition_id, citation_text, authority, awarded_at, created_at, created_by)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)'
        );
        $st->execute([
            $tenantId,
            (int) ($data['personnel_id'] ?? 0),
            (int) ($data['definition_id'] ?? 0),
            ($t = trim((string) ($data['citation_text'] ?? ''))) === '' ? null : $t,
            ($a = trim((string) ($data['authority'] ?? ''))) === '' ? null : $a,
            substr((string) ($data['awarded_at'] ?? date('Y-m-d')), 0, 10),
            $actorId,
        ]);

        return (int) $this->pdo->lastInsertId();
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
