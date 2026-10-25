<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Repositories\PlanPaymentRequestRepository;
use App\Repositories\PlatformSettingsRepository;
use App\Repositories\SubscriptionPlanRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Services\Audit\AuditService;
use App\Services\EmailService;
use App\Support\ManualBilling;

/**
 * Paiement des formules par PayPal, sans prestataire automatique :
 * 1. la personne choisit sa formule, une demande avec une référence ATH-XXXXXX est créée ;
 * 2. la page de paiement d’Athena ouvre PayPal avec le montant ; la personne paie en indiquant la référence ;
 * 3. elle colle son identifiant de transaction PayPal : l’administration du site reçoit un e-mail ;
 * 4. l’administration vérifie la réception sur PayPal et active la formule (un mois ou un an).
 */
final class PlanPaymentService
{
    public const PAID_PLANS = ['standard', 'pro', 'pro_plus'];

    public const HEART_AMOUNT_CENTS = 200;

    /** Réglage plateforme : formules retirées de la vente (liste JSON de slugs). */
    public const SETTING_PLANS_OFF = 'billing_plans_off';

    /** Relance de l’administration pour un paiement déclaré resté sans décision. */
    public const ADMIN_REMINDER_HOURS = 24;

    public function __construct(
        private PlanPaymentRequestRepository $requests,
        private SubscriptionPlanRepository $plans,
        private TenantRepository $tenants,
        private UserRepository $users,
        private ?EmailService $email = null,
        private ?PlatformSettingsRepository $settings = null,
        private ?AuditService $audit = null,
    ) {}

    public function schemaReady(): bool
    {
        return $this->requests->schemaReady();
    }

    public function paypalBaseUrl(): string
    {
        return \App\Support\PayPalDonation::accountUrl($this->settings);
    }

    /** @param array<string, mixed> $request */
    public function paypalUrlFor(array $request): string
    {
        return ManualBilling::paypalUrl($this->paypalBaseUrl(), (int) ($request['amount_cents'] ?? 0));
    }

    private function settingsRepo(): PlatformSettingsRepository
    {
        return $this->settings ??= new PlatformSettingsRepository();
    }

    /**
     * Formules payantes retirées de la vente par l’administration (plus de nouvelle souscription).
     *
     * @return list<string>
     */
    public function disabledPlans(): array
    {
        try {
            $raw = json_decode((string) $this->settingsRepo()->get(self::SETTING_PLANS_OFF, '[]'), true);
        } catch (\Throwable) {
            $raw = [];
        }

        return is_array($raw) ? array_values(array_intersect(self::PAID_PLANS, array_map('strval', $raw))) : [];
    }

    /** @param list<string> $slugs */
    public function setDisabledPlans(array $slugs): void
    {
        $slugs = array_values(array_intersect(self::PAID_PLANS, array_map('strval', $slugs)));
        $this->settingsRepo()->setMany([self::SETTING_PLANS_OFF => json_encode($slugs)]);
    }

    /** Prix (centimes) d’une formule pour une période, ou null si non réglé. */
    public function priceCents(array $planRow, string $interval): ?int
    {
        $raw = $planRow[$interval === 'yearly' ? 'price_yearly_cents' : 'price_monthly_cents'] ?? null;
        $cents = is_numeric($raw) ? (int) $raw : 0;

        return $cents > 0 ? $cents : null;
    }

