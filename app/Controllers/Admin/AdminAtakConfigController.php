<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Csrf;
use App\Repositories\AtakMapRepository;
use App\Repositories\TenantAtakConfigRepository;
use App\Services\Tactical\AtakActivityLogService;
use App\Support\ComspecApiKeyAuth;

class AdminAtakConfigController
{
    public function __construct(
        private TenantAtakConfigRepository $atakConfigRepository,
        private AtakMapRepository $atakMapRepository,
        private ?AtakActivityLogService $activityLog = null,
    ) {
        $this->activityLog ??= new AtakActivityLogService();
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $tenantId = (int) $tenantId;
        $config = $this->atakConfigRepository->getByTenantId($tenantId);
        $atakMaps = $this->atakMapRepository->getAll();
        $platformKeyConfigured = ComspecApiKeyAuth::expectedSecret() !== '';
        $accessKeyPrefix = is_array($config) ? trim((string) ($config['access_key_prefix'] ?? '')) : '';
        $accessKeyGeneratedAt = is_array($config) ? ($config['access_key_generated_at'] ?? null) : null;
        $hasTenantAccessKey = is_array($config) && trim((string) ($config['access_key'] ?? '')) !== '';
        $newAccessKey = Session::getFlash('new_atak_access_key');
        $authEvents = $this->activityLog->listRecent($tenantId, AtakActivityLogService::AUTH_MAP_ID, 30);

        return Response::view('layout.main', [
            'content' => 'admin.atak-config.index',
            'title' => 'Configuration ATAK / Tacmap',
            'config' => $config,
            'atakMaps' => $atakMaps,
            'tenantId' => $tenantId,
            'platformKeyConfigured' => $platformKeyConfigured,
            'accessKeyPrefix' => $accessKeyPrefix,
            'accessKeyGeneratedAt' => $accessKeyGeneratedAt,
            'hasTenantAccessKey' => $hasTenantAccessKey,
            'newAccessKeyPlain' => is_string($newAccessKey) ? $newAccessKey : null,
            'authEvents' => $authEvents,
            'portalBaseUrl' => rtrim(url(''), '/'),
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

        Session::flash('success', 'Configuration ATAK / Tacmap enregistrée.');
        return Response::redirect(url('admin/atak-config'));
    }

    /** Génère ou régénère la clé d’accès Overwatch pour la communauté courante. */
    public function regenerateAccessKey(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        if ($request->method() !== 'POST' || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Requête invalide.');

            return Response::redirect(url('admin/atak-config'));
        }

        $created = $this->atakConfigRepository->generateAccessKey((int) $tenantId);
        if ($created === null) {
            Session::flash('error', 'Impossible de générer la clé d’accès. Réessayez ou contactez le support plateforme.');

            return Response::redirect(url('admin/atak-config'));
        }

        Session::flash('new_atak_access_key', $created['plain_key']);
        Session::flash('success', 'Nouvelle clé d’accès générée. Copiez-la maintenant : elle ne sera plus affichée en entier ensuite.');
        $this->activityLog?->recordAuthAttempt(
            (int) $tenantId,
            true,
            'Clé d’accès régénérée par un administrateur',
            ['reason' => 'key_regenerated', 'method' => 'admin'],
            null
        );

        return Response::redirect(url('admin/atak-config'));
    }
}
