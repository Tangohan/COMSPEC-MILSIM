<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\CommunityEventRepository;
use App\Services\Auth\AuthService;
use App\Services\Platform\FeatureGateService;

final class CommunityEventsAdminController
{
    public function __construct(
        private CommunityEventRepository $events,
        private AuthService $authService,
        private FeatureGateService $featureGate
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$this->featureGate->allows($tenantId, 'events')) {
            return Response::view('layout.main', [
                'title' => 'Événements',
                'content' => 'platform.upgrade',
                'feature' => 'events',
                'planName' => 'pro',
            ]);
        }
        $rows = $this->events->upcomingForTenant($tenantId, 100);

        return Response::view('layout.main', [
            'title' => 'Gérer les événements',
            'content' => 'admin.organization.events',
            'events' => $rows,
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('admin/organization/events'));
        }
        $tenantId = (int) Session::get('tenant_id');
        if (!$this->featureGate->allows($tenantId, 'events')) {
            return Response::redirect(url('admin/organization/events'));
        }
        $user = $this->authService->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }
        $title = trim((string) $request->input('title'));
        $starts = trim((string) $request->input('starts_at'));
        if ($title === '' || $starts === '') {
            Session::flash('error', 'Titre et date de début requis.');

            return Response::redirect(url('admin/organization/events'));
        }
        $this->events->create(
            $tenantId,
            (int) $user['id'],
            $title,
            trim((string) $request->input('description')) ?: null,
            trim((string) $request->input('location')) ?: null,
            $starts,
            trim((string) $request->input('ends_at')) ?: null,
            trim((string) $request->input('campaign_tag')) ?: null
        );
        Session::flash('success', 'Événement créé.');

        return Response::redirect(url('admin/organization/events'));
    }
}
