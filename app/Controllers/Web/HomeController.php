<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

class HomeController
{
    public function index(Request $request, array $params = []): Response
    {
        return Response::view('home.index', ['title' => 'Athena — Commandement Aérien MILSIM']);
    }

    /** Page d’information sur les offres (fondateurs, essai Pro, Stripe). */
    public function platformUpgrade(Request $request, array $params = []): Response
    {
        $from = trim((string) $request->query('from', ''));
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId > 0 && $from !== '') {
            try {
                \App\Core\Container::get(\App\Repositories\PlatformUsageRepository::class)->record(
                    $tenantId,
                    Session::get('user_id') ? (int) Session::get('user_id') : null,
                    'upgrade_view',
                    $from
                );
            } catch (\Throwable) {
            }
        }

        return Response::view('layout.main', [
            'title' => 'Offres',
            'content' => 'platform.upgrade',
            'feature' => 'offre',
            'planName' => 'Standard ou Pro',
            'upgradeFrom' => $from,
        ]);
    }

    public function dashboard(Request $request, array $params = []): Response
    {
        $modpack = null;
        $currentUser = null;
        $personnelExtras = null;
        $grade = null;
        $atakModDownloadUrl = null;
        $communityMemberships = [];
        $founderTrialEndsAt = null;
        $showFounderTrialBanner = false;
        $email = Session::get('email');
        if ($email) {
            $userRepo = \App\Core\Container::get(\App\Repositories\UserRepository::class);
            $allMemberships = $userRepo->listTenantsForEmail((string) $email);
            $communityMemberships = $userRepo->filterSwitchableTenantsForUser($allMemberships);
        }
        $tenantId = Session::get('tenant_id');
        if ($tenantId) {
            try {
                $usage = \App\Core\Container::get(\App\Repositories\PlatformUsageRepository::class);
                $uid = Session::get('user_id') ? (int) Session::get('user_id') : null;
                $usage->record((int) $tenantId, $uid, 'dashboard_visit', 'view');
            } catch (\Throwable) {
            }
            $modpackRepo = \App\Core\Container::get(\App\Repositories\ModpackRepository::class);
            $modpack = $modpackRepo->getPrimaryForTenant((int) $tenantId);
            $auth = \App\Core\Container::get(\App\Services\Auth\AuthService::class);
            $currentUser = $auth->user();
            if ($currentUser) {
                $extrasRepo = \App\Core\Container::get(\App\Repositories\PersonnelExtrasRepository::class);
                $gradeRepo = \App\Core\Container::get(\App\Repositories\GradeRepository::class);
                $personnelExtras = $extrasRepo->getByUserId((int) $currentUser['id']);
                if (!empty($currentUser['grade_id'])) {
                    $grade = $gradeRepo->findById((int) $currentUser['grade_id'], (int) $tenantId);
                }
                $tenantRow = \App\Core\Container::get(\App\Repositories\TenantRepository::class)->findById((int) $tenantId);
                if ($tenantRow) {
                    $status = (string) ($tenantRow['subscription_status'] ?? 'none');
                    $paid = in_array($status, ['active', 'trialing'], true);
                    $owner = (int) ($tenantRow['owner_user_id'] ?? 0) === (int) $currentUser['id'];
                    $rawS = $tenantRow['settings'] ?? null;
                    $decoded = [];
                    if (is_string($rawS) && trim($rawS) !== '') {
                        $decoded = json_decode($rawS, true);
                        if (!is_array($decoded)) {
                            $decoded = [];
                        }
                    }
                    $end = $decoded['founder_trial_ends_at'] ?? null;
                    if ($owner && ! $paid && is_string($end) && $end !== '') {
                        $ts = strtotime($end);
                        if ($ts !== false && $ts > time()) {
                            $founderTrialEndsAt = $end;
                            $showFounderTrialBanner = true;
                        }
                    }
                }
            }
            $modPath = dirname(__DIR__, 2) . '/../storage/atak-mod/' . $tenantId . '/comspec-overwatch.zip';
            if (is_file($modPath) && is_readable($modPath)) {
                $atakModDownloadUrl = url('atak/mod/download');
            }
        }
        return Response::view('dashboard', [
            'title' => 'Dashboard — Athena',
            'modpack' => $modpack,
            'currentUser' => $currentUser,
            'personnelExtras' => $personnelExtras,
            'grade' => $grade,
            'atakModDownloadUrl' => $atakModDownloadUrl,
            'communityMemberships' => $communityMemberships,
            'founder_trial_ends_at' => $founderTrialEndsAt,
            'show_founder_trial_banner' => $showFounderTrialBanner,
        ]);
    }

    public function enlistment(Request $request, array $params = []): Response
    {
        return Response::view('layout.main', ['content' => 'enlistment', 'title' => 'Enrôlement']);
    }

    public function recrutement(Request $request, array $params = []): Response
    {
        return Response::view('recrutement');
    }

    public function equipement(Request $request, array $params = []): Response
    {
        return Response::view('equipement');
    }

    public function tacmap(Request $request, array $params = []): Response
    {
        return Response::view('tacmap');
    }

    public function overwatch(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $atakMapRepo = \App\Core\Container::get(\App\Repositories\AtakMapRepository::class);
        $atakMapsList = $atakMapRepo->getAll();
        $defaultMap = $tenantId ? $atakMapRepo->getDefaultForTenant($tenantId) : $atakMapRepo->getBySlug('altis');
        $defaultMap = $defaultMap ?? $atakMapRepo->getBySlug('altis');
        $defaultMapId = $defaultMap ? (int) $defaultMap['id'] : 1;
        $defaultMapSlug = $defaultMap['slug'] ?? 'altis';
        $defaultMapLabel = $defaultMap['label'] ?? 'Principal';

        $overwatchMapsList = [['slug' => 'world', 'label' => 'World (OSM)', 'type' => 'world']];
        foreach ($atakMapsList as $m) {
            $c = $m['config'] ?? [];
            $overwatchMapsList[] = [
                'id' => (int) $m['id'],
                'slug' => $m['slug'],
                'label' => $m['label'] ?? $m['slug'],
                'type' => 'arma',
                'tilePattern' => $m['tile_pattern'] ?? '',
                'hasCustomCrs' => ! empty($c['crs']),
            ];
        }

        $overwatchWorkspaces = [];
        foreach ($atakMapsList as $m) {
            $overwatchWorkspaces[] = [
                'mapId' => (int) $m['id'],
                'label' => $m['label'] ?? $m['slug'],
                'slug' => $m['slug'],
                'isDefault' => ($m['slug'] ?? '') === $defaultMapSlug,
            ];
        }
        if (empty($overwatchWorkspaces)) {
            $overwatchWorkspaces[] = ['mapId' => $defaultMapId, 'label' => $defaultMapLabel, 'slug' => $defaultMapSlug, 'isDefault' => true];
        }

        $baseUrl = rtrim(url(''), '/');
        $overwatchMapsConfigs = [];
        foreach ($atakMapsList as $m) {
            $c = $m['config'] ?? [];
            $overwatchMapsConfigs[$m['slug']] = [
                'mapId' => (int) $m['id'],
                'slug' => $m['slug'],
                'label' => $m['label'] ?? $m['slug'],
                'tilePattern' => $baseUrl . ($m['tile_pattern'] ?? ''),
                'center' => $c['center'] ?? [15000, 15000],
                'defaultZoom' => (int) ($c['defaultZoom'] ?? 3),
                'minZoom' => (int) ($c['minZoom'] ?? 0),
                'maxZoom' => (int) ($c['maxZoom'] ?? 6),
                'bounds' => $c['bounds'] ?? null,
                'crs' => $c['crs'] ?? null,
                'config' => $c,
            ];
        }

        $overwatchContext = [
            'tenantId' => $tenantId,
            'defaultMapId' => $defaultMapId,
            'defaultMapSlug' => $defaultMapSlug,
            'defaultMissionId' => "mission_{$tenantId}_map_{$defaultMapId}",
            'apiBase' => $baseUrl . '/api',
            'syncIntervalMs' => 8000,
        ];

        return Response::view('overwatch.index', [
            'title' => 'COMSPEC Overwatch — C2',
            'overwatchMapsList' => $overwatchMapsList,
            'overwatchWorkspaces' => $overwatchWorkspaces,
            'overwatchMapsConfigs' => $overwatchMapsConfigs,
            'overwatchDefaultMapId' => $defaultMapId,
            'overwatchDefaultMapSlug' => $defaultMapSlug,
            'overwatchDefaultWorkspace' => [
                'mapId' => $defaultMapId,
                'label' => $defaultMapLabel,
                'slug' => $defaultMapSlug,
            ],
            'overwatchContext' => $overwatchContext,
        ]);
    }
}
