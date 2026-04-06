<?php

declare(strict_types=1);

namespace App\Services\Community;

use App\Repositories\ForumNotificationRepository;
use App\Repositories\TenantCommunityFeedRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserNotificationPreferencesRepository;
use App\Repositories\UserRepository;
use App\Services\Email\EmailEvents;
use App\Services\EmailService;

/**
 * E-mails et notifications in-app autour des signalements (création, clôture).
 */
final class CommunityReportNotificationService
{
    public function __construct(
        private EmailService $emailService,
        private UserRepository $userRepository,
        private UserNotificationPreferencesRepository $notificationPreferencesRepository,
        private ForumNotificationRepository $forumNotificationRepository,
        private TenantCommunityFeedRepository $tenantCommunityFeedRepository,
        private TenantRepository $tenantRepository,
    ) {}

    public function notifyReportCreated(
        int $tenantId,
        int $reportId,
        int $reporterId,
        string $reasonText
    ): void {
        $summary = $this->truncateOneLine($reasonText, 220);
        if ($summary === '') {
            $summary = 'Nouveau signalement';
        }
        $tenant = $this->tenantRepository->findById($tenantId);
        $tenantName = $tenant ? (string) ($tenant['name'] ?? 'Communauté') : 'Communauté';
        if (function_exists('community_display_name') && $tenant) {
            $tenantName = community_display_name($tenant);
        }

        $reporter = $reporterId > 0 ? $this->userRepository->findById($reporterId, $tenantId) : null;
        $reporterEmail = $reporter ? trim((string) ($reporter['email'] ?? '')) : '';
        $reporterName = $reporter ? (string) ($reporter['display_name'] ?? 'Membre') : 'Membre';
        if ($reporterEmail !== '' && filter_var($reporterEmail, FILTER_VALIDATE_EMAIL)
            && $this->notificationPreferencesRepository->isEmailEventEnabled($reporterId, EmailEvents::COMMUNITY_REPORT_RECEIPT)) {
            try {
                $this->emailService->sendCommunityReportReceipt(
                    $reporterEmail,
                    $reporterName,
                    $tenantName,
                    $reportId,
                    $tenantId
                );
            } catch (\Throwable) {
            }
        }

        $modIds = $this->userRepository->listForumAlertRecipientUserIds($tenantId);
        foreach ($modIds as $modUserId) {
            if ($modUserId < 1 || $modUserId === $reporterId) {
                continue;
            }
            if ($this->forumNotificationRepository->tableExists()) {
                try {
                    $this->forumNotificationRepository->create($tenantId, $modUserId, 'report_opened', [
                        'report_id' => $reportId,
                        'summary' => $summary,
                    ]);
                } catch (\Throwable) {
                }
            }
            if (!$this->notificationPreferencesRepository->isEmailEventEnabled($modUserId, EmailEvents::COMMUNITY_REPORT_NEW_STAFF)) {
                continue;
            }
            $mod = $this->userRepository->findById($modUserId, $tenantId);
            $modEmail = $mod ? trim((string) ($mod['email'] ?? '')) : '';
            if ($modEmail === '' || !filter_var($modEmail, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $modName = $mod ? (string) ($mod['display_name'] ?? 'Modérateur') : 'Modérateur';
            try {
                $this->emailService->sendCommunityReportNewStaff(
                    $modEmail,
                    $modName,
                    $tenantName,
                    $summary,
                    $reportId,
                    $tenantId
                );
            } catch (\Throwable) {
            }
        }

        try {
            $this->tenantCommunityFeedRepository->insert(
                $tenantId,
                'moderation_report',
                'Nouveau signalement',
                $summary,
                \url('back-office/forum-moderation'),
                $reporterId > 0 ? $reporterId : null
            );
        } catch (\Throwable) {
        }
    }

    public function notifyReportHandled(
        int $tenantId,
        int $reportId,
        int $reporterId,
        int $handledByUserId
    ): void {
        if ($reporterId < 1 || $reporterId === $handledByUserId) {
            return;
        }
        $tenant = $this->tenantRepository->findById($tenantId);
        $tenantName = $tenant ? (string) ($tenant['name'] ?? 'Communauté') : 'Communauté';
        if (function_exists('community_display_name') && $tenant) {
            $tenantName = community_display_name($tenant);
        }
        $reporter = $this->userRepository->findById($reporterId, $tenantId);
        if (!$reporter) {
            return;
        }
        $reporterEmail = trim((string) ($reporter['email'] ?? ''));
        $reporterName = (string) ($reporter['display_name'] ?? 'Membre');

        if ($reporterEmail !== '' && filter_var($reporterEmail, FILTER_VALIDATE_EMAIL)
            && $this->notificationPreferencesRepository->isEmailEventEnabled($reporterId, EmailEvents::COMMUNITY_REPORT_HANDLED)) {
            try {
                $this->emailService->sendCommunityReportHandled(
                    $reporterEmail,
                    $reporterName,
                    $tenantName,
                    $reportId,
                    $tenantId
                );
            } catch (\Throwable) {
            }
        }

        if ($this->forumNotificationRepository->tableExists()) {
            try {
                $this->forumNotificationRepository->create($tenantId, $reporterId, 'report_closed', [
                    'report_id' => $reportId,
                ]);
            } catch (\Throwable) {
            }
        }
    }

    private function truncateOneLine(string $text, int $max): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        if ($text === '') {
            return '';
        }
        if (strlen($text) <= $max) {
            return $text;
        }

        return substr($text, 0, $max - 1) . '…';
    }
}
