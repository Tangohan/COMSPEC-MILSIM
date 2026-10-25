<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TenantRepository;
use App\Services\Auth\AuthService;
use App\Services\Billing\PlanPaymentService;
use App\Services\Billing\SubscriptionPlanFeaturesCatalog;

/**
 * Souscription / changement de formule pour une communauté existante : paiement PayPal sur la page
 * de paiement d’Athena, activation par l’administration du site (plus de Stripe).
 */
final class PlatformSubscriptionController
{
    public function __construct(
        private AuthService $authService,
        private TenantRepository $tenantRepository,
        private PlanPaymentService $payments,
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

        $canManageBilling = $this->canManageBilling();
        $openPayment = null;
        if ($tenantId > 0 && $this->payments->schemaReady()) {
            $openPayment = \App\Core\Container::get(\App\Repositories\PlanPaymentRequestRepository::class)->latestOpenForTenant($tenantId);
        }

        return Response::view('layout.main', [
            'title' => 'Offres et formules',
            'content' => 'platform.upgrade',
            'feature' => $featureKey !== '' ? SubscriptionPlanFeaturesCatalog::featureLabel($featureKey) : 'offre',
            'featureKey' => $featureKey,
            'planName' => 'Standard ou Pro',
            'upgradeFrom' => $from,
            'paymentOffers' => $this->payments->offers(),
            'openPayment' => $openPayment,
            'canManageBilling' => $canManageBilling,
            'csrfToken' => Csrf::token(),
        ]);
    }

    /**
     * Choix de formule : crée une demande de paiement (référence ATH-…) et ouvre la page de paiement PayPal d’Athena.
     */
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
        if ($tenant === null || strtolower((string) ($tenant['slug'] ?? '')) === 'default') {
            Session::flash('error', 'Ouvrez d’abord la communauté pour laquelle vous souhaitez une formule.');

            return Response::redirect(url('dashboard'));
        }

        $user = $this->authService->user();
        try {
            $payment = $this->payments->createPlanRequest(
                $tenantId,
                (int) Session::get('user_id'),
                is_array($user) ? (string) ($user['email'] ?? '') : null,
                (string) $request->input('plan_slug', ''),
                strtolower(trim((string) $request->input('interval', 'monthly')))
            );
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());

            return Response::redirect(url('platform/upgrade'));
        }

        return Response::redirect(url('paiement/' . rawurlencode((string) $payment['reference'])));
    }

    /** Ancien retour Stripe / PayPal : la page de paiement d’Athena remplace ces parcours. */
    public function complete(Request $request, array $params = []): Response
    {
        return Response::redirect(url('platform/upgrade'));
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
}
