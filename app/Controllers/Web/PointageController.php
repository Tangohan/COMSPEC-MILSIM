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

final class PointageController
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
                'title' => 'Pointage',
                'content' => 'platform.upgrade',
                'feature' => 'events',
                'planName' => 'pro',
            ]);
        }
        $user = $this->authService->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }
        $userId = (int) $user['id'];
        $this->featureGate->maybeRecordQuotaSoftBlock(
            $tenantId,
            $userId,
            'events'
        );

        $filter = trim((string) $request->query('type', ''));
        $validTypes = ['', 'operation', 'evenement', 'formation', 'autre'];
        if (!in_array($filter, $validTypes, true)) {
            $filter = '';
        }

        $upcoming = $this->events->upcomingForTenantWithUserRsvp($tenantId, $userId, 80);
        $today = $this->events->todayForTenantWithUserRsvp($tenantId, $userId);
        $past = $this->events->pastForTenantWithUserRsvp($tenantId, $userId, 15);

        if ($filter !== '') {
            $upcoming = array_values(array_filter(
                $upcoming,
                static fn (array $r): bool => (string) ($r['event_type'] ?? 'evenement') === $filter
            ));
            $today = array_values(array_filter(
                $today,
                static fn (array $r): bool => (string) ($r['event_type'] ?? 'evenement') === $filter
            ));
        }

        $checkInFlags = [];
        foreach (array_merge($today, $upcoming) as $row) {
            $eid = (int) ($row['id'] ?? 0);
            if ($eid > 0) {
                $checkInFlags[$eid] = $this->attendance->canUserCheckInNow($row, $userId);
            }
        }

        $historyEventIds = [];
        foreach (array_merge($today, $upcoming, $past) as $row) {
            $eid = (int) ($row['id'] ?? 0);
            if ($eid > 0) {
                $historyEventIds[] = $eid;
            }
        }
        $rsvpHistoryByEvent = $this->events->listRsvpHistoryForUserByEvents($userId, $historyEventIds, 10);

        return Response::view('layout.main', [
            'title' => 'Pointage & présence',
            'content' => 'pointage.index',
            'pointageUpcoming' => $upcoming,
            'pointageToday' => $today,
            'pointagePast' => $past,
            'pointageCheckInFlags' => $checkInFlags,
            'pointageTypeFilter' => $filter,
            'pointageRsvpHistoryByEvent' => $rsvpHistoryByEvent,
            'currentUserId' => $userId,
            'eventsQuota' => $this->featureGate->quotaStatusForFeature($tenantId, 'events'),
        ]);
    }

    public function rsvp(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('pointage'));
        }
        $tenantId = (int) Session::get('tenant_id');
        if (!$this->featureGate->allowsLimitedFeatureModule($tenantId, 'events')) {
            return Response::redirect(url('pointage'));
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

            return Response::redirect(url('pointage'));
        }
        $result = $this->attendance->setRsvpWithNotifications(
            $eventId,
            (int) $user['id'],
            $tenantId,
            $status,
            trim((string) $request->input('absence_reason', '')) ?: null,
            trim((string) $request->input('absence_note', '')) ?: null
        );
        if (!($result['ok'] ?? false)) {
            Session::flash('error', $result['error'] ?? 'Impossible d’enregistrer.');

            return Response::redirect(url('pointage'));
        }
        Session::flash('success', 'Participation enregistrée.');

        return Response::redirect(url('pointage'));
    }

    public function checkIn(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('pointage'));
        }
        $tenantId = (int) Session::get('tenant_id');
        if (!$this->featureGate->allowsLimitedFeatureModule($tenantId, 'events')) {
            return Response::redirect(url('pointage'));
        }
        $user = $this->authService->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }
        $eventId = (int) $request->input('event_id');
        $result = $this->attendance->checkIn($eventId, (int) $user['id'], $tenantId);
        if (!($result['ok'] ?? false)) {
            Session::flash('error', $result['error'] ?? 'Pointage impossible.');
        } else {
            Session::flash('success', 'Présence enregistrée.');
        }

        return Response::redirect(url('pointage'));
    }
}
