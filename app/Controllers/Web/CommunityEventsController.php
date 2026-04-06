<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\CommunityEventRepository;
use App\Services\Attendance\CommunityEventAttendanceService;
use App\Services\Auth\AuthService;
use App\Services\Platform\FeatureGateService;

final class CommunityEventsController
{
    public function __construct(
        private CommunityEventRepository $events,
        private AuthService $authService,
        private FeatureGateService $featureGate,
        private CommunityEventAttendanceService $attendance
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
        $userId = $user ? (int) $user['id'] : null;
        $rows = $userId
            ? $this->events->upcomingForTenantWithUserRsvp($tenantId, $userId, 50)
            : $this->events->upcomingForTenant($tenantId, 50);
        $checkInFlags = [];
        if ($userId) {
            foreach ($rows as $row) {
                $eid = (int) ($row['id'] ?? 0);
                if ($eid > 0) {
                    $checkInFlags[$eid] = $this->attendance->canUserCheckInNow($row, $userId);
                }
            }
        }

        return Response::view('layout.main', [
            'title' => 'Événements & opérations',
            'content' => 'community.events',
            'events' => $rows,
            'currentUserId' => $userId,
            'eventsQuota' => $this->featureGate->quotaStatusForFeature($tenantId, 'events'),
            'eventsCheckInFlags' => $checkInFlags,
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
        $result = $this->attendance->setRsvpWithNotifications(
            $eventId,
            (int) $user['id'],
            $tenantId,
            $status
        );
        if (!($result['ok'] ?? false)) {
            Session::flash('error', $result['error'] ?? 'Impossible d’enregistrer.');

            return Response::redirect(url('evenements'));
        }
        Session::flash('success', 'Participation enregistrée.');

        return Response::redirect(url('evenements'));
    }
}