    /**
     * Offres payantes affichables : formule, prix mensuel et annuel (null si non réglé).
     *
     * @return list<array{slug: string, name: string, monthly: ?int, yearly: ?int, available: bool, on_sale: bool}>
     */
    public function offers(): array
    {
        $off = $this->disabledPlans();
        $bySlug = [];
        foreach ($this->plans->tableExists() ? $this->plans->allOrdered() : [] as $row) {
            $bySlug[strtolower(trim((string) ($row['slug'] ?? '')))] = $row;
        }
        $out = [];
        foreach (self::PAID_PLANS as $slug) {
            $row = $bySlug[$slug] ?? null;
            if ($row === null) {
                continue;
            }
            $monthly = $this->priceCents($row, 'monthly');
            $yearly = $this->priceCents($row, 'yearly');
            $out[] = [
                'slug' => $slug,
                'name' => trim((string) ($row['name'] ?? '')) ?: $slug,
                'monthly' => $monthly,
                'yearly' => $yearly,
                'on_sale' => !in_array($slug, $off, true),
                'available' => $this->schemaReady() && ($monthly !== null || $yearly !== null) && !in_array($slug, $off, true),
            ];
        }

        return $out;
    }

    /**
     * @return array<string, mixed> la demande créée
     * @throws \RuntimeException message affichable
     */
    public function createPlanRequest(int $tenantId, ?int $userId, ?string $email, string $planSlug, string $interval): array
    {
        if (!$this->schemaReady()) {
            throw new \RuntimeException('Le paiement n’est pas encore prêt : lancez la mise à jour de la base.');
        }
        $planSlug = strtolower(trim($planSlug));
        $interval = $interval === 'yearly' ? 'yearly' : 'monthly';
        if (!in_array($planSlug, self::PAID_PLANS, true)) {
            throw new \RuntimeException('Formule invalide.');
        }
        if (in_array($planSlug, $this->disabledPlans(), true)) {
            throw new \RuntimeException('Cette formule n’est plus proposée pour le moment. Choisissez-en une autre ou contactez l’équipe Athena.');
        }
        $plan = $this->plans->findBySlug($planSlug);
        $amount = $plan !== null ? $this->priceCents($plan, $interval) : null;
        if ($amount === null) {
            throw new \RuntimeException('Le prix de cette formule n’est pas encore réglé. Réessayez plus tard ou contactez l’équipe Athena.');
        }

        return $this->insert(PlanPaymentRequestRepository::KIND_PLAN, $tenantId, $userId, $email, $planSlug, $interval, $amount);
    }

    /** @return array<string, mixed> */
    public function createHeartRequest(int $tenantId, ?int $userId, ?string $email): array
    {
        if (!$this->schemaReady()) {
            throw new \RuntimeException('Le paiement n’est pas encore prêt : lancez la mise à jour de la base.');
        }

        return $this->insert(PlanPaymentRequestRepository::KIND_HEART, $tenantId, $userId, $email, null, null, self::HEART_AMOUNT_CENTS);
    }

    /** @return array<string, mixed> */
    private function insert(string $kind, int $tenantId, ?int $userId, ?string $email, ?string $planSlug, ?string $interval, int $amount): array
    {
        $reference = ManualBilling::generateReference();
        for ($i = 0; $i < 5 && $this->requests->referenceExists($reference); $i++) {
            $reference = ManualBilling::generateReference();
        }
        $id = $this->requests->create([
            'reference' => $reference,
            'kind' => $kind,
            'tenant_id' => $tenantId > 0 ? $tenantId : null,
            'user_id' => $userId,
            'payer_email' => $email !== null && $email !== '' ? mb_substr(strtolower(trim($email)), 0, 190) : null,
            'plan_slug' => $planSlug,
            'billing_interval' => $interval,
            'amount_cents' => $amount,
        ]);

        return $this->requests->findById($id) ?? [];
    }

    /** @return array<string, mixed>|null */
    public function find(string $reference): ?array
    {
        return $this->schemaReady() ? $this->requests->findByReference($reference) : null;
    }

