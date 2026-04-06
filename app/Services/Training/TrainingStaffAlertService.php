<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Repositories\TenantCommunityFeedRepository;
use App\Repositories\TrainingCourseRepository;
use App\Repositories\TrainingStaffPingRepository;
use App\Repositories\UserRepository;
use App\Repositories\TenantRepository;
use App\Services\Email\EmailEvents;
use App\Services\EmailService;

/**
 * Alertes e-mail + fil tableau de bord communauté pour le pédagogique (formations).
 */
class TrainingStaffAlertService
{
    private const MODULE_PING_COOLDOWN_SEC = 86400;

    /** Null = une alerte peut être envoyée ; sinon secondes restantes avant le prochain envoi possible. */
    public function secondsBeforeNextModuleNotify(int $enrollmentId, int $moduleId): ?int
    {
        $since = $this->pingRepository->secondsSinceLastPing($enrollmentId, $moduleId, 'module_blocked');
        if ($since === null) {
            return null;
        }
        if ($since >= self::MODULE_PING_COOLDOWN_SEC) {
            return null;
        }

        return self::MODULE_PING_COOLDOWN_SEC - $since;
    }

    public function __construct(
        private TenantCommunityFeedRepository $feedRepository,
        private TrainingStaffPingRepository $pingRepository,
        private EmailService $emailService,
        private UserRepository $userRepository,
        private TenantRepository $tenantRepository,
        private TrainingEnrollmentPolicyService $enrollmentPolicyService,
        private TrainingCourseRepository $courseRepository
    ) {}

    /** @return list<array{email: string, name: string, user_id: int}> */
    public function resolveStaffRecipientsForCourse(array $course, int $tenantId): array
    {
        $policy = $this->enrollmentPolicyService->decodePolicy($course['enrollment_policy_json'] ?? null);
        $ids = [];
        foreach ($this->normalizeApproverIdList($policy['enrollment_approver_user_ids'] ?? []) as $aid) {
            if ($aid > 0) {
                $ids[] = $aid;
            }
        }
        $creatorId = (int) ($course['created_by'] ?? 0);
        if ($creatorId > 0) {
            $ids[] = $creatorId;
        }
        $ids = array_values(array_unique($ids));
        $out = [];
        $seen = [];
        foreach ($ids as $uid) {
            $staff = $this->userRepository->findById((int) $uid, $tenantId);
            if (!$staff) {
                continue;
            }
            $to = trim((string) ($staff['email'] ?? ''));
            if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL) || isset($seen[$to])) {
                continue;
            }
            $seen[$to] = true;
            $name = trim((string) ($staff['display_name'] ?? '')) ?: $to;
            $out[] = ['email' => $to, 'name' => $name, 'user_id' => (int) $uid];
        }

        return $out;
    }

    public function recordEnrollmentPendingApproval(int $tenantId, int $learnerUserId, int $enrollmentId, int $courseId): void
    {
        try {
            $course = $this->courseRepository->findByIdForViewer($courseId, $tenantId);
            if (!$course) {
                return;
            }
            $learner = $this->userRepository->findById($learnerUserId, $tenantId);
            $learnerLabel = $this->displayNameForUser($learner);
            $title = (string) ($course['title'] ?? 'Formation');
            $courseIdSafe = (int) ($course['id'] ?? $courseId);
            $link = \training_lms_admin_url('enrollments') . '?course_id=' . $courseIdSafe;
            $this->feedRepository->insert(
                $tenantId,
                'training_enrollment_pending',
                'Inscription à valider — ' . $title,
                $learnerLabel . ' a demandé à rejoindre cette formation. Ouvrez les assignations pour accepter ou refuser.',
                $link,
                $learnerUserId
            );
        } catch (\Throwable) {
        }
    }

    public function recordCourseCompletedByLearner(int $tenantId, int $learnerUserId, int $enrollmentId, int $courseId): void
    {
        try {
            $course = $this->courseRepository->findByIdForViewer($courseId, $tenantId);
            if (!$course) {
                return;
            }
            $learner = $this->userRepository->findById($learnerUserId, $tenantId);
            $learnerLabel = $this->displayNameForUser($learner);
            $title = (string) ($course['title'] ?? 'Formation');
            $slug = trim((string) ($course['slug'] ?? ''));
            $link = $slug !== '' ? \url('formations/' . rawurlencode($slug)) : \training_lms_admin_url('enrollments');
            $this->feedRepository->insert(
                $tenantId,
                'training_course_completed',
                'Parcours terminé — ' . $title,
                $learnerLabel . ' a validé l’ensemble des exigences de cette formation.',
                $link,
                $learnerUserId
            );
        } catch (\Throwable) {
        }
    }

    /**
     * E-mail + entrée fil pour blocage module. Respecte un délai entre deux envois (24 h).
     *
     * @param list<string> $gapLines résumé lisible des manques
     */
    public function notifyModuleBlockedByLearner(
        int $tenantId,
        int $learnerUserId,
        array $course,
        int $moduleId,
        string $moduleTitle,
        int $enrollmentId,
        array $gapLines
    ): bool {
        $since = $this->pingRepository->secondsSinceLastPing($enrollmentId, $moduleId, 'module_blocked');
        if ($since !== null && $since < self::MODULE_PING_COOLDOWN_SEC) {
            return false;
        }

        try {
            $learner = $this->userRepository->findById($learnerUserId, $tenantId);
            $learnerLabel = $this->displayNameForUser($learner);
            $learnerEmail = $learner ? trim((string) ($learner['email'] ?? '')) : '';
            $tenant = $this->tenantRepository->findById($tenantId);
            $tenantName = 'Communauté';
            if ($tenant) {
                $tenantName = function_exists('community_display_name')
                    ? community_display_name($tenant)
                    : (string) ($tenant['name'] ?? 'Communauté');
            }
            $courseTitle = (string) ($course['title'] ?? 'Formation');
            $courseId = (int) ($course['id'] ?? 0);
            $reviewUrl = \training_lms_admin_url('enrollments') . '?course_id=' . $courseId;
            $bodyLines = $gapLines !== [] ? implode("\n", $gapLines) : 'L’apprenant a signalé des difficultés pour valider ce module.';

            $recipients = $this->resolveStaffRecipientsForCourse($course, $tenantId);
            foreach ($recipients as $r) {
                $this->emailService->sendTrainingModuleBlockedStaff(
                    $r['email'],
                    $r['name'],
                    $learnerLabel,
                    $learnerEmail,
                    $tenantName,
                    $courseTitle,
                    $moduleTitle,
                    $bodyLines,
                    $reviewUrl,
                    $tenantId
                );
            }

            $this->feedRepository->insert(
                $tenantId,
                'training_module_blocked',
                'Module non validé — ' . $courseTitle,
                $learnerLabel . ' a besoin d’aide sur le module « ' . $moduleTitle . ' ». ' . ($gapLines !== [] ? 'Résumé : ' . implode(' · ', array_slice($gapLines, 0, 4)) : ''),
                $reviewUrl,
                $learnerUserId
            );
            $this->pingRepository->log($tenantId, $enrollmentId, $moduleId, 'module_blocked');

            return true;
        } catch (\Throwable) {
            return false;
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

    /** @param array<string, mixed>|null $user */
    private function displayNameForUser(?array $user): string
    {
        if (!$user) {
            return 'Membre';
        }
        $n = trim((string) ($user['display_name'] ?? ''));
        if ($n === '') {
            $n = trim((string) ($user['callsign'] ?? ''));
        }
        if ($n === '') {
            $n = trim((string) ($user['email'] ?? ''));
        }

        return $n !== '' ? $n : 'Membre';
    }
}
