<?php

declare(strict_types=1);

namespace App\Services\Community;

use App\Repositories\ForumNotificationRepository;
use App\Repositories\SiteRoleAssignmentRepository;
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
        private SiteRoleAssignmentRepository $siteRoleAssignmentRepository,
    ) {}

    /**
     * @param ?string $contentKind Valeur stockée côté dossier (ex. training_course) pour affinages d’alerte.
     */
    public function notifyReportCreated(
        int $tenantId,
        int $reportId,
        int $reporterId,
        string $reasonText,
        ?string $contentKind = null,
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

        /** @var array<string, true> */
        $staffEmailsSent = [];

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
                $staffEmailsSent[strtolower($modEmail)] = true;
            } catch (\Throwable) {
            }
        }

        $siteRoleEmails = $this->siteRoleAssignmentRepository->listActiveEmailsByRoleSlugs([
            'site_super_admin',
            'site_senior_moderator',
            'site_moderator',
            'site_report_supervisor',
            'site_report_operator',
            'site_support',
        ]);
        foreach ($siteRoleEmails as $email) {
            if ($reporterEmail !== '' && strcasecmp($reporterEmail, $email) === 0) {
                continue;
            }
            $el = strtolower(trim($email));
            if ($el === '' || isset($staffEmailsSent[$el])) {
                continue;
            }
            $display = $this->displayNameFromEmail($email);
            try {
                $this->emailService->sendCommunityReportNewStaff(
                    $email,
                    $display,
                    $tenantName,
                    $summary,
                    $reportId,
                    $tenantId
                );
                $staffEmailsSent[$el] = true;
            } catch (\Throwable) {
            }
        }

        $kind = strtolower(trim((string) $contentKind));
        $notifyOrgManagement = $kind === 'training_course' || $kind === 'org_anomaly';
        if ($notifyOrgManagement && $tenantId > 0) {
            $orgEmails = $this->userRepository->listAdministratorEmailsForTenant($tenantId);
            if ($kind === 'org_anomaly') {
                $orgEmails = array_merge(
                    $orgEmails,
                    $this->userRepository->listEmailsForTenantAccessDelegation($tenantId)
                );
            }
            foreach ($orgEmails as $email) {
                $el = strtolower(trim($email));
                if ($el === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || isset($staffEmailsSent[$el])) {
                    continue;
                }
                if ($reporterEmail !== '' && strcasecmp($reporterEmail, $email) === 0) {
                    continue;
                }
                $display = $this->displayNameFromEmail($email);
                try {
                    $this->emailService->sendCommunityReportNewStaff(
                        $email,
                        $display,
                        $tenantName,
                        $summary,
                        $reportId,
                        $tenantId
                    );
                    $staffEmailsSent[$el] = true;
                } catch (\Throwable) {
                }
            }
            $contact = $this->tenantCommunityContactEmail($tenantId);
            if ($contact !== null) {
                $el = strtolower($contact);
                if (!isset($staffEmailsSent[$el]) && ($reporterEmail === '' || strcasecmp($reporterEmail, $contact) !== 0)) {
                    try {
                        $this->emailService->sendCommunityReportNewStaff(
                            $contact,
                            $this->displayNameFromEmail($contact),
                            $tenantName,
                            $summary,
                            $reportId,
                            $tenantId
                        );
                        $staffEmailsSent[$el] = true;
                    } catch (\Throwable) {
                    }
                }
            }
        }

        try {
            $this->tenantCommunityFeedRepository->insert(
                $tenantId,
                'moderation_report',
                $kind === 'org_anomaly' ? 'Anomalie transmise à la gestion' : 'Nouveau signalement',
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

    /**
     * Dossier rouvert : alerte modérateurs (e-mail + notification forum) et option signaleur.
     *
     * @param non-empty-string $summaryLine
     */
    public function notifyReportReopened(
        int $tenantId,
        int $reportId,
        int $reopenedByUserId,
        int $reporterId,
        string $summaryLine,
        bool $emailReporter,
        ?string $internalNote = null
    ): void {
        $summary = $this->truncateOneLine($summaryLine, 220);
        if ($summary === '') {
            $summary = 'Dossier rouvert';
        }
        $tenant = $this->tenantRepository->findById($tenantId);
        $tenantName = $tenant ? (string) ($tenant['name'] ?? 'Communauté') : 'Communauté';
        if (function_exists('community_display_name') && $tenant) {
            $tenantName = community_display_name($tenant);
        }

        $modIds = $this->userRepository->listForumAlertRecipientUserIds($tenantId);
        foreach ($modIds as $modUserId) {
            if ($modUserId < 1 || $modUserId === $reopenedByUserId) {
                continue;
            }
            if ($this->forumNotificationRepository->tableExists()) {
                try {
                    $this->forumNotificationRepository->create($tenantId, $modUserId, 'report_reopened', [
                        'report_id' => $reportId,
                        'summary' => $summary,
                    ]);
                } catch (\Throwable) {
                }
            }
            if (!$this->notificationPreferencesRepository->isEmailEventEnabled($modUserId, EmailEvents::COMMUNITY_REPORT_REOPENED_STAFF)) {
                continue;
            }
            $mod = $this->userRepository->findById($modUserId, $tenantId);
            $modEmail = $mod ? trim((string) ($mod['email'] ?? '')) : '';
            if ($modEmail === '' || !filter_var($modEmail, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $modName = $mod ? (string) ($mod['display_name'] ?? 'Modérateur') : 'Modérateur';
            try {
                $this->emailService->sendCommunityReportReopenedStaff(
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

        $siteRoleEmails = $this->siteRoleAssignmentRepository->listActiveEmailsByRoleSlugs([
            'site_super_admin',
            'site_senior_moderator',
            'site_moderator',
            'site_report_supervisor',
            'site_report_operator',
            'site_support',
        ]);
        foreach ($siteRoleEmails as $email) {
            $display = $this->displayNameFromEmail($email);
            try {
                $this->emailService->sendCommunityReportReopenedStaff(
                    $email,
                    $display,
                    $tenantName,
                    $summary,
                    $reportId,
                    $tenantId
                );
            } catch (\Throwable) {
            }
        }

        if ($emailReporter && $reporterId > 0 && $reporterId !== $reopenedByUserId) {
            $reporter = $this->userRepository->findById($reporterId, $tenantId);
            if ($reporter) {
                $reporterEmail = trim((string) ($reporter['email'] ?? ''));
                $reporterName = (string) ($reporter['display_name'] ?? 'Membre');
                if ($reporterEmail !== '' && filter_var($reporterEmail, FILTER_VALIDATE_EMAIL)
                    && $this->notificationPreferencesRepository->isEmailEventEnabled($reporterId, EmailEvents::COMMUNITY_REPORT_REOPENED_REPORTER)) {
                    try {
                        $this->emailService->sendCommunityReportReopenedReporter(
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
                        $this->forumNotificationRepository->create($tenantId, $reporterId, 'report_reopened_reporter', [
                            'report_id' => $reportId,
                            'summary' => $summary,
                        ]);
                    } catch (\Throwable) {
                    }
                }
            }
        }

        if ($internalNote !== null && $internalNote !== '') {
            try {
                $this->tenantCommunityFeedRepository->insert(
                    $tenantId,
                    'moderation_report',
                    'Dossier de signalement rouvert',
                    $this->truncateOneLine($internalNote, 400),
                    \url('back-office/forum-moderation'),
                    $reopenedByUserId > 0 ? $reopenedByUserId : null
                );
            } catch (\Throwable) {
            }
        }
    }

    private function tenantCommunityContactEmail(int $tenantId): ?string
    {
        if ($tenantId < 1) {
            return null;
        }
        $row = $this->tenantRepository->findById($tenantId);
        if ($row === null) {
            return null;
        }
        $raw = $row['settings'] ?? null;
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return null;
        }
        $community = $decoded['community'] ?? null;
        if (!is_array($community)) {
            return null;
        }
        $contact = strtolower(trim((string) ($community['contact_email'] ?? '')));

        return $contact !== '' && filter_var($contact, FILTER_VALIDATE_EMAIL) ? $contact : null;
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

    private function displayNameFromEmail(string $email): string
    {
        $local = trim((string) strstr($email, '@', true));
        if ($local === '') {
            return 'Équipe modération';
        }
        $clean = str_replace(['.', '_', '-'], ' ', $local);
        $clean = preg_replace('/\s+/u', ' ', $clean) ?? $clean;
        $clean = trim($clean);
        if ($clean === '') {
            return 'Équipe modération';
        }
        if (function_exists('mb_convert_case')) {
            return mb_convert_case($clean, MB_CASE_TITLE, 'UTF-8');
        }

        return ucwords($clean);
    }
}