    /**
     * La personne indique avoir payé : identifiant de transaction PayPal + note libre. Prévient l’administration.
     *
     * @param array<string, mixed> $request
     * @return array{ok: bool, error?: string}
     */
    public function declare(array $request, string $transactionRef, string $note): array
    {
        $transactionRef = strtoupper(preg_replace('/[^A-Za-z0-9\-]/', '', $transactionRef) ?? '');
        if (strlen($transactionRef) < 6) {
            return ['ok' => false, 'error' => 'Collez l’identifiant de transaction PayPal (17 caractères, dans le reçu PayPal).'];
        }
        if (!in_array((string) $request['status'], [PlanPaymentRequestRepository::STATUS_AWAITING, PlanPaymentRequestRepository::STATUS_DECLARED], true)) {
            return ['ok' => false, 'error' => 'Cette demande est déjà traitée.'];
        }
        if (!$this->requests->markDeclared((int) $request['id'], $transactionRef, trim($note))) {
            return ['ok' => false, 'error' => 'La déclaration n’a pas pu être enregistrée. Réessayez.'];
        }
        $fresh = $this->requests->findById((int) $request['id']) ?? $request;
        $this->notifyAdmins($fresh);

        return ['ok' => true];
    }

    /**
     * Activation par l’administration : la formule s’applique pour un mois ou un an (prolonge la période en cours).
     *
     * @return array{ok: bool, error?: string, ends_at?: string}
     */
    public function activate(int $requestId, ?int $adminId, string $note = ''): array
    {
        $request = $this->requests->findById($requestId);
        if ($request === null) {
            return ['ok' => false, 'error' => 'Demande introuvable.'];
        }
        if (!in_array((string) $request['status'], [PlanPaymentRequestRepository::STATUS_AWAITING, PlanPaymentRequestRepository::STATUS_DECLARED], true)) {
            return ['ok' => false, 'error' => 'Cette demande est déjà traitée.'];
        }
        $endsAt = null;
        $tenantId = (int) ($request['tenant_id'] ?? 0);
        if ((string) $request['kind'] === PlanPaymentRequestRepository::KIND_PLAN) {
            $tenant = $tenantId > 0 ? $this->tenants->findById($tenantId) : null;
            if ($tenant === null) {
                return ['ok' => false, 'error' => 'La communauté de cette demande n’existe plus.'];
            }
            $planSlug = (string) $request['plan_slug'];
            $interval = (string) ($request['billing_interval'] ?? 'monthly');
            $current = ManualBilling::current($tenant);
            $sameRunning = $current !== null && $current['plan_slug'] === $planSlug
                && strtolower((string) ($tenant['plan_slug'] ?? '')) === $planSlug;
            $endsAt = ManualBilling::periodEnd($sameRunning ? $current['ends_at'] : null, $interval);
            $this->tenants->updateSubscriptionFromPayPal($tenantId, null, null, 'active', $planSlug, $endsAt);
            $this->tenants->mergeSettings($tenantId, [
                ManualBilling::SETTINGS_KEY => [
                    'reference' => (string) $request['reference'],
                    'plan_slug' => $planSlug,
                    'interval' => $interval,
                    'ends_at' => $endsAt,
                ],
            ]);
        }
        if (!$this->requests->markDecided($requestId, PlanPaymentRequestRepository::STATUS_ACTIVATED, $adminId, $note, $endsAt)) {
            return ['ok' => false, 'error' => 'La demande a changé entre-temps. Rechargez la page.'];
        }
        $this->auditLog('platform.plan_payment_activated', $tenantId, $adminId, $requestId, (string) $request['reference'], $endsAt);
        $this->notifyPayer($request, 'activated', $endsAt, $note);

        return ['ok' => true, 'ends_at' => $endsAt ?? ''];
    }

    /** @return array{ok: bool, error?: string} */
    public function reject(int $requestId, ?int $adminId, string $note): array
    {
        $request = $this->requests->findById($requestId);
        if ($request === null) {
            return ['ok' => false, 'error' => 'Demande introuvable.'];
        }
        if (!$this->requests->markDecided($requestId, PlanPaymentRequestRepository::STATUS_REJECTED, $adminId, $note)) {
            return ['ok' => false, 'error' => 'Cette demande est déjà traitée.'];
        }
        $this->auditLog('platform.plan_payment_rejected', (int) ($request['tenant_id'] ?? 0), $adminId, $requestId, (string) $request['reference'], null);
        $this->notifyPayer($request, 'rejected', null, $note);

        return ['ok' => true];
    }

