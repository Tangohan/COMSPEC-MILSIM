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

    /**
     * Source métier : `personnel_assignments` en priorité ; si aucune ligne active, repli sur `user_units`
     * (historique / compat). Pour les écritures futures, privilégier `personnel_assignments`.
     *
     * @return list<array<string, mixed>>
     */
    public function listActiveForUserResolved(int $userId): array
    {
        $rows = $this->listActiveForUser($userId);
        if ($rows !== []) {
            return $rows;
        }

        return $this->listActiveForUserLegacy($userId);
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

    /**
     * Insère dans personnel_assignments les couples (user, unit) présents dans user_units mais absents du dossier.
     * Idempotent ; utile après migration ou import legacy.
     */
    public function syncMissingFromUserUnits(int $userId): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO personnel_assignments (user_id, unit_id, role_name, is_primary, started_at, ended_at, status, created_at)
             SELECT uu.user_id, uu.unit_id,
                    COALESCE(NULLIF(TRIM(uu.assignment_type), \'\'), \'Membre\'),
                    COALESCE(uu.is_primary, 0),
                    CASE WHEN uu.assigned_at IS NULL THEN CURDATE() ELSE DATE(uu.assigned_at) END,
                    CASE WHEN uu.ended_at IS NULL THEN NULL ELSE DATE(uu.ended_at) END,
                    CASE WHEN uu.ended_at IS NULL OR uu.ended_at > NOW() THEN \'active\' ELSE \'inactive\' END,
                    NOW()
             FROM user_units uu
             WHERE uu.user_id = ?
               AND NOT EXISTS (
                 SELECT 1 FROM personnel_assignments pa
                 WHERE pa.user_id = uu.user_id AND pa.unit_id = uu.unit_id
               )'
        );
        $stmt->execute([$userId]);

        return $stmt->rowCount();
    }
}
