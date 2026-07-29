<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\SubscriptionPlanRepository;
use App\Repositories\TenantRepository;
use App\Services\Auth\AuthService;
use App\Services\Billing\BillingProvider;
use App\Services\Billing\PayPalCheckoutService;
use App\Services\Billing\StripeCheckoutService;
use App\Services\Billing\SubscriptionPlanFeaturesCatalog;

/**
 * Souscription / changement de formule pour une communauté existante (PayPal prioritaire).
 */
final class PlatformSubscriptionController
{
    public function __construct(
        private AuthService $authService,
        private TenantRepository $tenantRepository,
        private SubscriptionPlanRepository $planRepository,
        private PayPalCheckoutService $payPalCheckoutService,
        private StripeCheckoutService $stripeCheckoutService,
    ) {}

    public function upgrade(Request $request, array $params = []): Response
    {
        $from = trim((string) $request->query('from', ''));
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId > 0 && $from !== '') {
            try {
                \App\Core\Container::get(\App\Repositories\PlatformUsageRepository::class)->record(
                    $tenantId,
                    Session::get('user_id') ? (int) Session::get('user_id') : null,
                    'upgrade_view',
                    $from
                );
            } catch (\Throwable) {
            }
        }

        $featureKey = '';
        if (str_starts_with($from, 'quota_')) {
            $featureKey = substr($from, strlen('quota_'));
        } elseif ($from !== '' && !str_contains($from, '/')) {
            $featureKey = $from;
        }

        $plans = $this->planRepository->tableExists() ? $this->planRepository->allOrdered() : [];
        $offerCards = $this->buildOfferCards($plans);
        $canManageBilling = $this->canManageBilling();

