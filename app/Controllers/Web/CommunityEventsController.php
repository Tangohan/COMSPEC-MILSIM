<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\CommunityEventRepository;
use App\Services\Auth\AuthService;
use App\Services\Platform\FeatureGateService;

final class CommunityEventsController
{
    public function __construct(
        private CommunityEventRepository $events,
        private AuthService $authService,
        private FeatureGateService $featureGate
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$this->featureGate->allowsLimitedFeatureModule($tenantId, 'events')) {
            return Response::view('layout.main', [
                'title' => 'Événements',
                'content' => 'platform.upgrade',
                'feature' => 'events',
                'planName' => 'pro',
            ]);
        }
        $user = $this->authService->user();
        $this->featureGate->maybeRecordQuotaSoftBlock(
            $tenantId,
            $user ? (int) $user['id'] : null,
            'events'
        );
        $rows = $this->events->upcomingForTenant($tenantId);

        return Response::view('layout.main', [
            'title' => 'Événements & opérations',
            'content' => 'community.events',
            'events' => $rows,
            'currentUserId' => $user ? (int) $user['id'] : null,
            'eventsQuota' => $this->featureGate->quotaStatusForFeature($tenantId, 'events'),
        ]);
    }

    public function rsvp(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('evenements'));
        }
        $tenantId = (int) Session::get('tenant_id');
        if (!$this->featureGate->allowsLimitedFeatureModule($tenantId, 'events')) {
            return Response::redirect(url('evenements'));
        }
        $user = $this->authService->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }
        $eventId = (int) $request->input('event_id');
        $status = trim((string) $request->input('status', 'yes'));
        if (!in_array($status, ['yes', 'no', 'maybe'], true)) {
            $status = 'yes';
        }
        if (!$this->events->belongsToTenant($eventId, $tenantId)) {
            Session::flash('error', 'Événement introuvable.');

            return Response::redirect(url('evenements'));
        }
        $this->events->setRsvp($eventId, (int) $user['id'], $status);
        Session::flash('success', 'Participation enregistrée.');

        return Response::redirect(url('evenements'));
    }
}
