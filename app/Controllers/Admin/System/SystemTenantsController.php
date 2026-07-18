<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\SubscriptionPlanRepository;
use App\Repositories\TenantRepository;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;

final class SystemTenantsController
{
    /** @var list<string> */
    private const SUBSCRIPTION_STATUSES = [
        'none',
        'active',
        'trialing',
        'past_due',
        'canceled',
        'unpaid',
    ];

    public function __construct(
        private TenantRepository $tenants,
        private ?SubscriptionPlanRepository $subscriptionPlans = null,
        private ?AuditService $audit = null,
    ) {
        $this->subscriptionPlans ??= new SubscriptionPlanRepository();
        $this->audit ??= new AuditService();
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantRows = $this->tenants->listOverviewForPlatform();
        $planRows = [];
        $plansError = null;
        $planNameBySlug = [];
        try {
            $planRows = $this->subscriptionPlans->allOrdered();
            foreach ($planRows as $plan) {
                $slug = (string) ($plan['slug'] ?? '');
                if ($slug !== '') {
                    $planNameBySlug[$slug] = (string) ($plan['name'] ?? $slug);
                }
            }
        } catch (\Throwable) {
            $plansError = 'Les formules d’accès ne sont pas disponibles sur cette base (migration ou table manquante).';
        }

        return Response::view('layout.main', [
            'title' => 'Annuaire des communautés',
            'content' => 'admin.system.tenants_index',
            'platformTenants' => $tenantRows,
            'platformSubscriptionPlans' => $planRows,
            'platformSubscriptionPlansError' => $plansError,
            'platformPlanNameBySlug' => $planNameBySlug,
            'platformSubscriptionStatusLabels' => self::subscriptionStatusLabels(),
        ]);
    }

    public function edit(Request $request, array $params = []): Response
    {
        $tenantId = (int) ($params['id'] ?? 0);
        $tenant = $tenantId > 1 ? $this->tenants->findById($tenantId) : null;
        if ($tenant === null) {
            Session::flash('error', 'Communauté introuvable.');

            return Response::redirect(url('admin/tenants'));
        }

        if (!$this->subscriptionPlans->tableExists()) {
            Session::flash('error', 'Les formules d’accès ne sont pas disponibles (migrations).');

            return Response::redirect(url('admin/tenants'));
        }

        $settings = $this->tenants->getSettings($tenantId);
        $founderTrialEndsAt = isset($settings['founder_trial_ends_at'])
            ? trim((string) $settings['founder_trial_ends_at'])
            : '';

        return Response::view('layout.main', [
            'title' => 'Formule — ' . (string) ($tenant['name'] ?? ''),
            'content' => 'admin.system.tenants_plan_form',
            'platformTenant' => $tenant,
            'platformSubscriptionPlans' => $this->subscriptionPlans->allOrdered(),
            'platformSubscriptionStatusLabels' => self::subscriptionStatusLabels(),
            'platformFounderTrialEndsAt' => $founderTrialEndsAt !== '' ? $founderTrialEndsAt : null,
            'platformTenantPlanFormAction' => url('admin/tenants/' . $tenantId . '/plan'),
        ]);
    }

    public function updatePlan(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('admin/tenants'));
        }

        $tenantId = (int) ($params['id'] ?? 0);
        $before = $tenantId > 1 ? $this->tenants->findById($tenantId) : null;
        if ($before === null) {
            Session::flash('error', 'Communauté introuvable.');

            return Response::redirect(url('admin/tenants'));
        }

        if (!$this->subscriptionPlans->tableExists()) {
            Session::flash('error', 'Les formules d’accès ne sont pas disponibles.');

            return Response::redirect(url('admin/tenants'));
        }

        $planSlug = strtolower(trim((string) $request->input('plan_slug', '')));
        $plan = $planSlug !== '' ? $this->subscriptionPlans->findBySlug($planSlug) : null;
        if ($plan === null) {
            Session::flash('error', 'Choisissez une formule d’accès valide.');

            return Response::redirect(url('admin/tenants/' . $tenantId . '/edit'));
        }

        $status = strtolower(trim((string) $request->input('subscription_status', 'none')));
        if (!in_array($status, self::SUBSCRIPTION_STATUSES, true)) {
            Session::flash('error', 'Le statut d’abonnement choisi n’est pas reconnu.');

            return Response::redirect(url('admin/tenants/' . $tenantId . '/edit'));
        }

        $this->tenants->updatePlanAssignment($tenantId, $planSlug, $status);

        $endFounderTrial = (string) $request->input('end_founder_trial', '') === '1';
        if ($endFounderTrial) {
            $this->tenants->mergeSettings($tenantId, ['founder_trial_ends_at' => null]);
        }

        $after = $this->tenants->findById($tenantId) ?? [];
        $actorId = Session::get('user_id');
        $actorId = is_numeric($actorId) ? (int) $actorId : null;
        if ($actorId !== null && $actorId < 1) {
            $actorId = null;
        }

        $this->audit->logChange(
            AuditAction::TENANT_PLAN_ASSIGNED,
            $tenantId,
            $actorId,
            'tenant',
            $tenantId,
            [
                'plan_slug' => (string) ($before['plan_slug'] ?? ''),
                'subscription_status' => (string) ($before['subscription_status'] ?? ''),
                'end_founder_trial' => false,
            ],
            [
                'plan_slug' => $planSlug,
                'subscription_status' => $status,
                'end_founder_trial' => $endFounderTrial,
            ],
        );

        Session::flash('success', 'Formule d’accès mise à jour pour cette communauté.');

        return Response::redirect(url('admin/tenants'));
    }

    /**
     * @return array<string, string>
     */
    public static function subscriptionStatusLabels(): array
    {
        return [
            'none' => 'Sans abonnement payant',
            'active' => 'Abonnement actif',
            'trialing' => 'Période d’essai',
            'past_due' => 'Paiement en retard',
            'canceled' => 'Résilié',
            'unpaid' => 'Impayé',
        ];
    }
}
