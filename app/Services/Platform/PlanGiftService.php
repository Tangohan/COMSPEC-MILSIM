<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Core\Container;
use App\Repositories\PlanGiftRepository;
use App\Repositories\SubscriptionPlanRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;
use App\Services\EmailService;
use App\Support\PlanGift;

/**
 * Offrir une formule à une communauté : enregistre l'offre, l'applique (tenants.settings.plan_gift),
 * prévient les gestionnaires par e-mail et prépare la fenêtre affichée à leur prochaine page.
 */
final class PlanGiftService
{
    public function __construct(
        private ?PlanGiftRepository $gifts = null,
        private ?TenantRepository $tenants = null,
        private ?SubscriptionPlanRepository $plans = null,
        private ?UserRepository $users = null,
        private ?EmailService $email = null,
        private ?AuditService $audit = null,
    ) {
        $this->gifts ??= new PlanGiftRepository();
        $this->tenants ??= Container::get(TenantRepository::class);
        $this->plans ??= Container::get(SubscriptionPlanRepository::class);
        $this->users ??= Container::get(UserRepository::class);
        $this->audit ??= new AuditService();
    }

    private function email(): EmailService
    {
        return $this->email ??= Container::get(EmailService::class);
    }

    /**
     * @return array{gift_id: int, recipients: int, emails: int}
     * @throws \RuntimeException message affichable
     */
    public function grant(
        int $tenantId,
        string $planSlug,
        ?string $endsAt,
        string $messageFr,
        string $messageEn,
        ?int $actorId,
    ): array {
        $tenant = $tenantId > 1 ? $this->tenants->findById($tenantId) : null;
        if ($tenant === null) {
            throw new \RuntimeException('Communauté introuvable.');
        }
        $planSlug = strtolower(trim($planSlug));
        $plan = $planSlug !== '' ? $this->plans->findBySlug($planSlug) : null;
        if ($plan === null) {
            throw new \RuntimeException('Choisissez une formule d’accès valide.');
        }
        $planName = trim((string) ($plan['name'] ?? '')) ?: $planSlug;
        $tenantName = trim((string) ($tenant['name'] ?? '')) ?: 'Communauté';
        $messageFr = mb_substr(trim($messageFr), 0, 1000);
        $messageEn = mb_substr(trim($messageEn), 0, 1000);

        $this->gifts->ensureSchema();
        $before = PlanGift::activeFromSettings($tenant['settings'] ?? null);
        $this->gifts->revokeOpenForTenant($tenantId, $actorId);
        $giftId = $this->gifts->create(
            $tenantId,
            $planSlug,
            $planName,
            $endsAt,
            $messageFr !== '' ? $messageFr : null,
            $messageEn !== '' ? $messageEn : null,
            $actorId,
        );
        $this->tenants->mergeSettings($tenantId, [
            PlanGift::SETTINGS_KEY => ['gift_id' => $giftId, 'plan_slug' => $planSlug, 'ends_at' => $endsAt],
        ]);

        $recipients = $this->recipients($tenant);
        $manageUrl = url('back-office');
        $sent = 0;
        foreach ($recipients as $user) {
            $userId = (int) $user['id'];
            $to = (string) $user['email'];
            $this->gifts->addNotice($giftId, $tenantId, $userId, $to);
            try {
                $lang = PlanGift::locale($this->email()->recipientLocale($to));
                $ok = $this->email()->sendPlanGiftGranted(
                    $to,
                    $lang,
                    self::displayName($user),
                    $tenantName,
                    $planName,
                    $endsAt,
                    PlanGift::message($messageFr, $messageEn, $lang),
                    $manageUrl,
                    $tenantId,
                    $giftId,
                );
                if ($ok) {
                    $this->gifts->markEmailSent($giftId, $userId);
                    $sent++;
                }
            } catch (\Throwable) {
                // La fenêtre reste prévue même si l'e-mail échoue.
            }
        }

        $this->audit->logChange(
            AuditAction::TENANT_PLAN_GIFTED,
            $tenantId,
            $actorId,
            'tenant',
            $tenantId,
            [
                'plan_gift' => $before !== null ? $before['plan_slug'] . ' → ' . ($before['ends_at'] ?? 'sans limite') : '',
            ],
            [
                'plan_gift' => $planSlug . ' → ' . ($endsAt ?? 'sans limite'),
                'gift_id' => $giftId,
                'recipients' => count($recipients),
                'emails_sent' => $sent,
            ],
        );

        return ['gift_id' => $giftId, 'recipients' => count($recipients), 'emails' => $sent];
    }

    /** Retire une offre avant son terme. */
    public function revoke(int $giftId, ?int $actorId): bool
    {
        $this->gifts->ensureSchema();
        $gift = $this->gifts->find($giftId);
        if ($gift === null || !$this->gifts->revoke($giftId, $actorId)) {
            return false;
        }
        $tenantId = (int) $gift['tenant_id'];
        $tenant = $this->tenants->findById($tenantId);
        $current = $tenant !== null ? PlanGift::activeFromSettings($tenant['settings'] ?? null) : null;
        if ($current !== null && $current['gift_id'] === $giftId) {
            $this->tenants->mergeSettings($tenantId, [PlanGift::SETTINGS_KEY => null]);
        }
        $this->audit->logChange(
            AuditAction::TENANT_PLAN_GIFT_REVOKED,
            $tenantId,
            $actorId,
            'tenant',
            $tenantId,
            ['plan_gift' => (string) $gift['plan_slug'] . ' → ' . ($gift['ends_at'] ?? 'sans limite'), 'gift_id' => $giftId],
            ['plan_gift' => ''],
        );

        return true;
    }

    /**
     * Gestionnaires de la communauté : propriétaire, rôles fondateur et administrateur de la communauté.
     *
     * @param array<string, mixed> $tenant
     * @return list<array<string, mixed>>
     */
    public function recipients(array $tenant): array
    {
        $tenantId = (int) ($tenant['id'] ?? 0);
        $byId = [];
        $add = function (?array $user) use (&$byId): void {
            if ($user === null || ($user['status'] ?? 'active') !== 'active') {
                return;
            }
            $email = strtolower(trim((string) ($user['email'] ?? '')));
            $id = (int) ($user['id'] ?? 0);
            if ($id < 1 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return;
            }
            $user['email'] = $email;
            $byId[$id] = $user;
        };

        $ownerId = (int) ($tenant['owner_user_id'] ?? 0);
        if ($ownerId > 0) {
            $add($this->users->findById($ownerId));
        }
        $emails = array_merge(
            $this->users->listGovernanceEmailsForTenant($tenantId),
            $this->users->listAdministratorEmailsForTenant($tenantId),
        );
        foreach (array_unique($emails) as $email) {
            $add($this->users->findByEmail($tenantId, (string) $email));
        }

        return array_values($byId);
    }

    /** @param array<string, mixed> $user */
    private static function displayName(array $user): string
    {
        foreach (['callsign', 'display_name'] as $key) {
            $v = trim((string) ($user[$key] ?? ''));
            if ($v !== '') {
                return $v;
            }
        }

        return '';
    }
}
