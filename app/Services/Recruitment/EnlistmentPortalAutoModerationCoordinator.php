<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

use App\Repositories\BlockedIndicatorRepository;
use App\Repositories\EnlistmentTimelineRepository;
use App\Repositories\PlatformSettingsRepository;
use App\Repositories\UserRepository;
use App\Services\EmailService;

/**
 * Blocages (e-mail / IP côté communauté) + diffusion d’alertes lorsqu’un contenu du portail recrutement est refusé.
 */
final class EnlistmentPortalAutoModerationCoordinator
{
    private const BLOCK_TTL_DAYS = 365;

    /** Clé `platform_settings` : envoi des courriels d’alerte lors d’un refus automatique sur le portail recrutement. */
    public const SETTING_AUTOMOD_ALERT_EMAILS_ENABLED = 'enlistment_portal_automod_alert_emails_enabled';

    public function __construct(
        private EnlistmentPortalTextModerationScanner $textModerationScanner,
        private BlockedIndicatorRepository $blockedIndicatorRepository,
        private UserRepository $userRepository,
        private EmailService $emailService,
        private EnlistmentTimelineRepository $enlistmentTimelineRepository,
        private PlatformSettingsRepository $platformSettingsRepository,
    ) {}

    public function isPortalEmailBlockedForTenant(int $tenantId, string $email): bool
    {
        $e = strtolower(trim($email));

        return $e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)
            && $this->blockedIndicatorRepository->isEmailBlockedForTenant($tenantId, $e);
    }

    /**
     * @return array{code: string, public_label: string}|null
     */
    public function scan(string $text): ?array
    {
        return $this->textModerationScanner->scan($text);
    }

    public function isPortalAccessBlocked(int $tenantId, string $email, string $ip): bool
    {
        if ($tenantId < 1) {
            return false;
        }
        if ($this->blockedIndicatorRepository->isIpBlockedForContext($tenantId, $ip)) {
            return true;
        }
        $e = strtolower(trim($email));

        return $e !== '' && $this->blockedIndicatorRepository->isEmailBlockedForTenant($tenantId, $e);
    }

    /**
     * Candidat (invité) : blocage IP + e-mail dossier sur la communauté.
     *
     * @param array<string, mixed> $enlistment
     * @param array{code: string, public_label: string} $hit
     */
    public function enforceAfterCandidateViolation(
        int $tenantId,
        string $tenantName,
        array $enlistment,
        string $clientIp,
        array $hit,
        string $textPreviewForLog,
    ): void {
        $this->maybeBlockIpTenant($tenantId, $clientIp, 'Portail recrutement — contenu refusé (candidat)');
        $email = strtolower(trim((string) ($enlistment['email'] ?? '')));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->maybeBlockEmailTenant($tenantId, $email, 'Portail recrutement — contenu refusé (candidat)');
        }
        $this->broadcastAlerts($tenantId, $tenantName, $enlistment, 'candidat', $hit, $textPreviewForLog, null);
        $this->appendModerationTimeline(
            $tenantId,
            $enlistment,
            'candidat',
            $hit,
            null
        );
    }

    /**
     * Membre recrutement : blocage e-mail du compte (pas d’IP pour limiter les effets de bord sur le réseau du staff).
     *
     * @param array<string, mixed> $enlistment
     * @param array{code: string, public_label: string} $hit
     */
    public function enforceAfterStaffViolation(
        int $tenantId,
        string $tenantName,
        array $enlistment,
        int $staffUserId,
        array $hit,
        string $textPreviewForLog,
    ): void {
        $staffEmail = '';
        if ($staffUserId > 0) {
            $u = $this->userRepository->findById($staffUserId, $tenantId);
            if (is_array($u)) {
                $staffEmail = strtolower(trim((string) ($u['email'] ?? '')));
            }
        }
        if ($staffEmail !== '' && filter_var($staffEmail, FILTER_VALIDATE_EMAIL)) {
            $this->maybeBlockEmailTenant($tenantId, $staffEmail, 'Portail recrutement — contenu refusé (équipe)');
        }
        $this->broadcastAlerts($tenantId, $tenantName, $enlistment, 'equipe', $hit, $textPreviewForLog, $staffEmail !== '' ? $staffEmail : null);
        $this->appendModerationTimeline(
            $tenantId,
            $enlistment,
            'equipe',
            $hit,
            $staffUserId > 0 ? $staffUserId : null
        );
    }

    /**
     * @param array<string, mixed> $enlistment
     * @param array{code: string, public_label: string} $hit
     */
    private function appendModerationTimeline(
        int $tenantId,
        array $enlistment,
        string $side,
        array $hit,
        ?int $actorUserId,
    ): void {
        $eid = (int) ($enlistment['id'] ?? 0);
        if ($eid < 1 || !$this->enlistmentTimelineRepository->tableExists()) {
            return;
        }
        $label = trim((string) ($hit['public_label'] ?? ''));
        if ($label === '') {
            $label = 'contenu refusé';
        }
        $who = $side === 'equipe' ? 'un membre de l’équipe' : 'le candidat';
        $body = 'Filtre automatique du portail : « ' . $label . ' ». Origine : ' . $who . '.'
            . ' Des courriels d’alerte ont été envoyés au candidat, à l’équipe recrutement et aux contacts de pilotage de la communauté.'
            . ' Les blocages automatiques (e-mail / réseau) peuvent être levés dans le back-office : Blocages portail & sécurité.';
        $this->enlistmentTimelineRepository->append(
            $tenantId,
            $eid,
            'system',
            'instruction',
            'Modération automatique du portail',
            $body,
            $actorUserId,
            [
                'timeline_family' => 'moderation',
                'moderation_side' => $side,
                'moderation_code' => (string) ($hit['code'] ?? ''),
            ],
            null
        );
    }

    private function maybeBlockIpTenant(int $tenantId, string $ip, string $reason): void
    {
        $ip = trim($ip);
        if ($ip === '' || $tenantId < 1) {
            return;
        }
        if ($this->blockedIndicatorRepository->isIpBlocked($tenantId, $ip)) {
            return;
        }
        $expires = (new \DateTimeImmutable('+' . self::BLOCK_TTL_DAYS . ' days'));
        $this->blockedIndicatorRepository->add(
            'ip',
            BlockedIndicatorRepository::hashIp($ip),
            'tenant',
            $tenantId,
            $reason,
            $expires,
            null,
            null
        );
    }

    private function maybeBlockEmailTenant(int $tenantId, string $email, string $reason): void
    {
        $email = strtolower(trim($email));
        if ($email === '' || $tenantId < 1 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        $h = BlockedIndicatorRepository::hashEmail($email);
        if ($this->blockedIndicatorRepository->hasActiveEmailTenant($tenantId, $h)) {
            return;
        }
        $expires = (new \DateTimeImmutable('+' . self::BLOCK_TTL_DAYS . ' days'));
        $this->blockedIndicatorRepository->add(
            'email',
            $h,
            'tenant',
            $tenantId,
            $reason,
            $expires,
            null,
            null
        );
    }

    /**
     * @param array<string, mixed> $enlistment
     * @param array{code: string, public_label: string} $hit
     */
    private function broadcastAlerts(
        int $tenantId,
        string $tenantName,
        array $enlistment,
        string $side,
        array $hit,
        string $textPreviewForLog,
        ?string $staffActorEmail,
    ): void {
        if (!$this->platformSettingsRepository->getBool(self::SETTING_AUTOMOD_ALERT_EMAILS_ENABLED, true)) {
            return;
        }
        $eid = (int) ($enlistment['id'] ?? 0);
        $candidateEmail = strtolower(trim((string) ($enlistment['email'] ?? '')));
        if ($candidateEmail !== '' && !filter_var($candidateEmail, FILTER_VALIDATE_EMAIL)) {
            $candidateEmail = '';
        }
        $masked = $this->maskPreview($textPreviewForLog);
        $recipients = [];
        if ($candidateEmail !== '') {
            $recipients['candidate:' . $candidateEmail] = ['to' => $candidateEmail, 'audience' => 'candidate'];
        }
        $staffSet = $this->mergeStaffRecipientEmails($tenantId);
        foreach ($staffSet as $em) {
            $recipients['staff:' . $em] = ['to' => $em, 'audience' => 'staff'];
        }
        if ($staffActorEmail !== null && $staffActorEmail !== '') {
            $em = strtolower(trim($staffActorEmail));
            if ($em !== '' && filter_var($em, FILTER_VALIDATE_EMAIL)) {
                $recipients['actor:' . $em] = ['to' => $em, 'audience' => 'staff'];
            }
        }
        foreach ($recipients as $pack) {
            try {
                $this->emailService->sendEnlistmentPortalModerationAlert(
                    (string) $pack['to'],
                    $tenantName,
                    $eid,
                    (string) $pack['audience'],
                    $side,
                    (string) $hit['public_label'],
                    $masked,
                    $tenantId
                );
            } catch (\Throwable) {
            }
        }
    }

    /**
     * @return list<string>
     */
    private function mergeStaffRecipientEmails(int $tenantId): array
    {
        $out = [];
        foreach (
            array_merge(
                $this->userRepository->listRecruitmentNotificationEmailsForTenant($tenantId),
                $this->userRepository->listGovernanceEmailsForTenant($tenantId),
                $this->userRepository->listAdministratorEmailsForTenant($tenantId),
                $this->userRepository->listEmailsForTenantAccessDelegation($tenantId),
            ) as $em
        ) {
            $e = strtolower(trim((string) $em));
            if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) {
                $out[$e] = true;
            }
        }

        return array_keys($out);
    }

    private function maskPreview(string $raw): string
    {
        $s = trim(preg_replace('/\s+/u', ' ', $raw) ?? '');
        if (mb_strlen($s) > 120) {
            $s = mb_substr($s, 0, 117) . '…';
        }

        return $s !== '' ? $s : '—';
    }
}
