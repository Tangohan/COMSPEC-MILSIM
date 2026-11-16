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
use App\Services\Personnel\RoleplaySessionCodeService;
use App\Services\Personnel\RoleplaySessionNotifier;
use App\Services\Security\FileRateLimiter;
use App\Support\RoleplaySessionBoard;
use App\Support\GuardsBackOfficeRight;

/**
 * Sessions Arma (back-office/roleplay/sessions) : soirées de jeu dont le temps de présence alimente le suivi RH
 * (parcours, heures par catégorie, taux de présence). Une session se crée ici (ou depuis un événement du
 * calendrier) ; pendant qu’elle est démarrée, le temps de jeu remonté par le mod ATAK y est rattaché.
 */
final class RoleplayGameSessionAdminController
{
    use GuardsBackOfficeRight;

    private const KINDS = ['officielle', 'entrainement', 'libre'];

    public function __construct(
        private RoleplayGameSessionRepository $sessions,
        private RoleplayGameSessionService $service,
        private TenantRepository $tenants,
        private UserRepository $users,
        private CommunityEventRepository $events,
        private ?RoleplaySessionCodeService $codes = null,
        private ?RoleplaySessionNotifier $notifier = null,
    ) {}

    /*
     * Code de session et notifications en jeu (app Session du téléphone, docs/technical/ICD-SESSION-JEU.md) : le code est
     * émis à l’ouverture de la fiche, régénérable et révocable ici ; changements d’état et rappels partent vers les téléphones.
     */
    private function codes(): RoleplaySessionCodeService
    {
        return $this->codes ??= new RoleplaySessionCodeService(null, $this->sessions);
    }

    private function notifier(): RoleplaySessionNotifier
    {
        return $this->notifier ??= new RoleplaySessionNotifier($this->codes()->repository());
    }

    private function actor(): ?int
    {
        $id = (int) Session::get('user_id');

        return $id > 0 ? $id : null;
    }

    /** Le code et les notifications ne doivent jamais faire échouer l’action de la page. */
    private function quietly(callable $fn): mixed
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            error_log('[roleplay-session-app] ' . $e->getMessage());