        return Response::view('layout.main', [
            'title' => 'Offres et formules',
            'content' => 'platform.upgrade',
            'feature' => $featureKey !== '' ? SubscriptionPlanFeaturesCatalog::featureLabel($featureKey) : 'offre',
            'featureKey' => $featureKey,
            'planName' => 'Standard ou Pro',
            'upgradeFrom' => $from,
            'subscriptionOfferCards' => $offerCards,
            'billingConfigured' => BillingProvider::anyConfigured(),
            'billingProvider' => BillingProvider::preferred(),
            'canManageBilling' => $canManageBilling,
            'csrfToken' => Csrf::token(),
        ]);
    }

    public function checkout(Request $request, array $params = []): Response
    {
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('platform/upgrade'));
        }
        if (!$this->canManageBilling()) {
            Session::flash('error', 'Seul un responsable de la communauté peut souscrire ou changer de formule.');

            return Response::redirect(url('platform/upgrade'));
        }

        $tenantId = (int) Session::get('tenant_id');
        $tenant = $tenantId > 0 ? $this->tenantRepository->findById($tenantId) : null;
        if ($tenant === null) {
            Session::flash('error', 'Communauté introuvable.');

            return Response::redirect(url('dashboard'));
        }

        $planSlug = strtolower(trim((string) $request->input('plan_slug', '')));
        $interval = strtolower(trim((string) $request->input('interval', 'monthly')));
        if (!in_array($planSlug, ['standard', 'pro', 'pro_plus'], true)) {
            Session::flash('error', 'Formule invalide.');

            return Response::redirect(url('platform/upgrade'));
        }
        if (!in_array($interval, ['monthly', 'yearly'], true)) {
            $interval = 'monthly';
        }

        $planRow = $this->planRepository->findBySlug($planSlug);
        if ($planRow === null) {
            Session::flash('error', 'Cette formule n’est pas disponible.');

            return Response::redirect(url('platform/upgrade'));
        }

        $provider = BillingProvider::preferred();
        if ($provider === null) {
            Session::flash('error', 'Le paiement en ligne n’est pas disponible pour le moment.');

            return Response::redirect(url('platform/upgrade'));
        }

        $user = $this->authService->user();
        $email = is_array($user) ? (string) ($user['email'] ?? '') : '';
        $successUrl = url('platform/upgrade/complete') . '?plan=' . rawurlencode($planSlug);
        $cancelUrl = url('platform/upgrade');

        try {
            if ($provider === BillingProvider::PAYPAL) {
                $planId = $interval === 'yearly'
                    ? trim((string) ($planRow['paypal_plan_id_yearly'] ?? ''))
                    : trim((string) ($planRow['paypal_plan_id_monthly'] ?? ''));
                if ($planId === '') {
                    Session::flash('error', 'Cette formule n’est pas encore ouverte à la souscription PayPal.');

                    return Response::redirect(url('platform/upgrade'));
                }
                $successUrl .= '&provider=paypal&subscription_id={subscription_id}';
                // PayPal n’interpole pas {subscription_id} : on utilise return_url fixe + query PayPal
                $successUrl = url('platform/upgrade/complete') . '?provider=paypal&plan=' . rawurlencode($planSlug);
                $session = $this->payPalCheckoutService->createSubscription(
                    $planId,
                    $successUrl,
                    $cancelUrl,
                    $email !== '' ? $email : null,
                    [
                        'tid' => (string) $tenantId,
                        'plan' => $planSlug,
                    ]
                );
                Session::set('pending_upgrade_paypal_sub', $session['id']);
                Session::set('pending_upgrade_plan', $planSlug);

                return Response::redirect($session['url']);
            }

            $priceId = $interval === 'yearly'
                ? trim((string) ($planRow['stripe_price_id_yearly'] ?? ''))
                : trim((string) ($planRow['stripe_price_id_monthly'] ?? ''));
            if ($priceId === '') {
                Session::flash('error', 'Cette formule n’est pas encore ouverte à la souscription.');

                return Response::redirect(url('platform/upgrade'));
            }
            $session = $this->stripeCheckoutService->createSubscriptionCheckoutSession(
                $priceId,
                url('platform/upgrade/complete') . '?provider=stripe&session_id={CHECKOUT_SESSION_ID}&plan=' . rawurlencode($planSlug),
                $cancelUrl,
                $email !== '' ? $email : null,
                [
                    'tenant_id' => (string) $tenantId,
                    'plan_slug' => $planSlug,
                ]
            );

            return Response::redirect($session['url']);
        } catch (\Throwable $e) {
            error_log('[platform.subscription.checkout] ' . $e->getMessage());
            Session::flash('error', 'Le paiement n’a pas pu être démarré. Réessayez dans quelques instants.');

            return Response::redirect(url('platform/upgrade'));
        }
    }

    public function complete(Request $request, array $params = []): Response
    {
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $provider = strtolower(trim((string) $request->query('provider', BillingProvider::preferred() ?? '')));
        $planSlug = strtolower(trim((string) $request->query('plan', Session::get('pending_upgrade_plan') ?? 'standard')));

        if ($provider === BillingProvider::PAYPAL) {
            $subId = trim((string) ($request->query('subscription_id') ?: Session::get('pending_upgrade_paypal_sub') ?: ''));
            if ($subId !== '' && $tenantId > 0) {
                try {
                    $sub = $this->payPalCheckoutService->getSubscription($subId);
                    $status = strtoupper((string) ($sub['status'] ?? ''));
                    if (in_array($status, ['ACTIVE', 'APPROVED'], true)) {
                        $payerId = null;
                        if (isset($sub['subscriber']) && is_array($sub['subscriber'])) {
                            $p = $sub['subscriber']['payer_id'] ?? null;
                            $payerId = is_string($p) ? $p : null;
                        }
                        $periodEnd = isset($sub['billing_info']['next_billing_time'])
                            ? (string) $sub['billing_info']['next_billing_time']
                            : null;
                        $this->tenantRepository->updateSubscriptionFromPayPal(
                            $tenantId,
                            $payerId,
                            $subId,
                            'active',
                            $planSlug !== '' ? $planSlug : 'standard',
                            $periodEnd
                        );
                        Session::forget('pending_upgrade_paypal_sub');
                        Session::forget('pending_upgrade_plan');
                        Session::flash('success', 'Abonnement activé. Les fonctionnalités de votre nouvelle formule sont disponibles.');

                        return Response::redirect(url('dashboard'));
                    }
                } catch (\Throwable $e) {
                    error_log('[platform.subscription.complete] ' . $e->getMessage());
                }
            }
            Session::flash('success', 'Paiement reçu. L’activation peut prendre quelques instants.');

            return Response::redirect(url('dashboard'));
        }

        Session::flash('success', 'Paiement reçu. L’activation de votre formule sera confirmée sous peu.');

        return Response::redirect(url('dashboard'));
    }

    private function canManageBilling(): bool
    {
        if (!$this->authService->check()) {
            return false;
        }
        $gate = Gate::getInstance();

        return $gate->allows('admin.organization')
            || $gate->allows('admin.access')
            || $gate->allows('admin.system')
            || $gate->allows('admin.settings.manage');
    }

    /**
     * @param list<array<string, mixed>> $plans
     * @return list<array<string, mixed>>
     */
    private function buildOfferCards(array $plans): array
    {
        $bySlug = [];
        foreach ($plans as $row) {
            $slug = strtolower(trim((string) ($row['slug'] ?? '')));
            if ($slug !== '') {
                $bySlug[$slug] = $row;
            }
        }
        $billingOk = BillingProvider::anyConfigured();
        $cards = [];
        foreach (['standard', 'pro', 'pro_plus'] as $slug) {
            $row = $bySlug[$slug] ?? null;
            if ($row === null) {
                continue;
            }
            $interval = '';
            $provider = BillingProvider::preferred();
            if ($provider === BillingProvider::PAYPAL) {
                if (trim((string) ($row['paypal_plan_id_monthly'] ?? '')) !== '') {
                    $interval = 'monthly';
                } elseif (trim((string) ($row['paypal_plan_id_yearly'] ?? '')) !== '') {
                    $interval = 'yearly';
                }
            } else {
                if (trim((string) ($row['stripe_price_id_monthly'] ?? '')) !== '') {
                    $interval = 'monthly';
                } elseif (trim((string) ($row['stripe_price_id_yearly'] ?? '')) !== '') {
                    $interval = 'yearly';
                }
            }
            $cards[] = [
                'slug' => $slug,
                'name' => (string) ($row['name'] ?? $slug),
                'interval' => $interval,
                'available' => $billingOk && $interval !== '',
            ];
        }

        return $cards;
    }
}
