<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Csrf;
use App\Repositories\AtakMapRepository;
use App\Repositories\TenantAtakConfigRepository;

class AdminAtakConfigController
{
    public function __construct(
        private TenantAtakConfigRepository $atakConfigRepository,
        private AtakMapRepository $atakMapRepository
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $config = $this->atakConfigRepository->getByTenantId((int) $tenantId);
        $atakMaps = $this->atakMapRepository->getAll();
        return Response::view('layout.main', [
            'content' => 'admin.atak-config.index',
            'title' => 'Configuration ATAK / Arma',
            'config' => $config,
            'atakMaps' => $atakMaps,
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        if ($request->method() !== 'POST' || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Requête invalide.');
            return Response::redirect(url('admin/atak-config'));
        }

        $this->atakConfigRepository->createOrUpdate((int) $tenantId, [
            'node_url' => trim((string) $request->input('node_url', '')),
            'jwt_secret' => trim((string) $request->input('jwt_secret', '')),
            'arma_server_host' => trim((string) $request->input('arma_server_host', '')),
            'arma_server_port' => $request->input('arma_server_port') !== '' ? (int) $request->input('arma_server_port') : null,
            'arma_mod_credentials' => trim((string) $request->input('arma_mod_credentials', '')),
            'instructions' => trim((string) $request->input('instructions', '')),
            'default_map_slug' => trim((string) $request->input('default_map_slug', 'altis')) ?: 'altis',
        ]);

        Session::flash('success', 'Configuration ATAK / Arma enregistrée.');
        return Response::redirect(url('admin/atak-config'));
    }
}
