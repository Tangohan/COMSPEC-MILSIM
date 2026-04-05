<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\CommunityEventRepository;
use App\Services\Attendance\CommunityEventAttendanceService;
use App\Services\Auth\AuthService;
use App\Services\Platform\FeatureGateService;

final class CommunityEventsAdminController
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
        $rows = $this->events->upcomingForTenant($tenantId, 100);
        $quota = $this->featureGate->quotaStatusForFeature($tenantId, 'events');

        return Response::view('layout.main', [
            'title' => 'Gérer les événements',
            'content' => 'admin.organization.events',
            'events' => $rows,
            'eventsQuota' => $quota,
            'canCreateEvent' => $this->featureGate->allows($tenantId, 'events'),
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/events'));
        }
        $tenantId = (int) Session::get('tenant_id');
        if (!$this->featureGate->allowsLimitedFeatureModule($tenantId, 'events')) {
            return Response::redirect(url('back-office/events'));
        }
        $user = $this->authService->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }
        if (!$this->featureGate->allows($tenantId, 'events')) {
            $this->featureGate->recordQuotaLimitReached($tenantId, (int) $user['id'], 'events');
            Session::flash('error', 'Quota mensuel de créations d’événements atteint. Passez à un plan supérieur pour en ajouter davantage.');

            return Response::redirect(url('back-office/events'));
        }
        $title = trim((string) $request->input('title'));
        $starts = trim((string) $request->input('starts_at'));
        if ($title === '' || $starts === '') {
            Session::flash('error', 'Titre et date de début requis.');

            return Response::redirect(url('back-office/events'));
        }
        $eventType = trim((string) $request->input('event_type', 'evenement'));
        if (!in_array($eventType, ['operation', 'evenement', 'formation', 'autre'], true)) {
            $eventType = 'evenement';
        }
        $this->events->create(
            $tenantId,
            (int) $user['id'],
            $title,
            trim((string) $request->input('description')) ?: null,
            trim((string) $request->input('location')) ?: null,
            $starts,
            trim((string) $request->input('ends_at')) ?: null,
            trim((string) $request->input('campaign_tag')) ?: null,
            $eventType
        );
        $this->featureGate->recordQuotaUse($tenantId, 'events', (int) $user['id']);
        Session::flash('success', 'Événement créé.');

        return Response::redirect(url('back-office/events'));
    }

    public function show(Request $request, array $params = []): Response
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
        $id = (int) ($params['id'] ?? 0);
        $event = $id > 0 ? $this->events->findByIdForTenant($id, $tenantId) : null;
        if (!$event) {
            Session::flash('error', 'Événement introuvable.');

            return Response::redirect(url('back-office/events'));
        }
        $rsvps = $this->events->listRsvpsWithUsersForEvent($id);

        return Response::view('layout.main', [
            'title' => 'Participants — ' . (string) ($event['title'] ?? ''),
            'content' => 'admin.organization.event_show',
            'event' => $event,
            'eventRsvps' => $rsvps,
        ]);
    }

    public function cancel(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/events'));
        }
        $tenantId = (int) Session::get('tenant_id');
        if (!$this->featureGate->allowsLimitedFeatureModule($tenantId, 'events')) {
            return Response::redirect(url('back-office/events'));
        }
        $user = $this->authService->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }
        $id = (int) ($params['id'] ?? 0);
        if ($id < 1) {
            Session::flash('error', 'Événement invalide.');

            return Response::redirect(url('back-office/events'));
        }
        $reason = trim((string) $request->input('cancel_reason', ''));
        $result = $this->attendance->cancelEventByOrg($id, $tenantId, $reason !== '' ? $reason : null);
        if (!($result['ok'] ?? false)) {
            Session::flash('error', $result['error'] ?? 'Annulation impossible.');

            return Response::redirect(url('back-office/events/' . $id));
        }
        Session::flash('success', 'Événement annulé. Notifications envoyées : ' . (int) ($result['notified'] ?? 0) . '.');

        return Response::redirect(url('back-office/events'));
    }
