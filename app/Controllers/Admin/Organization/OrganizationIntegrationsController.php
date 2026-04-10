<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TenantApiKeyRepository;
use App\Services\Auth\AuthService;

final class OrganizationIntegrationsController
{
    private const AVAILABLE_SCOPES = ['events:read'];

    public function __construct(
        private AuthService $auth,
        private TenantApiKeyRepository $apiKeys,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $user = $this->auth->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }
        $gate = Gate::getInstance();
        if (!$gate->allows('admin.organization')) {
            return (new Response())->setStatusCode(403)->setBody('Accès refusé.');
        }
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        if ($tenantId < 2) {
            return Response::redirect(url('dashboard'));
        }
        $keys = $this->apiKeys->listForTenant($tenantId);
        $flashKey = Session::getFlash('new_integration_key');

        return Response::view('layout.main', [
            'title' => 'Intégrations — clés d’accès',
            'content' => 'admin.organization.integrations',
            'integration_keys' => $keys,
            'new_integration_key_plain' => is_string($flashKey) ? $flashKey : null,
            'available_scopes' => self::AVAILABLE_SCOPES,
        ]);
    }

    public function create(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/integrations'));
        }
        $user = $this->auth->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }
        $gate = Gate::getInstance();
        if (!$gate->allows('admin.organization')) {
            return (new Response())->setStatusCode(403)->setBody('Accès refusé.');
        }
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        if ($tenantId < 2 || !$this->apiKeys->tableExists()) {
            Session::flash('error', 'Fonction indisponible pour cette communauté.');

            return Response::redirect(url('back-office/integrations'));
        }
        $name = trim((string) $request->input('name', 'Intégration'));
        if ($name === '') {
            $name = 'Intégration';
        }
        $quota = (int) $request->input('quota_per_day', 10000);
        if ($quota < 100) {
            $quota = 100;
        } elseif ($quota > 500000) {
            $quota = 500000;
        }
        $scopes = $this->sanitizeScopes($request->input('scopes', []));
        if ($scopes === []) {
            $scopes = self::AVAILABLE_SCOPES;
        }
        $created = $this->apiKeys->createKey($tenantId, $name, $scopes, $quota);
        if ($created === null) {
            Session::flash('error', 'Impossible de créer la clé.');

            return Response::redirect(url('back-office/integrations'));
        }
        Session::flash('new_integration_key', $created['plain_key']);
        Session::flash('success', 'Clé créée : copiez-la maintenant, elle ne sera plus affichée en clair.');

        return Response::redirect(url('back-office/integrations'));
    }

    public function update(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/integrations'));
        }
        $user = $this->auth->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }
        $gate = Gate::getInstance();
        if (!$gate->allows('admin.organization')) {
            return (new Response())->setStatusCode(403)->setBody('Accès refusé.');
        }
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        $id = (int) ($params['id'] ?? 0);
        $name = trim((string) $request->input('name', ''));
        $quota = (int) $request->input('quota_per_day', 10000);
        if ($tenantId < 2 || $id < 1 || $name === '') {
            Session::flash('error', 'Paramètres invalides.');

            return Response::redirect(url('back-office/integrations'));
        }
        if ($quota < 100) {
            $quota = 100;
        } elseif ($quota > 500000) {
            $quota = 500000;
        }
        $scopes = $this->sanitizeScopes($request->input('scopes', []));
        if ($scopes === []) {
            $scopes = self::AVAILABLE_SCOPES;
        }
        $this->apiKeys->updateKeySettings($id, $tenantId, $name, $quota, $scopes);
        Session::flash('success', 'Paramètres de la clé mis à jour.');

        return Response::redirect(url('back-office/integrations'));
    }

    public function revoke(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/integrations'));
        }
        $user = $this->auth->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }
        $gate = Gate::getInstance();
        if (!$gate->allows('admin.organization')) {
            return (new Response())->setStatusCode(403)->setBody('Accès refusé.');
        }
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        $id = (int) ($params['id'] ?? 0);
        if ($tenantId > 1 && $id > 0) {
            $this->apiKeys->revoke($id, $tenantId);
            Session::flash('success', 'Clé révoquée.');
        }

        return Response::redirect(url('back-office/integrations'));
    }

    public function rotate(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/integrations'));
        }
        $user = $this->auth->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }
        $gate = Gate::getInstance();
        if (!$gate->allows('admin.organization')) {
            return (new Response())->setStatusCode(403)->setBody('Accès refusé.');
        }
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        $id = (int) ($params['id'] ?? 0);
        if ($tenantId < 2 || $id < 1) {
            Session::flash('error', 'Paramètres invalides.');

            return Response::redirect(url('back-office/integrations'));
        }
        $current = $this->apiKeys->findForTenant($id, $tenantId);
        if (!is_array($current) || !empty($current['revoked_at'])) {
            Session::flash('error', 'Clé introuvable ou déjà révoquée.');

            return Response::redirect(url('back-office/integrations'));
        }
        $scopesRaw = (string) ($current['scopes_json'] ?? '');
        $decodedScopes = json_decode($scopesRaw, true);
        $scopes = is_array($decodedScopes) ? $this->sanitizeScopes($decodedScopes) : self::AVAILABLE_SCOPES;
        if ($scopes === []) {
            $scopes = self::AVAILABLE_SCOPES;
        }
        $newKey = $this->apiKeys->createKey(
            $tenantId,
            (string) ($current['name'] ?? 'Intégration'),
            $scopes,
            (int) ($current['quota_per_day'] ?? 10000)
        );
        if ($newKey === null) {
            Session::flash('error', 'Impossible de générer une nouvelle clé.');

            return Response::redirect(url('back-office/integrations'));
        }
        $this->apiKeys->revoke($id, $tenantId);
        Session::flash('new_integration_key', $newKey['plain_key']);
        Session::flash('success', 'Clé tournée avec succès. Mettez à jour vos services immédiatement.');

        return Response::redirect(url('back-office/integrations'));
    }

    /**
     * @param mixed $rawScopes
     * @return list<string>
     */
    private function sanitizeScopes(mixed $rawScopes): array
    {
        $scopes = is_array($rawScopes) ? $rawScopes : [$rawScopes];
        $allowed = [];
        foreach ($scopes as $scope) {
            $value = trim((string) $scope);
            if ($value !== '' && in_array($value, self::AVAILABLE_SCOPES, true)) {
                $allowed[] = $value;
            }
        }

        return array_values(array_unique($allowed));
    }
}
