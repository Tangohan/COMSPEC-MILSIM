<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\CommunityEventRepository;
use App\Repositories\RoleplayGameSessionRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Services\Personnel\RoleplayGameSessionService;
use App\Services\Personnel\RoleplayGameSessionSettings;
use App\Support\RoleplaySessionBoard;

/**
 * Sessions Arma (back-office/roleplay/sessions) : soirées de jeu dont le temps de présence alimente le suivi RH
 * (parcours, heures par catégorie, taux de présence). Une session se crée ici (ou depuis un événement du
 * calendrier) ; pendant qu’elle est démarrée, le temps de jeu remonté par le mod ATAK y est rattaché.
 */
final class RoleplayGameSessionAdminController
{
    private const KINDS = ['officielle', 'entrainement', 'libre'];

    public function __construct(
        private RoleplayGameSessionRepository $sessions,
        private RoleplayGameSessionService $service,
        private TenantRepository $tenants,
        private UserRepository $users,
        private CommunityEventRepository $events,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }
        $cfg = RoleplayGameSessionSettings::forTenant($tenantId, $this->tenants);
        $ready = $this->sessions->schemaReady();
        $list = [];
        $stats = [];
        if ($ready) {
            try {
                $list = $this->sessions->listForTenant($tenantId, 200);
                $stats = $this->sessions->memberStatsForSessions($tenantId, array_map(static fn (array $s): int => (int) $s['id'], $list));
            } catch (\Throwable) {
                $list = [];
                $stats = [];
            }
        }
        $groups = RoleplaySessionBoard::group($list);

        // Opérations du calendrier à venir qui n’ont pas encore de session : proposées pour « Préparer la session ».
        $linked = [];
        foreach ($list as $s) {
            $eid = (int) ($s['community_event_id'] ?? 0);
            if ($eid > 0) {
                $linked[$eid] = true;
            }
        }
        $events = $this->upcomingEvents($tenantId);
        $eventsById = [];
        foreach ($events as $e) {
            $eventsById[(int) $e['id']] = $e;
        }
        $suggested = array_values(array_filter($events, static fn (array $e): bool => !isset($linked[(int) $e['id']])));

        $tab = $request->queryString('vue', '');
        $tabs = ['a-venir' => RoleplaySessionBoard::UPCOMING, 'en-cours' => RoleplaySessionBoard::LIVE, 'passees' => RoleplaySessionBoard::PAST];
        if (!isset($tabs[$tab])) {
            $tab = $groups[RoleplaySessionBoard::LIVE] !== [] ? 'en-cours' : 'a-venir';
        }

