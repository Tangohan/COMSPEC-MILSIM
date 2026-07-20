<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Matrice de compétences × grades : catalogue de référence (pas de suivi par opérateur) —
 * pour chaque compétence/module d'un palier de formation, le grade auquel elle est attendue
 * et le niveau d'acquisition visé.
 */
class CompetencyGradeRequirementRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    /** @return list<array<string, mixed>> */
    public function listForTenant(int $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM competency_grade_requirements WHERE tenant_id = ? ORDER BY palier_order ASC, sort_order ASC, id ASC'
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findByIdForTenant(int $id, int $tenantId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM competency_grade_requirements WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$id, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function nextSortOrderForPalier(int $tenantId, string $palier): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(MAX(sort_order), -1) + 1 FROM competency_grade_requirements WHERE tenant_id = ? AND palier = ?'
        );
        $stmt->execute([$tenantId, $palier]);

        return (int) $stmt->fetchColumn();
    }

    public function create(int $tenantId, array $data, ?int $createdByUserId): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO competency_grade_requirements (tenant_id, palier, palier_order, label, grade_id, acquisition_level, sort_order, created_by_user_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $tenantId,
            $data['palier'],
            $data['palier_order'] ?? 0,
            $data['label'],
            $data['grade_id'] ?? null,
            $data['acquisition_level'] ?? null,
            $data['sort_order'] ?? $this->nextSortOrderForPalier($tenantId, (string) $data['palier']),
            $createdByUserId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, int $tenantId, array $data): bool
    {
        $cols = ['palier', 'palier_order', 'label', 'grade_id', 'acquisition_level', 'sort_order'];
        $sets = [];
        $params = [];
        foreach ($cols as $col) {
            if (!array_key_exists($col, $data)) {
                continue;
            }
            $sets[] = "{$col} = ?";
            $params[] = $data[$col];
        }
        if ($sets === []) {
            return false;
        }
        $sets[] = 'updated_at = NOW()';
        $params[] = $id;
        $params[] = $tenantId;
        $stmt = $this->pdo->prepare(
            'UPDATE competency_grade_requirements SET ' . implode(', ', $sets) . ' WHERE id = ? AND tenant_id = ?'
        );
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id, int $tenantId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM competency_grade_requirements WHERE id = ? AND tenant_id = ?');
        $stmt->execute([$id, $tenantId]);

        return $stmt->rowCount() > 0;
    }
}
