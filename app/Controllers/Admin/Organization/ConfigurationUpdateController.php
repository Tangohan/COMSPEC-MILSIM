<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TenantRepository;
use App\Services\Auth\AuthService;
use App\Services\ConfigurationUpdate\ConfigurationUpdateService;

/**
 * Centre de configuration post-mise à jour + page « Nouveautés ».
 */
final class ConfigurationUpdateController
{
    public function __construct(
        private AuthService $authService,
        private TenantRepository $tenantRepository,
        private ConfigurationUpdateService $configurationUpdates,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $tenant = $this->tenantRepository->findById($tenantId);
        if (!$tenant) {
            return Response::redirect(url('dashboard'));
        }

        $this->configurationUpdates->refreshFromData($tenantId, (int) Session::get('user_id') ?: null);
        $summary = $this->configurationUpdates->hubSummary($tenantId);
        $canManage = $this->canManage();

        return Response::view('layout.main', [
            'title' => 'Mise à niveau de l’organisation',
            'content' => 'admin.organization.configuration_updates',
            'tenant' => $tenant,
            'summary' => $summary,
            'canManage' => $canManage,
        ]);
    }

    public function novelties(Request $request, array $params = []): Response
    {
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $tenant = $this->tenantRepository->findById($tenantId);
        if (!$tenant) {
            return Response::redirect(url('dashboard'));
        }

        $userId = (int) Session::get('user_id') ?: null;
        $this->configurationUpdates->refreshFromData($tenantId, $userId);
        $summary = $this->configurationUpdates->hubSummary($tenantId);

        foreach ($summary['actionable'] as $item) {
            if (($item['status'] ?? '') === ConfigurationUpdateService::STATUS_PENDING) {
                $this->configurationUpdates->markSeen($tenantId, (string) $item['code'], $userId);
            }
        }

        Session::set('configuration_updates_intro_shown', true);

        return Response::view('layout.main', [
            'title' => 'Nouveautés de votre organisation',
            'content' => 'admin.organization.configuration_updates_novelties',
            'tenant' => $tenant,
            'summary' => $summary,
            'canManage' => $this->canManage(),
        ]);
    }

    public function continueToDashboard(Request $request, array $params = []): Response
    {
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Merci de réessayer.');

            return Response::redirect(url('back-office/nouveautes-organisation'));
        }

        $tenantId = (int) Session::get('tenant_id');
        $this->configurationUpdates->markIntroSeen($tenantId);
        Session::set('configuration_updates_intro_shown', true);

        return Response::redirect(url('back-office'));
    }

    public function dismiss(Request $request, array $params = []): Response
    {
        return $this->mutate($request, static function (ConfigurationUpdateService $svc, int $tenantId, string $code, ?int $userId): void {
            $remindDays = (int) ($_POST['remind_days'] ?? 0);
            $remindAt = null;
            if ($remindDays > 0) {
                $remindAt = date('Y-m-d H:i:s', strtotime('+' . $remindDays . ' days'));
            }
            $svc->dismiss($tenantId, $code, $userId, $remindAt);
        }, 'Configuration mise de côté.');
    }

    public function reopen(Request $request, array $params = []): Response
    {
        return $this->mutate($request, static function (ConfigurationUpdateService $svc, int $tenantId, string $code, ?int $userId): void {
            $svc->reopen($tenantId, $code, $userId);
        }, 'Configuration rouverte.');
    }

    public function start(Request $request, array $params = []): Response
    {
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Merci de réessayer.');

            return Response::redirect(url('back-office/mise-a-niveau'));
        }
        if (!$this->canManage()) {
            Session::flash('error', 'Vous n’avez pas l’autorisation de modifier cette configuration.');

            return Response::redirect(url('back-office/mise-a-niveau'));
        }

        $code = trim((string) $request->input('code', ''));
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id') ?: null;
        $this->configurationUpdates->markStarted($tenantId, $code, $userId);

        $items = $this->configurationUpdates->listForTenant($tenantId, true);
        foreach ($items as $item) {
            if ($item['code'] === $code) {
                return Response::redirect($item['configure_url']);
            }
        }

        return Response::redirect(url('back-office/mise-a-niveau'));
    }

    /**
     * @param callable(ConfigurationUpdateService, int, string, ?int): void $fn
     */
    private function mutate(Request $request, callable $fn, string $okMessage): Response
    {
        $redirect = url('back-office/mise-a-niveau');
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Merci de réessayer.');

            return Response::redirect($redirect);
        }
        if (!$this->canManage()) {
            Session::flash('error', 'Vous n’avez pas l’autorisation de modifier cette configuration.');

            return Response::redirect($redirect);
        }

        $code = trim((string) $request->input('code', ''));
        if ($code === '') {
            Session::flash('error', 'Configuration introuvable.');

            return Response::redirect($redirect);
        }

        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id') ?: null;
        $fn($this->configurationUpdates, $tenantId, $code, $userId);
        Session::flash('success', $okMessage);

        return Response::redirect($redirect);
    }

    private function canManage(): bool
    {
        $gate = Gate::getInstance();

        return $gate->allows('tenant.configuration.manage')
            || $gate->allows('admin.settings.manage')
            || $gate->allows('admin.organization')
            || $gate->allows('admin.access')
            || $gate->allows('site.support');
    }
}
