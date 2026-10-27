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
use App\Services\Community\TenantArchiveState;
use App\Services\Community\TenantRemovalService;
use App\Services\Community\TenantSlugService;
use App\Services\Community\TenantTypeConfig;
use App\Services\Community\TenantTypeSwitchService;

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
        private ?TenantTypeSwitchService $tenantTypeSwitch = null,
        private ?TenantRemovalService $removal = null,
    ) {
        $this->subscriptionPlans ??= new SubscriptionPlanRepository();
        $this->audit ??= new AuditService();
        $this->tenantTypeSwitch ??= new TenantTypeSwitchService($this->tenants);
        $this->removal ??= new TenantRemovalService();
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
            'platformCorrelationUrl' => url('admin/system/donnees/correlations'),
            'backOfficePageCss' => ['platform-admin.css', 'admin-data-registry.css'],
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

        $plans = [];
        $plansError = null;
        if ($this->subscriptionPlans->tableExists()) {
            try {
                $plans = $this->subscriptionPlans->allOrdered();
            } catch (\Throwable) {
                $plansError = 'Les formules d’accès ne sont pas disponibles sur cette base.';
            }
        } else {
            $plansError = 'Les formules d’accès ne sont pas encore disponibles (mise à jour de la base à lancer).';
        }

        $settings = $this->tenants->getSettings($tenantId);
        $founderTrialEndsAt = isset($settings['founder_trial_ends_at'])
            ? trim((string) $settings['founder_trial_ends_at'])
            : '';

        $name = (string) ($tenant['name'] ?? 'Communauté');

        return Response::view('layout.main', [
            'title' => 'Communauté — ' . $name,
            'content' => 'admin.system.tenants_plan_form',
            'platformTenant' => $tenant,
            'platformTenantTypes' => TenantTypeConfig::availableTypes(),
            'platformTenantCurrentType' => TenantTypeConfig::normalizeType((string) ($tenant['tenant_type'] ?? 'full')),
            'platformSubscriptionPlans' => $plans,
            'platformSubscriptionPlansError' => $plansError,
            'platformSubscriptionStatusLabels' => self::subscriptionStatusLabels(),
            'platformFounderTrialEndsAt' => $founderTrialEndsAt !== '' ? $founderTrialEndsAt : null,
            'platformTenantIdentityFormAction' => url('admin/tenants/' . $tenantId . '/identity'),
            'platformTenantTypeFormAction' => url('admin/tenants/' . $tenantId . '/profil'),
            'platformTenantPlanFormAction' => url('admin/tenants/' . $tenantId . '/plan'),
            'platformTenantArchive' => TenantArchiveState::info($tenant),
            'platformTenantRemovalUrl' => url('admin/tenants/' . $tenantId . '/retrait'),
            'backOfficePageCss' => ['platform-admin.css'],
        ]);
    }

    public function updateIdentity(Request $request, array $params = []): Response
    {
        $tenantId = (int) ($params['id'] ?? 0);
        $editUrl = url('admin/tenants/' . $tenantId . '/edit');
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($editUrl);
        }

        $before = $tenantId > 1 ? $this->tenants->findById($tenantId) : null;
        if ($before === null) {
            Session::flash('error', 'Communauté introuvable.');

            return Response::redirect(url('admin/tenants'));
        }

        $newName = trim((string) $request->input('tenant_name'));
        if ($newName === '') {
            Session::flash('error', 'Le nom affiché est obligatoire.');

            return Response::redirect($editUrl . '#identite');
        }
        $newName = mb_substr($newName, 0, 255);

        $newSlug = strtolower(trim((string) $request->input('tenant_slug')));
        $oldSlug = (string) ($before['slug'] ?? '');
        if ($newSlug === '') {
            Session::flash('error', 'L’adresse courte de la page publique est obligatoire.');

            return Response::redirect($editUrl . '#identite');
        }
        if ($newSlug !== $oldSlug) {
            if (!TenantSlugService::isValidFormat($newSlug)) {
                Session::flash('error', 'L’adresse courte est invalide (lettres minuscules, chiffres, tirets, 50 caractères au plus).');

                return Response::redirect($editUrl . '#identite');
            }
            if (TenantSlugService::isReserved($newSlug)) {
                Session::flash('error', 'Cette adresse courte est réservée.');

                return Response::redirect($editUrl . '#identite');
            }
            if ($this->tenants->isSlugTakenByOther($tenantId, $newSlug)) {
                Session::flash('error', 'Cette adresse courte est déjà utilisée par une autre communauté.');

                return Response::redirect($editUrl . '#identite');
            }
        }

        $this->tenants->updateName($tenantId, $newName);
        if ($newSlug !== $oldSlug) {
            $this->tenants->updateSlug($tenantId, $newSlug);
        }

        $this->audit->logChange(
            AuditAction::TENANT_IDENTITY_UPDATED,
            $tenantId,
            $this->actorId(),
            'tenant',
            $tenantId,
            [
                'name' => (string) ($before['name'] ?? ''),
                'slug' => $oldSlug,
            ],
            [
                'name' => $newName,
                'slug' => $newSlug,
            ],
        );

        Session::flash('success', 'Identité de la communauté mise à jour.');

        return Response::redirect($editUrl . '#identite');
    }

    public function updateType(Request $request, array $params = []): Response
    {
        $tenantId = (int) ($params['id'] ?? 0);
        $editUrl = url('admin/tenants/' . $tenantId . '/edit') . '#profil';
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($editUrl);
        }

        $before = $tenantId > 1 ? $this->tenants->findById($tenantId) : null;
        if ($before === null) {
            Session::flash('error', 'Communauté introuvable.');

            return Response::redirect(url('admin/tenants'));
        }

        $newType = TenantTypeConfig::normalizeType((string) $request->input('tenant_type', ''));
        if ((string) $request->input('confirm_type_change', '') !== '1') {
            Session::flash('error', 'Cochez la confirmation avant d’appliquer le profil de la communauté.');

            return Response::redirect($editUrl);
        }

        try {
            $result = $this->tenantTypeSwitch->switchType($tenantId, $newType, true);
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            if ($e instanceof \RuntimeException && $msg !== '' && !preg_match('/SQLSTATE|Unknown column|PDOException/i', $msg)) {
                Session::flash('error', $msg);
            } else {
                Session::flash('error', 'Impossible de modifier le profil de la communauté. Réessayez ou contactez le support.');
            }

            return Response::redirect($editUrl);
        }

        $this->audit->logChange(
            AuditAction::TENANT_TYPE_ASSIGNED,
            $tenantId,
            $this->actorId(),
            'tenant',
            $tenantId,
            [
                'tenant_type' => (string) ($result['from'] ?? ''),
            ],
            [
                'tenant_type' => (string) ($result['to'] ?? $newType),
                'changed' => !empty($result['changed']),
                'reapplied' => !empty($result['reapplied']),
            ],
        );

        if (!empty($result['changed'])) {
            Session::flash(
                'success',
                'Profil mis à jour : « ' . TenantTypeConfig::label((string) $result['from']) . ' » → « '
                . TenantTypeConfig::label((string) $result['to']) . ' ». Les menus et accès ont été ajustés.'
            );
        } else {
            Session::flash(
                'success',
                'Profil « ' . TenantTypeConfig::label($newType) . ' » réappliqué : menus et permissions ont été réalignés.'
            );
        }

        return Response::redirect($editUrl);
    }

    public function updatePlan(Request $request, array $params = []): Response
    {
        $tenantId = (int) ($params['id'] ?? 0);
        $editUrl = url('admin/tenants/' . max(0, $tenantId) . '/edit') . '#formule';
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($tenantId > 1 ? $editUrl : url('admin/tenants'));
        }

        $before = $tenantId > 1 ? $this->tenants->findById($tenantId) : null;
        if ($before === null) {
            Session::flash('error', 'Communauté introuvable.');

            return Response::redirect(url('admin/tenants'));
        }

        if (!$this->subscriptionPlans->tableExists()) {
            Session::flash('error', 'Les formules d’accès ne sont pas disponibles.');

            return Response::redirect($editUrl);
        }

        $planSlug = strtolower(trim((string) $request->input('plan_slug', '')));
        $plan = $planSlug !== '' ? $this->subscriptionPlans->findBySlug($planSlug) : null;
        if ($plan === null) {
            Session::flash('error', 'Choisissez une formule d’accès valide.');

            return Response::redirect($editUrl);
        }

        $status = strtolower(trim((string) $request->input('subscription_status', 'none')));
        if (!in_array($status, self::SUBSCRIPTION_STATUSES, true)) {
            Session::flash('error', 'Le statut d’abonnement choisi n’est pas reconnu.');

            return Response::redirect($editUrl);
        }

        $this->tenants->updatePlanAssignment($tenantId, $planSlug, $status);

        $endFounderTrial = (string) $request->input('end_founder_trial', '') === '1';
        if ($endFounderTrial) {
            $this->tenants->mergeSettings($tenantId, ['founder_trial_ends_at' => null]);
        }

        $this->audit->logChange(
            AuditAction::TENANT_PLAN_ASSIGNED,
            $tenantId,
            $this->actorId(),
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

        return Response::redirect($editUrl);
    }

    /**
     * Archiver, restaurer ou supprimer définitivement : récapitulatif de ce qui part et de ce qui reste.
     */
    public function removal(Request $request, array $params = []): Response
    {
        $tenantId = (int) ($params['id'] ?? 0);
        $preview = $tenantId > 1 ? $this->removal->preview($tenantId) : null;
        if ($preview === null) {
            Session::flash('error', 'Communauté introuvable.');

            return Response::redirect(url('admin/tenants'));
        }
        $name = (string) ($preview['tenant']['name'] ?? 'Communauté');

        return Response::view('layout.main', [
            'title' => 'Retirer une communauté — ' . $name,
            'content' => 'admin.system.tenant_removal',
            'removalPreview' => $preview,
            'removalArchiveAction' => url('admin/tenants/' . $tenantId . '/archiver'),
            'removalRestoreAction' => url('admin/tenants/' . $tenantId . '/restaurer'),
            'removalDeleteAction' => url('admin/tenants/' . $tenantId . '/supprimer'),
            'removalEditUrl' => url('admin/tenants/' . $tenantId . '/edit'),
            'backOfficePageCss' => ['platform-admin.css'],
        ]);
    }

    public function archive(Request $request, array $params = []): Response
    {
        $tenantId = (int) ($params['id'] ?? 0);
        $back = url('admin/tenants/' . max(0, $tenantId) . '/retrait');
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($back);
        }
        $before = $tenantId > 1 ? $this->tenants->findById($tenantId) : null;
        if ($before === null) {
            Session::flash('error', 'Communauté introuvable.');

            return Response::redirect(url('admin/tenants'));
        }
        if ((string) $request->input('confirm_archive', '') !== '1') {
            Session::flash('error', 'Cochez la confirmation avant d’archiver la communauté.');

            return Response::redirect($back);
        }
        $reason = trim((string) $request->input('archive_reason', ''));
        $result = $this->removal->archive($tenantId, $this->actorId(), $reason);
        if (!$result['ok']) {
            Session::flash('error', (string) ($result['error'] ?? 'Archivage impossible.'));

            return Response::redirect($back);
        }

        $this->audit->logChange(
            AuditAction::TENANT_ARCHIVED,
            $tenantId,
            $this->actorId(),
            'tenant',
            $tenantId,
            ['name' => (string) ($before['name'] ?? ''), 'slug' => (string) ($before['slug'] ?? ''), 'archived' => false],
            [
                'archived' => true,
                'reason' => $reason,
                'memberships_archived' => (int) ($result['memberships'] ?? 0),
                'accounts_moved_to_other_community' => (int) ($result['moved_other'] ?? 0),
                'accounts_moved_to_no_community' => (int) ($result['moved_no_org'] ?? 0),
            ],
        );

        $msg = 'Communauté archivée : ' . (int) ($result['memberships'] ?? 0) . ' appartenance(s) mises en sommeil, '
            . ((int) ($result['moved_other'] ?? 0) + (int) ($result['moved_no_org'] ?? 0)) . ' compte(s) relogé(s). Aucune donnée supprimée.';
        if ((int) ($result['not_moved'] ?? 0) > 0) {
            $msg .= ' ' . (int) $result['not_moved'] . ' compte(s) n’ont pas pu être relogés (voir le récapitulatif).';
        }
        Session::flash('success', $msg);

        return Response::redirect($back);
    }

    public function restore(Request $request, array $params = []): Response
    {
        $tenantId = (int) ($params['id'] ?? 0);
        $back = url('admin/tenants/' . max(0, $tenantId) . '/retrait');
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($back);
        }
        $before = $tenantId > 1 ? $this->tenants->findById($tenantId) : null;
        if ($before === null) {
            Session::flash('error', 'Communauté introuvable.');

            return Response::redirect(url('admin/tenants'));
        }
        $result = $this->removal->restore($tenantId);
        if (!$result['ok']) {
            Session::flash('error', (string) ($result['error'] ?? 'Restauration impossible.'));

            return Response::redirect($back);
        }

        $this->audit->logChange(
            AuditAction::TENANT_RESTORED,
            $tenantId,
            $this->actorId(),
            'tenant',
            $tenantId,
            ['archived' => true, 'archive' => TenantArchiveState::info($before)],
            ['archived' => false, 'memberships_restored' => (int) ($result['memberships'] ?? 0), 'moved_back' => (int) ($result['moved_back'] ?? 0)],
        );
        Session::flash(
            'success',
            'Communauté restaurée : ' . (int) ($result['memberships'] ?? 0) . ' appartenance(s) réactivée(s), '
            . (int) ($result['moved_back'] ?? 0) . ' compte(s) ramené(s) dans la communauté.'
        );

        return Response::redirect($back);
    }

    public function destroy(Request $request, array $params = []): Response
    {
        $tenantId = (int) ($params['id'] ?? 0);
        $back = url('admin/tenants/' . max(0, $tenantId) . '/retrait');
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($back);
        }
        if ($tenantId < 2) {
            Session::flash('error', 'Communauté introuvable.');

            return Response::redirect(url('admin/tenants'));
        }
        if ((string) $request->input('confirm_delete', '') !== '1') {
            Session::flash('error', 'Cochez la confirmation avant la suppression définitive.');

            return Response::redirect($back);
        }

        $result = $this->removal->delete($tenantId, (string) $request->input('confirm_slug', ''));
        if (!$result['ok']) {
            Session::flash('error', (string) ($result['error'] ?? 'Suppression impossible.'));

            return Response::redirect($back);
        }

        $tenant = is_array($result['tenant'] ?? null) ? $result['tenant'] : [];
        // Journal plateforme (tenant_id NULL) : l’entrée survit à la communauté.
        $this->audit->logChange(
            AuditAction::TENANT_DELETED,
            null,
            $this->actorId(),
            'tenant',
            $tenantId,
            [
                'name' => (string) ($tenant['name'] ?? ''),
                'slug' => (string) ($tenant['slug'] ?? ''),
                'tenant_type' => (string) ($tenant['tenant_type'] ?? ''),
                'plan_slug' => (string) ($tenant['plan_slug'] ?? ''),
                'created_at' => (string) ($tenant['created_at'] ?? ''),
                'archive' => TenantArchiveState::info($tenant),
            ],
            [
                'deleted' => true,
                'backup_file' => (string) ($result['backup'] ?? ''),
                'rows_deleted' => (int) ($result['rows_deleted'] ?? 0),
                'tables' => (int) ($result['tables'] ?? 0),
                'accounts' => $result['accounts'] ?? [],
            ],
        );

        Session::flash(
            'success',
            'Communauté « ' . (string) ($tenant['name'] ?? '') . ' » supprimée définitivement. Sauvegarde : '
            . (string) ($result['backup'] ?? '') . '. Les comptes des joueurs sont conservés.'
        );

        return Response::redirect(url('admin/tenants'));
    }

    private function actorId(): ?int
    {
        $actorId = Session::get('user_id');
        $actorId = is_numeric($actorId) ? (int) $actorId : null;
        if ($actorId !== null && $actorId < 1) {
            return null;
        }

        return $actorId;
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