        return Response::view('layout.main', [
            'title' => 'Sessions Arma',
            'content' => 'admin.organization.roleplay_game_sessions',
            'sessionSchemaReady' => $ready,
            'sessionGroups' => $groups,
            'sessionStats' => $stats,
            'sessionSettings' => $cfg,
            'sessionTab' => $tab,
            'sessionTabs' => $tabs,
            'sessionSuggestedEvents' => $suggested,
            'sessionEventsById' => $eventsById,
            'sessionEvents' => $events,
        ]);
    }

    public function saveSettings(Request $request, array $params = []): Response
    {
        $redirect = url('back-office/roleplay/sessions') . '#regles';
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Merci de réessayer.');

            return Response::redirect($redirect);
        }
        RoleplayGameSessionSettings::saveFromPost($tenantId, $request->all(), $this->tenants);
        Session::flash('success', 'Règles de comptage enregistrées.');

        return Response::redirect($redirect);
    }

    public function store(Request $request, array $params = []): Response
    {
        $redirect = url('back-office/roleplay/sessions');
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1 || !Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Merci de réessayer.');

            return Response::redirect($redirect);
        }
        if (!$this->sessions->schemaReady()) {
            Session::flash('error', 'Les sessions Arma seront disponibles après la prochaine mise à jour de la plateforme.');

            return Response::redirect($redirect);
        }
        $cfg = RoleplayGameSessionSettings::forTenant($tenantId, $this->tenants);
        $kind = $this->kind($request->input('session_kind', 'officielle'));

        // Préparée depuis un événement du calendrier : nom et horaires repris de l’événement si non saisis.
        $eventId = (int) $request->input('community_event_id', 0);
        $event = $eventId > 0 ? $this->findEvent($tenantId, $eventId) : null;
        $name = trim((string) $request->input('mission_name', ''));
        $plannedStart = RoleplaySessionBoard::parseInput($request->input('planned_starts_at', ''));
        $plannedEnd = RoleplaySessionBoard::parseInput($request->input('planned_ends_at', ''));
        if ($event !== null) {
            $name = $name !== '' ? $name : trim((string) ($event['title'] ?? ''));
            $plannedStart ??= RoleplaySessionBoard::parseInput($event['starts_at'] ?? '');
            $plannedEnd ??= RoleplaySessionBoard::parseInput($event['ends_at'] ?? '');
        }
        // « Démarrer maintenant » : la session est en cours tout de suite ; sinon elle attend son heure (à venir).
        $startNow = (string) $request->input('when', $plannedStart === null ? 'now' : 'later') === 'now' || $plannedStart === null;
        $category = trim((string) $request->input('hour_category', ''));
        if ($category === '' || !in_array($category, $cfg['hour_categories'], true)) {
            $category = (string) ($cfg['hour_categories'][0] ?? 'Opération');
        }

        $id = $this->sessions->create($tenantId, [
            'session_uid' => 'staff-' . date('YmdHis') . '-' . bin2hex(random_bytes(3)),
            'mission_name' => mb_substr($name !== '' ? $name : 'Session', 0, 191),
            'server_name' => trim((string) $request->input('server_name', '')) ?: null,
            'session_kind' => $kind,
            'hour_category' => $category,
            'community_event_id' => $event !== null ? $eventId : null,
            'is_official' => $kind === 'officielle' ? 1 : 0,
            'attendance_enabled' => $kind === 'officielle' || $request->input('attendance_enabled') ? 1 : 0,
            'status' => 'open',
            'planned_starts_at' => $plannedStart,
            'planned_ends_at' => $plannedEnd,
            'started_at' => $startNow ? date('Y-m-d H:i:s') : null,
            'min_minutes' => $cfg['min_minutes'],
            'min_percent' => $cfg['min_percent'],
            'late_tolerance_minutes' => $cfg['late_tolerance_minutes'],
            'created_by_user_id' => (int) Session::get('user_id') ?: null,
        ]);
        Session::flash('success', $startNow
            ? 'Session créée et démarrée : le temps de jeu remonté par le mod y est maintenant rattaché.'
            : 'Session prévue. Démarrez-la au début de la soirée pour que le temps de jeu soit compté.');

        return Response::redirect(url('back-office/roleplay/sessions/' . $id));
    }

    public function show(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        $session = $tenantId > 0 && $this->sessions->schemaReady() ? $this->sessions->findById($tenantId, $id) : null;
        if (!$session) {
            Session::flash('error', 'Session introuvable.');

            return Response::redirect(url('back-office/roleplay/sessions'));
        }
        $members = $this->sessions->listMembers($tenantId, $id);
        $cfg = RoleplayGameSessionSettings::forTenant($tenantId, $this->tenants);
        $events = $this->upcomingEvents($tenantId);
        $linkedEvent = null;
        $eid = (int) ($session['community_event_id'] ?? 0);
        if ($eid > 0) {
            $linkedEvent = $this->findEvent($tenantId, $eid);
            if ($linkedEvent !== null && !in_array($eid, array_map(static fn (array $e): int => (int) $e['id'], $events), true)) {
                array_unshift($events, $linkedEvent);
            }
        }
        $inSession = [];
        foreach ($members as $m) {
            if ((int) ($m['user_id'] ?? 0) > 0) {
                $inSession[(int) $m['user_id']] = true;
            }
        }
        $roster = [];
        try {
            foreach ($this->users->allForTenant($tenantId) as $u) {
                $uid = (int) ($u['id'] ?? 0);
                if ($uid < 1 || isset($inSession[$uid]) || (string) ($u['status'] ?? 'active') !== 'active') {
                    continue;
                }
                $label = trim((string) ($u['display_name'] ?? '')) ?: (trim((string) ($u['callsign'] ?? '')) ?: (string) ($u['email'] ?? ''));
                $roster[] = ['id' => $uid, 'label' => $label];
            }
            usort($roster, static fn (array $a, array $b): int => strcasecmp($a['label'], $b['label']));
        } catch (\Throwable) {
            $roster = [];
        }

        return Response::view('layout.main', [
            'title' => (string) ($session['mission_name'] ?? '') ?: 'Session Arma',
            'content' => 'admin.organization.roleplay_game_session_show',
            'gameSession' => $session,
            'sessionMembers' => $members,
            'sessionSettings' => $cfg,
            'sessionEvents' => $events,
            'sessionLinkedEvent' => $linkedEvent,
            'sessionRoster' => $roster,
            'sessionLengthSeconds' => $this->service->sessionLengthSeconds($session),
        ]);
    }

    public function update(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        $redirect = url('back-office/roleplay/sessions/' . $id);
        if ($tenantId < 1 || !Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Merci de réessayer.');

            return Response::redirect($redirect);
        }
        $session = $this->sessions->findById($tenantId, $id);
        if (!$session) {
            Session::flash('error', 'Session introuvable.');

            return Response::redirect(url('back-office/roleplay/sessions'));
        }
        $cfg = RoleplayGameSessionSettings::forTenant($tenantId, $this->tenants);
        $kind = $this->kind($request->input('session_kind', (string) ($session['session_kind'] ?? 'libre')));
        $category = trim((string) $request->input('hour_category', ''));
        if ($category === '') {
            $category = (string) ($session['hour_category'] ?? ($cfg['hour_categories'][0] ?? 'Libre'));
        }
        $eventId = (int) $request->input('community_event_id', 0);
        $patch = [
            'mission_name' => mb_substr(trim((string) $request->input('mission_name', '')) ?: (string) ($session['mission_name'] ?? 'Session'), 0, 191),
            'server_name' => trim((string) $request->input('server_name', '')) ?: null,
            'session_kind' => $kind,
            'hour_category' => mb_substr($category, 0, 80),
            'community_event_id' => $eventId > 0 && $this->findEvent($tenantId, $eventId) !== null ? $eventId : null,
            'is_official' => $kind === 'officielle' ? 1 : 0,
            'attendance_enabled' => $request->input('attendance_enabled') ? 1 : 0,
            'planned_starts_at' => RoleplaySessionBoard::parseInput($request->input('planned_starts_at', '')),
            'planned_ends_at' => RoleplaySessionBoard::parseInput($request->input('planned_ends_at', '')),
        ];
        // Seuils propres à la session : seulement s’ils sont envoyés (l’ancien formulaire les remettait à 45 / 50 / 15).
        foreach (['min_minutes' => [0, 240], 'min_percent' => [0, 100], 'late_tolerance_minutes' => [0, 120]] as $key => [$min, $max]) {
            $raw = $request->input($key);
            if ($raw !== null && $raw !== '') {
                $patch[$key] = max($min, min($max, (int) $raw));
            }
        }
        $this->sessions->update($tenantId, $id, $patch);
        $this->service->refreshSession($tenantId, $id);
        Session::flash('success', 'Session mise à jour.');

        return Response::redirect($redirect);
    }

    /** Démarrer, clôturer, annuler ou rouvrir une session. */
    public function changeState(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        $back = (string) $request->input('back', '') === 'liste' ? url('back-office/roleplay/sessions') : url('back-office/roleplay/sessions/' . $id);
        if ($tenantId < 1 || !Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Merci de réessayer.');

            return Response::redirect($back);
        }
        if (!$this->sessions->findById($tenantId, $id)) {
            Session::flash('error', 'Session introuvable.');

            return Response::redirect(url('back-office/roleplay/sessions'));
        }
        switch ((string) $request->input('state', '')) {
            case 'start':
            case 'reopen':
                $this->service->startSession($tenantId, $id);
                Session::flash('success', 'Session démarrée : le temps de jeu remonté par le mod y est rattaché.');
                break;
            case 'close':
                $this->service->closeSession($tenantId, $id);
                Session::flash('success', 'Session clôturée : les temps sont figés et comptent pour le suivi.');
                break;
            case 'cancel':
                $this->service->closeSession($tenantId, $id, 'cancelled');
                Session::flash('success', 'Session annulée.');
                break;
        }

        return Response::redirect($back);
    }

    public function memberAction(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        $memberId = (int) ($params['memberId'] ?? 0);
        $redirect = url('back-office/roleplay/sessions/' . $id) . '#participants';
        if ($tenantId < 1 || !Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Merci de réessayer.');

            return Response::redirect($redirect);
        }
        if (!$this->sessions->findById($tenantId, $id)) {
            Session::flash('error', 'Session introuvable.');

            return Response::redirect(url('back-office/roleplay/sessions'));
        }
        $action = (string) $request->input('member_action', '');
        $actor = (int) Session::get('user_id');
        if ($action === 'validate') {
            $this->service->staffValidate($tenantId, $id, $memberId, $actor, true, false);
            Session::flash('success', 'Présence retenue : elle compte pour le suivi.');
        } elseif ($action === 'exclude') {
            $this->service->staffValidate($tenantId, $id, $memberId, $actor, false, true);
            Session::flash('success', 'Cette présence ne compte plus pour le suivi.');
        } elseif ($action === 'add') {
            $uid = (int) $request->input('user_id', 0);
            $user = $uid > 0 ? $this->users->findById($uid, $tenantId) : null;
            if ($user) {
                $this->service->addMemberByStaff($tenantId, $id, $uid, (int) $request->input('minutes', 0), $actor);
                Session::flash('success', 'Participant ajouté à la session.');
            } else {
                Session::flash('error', 'Choisissez un membre de la communauté.');
            }
        }

        return Response::redirect($redirect);
    }

    private function kind(mixed $value): string
    {
        $kind = (string) $value;

        return in_array($kind, self::KINDS, true) ? $kind : 'libre';
    }

    /** @return list<array<string, mixed>> */
    private function upcomingEvents(int $tenantId): array
    {
        try {
            $rows = $this->events->upcomingForTenant($tenantId, 30);
        } catch (\Throwable) {
            return [];
        }

        return array_values($rows);
    }

    /** @return array<string, mixed>|null */
    private function findEvent(int $tenantId, int $eventId): ?array
    {
        try {
            return $this->events->findByIdForTenant($eventId, $tenantId);
        } catch (\Throwable) {
            return null;
        }
    }
}
