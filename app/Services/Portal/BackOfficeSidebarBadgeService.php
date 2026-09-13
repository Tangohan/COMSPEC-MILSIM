<?php

declare(strict_types=1);

namespace App\Services\Portal;

use App\Core\Gate;
use App\Repositories\CommunityEventRepository;
use App\Repositories\EnlistmentRepository;
use App\Repositories\ForumReportRepository;
use App\Repositories\ModerationArtifactRepository;
use App\Repositories\PersonnelQualificationRepository;
use App\Repositories\UserRepository;
use App\Services\Notifications\PersonalMessageUnreadCounter;

/**
 * Compteurs pour pastilles de la barre latérale back-office communauté.
 */
final class BackOfficeSidebarBadgeService
{
    public function __construct(
        private EnlistmentRepository $enlistmentRepository,
        private ForumReportRepository $forumReportRepository,
        private ModerationArtifactRepository $moderationArtifactRepository,
        private UserRepository $userRepository,
        private PersonalMessageUnreadCounter $personalMessageUnreadCounter,
        private ?CommunityEventRepository $communityEventRepository = null,
        private ?PersonnelQualificationRepository $qualificationRepository = null,
    ) {
        $this->communityEventRepository ??= new CommunityEventRepository();
        $this->qualificationRepository ??= new PersonnelQualificationRepository();
    }

    /**
     * @return array{
     *   recruitments_submitted: int,
     *   forum_moderation_total: int,
     *   personal_inbox: int,
     *   messages_unread: int,
     *   events_rsvp_pending: int,
     *   qualifications_expiring: int,
     *   my_enlistments_pending: int,
     *   show_staff_recruitment: bool
     * }
     */
    public function build(int $tenantId, int $userId, string $userEmail, Gate $gate): array
    {
        $showStaffRecruitment = $this->resolveShowStaffRecruitment($userId, $gate);

        $recruitmentsSubmitted = 0;
        if ($showStaffRecruitment && $tenantId > 0) {
            try {
                $by = $this->enlistmentRepository->countsByStatusForTenant($tenantId);
                $recruitmentsSubmitted = (int) ($by['submitted'] ?? 0);
            } catch (\Throwable) {
                $recruitmentsSubmitted = 0;
            }
        }

        $forumModerationTotal = 0;
        if ($tenantId > 0 && function_exists('forum_user_can_moderate') && forum_user_can_moderate()) {
            try {
                $forumModerationTotal = $this->forumReportRepository->countPending($tenantId);
                if ($this->moderationArtifactRepository->tableExists()) {
                    $forumModerationTotal += $this->moderationArtifactRepository->countQueue($tenantId, null);
                }
            } catch (\Throwable) {
                $forumModerationTotal = 0;
            }
        }

        $messagesUnread = 0;
        $myEnlistmentsPending = 0;
        $eventsRsvpPending = 0;
        $qualificationsExpiring = 0;
        if ($tenantId > 0 && $userId > 0) {
            try {
                $messagesUnread = (int) ($this->personalMessageUnreadCounter
                    ->countsForUser($tenantId, $userId, $gate)['total'] ?? 0);
            } catch (\Throwable) {
                $messagesUnread = 0;
            }
            try {
                $myPending = $this->enlistmentRepository->listPendingSubmittedForSubmitter($tenantId, $userId, $userEmail);
                $myEnlistmentsPending = count($myPending);
            } catch (\Throwable) {
                $myEnlistmentsPending = 0;
            }
            try {
                $events = $this->communityEventRepository->upcomingForTenantWithUserRsvp($tenantId, $userId, 20);
                $horizon = strtotime('+14 days');
                foreach ($events as $event) {
                    if (!is_array($event)) {
                        continue;
                    }
                    $startsAt = trim((string) ($event['starts_at'] ?? ''));
                    $startsTs = $startsAt !== '' ? strtotime($startsAt) : false;
                    if ($startsTs === false || ($horizon !== false && $startsTs > $horizon)) {
                        continue;
                    }
                    if (trim((string) ($event['rsvp_status'] ?? '')) === '') {
                        $eventsRsvpPending++;
                    }
                }
            } catch (\Throwable) {
                $eventsRsvpPending = 0;
            }
            try {
                $expiration = $this->qualificationRepository->getNextExpiration($userId);
                $expirationTs = $expiration !== null ? strtotime($expiration) : false;
                if ($expirationTs !== false && $expirationTs <= strtotime('+30 days')) {
                    $qualificationsExpiring = 1;
                }
            } catch (\Throwable) {
                $qualificationsExpiring = 0;
            }
        }

        // Compat : personal_inbox = messages + dossiers perso à compléter.
        $personalInbox = max(0, $messagesUnread) + max(0, $myEnlistmentsPending);

        return [
            'recruitments_submitted' => max(0, $recruitmentsSubmitted),
            'forum_moderation_total' => max(0, $forumModerationTotal),
            'personal_inbox' => $personalInbox,
            'messages_unread' => max(0, $messagesUnread),
            'events_rsvp_pending' => max(0, $eventsRsvpPending),
            'qualifications_expiring' => max(0, $qualificationsExpiring),
            'my_enlistments_pending' => max(0, $myEnlistmentsPending),
            'show_staff_recruitment' => $showStaffRecruitment,
        ];
    }

    private function resolveShowStaffRecruitment(int $userId, Gate $gate): bool
    {
        if ($userId < 1) {
            return false;
        }
        if ($gate->allows('admin.organization') || $gate->allows('admin.access')) {
            return true;
        }
        $roleSlug = $this->userRepository->getRoleSlugForUser($userId) ?? '';

        return in_array($roleSlug, ['recruiter', 'community_owner', 'hr', 'tenant_admin'], true);
    }
}
