<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Core\Database;
use PDO;

class TrainingAuditService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function log(
        string $action,
        string $targetType,
        int $targetId,
        ?int $tenantId = null,
        ?int $userId = null,
        ?array $oldValue = null,
        ?array $newValue = null,
        ?string $ip = null,
        ?string $userAgent = null
    ): void {
        $ip = $ip ?? ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null);
        $userAgent = $userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? null);
        if (is_string($ip) && strpos($ip, ',') !== false) {
            $ip = trim(explode(',', $ip)[0]);
        }
        if (is_string($userAgent) && strlen($userAgent) > 500) {
            $userAgent = substr($userAgent, 0, 500);
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO training_audit_log (tenant_id, user_id, action, target_type, target_id, old_value, new_value, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $tenantId,
            $userId,
            $action,
            $targetType,
            $targetId,
            $oldValue !== null ? json_encode($oldValue, JSON_UNESCAPED_UNICODE) : null,
            $newValue !== null ? json_encode($newValue, JSON_UNESCAPED_UNICODE) : null,
            $ip,
            $userAgent,
        ]);
    }

    public function logCourseCreated(int $tenantId, int $userId, int $courseId, array $newValue): void
    {
        $this->log('course_created', 'training_course', $courseId, $tenantId, $userId, null, $newValue);
    }

    public function logCourseUpdated(int $tenantId, int $userId, int $courseId, ?array $oldValue, array $newValue): void
    {
        $this->log('course_updated', 'training_course', $courseId, $tenantId, $userId, $oldValue, $newValue);
    }

    public function logCoursePublished(int $tenantId, int $userId, int $courseId): void
    {
        $this->log('course_published', 'training_course', $courseId, $tenantId, $userId);
    }

    public function logEnrollmentAssigned(int $tenantId, ?int $userId, int $enrollmentId, array $newValue): void
    {
        $this->log('enrollment_assigned', 'training_enrollment', $enrollmentId, $tenantId, $userId, null, $newValue);
    }

    public function logLessonCompleted(int $tenantId, int $userId, int $enrollmentId, int $lessonId): void
    {
        $this->log('lesson_completed', 'training_progress', $enrollmentId, $tenantId, $userId, null, ['lesson_id' => $lessonId]);
    }

    public function logQuizAttemptSubmitted(int $tenantId, int $userId, int $attemptId, array $newValue): void
    {
        $this->log('quiz_attempt_submitted', 'training_quiz_attempt', $attemptId, $tenantId, $userId, null, $newValue);
    }

    public function logCertificateIssued(int $tenantId, int $userId, int $certificateId, array $newValue): void
    {
        $this->log('certificate_issued', 'training_certificate', $certificateId, $tenantId, $userId, null, $newValue);
    }

    public function logCertificateRevoked(int $tenantId, int $userId, int $certificateId): void
    {
        $this->log('certificate_revoked', 'training_certificate', $certificateId, $tenantId, $userId);
    }

    /** @return list<array<string, mixed>> */
    public function listLogs(?int $tenantId = null, ?string $targetType = null, ?int $targetId = null, ?int $userId = null, ?int $limit = 100): array
    {
        $sql = 'SELECT * FROM training_audit_log WHERE 1=1';
        $params = [];
        if ($tenantId !== null) {
            $sql .= ' AND tenant_id = ?';
            $params[] = $tenantId;
        }
        if ($targetType !== null) {
            $sql .= ' AND target_type = ?';
            $params[] = $targetType;
        }
        if ($targetId !== null) {
            $sql .= ' AND target_id = ?';
            $params[] = $targetId;
        }
        if ($userId !== null) {
            $sql .= ' AND user_id = ?';
            $params[] = $userId;
        }
        $sql .= ' ORDER BY created_at DESC LIMIT ' . (int) $limit;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