    /**
     * Annulation par l’administration d’une demande pas encore activée (doublon, erreur de formule…).
     *
     * @return array{ok: bool, error?: string}
     */
    public function cancelByAdmin(int $requestId, ?int $adminId, string $note): array
    {
        $request = $this->requests->findById($requestId);
        if ($request === null) {
            return ['ok' => false, 'error' => 'Demande introuvable.'];
        }
        if (!$this->requests->cancelByAdmin($requestId, $adminId, $note)) {
            return ['ok' => false, 'error' => 'Seule une demande pas encore traitée peut être annulée.'];
        }
        $this->auditLog('platform.plan_payment_cancelled', (int) ($request['tenant_id'] ?? 0), $adminId, $requestId, (string) $request['reference'], null);
        if ((string) $request['status'] === PlanPaymentRequestRepository::STATUS_DECLARED) {
            $this->notifyPayer($request, 'cancelled', null, $note);
        }

        return ['ok' => true];
    }

    /**
     * Paiement activé puis remboursé (le remboursement se fait sur PayPal) : noté « remboursé » et, au choix,
     * la formule payée par cette demande s’arrête tout de suite.
     *
     * @return array{ok: bool, error?: string, plan_ended?: bool}
     */
    public function refund(int $requestId, ?int $adminId, string $note, bool $endPlan): array
    {
        $request = $this->requests->findById($requestId);
        if ($request === null) {
            return ['ok' => false, 'error' => 'Demande introuvable.'];
        }
        if (!$this->requests->markRefunded($requestId, $adminId, $note)) {
            return ['ok' => false, 'error' => 'Seul un paiement activé peut être marqué remboursé.'];
        }
        $this->auditLog('platform.plan_payment_refunded', (int) ($request['tenant_id'] ?? 0), $adminId, $requestId, (string) $request['reference'], null);
        $ended = false;
        $tenantId = (int) ($request['tenant_id'] ?? 0);
        if ($endPlan && (string) $request['kind'] === PlanPaymentRequestRepository::KIND_PLAN && $tenantId > 0) {
            $ended = !empty($this->endPlan($tenantId, $adminId, $note !== '' ? $note : 'Paiement ' . $request['reference'] . ' remboursé.', false)['ok']);
        }
        $this->notifyPayer($request, 'refunded', null, $note);

        return ['ok' => true, 'plan_ended' => $ended];
    }

    /**
     * Arrête tout de suite la formule payée par PayPal d’une communauté : retour à la formule gratuite.
     *
     * @return array{ok: bool, error?: string}
     */
    public function endPlan(int $tenantId, ?int $adminId, string $note, bool $notify = true): array
    {
        $tenant = $this->tenants->findById($tenantId);
        if ($tenant === null) {
            return ['ok' => false, 'error' => 'Communauté introuvable.'];
        }
        $current = ManualBilling::current($tenant);
        if ($current === null || !ManualBilling::paidActive($tenant)) {
            return ['ok' => false, 'error' => 'Cette communauté n’a pas de formule payée par PayPal en cours.'];
        }
        $now = date('Y-m-d H:i:s');
        $this->tenants->updateSubscriptionFromPayPal($tenantId, null, null, 'canceled', 'free', null);
        $this->tenants->mergeSettings($tenantId, [
            ManualBilling::SETTINGS_KEY => array_merge($current, ['ends_at' => $now, 'ended_by_admin' => true]),
        ]);
        $this->auditLog('platform.plan_manual_ended', $tenantId, $adminId, 0, $current['reference'], $now);
        if ($notify && $current['reference'] !== '') {
            $request = $this->requests->findByReference($current['reference']);
            if ($request !== null) {
                $this->notifyPayer($request, 'ended', null, $note);
            }
        }

        return ['ok' => true];
    }

