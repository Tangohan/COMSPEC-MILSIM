<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Repositories\TenantCommunityFeedRepository;
use App\Repositories\TrainingCourseRepository;
use App\Repositories\TrainingStaffPingRepository;
use App\Repositories\UserNotificationPreferencesRepository;
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

    private const PUBLISH_ELEVATION_COOLDOWN_SEC = 86400;

    private const PUBLISH_ELEVATION_PING_KIND = 'publish_elevation';

    /**
     * Droits communauté permettant de publier ou d’attribuer le droit (alignés Gate / implications).
     * Pas de admin.system (permission site) — les destinataires restent bornés au tenant courant.
     *
     * @var list<string>
     */
    private const PUBLISH_ELEVATION_PERMISSION_SLUGS = [
        'admin.access',
        'admin.organization',
        'training.manage',
        'training.publish',
    ];

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

    /** Null = une demande peut être envoyée ; sinon secondes restantes (anti-doublon fiche / demandeur). */
    public function secondsBeforeNextPublishElevationRequest(int $courseId, int $requesterUserId): ?int
    {
        if ($courseId < 1 || $requesterUserId < 1) {
            return null;
        }
        $since = $this->pingRepository->secondsSinceLastPing(
            $courseId,
            $requesterUserId,
            self::PUBLISH_ELEVATION_PING_KIND
        );
        if ($since === null) {
            return null;
        }
        if ($since >= self::PUBLISH_ELEVATION_COOLDOWN_SEC) {
            return null;
        }

        return self::PUBLISH_ELEVATION_COOLDOWN_SEC - $since;
    }

    /**
     * Personnes pouvant publier ou attribuer le droit de publication (hors demandeur).
     * Strictement limitées au `$tenantId` (fil + e-mails) — pas de liste globale.
     *
     * @return list<array{user_id: int, name: string, email: string}>
     */
    public function listPublishElevationRecipients(int $tenantId, ?int $excludeUserId = null): array
    {
        if ($tenantId < 1) {
            return [];
        }
        $ids = $this->userRepository->listActiveUserIdsWithAnyPermissionSlug(
            $tenantId,
            self::PUBLISH_ELEVATION_PERMISSION_SLUGS
        );
        if ($excludeUserId !== null && $excludeUserId > 0) {
            $ids = array_values(array_filter($ids, static fn (int $id): bool => $id !== $excludeUserId));
        }
        if ($ids === []) {
            return [];
        }
        $users = $this->userRepository->findByIdsForTenant($tenantId, $ids);
        $out = [];
        $seenEmails = [];
        foreach ($ids as $uid) {
            $user = $users[$uid] ?? null;
            if (!$user || (int) ($user['tenant_id'] ?? 0) !== $tenantId) {
                continue;
            }
            $email = strtolower(trim((string) ($user['email'] ?? '')));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || isset($seenEmails[$email])) {
                continue;
            }
            $seenEmails[$email] = true;
            $out[] = [
                'user_id' => $uid,
                'name' => $this->displayNameForUser($user),
                'email' => $email,
            ];
        }

        return $out;
    }

    /**
     * Alerte fil + e-mails aux personnes habilitées à publier / accorder le droit.
     *
     * @param array<string, mixed> $course
     * @return array{ok: bool, message: string, recipient_names: list<string>}
     */
    public function requestPublishElevation(int $tenantId, int $requesterUserId, array $course): array
    {
        $courseId = (int) ($course['id'] ?? 0);
        $courseTitle = trim((string) ($course['title'] ?? 'Formation'));
        if ($courseTitle === '') {
            $courseTitle = 'Formation';
        }

        if ($tenantId < 1 || $requesterUserId < 1 || $courseId < 1) {
            return [
                'ok' => false,
                'message' => 'Impossible d’envoyer la demande pour le moment.',
                'recipient_names' => [],
            ];
        }

        $wait = $this->secondsBeforeNextPublishElevationRequest($courseId, $requesterUserId);
        if ($wait !== null) {
            $hours = max(1, (int) ceil($wait / 3600));

            return [
                'ok' => false,
                'message' => 'Une demande est déjà en cours pour cette fiche. Vous pourrez renvoyer un rappel dans environ '
                    . $hours . ' heure' . ($hours > 1 ? 's' : '') . '.',
                'recipient_names' => [],
            ];
        }

        $recipients = $this->listPublishElevationRecipients($tenantId, $requesterUserId);
        if ($recipients === []) {
            return [
                'ok' => false,
                'message' => 'Aucune personne habilitée à publier n’est joignable dans cette communauté. Contactez un administrateur autrement.',
                'recipient_names' => [],
            ];
        }

        $requester = $this->userRepository->findById($requesterUserId, $tenantId);
        $requesterName = $this->displayNameForUser($requester);
        $requesterEmail = $requester ? strtolower(trim((string) ($requester['email'] ?? ''))) : '';
        $tenant = $this->tenantRepository->findById($tenantId);
        $tenantName = 'Communauté';
        if ($tenant) {
            $tenantName = function_exists('community_display_name')
                ? community_display_name($tenant)
                : (string) ($tenant['name'] ?? 'Communauté');
        }

        $studioFicheUrl = \training_studio_url($courseId . '/fiche');
        $requesterMemberUrl = \url('back-office/users/' . $requesterUserId . '/edit');
        $names = [];
        $sent = 0;

        try {
            foreach ($recipients as $r) {
                $names[] = $r['name'];
                $sid = (int) ($r['user_id'] ?? 0);
                if ($sid < 1 || !$this->notificationPreferencesRepository->isEmailEventEnabled($sid, EmailEvents::TRAINING_PUBLISH_ELEVATION_REQUEST)) {
                    continue;
                }
                if ($this->emailService->sendTrainingPublishElevationRequest(
                    $r['email'],
                    $r['name'],
                    $requesterName,
                    $requesterEmail,
                    $tenantName,
                    $courseTitle,
                    $studioFicheUrl,
                    $requesterMemberUrl,
                    $tenantId
                )) {
                    $sent++;
                }
            }

            $nameList = implode(', ', array_slice($names, 0, 8));
            if (count($names) > 8) {
                $nameList .= '…';
            }
            $this->feedRepository->insert(
                $tenantId,
                'training_publish_elevation',
                'Publication demandée — ' . $courseTitle,
                $requesterName . ' demande le droit de publier cette formation. Personnes prévenues : ' . $nameList . '.',
                $studioFicheUrl,
                $requesterUserId
            );
            $this->pingRepository->log($tenantId, $courseId, $requesterUserId, self::PUBLISH_ELEVATION_PING_KIND);

            if ($sent === 0) {
                return [
                    'ok' => true,
                    'message' => 'L’alerte a été déposée sur le tableau de bord. Aucun e-mail n’a pu être envoyé (préférences ou problème d’envoi) — les personnes listées restent prévenues via le fil communauté.',
                    'recipient_names' => $names,
                ];
            }

            return [
                'ok' => true,
                'message' => 'Demande envoyée à ' . count($names) . ' personne'
                    . (count($names) > 1 ? 's' : '')
                    . ' : ' . implode(', ', $names) . '.',
                'recipient_names' => $names,
            ];
        } catch (\Throwable) {
            return [
                'ok' => false,
                'message' => 'L’envoi de la demande a échoué. Réessayez plus tard ou contactez un administrateur.',
                'recipient_names' => [],
            ];
        }
    }

    public function __construct(
        private TenantCommunityFeedRepository $feedRepository,
        private TrainingStaffPingRepository $pingRepository,
        private EmailService $emailService,
        private UserRepository $userRepository,
        private TenantRepository $tenantRepository,
        private TrainingEnrollmentPolicyService $enrollmentPolicyService,
        private TrainingCourseRepository $courseRepository,
        private UserNotificationPreferencesRepository $notificationPreferencesRepository
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
            $courseIdSafe = (int) ($course['id'] ?? $courseId);
            $link = \training_lms_admin_url('enrollments') . '?course_id=' . $courseIdSafe;
            $this->feedRepository->insert(
                $tenantId,
                'training_course_completed',
                'Parcours terminé — ' . $title,
                $learnerLabel . ' a validé l’ensemble des exigences de cette formation.',
                $link,
                $learnerUserId,
                $enrollmentId
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
                $sid = (int) ($r['user_id'] ?? 0);
                if ($sid < 1 || !$this->notificationPreferencesRepository->isEmailEventEnabled($sid, EmailEvents::TRAINING_MODULE_BLOCKED_STAFF)) {
                    continue;
                }
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
