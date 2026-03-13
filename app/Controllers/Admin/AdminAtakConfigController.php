<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Csrf;
use App\Repositories\TenantAtakConfigRepository;

class AdminAtakConfigController
{
    public function __construct(
        private TenantAtakConfigRepository $atakConfigRepository
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $config = $this->atakConfigRepository->getByTenantId((int) $tenantId);
        return Response::view('layout.main', [
            'content' => 'admin.atak-config.index',
            'title' => 'Configuration ATAK / Arma',
            'config' => $config,
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        if ($request->method() !== 'POST' || !Csrf::validate($request->post('_csrf_token'))) {
            Session::flash('error', 'Requête invalide.');
            return Response::redirect(url('admin/atak-config'));
        }

        $this->atakConfigRepository->createOrUpdate((int) $tenantId, [
            'node_url' => trim((string) $request->post('node_url', '')),
            'jwt_secret' => trim((string) $request->post('jwt_secret', '')),
            'arma_server_host' => trim((string) $request->post('arma_server_host', '')),
            'arma_server_port' => $request->post('arma_server_port') !== '' ? (int) $request->post('arma_server_port') : null,
            'arma_mod_credentials' => trim((string) $request->post('arma_mod_credentials', '')),
            'instructions' => trim((string) $request->post('instructions', '')),
        ]);

        Session::flash('success', 'Configuration ATAK / Arma enregistrée.');
        return Response::redirect(url('admin/atak-config'));
    }
}
