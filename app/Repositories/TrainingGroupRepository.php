<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Groupes de formation (cohortes) : rattachement optionnel à une formation du catalogue,
 * membres gérés via training_group_members.
 */
class TrainingGroupRepository
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
            'SELECT g.*, c.title AS course_title,
                    (SELECT COUNT(*) FROM training_group_members m WHERE m.group_id = g.id) AS member_count
             FROM training_groups g
             LEFT JOIN training_courses c ON c.id = g.course_id AND c.tenant_id = g.tenant_id
             WHERE g.tenant_id = ?
             ORDER BY g.created_at DESC, g.id DESC'
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findByIdForTenant(int $id, int $tenantId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT g.*, c.title AS course_title
             FROM training_groups g
             LEFT JOIN training_courses c ON c.id = g.course_id AND c.tenant_id = g.tenant_id
             WHERE g.id = ? AND g.tenant_id = ? LIMIT 1'
        );
        $stmt->execute([$id, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function create(int $tenantId, string $name, string $description, ?int $courseId, ?int $createdBy): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO training_groups (tenant_id, name, description, course_id, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$tenantId, $name, $description !== '' ? $description : null, $courseId, $createdBy]);

        return (int) $this->pdo->lastInsertId();
    }

    public function delete(int $id, int $tenantId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM training_groups WHERE id = ? AND tenant_id = ?');
        $stmt->execute([$id, $tenantId]);

        return $stmt->rowCount() > 0;
    }

    /** @return list<array<string, mixed>> */
    public function listMembers(int $groupId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT m.user_id, m.joined_at, u.display_name, u.callsign, u.email
             FROM training_group_members m
             INNER JOIN users u ON u.id = m.user_id
             WHERE m.group_id = ?
             ORDER BY u.display_name ASC, u.callsign ASC'
        );
        $stmt->execute([$groupId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function addMember(int $groupId, int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            'INSERT IGNORE INTO training_group_members (group_id, user_id, joined_at) VALUES (?, ?, NOW())'
        );
        $stmt->execute([$groupId, $userId]);

        return $stmt->rowCount() > 0;
    }

    public function removeMember(int $groupId, int $userId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM training_group_members WHERE group_id = ? AND user_id = ?');
        $stmt->execute([$groupId, $userId]);

        return $stmt->rowCount() > 0;
    }
}
