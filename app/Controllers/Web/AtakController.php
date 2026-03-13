<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TenantAtakConfigRepository;
use App\Services\Tactical\AtakTokenService;

class AtakController
{
    public function __construct(
        private AtakTokenService $atakTokenService,
        private TenantAtakConfigRepository $atakConfigRepository
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $config = $tenantId ? $this->atakConfigRepository->getByTenantId($tenantId) : null;

        $nodeUrl = $config['node_url'] ?? env('NODE_ATAK_URL', '');
        if ($nodeUrl === '') {
            $nodeUrl = (isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'https') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ':3001';
        }
        $nodeUrl = rtrim($nodeUrl, '/');

        $jwtSecret = isset($config['jwt_secret']) && $config['jwt_secret'] !== '' ? $config['jwt_secret'] : null;
        $token = $this->atakTokenService->generate($jwtSecret);

        return Response::view('atak', [
            'atakToken' => $token,
            'nodeAtakUrl' => $nodeUrl,
            'atakConfig' => $config ? [
                'arma_server_host' => $config['arma_server_host'] ?? null,
                'arma_server_port' => $config['arma_server_port'] ?? null,
                'arma_mod_credentials' => $config['arma_mod_credentials'] ?? null,
                'instructions' => $config['instructions'] ?? null,
            ] : null,
        ]);
    }
}
