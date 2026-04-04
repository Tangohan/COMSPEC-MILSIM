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

    public function dashboard(Request $request, array $params = []): Response
    {
        $modpack = null;
        $currentUser = null;
        $personnelExtras = null;
        $grade = null;
        $tenantId = Session::get('tenant_id');
        $atakModDownloadUrl = null;
        if ($tenantId) {
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
