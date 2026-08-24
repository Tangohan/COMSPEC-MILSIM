<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\CommunityEventRepository;
use App\Repositories\CommunityEventSlotAssignmentRepository;
use App\Repositories\CommunityEventSlotRepository;
use App\Services\Attendance\CommunityEventAttendanceService;
use App\Services\Attendance\CommunityEventSlotService;
use App\Services\Auth\AuthService;
use App\Services\Calendar\CommunityCalendarFeedTokenService;
use App\Services\Platform\FeatureGateService;

final class CommunityEventsController
{
    public function __construct(
        private CommunityEventRepository $events,
        private AuthService $authService,
        private FeatureGateService $featureGate,
        private CommunityEventAttendanceService $attendance,
        private CommunityEventSlotRepository $slots,
        private CommunityEventSlotAssignmentRepository $slotAssignments,
        private CommunityEventSlotService $slotService
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

        $calendarSubscriptionUrl = null;
        if ($userId) {
            try {
                $tok = CommunityCalendarFeedTokenService::fromEnv()->mint($userId, $tenantId);
                $calendarSubscriptionUrl = url('calendrier/abonnement/' . rawurlencode($tok));
            } catch (\Throwable) {
                $calendarSubscriptionUrl = null;
            }
        }

        $gate = Gate::getInstance();
        $canPublishOperationalBoard = $gate->allows('operational.board.edit')
            || $gate->allows('admin.organization')
            || $gate->allows('admin.access')
            || $gate->allows('admin.system');

        $eventIds = [];
        foreach ($rows as $row) {
            $eid = (int) ($row['id'] ?? 0);
            if ($eid > 0) {
                $eventIds[] = $eid;
            }
        }
        $rsvpSummaries = $this->events->rsvpSummariesForEvents($eventIds);
        $eventSlotsByEvent = $this->slots->listForEventsWithCounts($eventIds);
        $mySlotAssignmentByEvent = $userId ? $this->slotAssignments->listForUserAcrossEvents($userId, $eventIds) : [];

        return Response::view('layout.main', [
            'title' => 'Événements & opérations',
            'content' => 'community.events',
            'events' => $rows,
            'currentUserId' => $userId,
            'eventsQuota' => $this->featureGate->quotaStatusForFeature($tenantId, 'events'),
            'eventsCheckInFlags' => $checkInFlags,
            'calendar_subscription_url' => $calendarSubscriptionUrl,
            'canPublishOperationalBoard' => $canPublishOperationalBoard,
            'eventsRsvpSummaries' => $rsvpSummaries,
            'eventSlotsByEvent' => $eventSlotsByEvent,
            'mySlotAssignmentByEvent' => $mySlotAssignmentByEvent,
        ]);
    }

    public function signUpSlot(Request $request, array $params = []): Response
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
        $eventId = (int) ($params['id'] ?? 0);
        $slotId = (int) ($params['slotId'] ?? 0);
        $result = $this->slotService->signUp($tenantId, $eventId, $slotId, (int) $user['id']);
        if (!($result['ok'] ?? false)) {
            Session::flash('error', $result['error'] ?? 'Inscription impossible.');

            return Response::redirect(url('evenements'));
        }
        Session::flash('success', $result['status'] === 'waitlisted' ? 'Poste complet : vous êtes en liste d’attente.' : 'Inscription au poste confirmée.');
        // Prérequis de qualification non satisfait en mode « advisory » : l'inscription passe,
        // mais le membre doit savoir qu'il lui manque une qualification.
        if (!empty($result['warning'])) {
            Session::flash('warning', (string) $result['warning']);
        }

        return Response::redirect(url('evenements'));
    }

    public function leaveSlot(Request $request, array $params = []): Response
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
        $eventId = (int) ($params['id'] ?? 0);
        $result = $this->slotService->leave($tenantId, $eventId, (int) $user['id']);
        if (!($result['ok'] ?? false)) {
            Session::flash('error', $result['error'] ?? 'Désinscription impossible.');

            return Response::redirect(url('evenements'));
        }
        Session::flash('success', 'Désinscription du poste effectuée.');

        return Response::redirect(url('evenements'));
    }

    public function rsvp(Request $request, array $params = []): Response
    {
        $returnTo = trim((string) $request->input('return_to', 'evenements'));
        $returnUrl = $returnTo === 'aujourdhui' ? url('aujourdhui') . '#agenda-et-echeances' : url('evenements');
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect($returnUrl);
        }
        $tenantId = (int) Session::get('tenant_id');
        if (!$this->featureGate->allowsLimitedFeatureModule($tenantId, 'events')) {
            return Response::redirect($returnUrl);
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

            return Response::redirect($returnUrl);
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

            return Response::redirect($returnUrl);
        }
        Session::flash('success', 'Participation enregistrée.');

        return Response::redirect($returnUrl);
    }

    /**
     * RSVP rapide (dashboard, sans rechargement de page) — même logique que rsvp(), réponse JSON.
     */
    public function rsvpApi(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            return Response::json(['ok' => false, 'error' => 'Session expirée, rechargez la page.'], 403);
        }
        $tenantId = (int) Session::get('tenant_id');
        if (!$this->featureGate->allowsLimitedFeatureModule($tenantId, 'events')) {
            return Response::json(['ok' => false, 'error' => 'Module événements indisponible.'], 403);
        }
        $user = $this->authService->user();
        if (!$user) {
            return Response::json(['ok' => false, 'error' => 'Authentification requise.'], 401);
        }
        $eventId = (int) ($params['id'] ?? 0);
        $status = trim((string) $request->input('status', 'yes'));
        if (!in_array($status, ['yes', 'no', 'maybe'], true)) {
            $status = 'yes';
        }
        if ($eventId < 1 || !$this->events->belongsToTenant($eventId, $tenantId)) {
            return Response::json(['ok' => false, 'error' => 'Événement introuvable.'], 404);
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
            return Response::json(['ok' => false, 'error' => $result['error'] ?? 'Impossible d’enregistrer.'], 422);
        }

        return Response::json(['ok' => true, 'status' => $status]);
    }
}
