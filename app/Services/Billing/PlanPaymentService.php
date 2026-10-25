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
        try {
            $raw = ($this->settings ?? new PlatformSettingsRepository())->get(ManualBilling::SETTING_PAYPAL_URL, '');
        } catch (\Throwable) {
            $raw = '';
        }
        $url = \App\Support\PayPalDonation::normalizeUrl($raw);

        return $url ?? ManualBilling::DEFAULT_PAYPAL_URL;
    }

    /** @param array<string, mixed> $request */
    public function paypalUrlFor(array $request): string
    {
        return ManualBilling::paypalUrl($this->paypalBaseUrl(), (int) ($request['amount_cents'] ?? 0));
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
     * @return list<array{slug: string, name: string, monthly: ?int, yearly: ?int, available: bool}>
     */
    public function offers(): array
    {
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
                'available' => $this->schemaReady() && ($monthly !== null || $yearly !== null),
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
        $this->notifyPayer($request, true, $endsAt, $note);

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
        $this->notifyPayer($request, false, null, $note);

        return ['ok' => true];
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

    /** @param array<string, mixed> $request */
    private function notifyAdmins(array $request): void
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
        ];
        try {
            $recipients = $this->users->listPlatformAdminEmails();
        } catch (\Throwable) {
            $recipients = [];
        }
        foreach ($recipients as $to) {
            $to = strtolower(trim((string) $to));
            if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            try {
                $this->email()->sendTemplated(
                    \App\Services\Email\EmailEvents::PLAN_PAYMENT_DECLARED,
                    'plan_payment_declared',
                    $to,
                    'Paiement PayPal à vérifier — ' . $vars['reference'] . ' (' . $vars['amount'] . ')',
                    $vars,
                    null,
                    $vars['payerEmail'] !== '' ? $vars['payerEmail'] : null,
                    ['purpose' => 'plan_payment_declared', 'reference' => $vars['reference']],
                    true
                );
            } catch (\Throwable) {
                // La demande reste visible dans Admin > Paiements.
            }
        }
    }

    /** @param array<string, mixed> $request */
    private function notifyPayer(array $request, bool $activated, ?string $endsAt, string $note): void
    {
        $to = strtolower(trim((string) ($request['payer_email'] ?? '')));
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        try {
            $this->email()->sendTemplated(
                \App\Services\Email\EmailEvents::PLAN_PAYMENT_DECIDED,
                'plan_payment_decided',
                $to,
                ($activated ? 'Paiement confirmé' : 'Paiement non confirmé') . ' — ' . (string) $request['reference'],
                [
                    'activated' => $activated,
                    'reference' => (string) $request['reference'],
                    'description' => $this->describe($request),
                    'amount' => ManualBilling::formatAmount((int) $request['amount_cents']),
                    'endsAt' => $endsAt !== null ? date('d/m/Y', (int) strtotime($endsAt)) : '',
                    'adminNote' => $note,
                    'pageUrl' => url('paiement/' . rawurlencode((string) $request['reference'])),
                ],
                !empty($request['tenant_id']) ? (int) $request['tenant_id'] : null,
                null,
                ['purpose' => 'plan_payment_decided', 'reference' => (string) $request['reference']]
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
