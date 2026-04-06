<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class TrainingEnrollmentRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    /** @return list<array<string, mixed>> */
    public function listByUserId(int $userId, ?int $tenantId = null): array
    {
        $sql = 'SELECT e.*, c.title AS course_title, c.slug AS course_slug, c.estimated_minutes, c.banner_path,
                       c.short_description, c.category, c.level, c.is_certifying, c.is_mandatory, c.thumbnail_path,
                       (SELECT id FROM training_certificates WHERE enrollment_id = e.id AND status = \'valid\' ORDER BY id DESC LIMIT 1) AS certificate_id
                FROM training_enrollments e
                JOIN training_courses c ON c.id = e.course_id
                WHERE e.user_id = ?';
        $params = [$userId];
        if ($tenantId !== null) {
            $sql .= ' AND e.tenant_id = ?';
            $params[] = $tenantId;
        }
        $sql .= ' ORDER BY e.assigned_at DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string, mixed>> */
    public function listByCourseId(int $courseId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT e.*, u.email, u.display_name
             FROM training_enrollments e
             JOIN users u ON u.id = e.user_id
             WHERE e.course_id = ?
             ORDER BY e.assigned_at DESC'
        );
        $stmt->execute([$courseId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id, ?int $tenantId = null): ?array
    {
        $sql = 'SELECT e.*, c.title AS course_title, c.slug AS course_slug, c.estimated_minutes, c.passing_score, c.is_certifying, c.validity_days
                FROM training_enrollments e
                JOIN training_courses c ON c.id = e.course_id
                WHERE e.id = ?';
        $params = [$id];
        if ($tenantId !== null) {
            $sql .= ' AND e.tenant_id = ?';
            $params[] = $tenantId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByCourseAndUser(int $courseId, int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM training_enrollments WHERE course_id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$courseId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function userHasCompletedCourse(int $userId, int $courseId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM training_enrollments WHERE user_id = ? AND course_id = ? AND status = ? LIMIT 1'
        );
        $stmt->execute([$userId, $courseId, 'completed']);

        return (bool) $stmt->fetchColumn();
    }

    public function create(int $tenantId, array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO training_enrollments (tenant_id, course_id, user_id, assigned_by, assignment_type, status, expires_at, motivation_text)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $tenantId,
            $data['course_id'],
            $data['user_id'],
            $data['assigned_by'] ?? null,
            $data['assignment_type'] ?? 'manual',
            $data['status'] ?? 'assigned',
            $data['expires_at'] ?? null,
            $data['motivation_text'] ?? null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $allowed = ['status', 'started_at', 'completed_at', 'expires_at', 'motivation_text'];
        $fields = [];
        $params = [];
        foreach ($allowed as $k) {
            if (array_key_exists($k, $data)) {
                $fields[] = "`$k` = ?";
                $params[] = $data[$k];
            }
        }
        if ($fields === []) {
            return;
        }
        $params[] = $id;
        $stmt = $this->pdo->prepare('UPDATE training_enrollments SET ' . implode(', ', $fields) . ' WHERE id = ?');
        $stmt->execute($params);
    }

    public function revoke(int $id): void
    {
        $stmt = $this->pdo->prepare("UPDATE training_enrollments SET status = 'revoked' WHERE id = ?");
        $stmt->execute([$id]);
    }

    /** Enrollments expirant ou expirés pour un tenant. */
    public function listExpiringOrExpired(int $tenantId, ?int $daysAhead = 30): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT e.*, c.title AS course_title, u.email, u.display_name
             FROM training_enrollments e
             JOIN training_courses c ON c.id = e.course_id
             JOIN users u ON u.id = e.user_id
             WHERE e.tenant_id = ? AND e.expires_at IS NOT NULL AND e.expires_at <= DATE_ADD(NOW(), INTERVAL ? DAY) AND e.status IN (\'assigned\', \'in_progress\')
             ORDER BY e.expires_at ASC'
        );
        $stmt->execute([$tenantId, $daysAhead]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
