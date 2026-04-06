<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Repositories\TenantRepository;
use App\Repositories\TrainingCourseRepository;
use App\Repositories\TrainingEnrollmentRepository;
use App\Repositories\UserNotificationPreferencesRepository;
use App\Services\Email\EmailEvents;
use App\Services\EmailService;

/**
 * Lorsqu’un créneau (session) est ajouté sur une formation, informe les apprenants encore engagés sur le parcours.
 */
final class TrainingCourseSessionNotificationService
{
    public function __construct(
        private EmailService $emailService,
        private TrainingEnrollmentRepository $enrollmentRepository,
        private TrainingCourseRepository $courseRepository,
        private TenantRepository $tenantRepository,
        private UserNotificationPreferencesRepository $notificationPreferencesRepository,
    ) {}

    /**
     * @param array{starts_at: string, ends_at: string, label?: string|null, location?: string|null} $sessionData
     */
    public function notifyEnrolledLearnersOfNewSession(int $tenantId, int $courseId, int $actorUserId, array $sessionData): void
    {
        if ($tenantId < 1 || $courseId < 1) {
            return;
        }
        $course = $this->courseRepository->findById($courseId, $tenantId);
        if (!$course) {
            return;
        }
        $learners = $this->enrollmentRepository->listIncompleteLearnersForCourseSessionNotify($tenantId, $courseId);
        if ($learners === []) {
            return;
        }

        $tenant = $this->tenantRepository->findById($tenantId);
        $tenantName = 'Communauté';
        if ($tenant) {
            $tenantName = function_exists('community_display_name')
                ? community_display_name($tenant)
                : (string) ($tenant['name'] ?? 'Communauté');
        }

        $courseTitle = (string) ($course['title'] ?? 'Formation');
        $slug = trim((string) ($course['slug'] ?? ''));
        $courseUrl = $slug !== '' ? \url('formations/' . rawurlencode($slug)) : \url('formations/mes-formations');

        $startsHuman = $this->formatSessionInstant((string) ($sessionData['starts_at'] ?? ''));
        $endsHuman = $this->formatSessionInstant((string) ($sessionData['ends_at'] ?? ''));
        $periodLine = $startsHuman !== '' && $endsHuman !== ''
            ? 'Du ' . $startsHuman . ' au ' . $endsHuman
            : '';

        $label = trim((string) ($sessionData['label'] ?? ''));
        $location = trim((string) ($sessionData['location'] ?? ''));

        $sentEmails = [];
        foreach ($learners as $row) {
            $uid = (int) ($row['user_id'] ?? 0);
            if ($uid < 1 || $uid === $actorUserId) {
                continue;
            }
            if (!$this->notificationPreferencesRepository->isEmailEventEnabled($uid, EmailEvents::TRAINING_COURSE_SESSION_SCHEDULED_LEARNER)) {
                continue;
            }
            $email = trim((string) ($row['email'] ?? ''));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || isset($sentEmails[$email])) {
                continue;
            }
            $sentEmails[$email] = true;

            $display = trim((string) ($row['display_name'] ?? ''));
            if ($display === '') {
                $display = trim((string) ($row['callsign'] ?? ''));
            }
            if ($display === '') {
                $display = $email;
            }

            try {
                $this->emailService->sendTrainingCourseSessionScheduledLearner(
                    $email,
                    $display,
                    $tenantName,
                    $courseTitle,
                    $courseUrl,
                    $periodLine,
                    $label !== '' ? $label : null,
                    $location !== '' ? $location : null,
                    $tenantId
                );
            } catch (\Throwable) {
                // ne pas bloquer l’enregistrement du créneau
            }
        }
    }

    private function formatSessionInstant(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        $t = strtotime($raw);
        if ($t === false) {
            return $raw;
        }

        return date('d/m/Y \à H\hi', $t);
    }
}