    /**
     * Communautés avec une formule payée par PayPal encore en cours (liste de suivi de l’administration).
     *
     * @return list<array{tenant_id: int, tenant_name: string, plan_slug: string, plan_name: string, interval: string, ends_at: string, days_left: int, reference: string}>
     */
    public function activeManualSubscriptions(?int $now = null): array
    {
        $now ??= time();
        $out = [];
        foreach ($this->tenants->listOverviewForPlatform() as $row) {
            $w = is_array($row['plan_window'] ?? null) ? $row['plan_window'] : [];
            $manual = is_array($w['manual_billing'] ?? null) ? $w['manual_billing'] : null;
            if ($manual === null) {
                continue;
            }
            $tenant = [
                'subscription_status' => (string) ($row['subscription_status'] ?? 'none'),
                'settings' => [ManualBilling::SETTINGS_KEY => $manual],
            ];
            $cur = ManualBilling::current($tenant);
            if ($cur === null || !ManualBilling::paidActive($tenant, $now)) {
                continue;
            }
            $ts = (int) strtotime($cur['ends_at']);
            $plan = $this->plans->findBySlug($cur['plan_slug']);
            $out[] = [
                'tenant_id' => (int) ($row['id'] ?? 0),
                'tenant_name' => (string) ($row['name'] ?? ''),
                'plan_slug' => $cur['plan_slug'],
                'plan_name' => $plan !== null ? (trim((string) ($plan['name'] ?? '')) ?: $cur['plan_slug']) : $cur['plan_slug'],
                'interval' => $cur['interval'],
                'ends_at' => $cur['ends_at'],
                'days_left' => max(0, (int) ceil(($ts - $now) / 86400)),
                'reference' => $cur['reference'],
            ];
        }
        usort($out, static fn (array $a, array $b): int => strcmp($a['ends_at'], $b['ends_at']));

        return $out;
    }

    /**
     * Relance par e-mail de l’administration pour les paiements déclarés depuis plus de 24 h sans décision.
     *
     * @return array{requests: int, emails: int}
     */
    public function remindAdminsOfPendingDeclarations(?int $now = null): array
    {
        if (!$this->schemaReady()) {
            return ['requests' => 0, 'emails' => 0];
        }
        $rows = $this->requests->listDeclaredAwaitingReminder(self::ADMIN_REMINDER_HOURS, $now);
        if ($rows === []) {
            return ['requests' => 0, 'emails' => 0];
        }
        $emails = 0;
        foreach ($rows as $row) {
            $emails += $this->notifyAdmins($row, true);
        }
        $this->requests->markAdminReminded(array_map(static fn (array $r): int => (int) $r['id'], $rows));

        return ['requests' => count($rows), 'emails' => $emails];
    }

    /** @param array<string, mixed> $request */
    public function describe(array $request): string
    {
        if ((string) ($request['kind'] ?? '') === PlanPaymentRequestRepository::KIND_HEART) {
            return 'Support du cœur (soutien unique)';
        }
        $plan = $this->plans->findBySlug((string) ($request['plan_slug'] ?? ''));
        $name = $plan !== null ? (trim((string) ($plan['name'] ?? '')) ?: (string) $request['plan_slug']) : (string) ($request['plan_slug'] ?? '');

        return 'Formule ' . $name . ' — ' . (((string) ($request['billing_interval'] ?? '')) === 'yearly' ? '1 an' : '1 mois');
    }

