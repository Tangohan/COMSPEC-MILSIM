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

    /** @param array<string, mixed> $snapshot Titre, slug, etc. pour trace après suppression. */
    public function logCourseDeleted(int $tenantId, int $userId, int $courseId, array $snapshot): void
    {
        $this->log('course_deleted', 'training_course', $courseId, $tenantId, $userId, $snapshot, null);
    }

    public function logEnrollmentAssigned(int $tenantId, ?int $userId, int $enrollmentId, array $newValue): void
    {
        $this->log('enrollment_assigned', 'training_enrollment', $enrollmentId, $tenantId, $userId, null, $newValue);
    }

    /** @param array<string, mixed> $newValue course_id, course_title, previous_status, user_id */
    public function logEnrollmentWithdrawn(int $tenantId, int $userId, int $enrollmentId, array $newValue): void
    {
        $this->log('enrollment_withdrawn', 'training_enrollment', $enrollmentId, $tenantId, $userId, null, $newValue);
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

    /**
     * Journal d’audit pour l’écran tenant : acteur (profil), formation liée, auteur du parcours.
     *
     * @return list<array<string, mixed>>
     */
    public function listLogsForTenantDisplay(int $tenantId, int $limit = 200): array
    {
        $limit = max(1, min(500, $limit));
        $sql = <<<SQL
SELECT
    a.id,
    a.tenant_id,
    a.user_id,
    a.action,
    a.target_type,
    a.target_id,
    a.old_value,
    a.new_value,
    a.ip_address,
    a.user_agent,
    a.created_at,
    ua.display_name AS actor_display_name,
    ua.email AS actor_email,
    COALESCE(cd.id, te.course_id, tprog.course_id, tquiz_e.course_id, tcert_e.course_id) AS ctx_course_id,
    cctx.title AS ctx_course_title,
    uauth.display_name AS course_author_display_name,
    uauth.email AS course_author_email
FROM training_audit_log a
LEFT JOIN users ua ON ua.id = a.user_id
LEFT JOIN training_courses cd
    ON a.target_type = 'training_course' AND cd.id = a.target_id AND cd.tenant_id = a.tenant_id
LEFT JOIN training_enrollments te
    ON a.target_type = 'training_enrollment' AND te.id = a.target_id AND te.tenant_id = a.tenant_id
LEFT JOIN training_enrollments tprog
    ON a.target_type = 'training_progress' AND tprog.id = a.target_id AND tprog.tenant_id = a.tenant_id
LEFT JOIN training_quiz_attempts tqa
    ON a.target_type = 'training_quiz_attempt' AND tqa.id = a.target_id
LEFT JOIN training_enrollments tquiz_e
    ON tquiz_e.id = tqa.enrollment_id AND tquiz_e.tenant_id = a.tenant_id
LEFT JOIN training_certificates tcert
    ON a.target_type = 'training_certificate' AND tcert.id = a.target_id AND tcert.tenant_id = a.tenant_id
LEFT JOIN training_enrollments tcert_e
    ON tcert_e.id = tcert.enrollment_id AND tcert_e.tenant_id = a.tenant_id
LEFT JOIN training_courses cctx
    ON cctx.id = COALESCE(cd.id, te.course_id, tprog.course_id, tquiz_e.course_id, tcert_e.course_id)
    AND cctx.tenant_id = a.tenant_id
LEFT JOIN users uauth ON uauth.id = cctx.created_by
WHERE a.tenant_id = ?
ORDER BY a.created_at DESC
LIMIT {$limit}
SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$tenantId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            foreach (['old_value', 'new_value'] as $jsonCol) {
                $raw = $row[$jsonCol] ?? null;
                if ($raw === null || $raw === '') {
                    $row[$jsonCol] = null;

                    continue;
                }
                if (is_string($raw)) {
                    $decoded = json_decode($raw, true);
                    $row[$jsonCol] = is_array($decoded) ? $decoded : null;
                }
            }
        }
        unset($row);

        return $rows;
    }
}
