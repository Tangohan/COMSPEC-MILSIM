<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Repositories\TrainingCourseRepository;
use App\Repositories\TrainingEnrollmentRepository;
use App\Repositories\UserRepository;

class TrainingAssignmentService
{
    public function __construct(
        private TrainingEnrollmentRepository $enrollmentRepository,
        private TrainingCourseRepository $courseRepository,
        private UserRepository $userRepository,
        private TrainingAuditService $auditService
    ) {}

    public function assignUser(int $courseId, int $userId, int $tenantId, ?int $assignedBy = null, string $assignmentType = 'manual', ?string $expiresAt = null): int
    {
        $course = $this->courseRepository->findById($courseId, $tenantId);
        if (!$course) {
            throw new \InvalidArgumentException('Course not found.');
        }
        $existing = $this->enrollmentRepository->findByCourseAndUser($courseId, $userId);
        if ($existing) {
            if (in_array($existing['status'], ['revoked', 'expired'], true)) {
                $this->enrollmentRepository->update($existing['id'], [
                    'status' => 'assigned',
                    'assigned_at' => date('Y-m-d H:i:s'),
                    'expires_at' => $expiresAt,
                    'assigned_by' => $assignedBy,
                    'assignment_type' => $assignmentType,
                ]);
                $this->auditService->logEnrollmentAssigned($tenantId, $assignedBy, $existing['id'], [
                    'user_id' => $userId,
                    'course_id' => $courseId,
                    'assignment_type' => $assignmentType,
                ]);
                return (int) $existing['id'];
            }
            return (int) $existing['id'];
        }
        $enrollmentId = $this->enrollmentRepository->create($tenantId, [
            'course_id' => $courseId,
            'user_id' => $userId,
            'assigned_by' => $assignedBy,
            'assignment_type' => $assignmentType,
            'status' => 'assigned',
            'expires_at' => $expiresAt,
        ]);
        $this->auditService->logEnrollmentAssigned($tenantId, $assignedBy, $enrollmentId, [
            'user_id' => $userId,
            'course_id' => $courseId,
            'assignment_type' => $assignmentType,
        ]);
        return $enrollmentId;
    }

    /** Assignation par rôle : tous les utilisateurs ayant ce rôle. */
    public function assignByRole(int $courseId, int $roleId, int $tenantId, int $assignedBy, ?string $expiresAt = null): int
    {
        $userIds = $this->userRepository->getIdsByRole($tenantId, $roleId);
        $count = 0;
        foreach ($userIds as $uid) {
            $this->assignUser($courseId, $uid, $tenantId, $assignedBy, 'role', $expiresAt);
            $count++;
        }
        return $count;
    }

    /** Assignation par unité : tous les utilisateurs de l'unité. */
    public function assignByUnit(int $courseId, int $unitId, int $tenantId, int $assignedBy, ?string $expiresAt = null): int
    {
        $userIds = $this->userRepository->getIdsByUnit($unitId);
        $count = 0;
        foreach ($userIds as $uid) {
            $this->assignUser($courseId, $uid, $tenantId, $assignedBy, 'unit', $expiresAt);
            $count++;
        }
        return $count;
    }

    public function revokeEnrollment(int $enrollmentId, int $tenantId): void
    {
        $enrollment = $this->enrollmentRepository->findById($enrollmentId, $tenantId);
        if (!$enrollment) {
            throw new \InvalidArgumentException('Enrollment not found.');
        }
        $this->enrollmentRepository->revoke($enrollmentId);
    }

    /** @return list<array<string, mixed>> */
    public function listEnrollmentsForCourse(int $courseId): array
    {
        return $this->enrollmentRepository->listByCourseId($courseId);
    }

    /** Enrollments expirant ou expirés (pour relances). */
    public function listOverdueOrExpiring(int $tenantId, int $daysAhead = 30): array
    {
        return $this->enrollmentRepository->listExpiringOrExpired($tenantId, $daysAhead);
    }
}
