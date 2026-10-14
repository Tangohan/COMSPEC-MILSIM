<?php

declare(strict_types=1);

namespace App\Controllers\Admin\System;

use App\Core\Container;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\PlanGiftRepository;
use App\Repositories\SubscriptionPlanRepository;
use App\Repositories\TenantRepository;
use App\Services\Platform\PlanEndWarningService;
use App\Services\Platform\PlanGiftService;
use App\Support\PlanGift;

/**
 * /admin/formules-offertes : offrir une formule d'accès à une communauté (administrateur de la plateforme).
 */
final class SystemPlanGiftsController
{
    public function __construct(
        private ?PlanGiftRepository $gifts = null,
        private ?PlanGiftService $service = null,
        private ?TenantRepository $tenants = null,
        private ?SubscriptionPlanRepository $plans = null,
    ) {
        $this->gifts ??= new PlanGiftRepository();
        $this->tenants ??= Container::get(TenantRepository::class);
        $this->plans ??= Container::get(SubscriptionPlanRepository::class);
    }

    private function service(): PlanGiftService
    {
        return $this->service ??= new PlanGiftService($this->gifts, $this->tenants, $this->plans);
    }

    public function index(Request $request, array $params = []): Response
    {
        $error = null;
        $rows = [];
        $plans = [];
        try {
            $this->gifts->ensureSchema();
            $rows = $this->gifts->listRecent(100);
        } catch (\Throwable) {
            $error = 'Le registre des formules offertes n’est pas disponible (mise à jour de la base à lancer).';
        }
        try {
            $plans = $this->plans->allOrdered();
        } catch (\Throwable) {
            $error ??= 'Les formules d’accès ne sont pas disponibles sur cette base.';
        }

        $endWatch = ['ending' => [], 'grace' => [], 'gated' => []];
        $endConfig = null;
        try {
            $endService = new PlanEndWarningService(null, $this->tenants, $this->plans);
            $endConfig = $endService->config();
            $endWatch = $endService->watchList();
        } catch (\Throwable) {
            $error ??= 'Le suivi des fins de formule n’est pas disponible sur cette base.';
        }

        return Response::view('layout.main', [
            'title' => 'Formules offertes',
            'content' => 'admin.system.plan_gifts',
            'isPlatformAdminShell' => true,
            'backOfficePageCss' => ['platform-admin.css'],
            'planGiftRows' => $rows,
            'planGiftPlans' => $plans,
            'planGiftTenants' => $this->tenants->listOverviewForPlatform(),
            'planGiftSelectedTenant' => (int) $request->input('tenant_id', 0),
            'planGiftError' => $error,
            'planGiftDurations' => PlanGift::DURATIONS,
            'planGiftFormAction' => url('admin/formules-offertes'),
            'planEndWatch' => $endWatch,
            'planEndConfig' => $endConfig,
            'planEndSettingsAction' => url('admin/formules-offertes/reglages'),
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        $back = url('admin/formules-offertes');
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($back);
        }

        $tenantId = (int) $request->input('tenant_id', 0);
        $days = (int) $request->input('duration_days', 30);
        if (!in_array($days, PlanGift::DURATIONS, true)) {
            $days = 30;
        }
        $endsAt = PlanGift::resolveEndsAt($days, (string) $request->input('ends_on', ''));
        if ($endsAt === false) {
            Session::flash('error', 'La date de fin doit être une date à venir.');

            return Response::redirect($back . '?tenant_id=' . $tenantId);
        }

        try {
            $result = $this->service()->grant(
                $tenantId,
                (string) $request->input('plan_slug', ''),
                $endsAt,
                (string) $request->input('message_fr', ''),
                (string) $request->input('message_en', ''),
                self::actorId(),
            );
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());

            return Response::redirect($back . '?tenant_id=' . $tenantId);
        } catch (\Throwable) {
            Session::flash('error', 'L’offre n’a pas pu être enregistrée. Lancez la mise à jour de la base puis réessayez.');

            return Response::redirect($back . '?tenant_id=' . $tenantId);
        }

        $msg = 'Formule offerte. ';
        if ($result['recipients'] === 0) {
            $msg .= 'Aucun gestionnaire actif avec une adresse e-mail : personne ne sera prévenu.';
        } else {
            $msg .= $result['emails'] . ' e-mail(s) envoyé(s) sur ' . $result['recipients']
                . ' gestionnaire(s) ; la fenêtre s’affichera à leur prochaine connexion.';
        }
        Session::flash('success', $msg);

        return Response::redirect($back);
    }

    /** Réglages des fins de formule : avertissement (bandeau, e-mail) et période de grâce. */
    public function saveSettings(Request $request, array $params = []): Response
    {
        $back = url('admin/formules-offertes') . '#fins-de-formule';
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($back);
        }
        try {
            (new PlanEndWarningService())->saveConfig([
                'grace_enabled' => $request->input('grace_enabled', ''),
                'grace_days' => $request->input('grace_days', ''),
                'warning_days' => $request->input('warning_days', ''),
                'warning_email' => $request->input('warning_email', ''),
            ]);
            Session::flash('success', 'Réglages des fins de formule enregistrés.');
        } catch (\Throwable) {
            Session::flash('error', 'Les réglages n’ont pas pu être enregistrés (table platform_settings absente ?).');
        }

        return Response::redirect($back);
    }

    public function revoke(Request $request, array $params = []): Response
    {
        $back = url('admin/formules-offertes');
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($back);
        }
        $ok = false;
        try {
            $ok = $this->service()->revoke((int) ($params['id'] ?? 0), self::actorId());
        } catch (\Throwable) {
        }
        Session::flash($ok ? 'success' : 'error', $ok
            ? 'Offre retirée : la communauté revient à sa formule habituelle.'
            : 'Cette offre est introuvable ou déjà terminée.');

        return Response::redirect($back);
    }

    private static function actorId(): ?int
    {
        $id = Session::get('user_id');

        return is_numeric($id) && (int) $id > 0 ? (int) $id : null;
    }
}
