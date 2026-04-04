<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AtakMapRepository;
use App\Repositories\TenantAtakConfigRepository;
use App\Repositories\UserProfileRepository;
use App\Repositories\UserRepository;
use App\Services\Auth\AuthService;
use App\Services\Platform\FeatureGateService;
use App\Services\Tactical\AtakTokenService;

class AtakController
{
    public function __construct(
        private AtakTokenService $atakTokenService,
        private TenantAtakConfigRepository $atakConfigRepository,
        private AtakMapRepository $atakMapRepository,
        private AuthService $authService,
        private UserProfileRepository $userProfileRepository,
        private UserRepository $userRepository,
        private FeatureGateService $featureGate
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId && !$this->featureGate->allows($tenantId, 'atak')) {
            return Response::view('layout.main', [
                'title' => 'ATAK / Overwatch',
                'content' => 'platform.upgrade',
                'feature' => 'atak',
                'planName' => 'standard',
            ]);
        }
        $config = $tenantId ? $this->atakConfigRepository->getByTenantId($tenantId) : null;

        $nodeUrl = $config['node_url'] ?? env('NODE_ATAK_URL', '');
        $nodeUrl = $nodeUrl === '' ? '' : rtrim($nodeUrl, '/');

        $visitorIp = $_SERVER['REMOTE_ADDR'] ?? '';
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $visitorIp = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        }

        $jwtSecret = isset($config['jwt_secret']) && $config['jwt_secret'] !== '' ? $config['jwt_secret'] : null;
        $token = $this->atakTokenService->generate($jwtSecret);

        $atakMap = $tenantId ? $this->atakMapRepository->getDefaultForTenant($tenantId) : $this->atakMapRepository->getBySlug('altis');
        $atakMapsList = $this->atakMapRepository->getAll();
        $atakWorkspaces = [];
        foreach ($atakMapsList as $m) {
            $atakWorkspaces[] = [
                'mapId' => (int) ($m['id'] ?? 0),
                'label' => $m['label'] ?? $m['slug'] ?? ('Carte ' . ($m['id'] ?? '')),
            ];
        }
        if (empty($atakWorkspaces)) {
            $atakWorkspaces[] = ['mapId' => 1, 'label' => 'Principal'];
        }
        $atakDefaultMapId = $atakMap ? (int) ($atakMap['id'] ?? 1) : 1;

        $atakCallsignToUser = [];
        if ($tenantId) {
            $users = $this->userRepository->allForTenant($tenantId);
            foreach ($users as $u) {
                $userId = (int) ($u['id'] ?? 0);
                $profile = $this->userProfileRepository->getByUserId($userId);
                $callsign = trim((string) ($u['callsign'] ?? ''));
                $armaCallsign = trim((string) ($profile['arma_callsign'] ?? ''));
                $personnelUrl = url('personnel/' . $userId);
                if ($callsign !== '') {
                    $atakCallsignToUser[strtoupper($callsign)] = ['userId' => $userId, 'url' => $personnelUrl];
                }
                if ($armaCallsign !== '' && ! isset($atakCallsignToUser[strtoupper($armaCallsign)])) {
                    $atakCallsignToUser[strtoupper($armaCallsign)] = ['userId' => $userId, 'url' => $personnelUrl];
                }
            }
        }

        $atakMapsConfigs = [];
        $baseUrl = rtrim(url(''), '/');
        foreach ($atakMapsList as $m) {
            $c = $m['config'] ?? [];
            $tp = $m['tile_pattern'] ?? '/assets/maps/altis/{z}/{x}/{y}.png';
            if ($tp !== '' && $tp[0] !== '/') {
                $tp = '/' . $tp;
            }
            $atakMapsConfigs[$m['slug'] ?? ''] = [
                'slug' => $m['slug'] ?? 'altis',
                'tilePattern' => $baseUrl . $tp,
                'center' => $c['center'] ?? [15000, 15000],
                'defaultZoom' => (int) ($c['defaultZoom'] ?? 3),
                'minZoom' => (int) ($c['minZoom'] ?? 0),
                'maxZoom' => (int) ($c['maxZoom'] ?? 6),
                'tileSize' => (int) ($c['tileSize'] ?? 212),
                'attribution' => $c['attribution'] ?? '&copy; Bohemia Interactive',
                'crs' => $c['crs'] ?? ['factorx' => 0.006839, 'factory' => 0.006836, 'tileWidth' => 212],
                'offsetX' => isset($c['offset_x']) ? (float) $c['offset_x'] : 0,
                'offsetY' => isset($c['offset_y']) ? (float) $c['offset_y'] : 0,
            ];
        }

        $currentUser = $this->authService->user();
        if ($currentUser) {
            $profile = $this->userProfileRepository->getByUserId((int) $currentUser['id']);
            $currentUser['arma_callsign'] = $profile['arma_callsign'] ?? null;
        }
        $atakUserForJs = null;
        if ($currentUser) {
            $atakUserForJs = [
                'displayName' => $currentUser['display_name'] ?? '',
                'callsign' => $currentUser['callsign'] ?? '',
                'steamId' => $currentUser['steam_id'] ?? null,
                'armaCallsign' => $currentUser['arma_callsign'] ?? null,
            ];
        }

        $atakModDownloadUrl = null;
        if ($tenantId) {
            $modPath = dirname(__DIR__, 2) . '/../storage/atak-mod/' . $tenantId . '/comspec-overwatch.zip';
            if (is_file($modPath) && is_readable($modPath)) {
                $atakModDownloadUrl = url('atak/mod/download');
            }
        }

        return Response::view('atak', [
            'atakToken' => $token,
            'nodeAtakUrl' => $nodeUrl,
            'visitorIp' => $visitorIp,
            'atakConfig' => $config ? [
                'arma_server_host' => $config['arma_server_host'] ?? null,
                'arma_server_port' => $config['arma_server_port'] ?? null,
                'arma_mod_credentials' => $config['arma_mod_credentials'] ?? null,
                'instructions' => $config['instructions'] ?? null,
            ] : null,
            'atakMapConfig' => $atakMap,
            'atakMapsList' => $atakMapsList,
            'atakMapsConfigs' => $atakMapsConfigs,
            'atakWorkspaces' => $atakWorkspaces,
            'atakDefaultMapId' => $atakDefaultMapId,
            'atakCallsignToUser' => $atakCallsignToUser,
            'currentUser' => $currentUser,
            'atakUserForJs' => $atakUserForJs,
            'canAccessAdminAtakConfig' => function_exists('can') && can('admin.access'),
            'atakModDownloadUrl' => $atakModDownloadUrl,
        ]);
    }

    private function requireAtakFeature(): ?Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId > 0 && !$this->featureGate->allows($tenantId, 'atak')) {
            return Response::view('layout.main', [
                'title' => 'ATAK / Overwatch',
                'content' => 'platform.upgrade',
                'feature' => 'atak',
                'planName' => 'standard',
            ]);
        }

        return null;
    }

    public function downloadMod(Request $request, array $params = []): Response
    {
        $block = $this->requireAtakFeature();
        if ($block !== null) {
            return $block;
        }
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $modPath = dirname(__DIR__, 2) . '/../storage/atak-mod/' . $tenantId . '/comspec-overwatch.zip';
        if (!is_file($modPath) || !is_readable($modPath)) {
            return Response::redirect(url('atak'));
        }
        $name = 'COMSPEC-Overwatch.zip';
        $response = new \App\Core\Response();
        $response->header('Content-Type', 'application/zip');
        $response->header('Content-Disposition', 'attachment; filename="' . str_replace('"', '\\"', $name) . '"');
        $response->header('Content-Length', (string) filesize($modPath));
        $response->header('Cache-Control', 'private, no-cache');
        $response->setBodyStream(static function () use ($modPath): void {
            $h = fopen($modPath, 'rb');
            if ($h) {
                fpassthru($h);
                fclose($h);
            }
        });
        return $response;
    }

    public function setup(Request $request, array $params = []): Response
    {
        $block = $this->requireAtakFeature();
        if ($block !== null) {
            return $block;
        }
        $tenantId = (int) Session::get('tenant_id');
        $config = $tenantId ? $this->atakConfigRepository->getByTenantId($tenantId) : null;

        $nodeUrl = $config['node_url'] ?? env('NODE_ATAK_URL', '');
        if ($nodeUrl === '') {
            $nodeUrl = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ':3001';
        }
        $nodeUrl = rtrim($nodeUrl, '/');

        $atakModDownloadUrl = null;
        if ($tenantId) {
            $modPath = dirname(__DIR__, 2) . '/../storage/atak-mod/' . $tenantId . '/comspec-overwatch.zip';
            if (is_file($modPath) && is_readable($modPath)) {
                $atakModDownloadUrl = url('atak/mod/download');
            }
        }

        return Response::view('layout.main', [
            'content' => 'atak-setup',
            'title' => 'Assistant Mod Arma — Installation, configuration, vérification',
            'nodeAtakUrl' => $nodeUrl,
            'atakConfig' => $config ? [
                'arma_server_host' => $config['arma_server_host'] ?? null,
                'arma_server_port' => $config['arma_server_port'] ?? null,
                'arma_mod_credentials' => $config['arma_mod_credentials'] ?? null,
                'instructions' => $config['instructions'] ?? null,
            ] : null,
            'atakModDownloadUrl' => $atakModDownloadUrl,
        ]);
    }

    public function tuto(Request $request, array $params = []): Response
    {
        $block = $this->requireAtakFeature();
        if ($block !== null) {
            return $block;
        }

        return Response::view('layout.main', [
            'content' => 'atak-tuto',
            'title' => 'Tutoriel — Mod Arma COMSPEC Overwatch',
        ]);
    }
}