    /**
     * E-mail aux administrateurs de la plateforme : paiement déclaré, ou relance s’il attend toujours.
     *
     * @param array<string, mixed> $request
     * @return int e-mails envoyés
     */
    private function notifyAdmins(array $request, bool $reminder = false): int
    {
        $tenant = !empty($request['tenant_id']) ? $this->tenants->findById((int) $request['tenant_id']) : null;
        $vars = [
            'reference' => (string) $request['reference'],
            'description' => $this->describe($request),
            'amount' => ManualBilling::formatAmount((int) $request['amount_cents']),
            'transactionRef' => (string) ($request['transaction_ref'] ?? ''),
            'payerNote' => (string) ($request['payer_note'] ?? ''),
            'payerEmail' => (string) ($request['payer_email'] ?? ''),
            'tenantName' => $tenant !== null ? (string) ($tenant['name'] ?? '') : '—',
            'adminUrl' => url('admin/paiements') . '?ref=' . rawurlencode((string) $request['reference']),
            'reminder' => $reminder,
            'declaredAt' => !empty($request['declared_at']) ? date('d/m/Y H:i', (int) strtotime((string) $request['declared_at'])) : '',
        ];
        try {
            $recipients = $this->users->listPlatformAdminEmails();
        } catch (\Throwable) {
            $recipients = [];
        }
        $sent = 0;
        foreach ($recipients as $to) {
            $to = strtolower(trim((string) $to));
            if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            try {
                if ($this->email()->sendTemplated(
                    \App\Services\Email\EmailEvents::PLAN_PAYMENT_DECLARED,
                    'plan_payment_declared',
                    $to,
                    ($reminder ? 'Relance : paiement PayPal toujours à vérifier — ' : 'Paiement PayPal à vérifier — ')
                        . $vars['reference'] . ' (' . $vars['amount'] . ')',
                    $vars,
                    null,
                    $vars['payerEmail'] !== '' ? $vars['payerEmail'] : null,
                    ['purpose' => $reminder ? 'plan_payment_reminder' : 'plan_payment_declared', 'reference' => $vars['reference']],
                    true
                )) {
                    $sent++;
                }
            } catch (\Throwable) {
                // La demande reste visible dans Admin > Paiements.
            }
        }

        return $sent;
    }

    /**
     * E-mail à la personne qui a payé : activé, refusé, annulé, remboursé ou formule arrêtée.
     *
     * @param array<string, mixed> $request
     */
    private function notifyPayer(array $request, string $state, ?string $endsAt, string $note): void
    {
        $to = strtolower(trim((string) ($request['payer_email'] ?? '')));
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        $subjects = [
            'activated' => 'Paiement confirmé',
            'rejected' => 'Paiement non confirmé',
            'cancelled' => 'Demande de paiement annulée',
            'refunded' => 'Paiement remboursé',
            'ended' => 'Formule arrêtée',
        ];
        try {
            $this->email()->sendTemplated(
                \App\Services\Email\EmailEvents::PLAN_PAYMENT_DECIDED,
                'plan_payment_decided',
                $to,
                ($subjects[$state] ?? 'Paiement') . ' — ' . (string) $request['reference'],
                [
                    'state' => $state,
                    'activated' => $state === 'activated',
                    'reference' => (string) $request['reference'],
                    'description' => $this->describe($request),
                    'amount' => ManualBilling::formatAmount((int) $request['amount_cents']),
                    'endsAt' => $endsAt !== null ? date('d/m/Y', (int) strtotime($endsAt)) : '',
                    'adminNote' => $note,
                    'pageUrl' => url('paiement/' . rawurlencode((string) $request['reference'])),
                ],
                !empty($request['tenant_id']) ? (int) $request['tenant_id'] : null,
                null,
                ['purpose' => 'plan_payment_' . $state, 'reference' => (string) $request['reference']]
            );
        } catch (\Throwable) {
        }
    }

    private function auditLog(string $action, int $tenantId, ?int $adminId, int $requestId, string $reference, ?string $endsAt): void
    {
        try {
            ($this->audit ?? new AuditService())->log(
                $action,
                $tenantId > 0 ? $tenantId : null,
                $adminId,
                'plan_payment_request',
                $requestId,
                null,
                $reference . ($endsAt !== null ? ' → ' . $endsAt : '')
            );
        } catch (\Throwable) {
        }
    }

    private function email(): EmailService
    {
        return $this->email ??= \App\Core\Container::get(EmailService::class);
    }
}
