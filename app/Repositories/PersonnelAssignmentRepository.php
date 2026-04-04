<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class PersonnelAssignmentRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    /** @return list<array<string, mixed>> Affectations actives (status = active, ended_at null ou future). */
    public function listActiveForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT pa.*, u.name AS unit_name, u.slug AS unit_slug, u.type AS unit_type, u.commander_user_id
             FROM personnel_assignments pa
             JOIN units u ON u.id = pa.unit_id
             WHERE pa.user_id = ? AND pa.status = ? AND (pa.ended_at IS NULL OR pa.ended_at > CURDATE())
             ORDER BY pa.is_primary DESC, pa.started_at DESC'
        );
        $stmt->execute([$userId, 'active']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPrimaryAssignment(int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT pa.*, u.name AS unit_name, u.slug AS unit_slug, u.commander_user_id
             FROM personnel_assignments pa
             JOIN units u ON u.id = pa.unit_id
             WHERE pa.user_id = ? AND pa.is_primary = 1 AND pa.status = ? AND (pa.ended_at IS NULL OR pa.ended_at > CURDATE())
             LIMIT 1'
        );
        $stmt->execute([$userId, 'active']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Liste depuis user_units si personnel_assignments vide (rétrocompat). */
    public function listActiveForUserLegacy(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT uu.id, uu.user_id, uu.unit_id, uu.is_primary, uu.assigned_at AS started_at, uu.ended_at,
             COALESCE(NULLIF(TRIM(uu.assignment_type), ""), "Membre") AS role_name,
             u.name AS unit_name, u.slug AS unit_slug, u.type AS unit_type, u.commander_user_id
             FROM user_units uu
             JOIN units u ON u.id = uu.unit_id
             WHERE uu.user_id = ? AND (uu.ended_at IS NULL OR uu.ended_at > NOW())
             ORDER BY uu.is_primary DESC, uu.assigned_at DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function setPrimary(int $userId, int $assignmentId): bool
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('UPDATE personnel_assignments SET is_primary = 0 WHERE user_id = ?')->execute([$userId]);
            $stmt = $this->pdo->prepare('UPDATE personnel_assignments SET is_primary = 1 WHERE id = ? AND user_id = ?');
            $stmt->execute([$assignmentId, $userId]);
            $ok = $stmt->rowCount() > 0;
            $this->pdo->commit();
            return $ok;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
