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
        $created = $this->apiKeys->createKey($tenantId, $name, ['events:read']);
        if ($created === null) {
            Session::flash('error', 'Impossible de créer la clé.');

            return Response::redirect(url('back-office/integrations'));
        }
        Session::flash('new_integration_key', $created['plain_key']);
        Session::flash('success', 'Clé créée : copiez-la maintenant, elle ne sera plus affichée en clair.');

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
}