            return null;
        }
    }

    public function index(Request $request, array $params = []): Response
    {
        if (($denied = $this->guardBackOfficeRight($request)) !== null) {
            return $denied;
        }
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
        $codes = $this->quietly(fn (): array => $this->codes()->listCodes($tenantId, $list)) ?? [];

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
            'sessionCodes' => $codes,
        ]);
    }

    public function saveSettings(Request $request, array $params = []): Response
    {
        if (($denied = $this->guardBackOfficeRight($request)) !== null) {
            return $denied;
        }
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
        if (($denied = $this->guardBackOfficeRight($request)) !== null) {
            return $denied;
        }
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
        $this->quietly(function () use ($tenantId, $id): void {
            $created = $this->sessions->findById($tenantId, $id);
            if ($created !== null && $this->codes()->ready()) {
                $this->codes()->ensure($tenantId, $created, $this->actor());
                $this->notifier()->onCreated($tenantId, $created, $this->actor());
            }
        });
        Session::flash('success', $startNow
            ? 'Session créée et démarrée : le temps de jeu remonté par le mod y est maintenant rattaché.'
            : 'Session prévue. Démarrez-la au début de la soirée pour que le temps de jeu soit compté.');

        return Response::redirect(url('back-office/roleplay/sessions/' . $id));
    }

    public function show(Request $request, array $params = []): Response
    {
        if (($denied = $this->guardBackOfficeRight($request)) !== null) {
            return $denied;
        }
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
            $allUsers = $this->users->allForTenant($tenantId);
            \App\Support\MemberIdentity::preload($tenantId, array_map(static fn (array $u): int => (int) ($u['id'] ?? 0), $allUsers));
            foreach ($allUsers as $u) {
                $uid = (int) ($u['id'] ?? 0);
                if ($uid < 1 || isset($inSession[$uid]) || (string) ($u['status'] ?? 'active') !== 'active') {
                    continue;
                }
                $label = member_label(['id' => $uid, 'display_name' => (string) ($u['display_name'] ?? ''), 'callsign' => (string) ($u['callsign'] ?? '')], 'list', null);
                $roster[] = ['id' => $uid, 'label' => $label];
            }
            usort($roster, static fn (array $a, array $b): int => strcasecmp($a['label'], $b['label']));
        } catch (\Throwable) {
            $roster = [];
        }

        $code = $this->quietly(fn (): array => $this->codes()->ensure($tenantId, $session, $this->actor()))
            ?? ['ready' => false, 'state' => 'none', 'code' => null, 'formatted' => null, 'expires_at' => null, 'created_at' => null, 'can_issue' => false];
        $app = $code['ready'] ? $this->quietly(fn (): array => $this->codes()->repository()->settings($tenantId, $id)) : null;
        $journal = $code['ready'] ? ($this->quietly(fn (): array => $this->codes()->repository()->journal($tenantId, $id, 20)) ?? []) : [];
        $qr = null;
        if ($code['state'] === 'active' && $code['formatted'] !== null) {
            $png = $this->quietly(fn (): ?array => (new \App\Services\Qr\QrPngGenerator())->png((string) $code['formatted'], 320, 8));
            if (is_array($png)) {
                $qr = 'data:' . $png['mime'] . ';base64,' . base64_encode($png['body']);
            }
        }
        $me = $this->actor() !== null ? $this->quietly(fn (): ?array => $this->users->findById((int) $this->actor(), $tenantId)) : null;
        $live = $this->quietly(fn (): array => $this->roster()->roster($tenantId, $members)) ?? ['players' => [], 'connected' => 0, 'server_time' => time()];
        $diagnostics = $this->quietly(fn (): array => $this->roster()->diagnostics($tenantId)) ?? [];

        return Response::view('layout.main', [
            'title' => (string) ($session['mission_name'] ?? '') ?: 'Session Arma',
            'content' => 'admin.organization.roleplay_game_session_show',
            // Le titre vit dans la bande d’en-tête de la fiche : pas de second en-tête de shell.
            'boSkipPageHead' => true,
            'backOfficePageCss' => ['back-office-bands.css'],
            'sessionCode' => $code,
            'sessionCodeQr' => $qr,
            'sessionApp' => $app,
            'sessionJournal' => $journal,
            'sessionCanTestNotify' => is_array($me) && trim((string) ($me['steam_id'] ?? '')) !== '',
            'sessionLive' => $live,
            'sessionDiagnostics' => $diagnostics,
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
        if (($denied = $this->guardBackOfficeRight($request)) !== null) {
            return $denied;
        }
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
        $this->quietly(function () use ($tenantId, $id, $session): void {
            $after = $this->sessions->findById($tenantId, $id);
            if ($after !== null) {
                $this->notifier()->onUpdated($tenantId, $session, $after, $this->actor());
            }
        });
        Session::flash('success', 'Session mise à jour.');

        return Response::redirect($redirect);
    }

    /** Démarrer, clôturer, annuler ou rouvrir une session. */
    public function changeState(Request $request, array $params = []): Response
    {
        if (($denied = $this->guardBackOfficeRight($request)) !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        $back = (string) $request->input('back', '') === 'liste' ? url('back-office/roleplay/sessions') : url('back-office/roleplay/sessions/' . $id);
        if ($tenantId < 1 || !Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Merci de réessayer.');

            return Response::redirect($back);
        }
        $before = $this->sessions->findById($tenantId, $id);
        if (!$before) {
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
        $this->quietly(function () use ($tenantId, $id, $before): void {
            $after = $this->sessions->findById($tenantId, $id);
            if ($after !== null && $this->codes()->ready()) {
                $this->notifier()->onStateChange($tenantId, $before, $after, $this->actor());
                // Session rouverte : un code valable est émis si l’ancien a expiré.
                $this->codes()->ensure($tenantId, $after, $this->actor());
            }
        });

        return Response::redirect($back);
    }

    /**
     * Garde commune des actions de l’app Session : droit de la page, jeton CSRF, session de la communauté.
     * Session inconnue ou d’une autre communauté : même réponse (« Session introuvable. »).
     *
     * @return array{0: int, 1: array<string, mixed>, 2: string}|Response
     */
    private function appAction(Request $request, array $params, string $anchor): array|Response
    {
        if (($denied = $this->guardBackOfficeRight($request)) !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        $redirect = url('back-office/roleplay/sessions/' . $id) . $anchor;
        if ($tenantId < 1 || !Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Merci de réessayer.');

            return Response::redirect($redirect);
        }
        $session = $this->sessions->schemaReady() ? $this->sessions->findById($tenantId, $id) : null;
        if (!$session) {
            Session::flash('error', 'Session introuvable.');

            return Response::redirect(url('back-office/roleplay/sessions'));
        }
        if (!$this->codes()->ready()) {
            Session::flash('error', 'Le code de session et les notifications en jeu seront disponibles après la prochaine mise à jour de la plateforme.');

            return Response::redirect($redirect);
        }

        return [$tenantId, $session, $redirect];
    }

    public function regenerateCode(Request $request, array $params = []): Response
    {
        $ctx = $this->appAction($request, $params, '#code');
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $session, $redirect] = $ctx;
        $res = $this->codes()->regenerate($tenantId, $session, $this->actor());
        Session::flash($res['ok'] ? 'success' : 'error', match (true) {
            $res['ok'] => 'Nouveau code émis : l’ancien ne permet plus de rejoindre la session.',
            ($res['error'] ?? '') === 'session_closed' => 'Cette session est terminée : elle ne reçoit plus de code.',
            default => 'Le code n’a pas pu être émis. Réessayez.',
        });
        if ($res['ok']) {
            $this->audit('roleplay_session.code_regenerated', $tenantId, (int) $session['id']);
        }

        return Response::redirect($redirect);
    }

    public function revokeCode(Request $request, array $params = []): Response
    {
        $ctx = $this->appAction($request, $params, '#code');
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $session, $redirect] = $ctx;
        $res = $this->codes()->revoke($tenantId, $session, $this->actor());
        Session::flash($res['ok'] ? 'success' : 'error', $res['ok']
            ? 'Code révoqué : plus personne ne peut rejoindre la session avec lui. Émettez-en un nouveau quand vous voulez.'
            : 'Aucun code actif à révoquer.');
        if ($res['ok']) {
            $this->audit('roleplay_session.code_revoked', $tenantId, (int) $session['id']);
        }

        return Response::redirect($redirect);
    }

    public function saveNotifications(Request $request, array $params = []): Response
    {
        $ctx = $this->appAction($request, $params, '#notifications');
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $session, $redirect] = $ctx;
        $briefing = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $request->input('briefing', ''))) ?? '');
        if (mb_strlen($briefing) > 400) {
            Session::flash('error', 'Les consignes tiennent en 400 caractères au plus.');

            return Response::redirect($redirect);
        }
        $this->codes()->repository()->saveSettings($tenantId, (int) $session['id'], [
            'notify_enabled' => (bool) $request->input('notify_enabled'),
            'remind_15' => (bool) $request->input('remind_15'),
            'remind_5' => (bool) $request->input('remind_5'),
            'briefing' => $briefing,
        ], $this->actor(), date('Y-m-d H:i:s'));
        Session::flash('success', 'Réglages des notifications en jeu enregistrés.');

        return Response::redirect($redirect);
    }

    public function testNotification(Request $request, array $params = []): Response
    {
        $ctx = $this->appAction($request, $params, '#notifications');
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $session, $redirect] = $ctx;
        $me = $this->actor() !== null ? $this->users->findById((int) $this->actor(), $tenantId) : null;
        $steam = \App\Support\SteamId::normalize((string) ($me['steam_id'] ?? '')) ?? '';
        if ($steam === '') {
            Session::flash('error', 'Liez votre compte Steam à Athena pour recevoir la notification de test sur votre téléphone.');

            return Response::redirect($redirect);
        }
        if ((new FileRateLimiter())->tooManyAttempts('rp_session_test|' . $tenantId . '|' . (int) $this->actor(), 5, 600)) {
            Session::flash('error', 'Trop de notifications de test : réessayez dans quelques minutes.');

            return Response::redirect($redirect);
        }
        $res = $this->notifier()->publish($tenantId, $session, 'test', $this->actor(), null, $steam);
        Session::flash($res['sent'] ? 'success' : 'error', $res['sent']
            ? 'Notification de test envoyée : elle arrive sur votre téléphone en jeu dans les secondes qui suivent (gardée 10 min si vous n’êtes pas connecté).'
            : 'La notification de test n’a pas pu partir. Réessayez.');

        return Response::redirect($redirect);
    }

    public function checkinCall(Request $request, array $params = []): Response
    {
        $ctx = $this->appAction($request, $params, '#notifications');
        if ($ctx instanceof Response) {
            return $ctx;
        }
        [$tenantId, $session, $redirect] = $ctx;
        if (RoleplaySessionBoard::phase($session) !== RoleplaySessionBoard::LIVE) {
            Session::flash('error', 'L’appel à pointer se fait pendant la session, une fois démarrée.');

            return Response::redirect($redirect);
        }
        if ((new FileRateLimiter())->tooManyAttempts('rp_session_call|' . $tenantId . '|' . (int) $session['id'], 1, 120)) {
            Session::flash('error', 'Un appel à pointer vient de partir : attendez deux minutes avant le suivant.');

            return Response::redirect($redirect);
        }
        $res = $this->notifier()->publish($tenantId, $session, 'checkin_call', $this->actor());
        Session::flash($res['sent'] ? 'success' : 'error', match (true) {
            $res['sent'] => 'Appel à pointer envoyé aux téléphones de la communauté.',
            ($res['reason'] ?? '') === 'disabled' => 'Les notifications en jeu sont coupées pour cette session.',
            default => 'L’appel à pointer n’a pas pu partir. Réessayez.',
        });

        return Response::redirect($redirect);
    }

    private function roster(): \App\Services\Personnel\RoleplaySessionLiveRoster
    {
        return new \App\Services\Personnel\RoleplaySessionLiveRoster($this->sessions->pdo());
    }

    private function attendance(): \App\Services\Personnel\RoleplaySessionAttendance
    {
        return new \App\Services\Personnel\RoleplaySessionAttendance($this->sessions, $this->service, $this->codes()->repository());
    }

    /** GET …/sessions/{id}/connectes : joueurs ATAK connectés de la communauté, relu toutes les 15 s par la fiche. */
    public function liveRoster(Request $request, array $params = []): Response
    {
        if (($denied = $this->guardBackOfficeRight($request)) !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        $session = $tenantId > 0 && $this->sessions->schemaReady() ? $this->sessions->findById($tenantId, $id) : null;
        if (!$session) {
            return Response::json(['ok' => false, 'error' => 'not_found'], 404)->header('Cache-Control', 'no-store');
        }
        $roster = $this->quietly(fn (): array => $this->roster()->roster($tenantId, $this->sessions->listMembers($tenantId, $id)))
            ?? ['players' => [], 'connected' => 0, 'server_time' => time()];

        return Response::json(['ok' => true] + $roster)->header('Cache-Control', 'no-store');
    }

    /** POST …/sessions/{id}/pointage : pointer (sélection, tous les connectés, une ligne) ou retirer un pointage. */
    public function staffCheckin(Request $request, array $params = []): Response
    {
        if (($denied = $this->guardBackOfficeRight($request)) !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        $redirect = url('back-office/roleplay/sessions/' . $id) . '#connectes';
        if ($tenantId < 1 || !Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Merci de réessayer.');

            return Response::redirect($redirect);
        }
        $session = $this->sessions->schemaReady() ? $this->sessions->findById($tenantId, $id) : null;
        if (!$session) {
            Session::flash('error', 'Session introuvable.');

            return Response::redirect(url('back-office/roleplay/sessions'));
        }
        $mode = (string) $request->input('mode', 'point');
        $ids = $request->input('user_ids', []);
        $ids = is_array($ids) ? array_values($ids) : [$ids];
        // Bouton d'une ligne (« point:12 » ou « remove:12 ») : seule cette ligne, la sélection cochée est ignorée.
        $row = (string) $request->input('row', '');
        if (preg_match('/^(point|remove):(\d{1,10})$/', $row, $m) === 1) {
            $mode = $m[1];
            $ids = [(int) $m[2]];
        }
        if ($mode === 'all') {
            $ids = $this->roster()->connectedUserIds($tenantId);
        }
        if ($mode === 'remove') {
            $res = $this->attendance()->unpoint($tenantId, $session, $ids, $this->actor());
            Session::flash('success', $res['removed'] > 0 ? $res['removed'] . ' pointage(s) retiré(s).' : 'Aucun pointage à retirer.');
        } else {
            $res = $this->attendance()->point($tenantId, $session, $ids, $this->actor());
            if ($res['pointed'] === 0 && $res['already'] === 0) {
                Session::flash('error', RoleplaySessionBoard::phase($session) === RoleplaySessionBoard::PAST
                    ? 'Cette session est terminée : rouvrez-la pour pointer des joueurs.'
                    : 'Aucun joueur pointable : cochez des joueurs reliés à un compte de la communauté.');
            } else {
                Session::flash('success', $res['pointed'] . ' joueur(s) pointé(s)' . ($res['already'] > 0 ? ', ' . $res['already'] . ' déjà pointé(s)' : '') . '.');
            }
        }
        $this->audit('roleplay_session.staff_checkin_' . ($mode === 'remove' ? 'remove' : 'add'), $tenantId, $id);

        return Response::redirect($redirect);
    }

    /** POST …/sessions/{id}/enregistrement : démarrer (start) ou terminer (stop) l'enregistrement de la session. */
    public function recording(Request $request, array $params = []): Response
    {
        if (($denied = $this->guardBackOfficeRight($request)) !== null) {
            return $denied;
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) ($params['id'] ?? 0);
        $redirect = url('back-office/roleplay/sessions/' . $id);
        if ($tenantId < 1 || !Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Merci de réessayer.');

            return Response::redirect($redirect);
        }
        $before = $this->sessions->schemaReady() ? $this->sessions->findById($tenantId, $id) : null;
        if (!$before) {
            Session::flash('error', 'Session introuvable.');

            return Response::redirect(url('back-office/roleplay/sessions'));
        }
        $stop = (string) $request->input('action', 'start') === 'stop';
        $res = $stop ? $this->attendance()->stopRecording($tenantId, $before, $this->actor()) : $this->attendance()->startRecording($tenantId, $before, $this->actor());
        if (!$res['ok']) {
            Session::flash('error', $stop ? 'La session n’est pas en cours d’enregistrement.' : 'Une session annulée ne peut pas être enregistrée.');

            return Response::redirect($redirect);
        }
        Session::flash('success', $stop
            ? 'Enregistrement terminé : les temps sont figés et comptent pour le suivi.'
            : 'Enregistrement démarré : le temps des ' . (int) ($res['opened'] ?? 0) . ' joueur(s) pointé(s) court maintenant.');
        $this->audit($stop ? 'roleplay_session.recording_stopped' : 'roleplay_session.recording_started', $tenantId, $id);
        $this->quietly(function () use ($tenantId, $id, $before): void {
            $after = $this->sessions->findById($tenantId, $id);
            if ($after !== null && $this->codes()->ready()) {
                $this->notifier()->onStateChange($tenantId, $before, $after, $this->actor());
                $this->codes()->ensure($tenantId, $after, $this->actor());
            }
        });

        return Response::redirect($redirect);
    }

    private function audit(string $action, int $tenantId, int $sessionId): void
    {
        $this->quietly(fn () => \App\Core\Container::get(\App\Services\Audit\AuditService::class)->log($action, $tenantId, $this->actor(), 'roleplay_game_session', $sessionId));
    }

    public function memberAction(Request $request, array $params = []): Response
    {
        if (($denied = $this->guardBackOfficeRight($request)) !== null) {
            return $denied;
        }
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
