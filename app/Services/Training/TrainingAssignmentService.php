<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Repositories\TenantRepository;
use App\Repositories\TrainingCourseRepository;
use App\Repositories\TrainingEnrollmentRepository;
use App\Repositories\UserRepository;
use App\Services\EmailService;

class TrainingAssignmentService
{
    public function __construct(
        private TrainingEnrollmentRepository $enrollmentRepository,
        private TrainingCourseRepository $courseRepository,
        private UserRepository $userRepository,
        private TrainingAuditService $auditService,
        private TrainingEnrollmentPolicyService $enrollmentPolicyService,
        private EmailService $emailService,
        private TenantRepository $tenantRepository
    ) {}

    public function assignUser(int $courseId, int $userId, int $tenantId, ?int $assignedBy = null, string $assignmentType = 'manual', ?string $expiresAt = null, ?string $motivationText = null): int
    {
        $course = $this->courseRepository->findById($courseId, $tenantId);
        if (!$course) {
            throw new \InvalidArgumentException('Course not found.');
        }
        if ($assignmentType === 'self_enroll') {
            $eval = $this->enrollmentPolicyService->evaluateSelfEnroll($userId, $tenantId, $course);
            if (!$eval['allowed']) {
                $msg = $eval['messages'][0] ?? 'Inscription refusée.';

                throw new \InvalidArgumentException($msg);
            }
        }
        $motivationStored = null;
        if ($motivationText !== null) {
            $t = trim($motivationText);
            $motivationStored = $t === '' ? null : mb_substr($t, 0, 4000);
        }
        $initialStatus = 'assigned';
        if ($assignmentType === 'self_enroll') {
            $pol = $this->enrollmentPolicyService->decodePolicy($course['enrollment_policy_json'] ?? null);
            if (!empty($pol['self_enroll_requires_approval'])) {
                $initialStatus = 'pending_approval';
            }
        }
        $existing = $this->enrollmentRepository->findByCourseAndUser($courseId, $userId);
        if ($existing) {
            if (in_array($existing['status'], ['revoked', 'expired'], true)) {
                $upd = [
                    'status' => $initialStatus,
                    'expires_at' => $expiresAt,
                ];
                if ($motivationStored !== null) {
                    $upd['motivation_text'] = $motivationStored;
                }
                $this->enrollmentRepository->update($existing['id'], $upd);
                $this->auditService->logEnrollmentAssigned($tenantId, $assignedBy, $existing['id'], [
                    'user_id' => $userId,
                    'course_id' => $courseId,
                    'assignment_type' => $assignmentType,
                    'motivation_provided' => $motivationStored !== null,
                    'status' => $initialStatus,
                ]);
                if ($initialStatus === 'pending_approval' && $assignmentType === 'self_enroll') {
                    $this->notifyEnrollmentPendingApproval($tenantId, $userId, $course, $existing['id']);
                } else {
                    $this->notifyEnrollmentAssigned($tenantId, $userId, $course, $assignmentType);
                }

                return (int) $existing['id'];
            }

            return (int) $existing['id'];
        }
        $enrollmentId = $this->enrollmentRepository->create($tenantId, [
            'course_id' => $courseId,
            'user_id' => $userId,
            'assigned_by' => $assignedBy,
            'assignment_type' => $assignmentType,
            'status' => $initialStatus,
            'expires_at' => $expiresAt,
            'motivation_text' => $motivationStored,
        ]);
        $this->auditService->logEnrollmentAssigned($tenantId, $assignedBy, $enrollmentId, [
            'user_id' => $userId,
            'course_id' => $courseId,
            'assignment_type' => $assignmentType,
            'motivation_provided' => $motivationStored !== null,
            'status' => $initialStatus,
        ]);
        if ($initialStatus === 'pending_approval' && $assignmentType === 'self_enroll') {
            $this->notifyEnrollmentPendingApproval($tenantId, $userId, $course, $enrollmentId);
        } else {
            $this->notifyEnrollmentAssigned($tenantId, $userId, $course, $assignmentType);
        }

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

    /** E-mail à l’apprenant (sauf auto-inscription). Les erreurs d’envoi n’empêchent pas l’assignation. */
    private function notifyEnrollmentAssigned(int $tenantId, int $userId, array $course, string $assignmentType): void
    {
        if ($assignmentType === 'self_enroll') {
            return;
        }
        try {
            $user = $this->userRepository->findById($userId, $tenantId);
            if (!$user) {
                return;
            }
            $email = trim((string) ($user['email'] ?? ''));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return;
            }
            $tenant = $this->tenantRepository->findById($tenantId);
            $tenantName = 'Communauté';
            if ($tenant) {
                $tenantName = function_exists('community_display_name')
                    ? community_display_name($tenant)
                    : (string) ($tenant['name'] ?? 'Communauté');
            }
            $display = trim((string) ($user['display_name'] ?? ''));
            if ($display === '') {
                $display = trim((string) ($user['callsign'] ?? ''));
            }
            if ($display === '') {
                $display = $email;
            }
            $slug = trim((string) ($course['slug'] ?? ''));
            $courseUrl = $slug !== '' ? \url('formations/' . rawurlencode($slug)) : \url('formations/mes-formations');
            $this->emailService->sendTrainingEnrollmentAssigned(
                $email,
                $display,
                $tenantName,
                (string) ($course['title'] ?? 'Formation'),
                $courseUrl,
                $tenantId
            );
        } catch (\Throwable) {
            // ne pas bloquer l’assignation
        }
    }

