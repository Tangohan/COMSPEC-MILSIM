<?php

declare(strict_types=1);

namespace App\Services\Portal;

use App\Core\Gate;
use App\Repositories\EnlistmentRepository;
use App\Repositories\ForumReportRepository;
use App\Repositories\ModerationArtifactRepository;
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
    ) {}

    /**
     * @return array{
     *   recruitments_submitted: int,
     *   forum_moderation_total: int,
     *   personal_inbox: int,
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

        $personalInbox = 0;
        if ($tenantId > 0 && $userId > 0) {
            try {
                $personalInbox = $this->personalMessageUnreadCounter->countsForUser($tenantId, $userId, $gate)['total'];
                $myPending = $this->enlistmentRepository->listPendingSubmittedForSubmitter($tenantId, $userId, $userEmail);
                $personalInbox += count($myPending);
            } catch (\Throwable) {
                $personalInbox = 0;
            }
        }

        return [
            'recruitments_submitted' => max(0, $recruitmentsSubmitted),
            'forum_moderation_total' => max(0, $forumModerationTotal),
            'personal_inbox' => max(0, $personalInbox),
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