    /** Demande d’auto-inscription en attente : e-mail aux formateurs référents. */
    private function notifyEnrollmentPendingApproval(int $tenantId, int $learnerUserId, array $course, int $enrollmentId): void
    {
        try {
            $policy = $this->enrollmentPolicyService->decodePolicy($course['enrollment_policy_json'] ?? null);
            $approverIds = [];
            foreach ($this->normalizeApproverIdList($policy['enrollment_approver_user_ids'] ?? []) as $aid) {
                if ($aid > 0) {
                    $approverIds[] = $aid;
                }
            }
            $creatorId = (int) ($course['created_by'] ?? 0);
            if ($creatorId > 0) {
                $approverIds[] = $creatorId;
            }
            $approverIds = array_values(array_unique($approverIds));
            $learner = $this->userRepository->findById($learnerUserId, $tenantId);
            if (!$learner) {
                return;
            }
            $learnerName = trim((string) ($learner['display_name'] ?? ''));
            if ($learnerName === '') {
                $learnerName = trim((string) ($learner['callsign'] ?? ''));
            }
            if ($learnerName === '') {
                $learnerName = (string) ($learner['email'] ?? 'Apprenant');
            }
            $learnerEmail = trim((string) ($learner['email'] ?? ''));
            $tenant = $this->tenantRepository->findById($tenantId);
            $tenantName = 'Communauté';
            if ($tenant) {
                $tenantName = function_exists('community_display_name')
                    ? community_display_name($tenant)
                    : (string) ($tenant['name'] ?? 'Communauté');
            }
            $courseTitle = (string) ($course['title'] ?? 'Formation');
            $courseId = (int) ($course['id'] ?? 0);
            $reviewUrl = \url('admin/training/enrollments?course_id=' . $courseId);
            $sent = [];
            foreach ($approverIds as $uid) {
                $staff = $this->userRepository->findById((int) $uid, $tenantId);
                if (!$staff) {
                    continue;
                }
                $to = trim((string) ($staff['email'] ?? ''));
                if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL) || isset($sent[$to])) {
                    continue;
                }
                $sent[$to] = true;
                $staffName = trim((string) ($staff['display_name'] ?? '')) ?: $to;
                $this->emailService->sendTrainingEnrollmentPendingApproval(
                    $to,
                    $staffName,
                    $learnerName,
                    $learnerEmail,
                    $tenantName,
                    $courseTitle,
                    $reviewUrl,
                    $enrollmentId,
                    $tenantId
                );
            }
        } catch (\Throwable) {
        }
    }

    /** @param mixed $raw @return list<int> */
    private function normalizeApproverIdList(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $v) {
            $i = (int) $v;
            if ($i > 0) {
                $out[] = $i;
            }
        }

        return array_values(array_unique($out));
    }
}
