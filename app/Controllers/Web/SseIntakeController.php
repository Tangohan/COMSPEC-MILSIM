<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Controllers\Admin\AdminIntelIntakeController;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\DiscordBotRepository;
use App\Repositories\SseAuditLogRepository;
use App\Repositories\SseCaseRepository;
use App\Services\Intel\IntelIntakeDesk;
use App\Services\Intel\IntelIntakeDiscord;
use App\Services\Intel\IntelIntakePresenter;
use App\Services\Intel\IntelIntakeService;
use App\Services\Intel\IntelPlayerFeedService;
use App\Services\Intel\IntelPlayerIdentity;
use App\Services\Intel\IntelRedaction;
use App\Services\Sse\SseAccessCodeService;
use App\Services\Sse\SseAccessPolicy;
use App\Services\Sse\SseClearanceService;
use App\Support\SseAccessCatalog;

/**
 * Remontées du terrain dans le bureau SSE : toutes les pages qui vivaient au back-office (/back-office/remontees/*,
 * désormais redirigées ici), traitées par le même service (IntelIntakeDesk) avec l'habillage du portail.
 *   GET  /atak/sse/remontees                               file des remontées (comptes rendus et fiches), filtres, tri
 *   GET  /atak/sse/remontees/joueurs                       tableau par joueur (un compteur par catégorie de remontée)
 *   GET  /atak/sse/remontees/joueurs/{key}                 tout ce qu'un joueur a remonté, dans l'ordre chronologique
 *   GET  /atak/sse/remontees/reglages                      délais cibles, états de la file, notifications Discord
 *   POST /atak/sse/remontees/reglages                      enregistre les notifications Discord (mêmes champs qu'avant)
 *   GET  /atak/sse/remontees/{source}/{id}                 fil de suivi d'une remontée (compte rendu ou fiche)
 *   GET  /atak/sse/remontees/{source}/{id}/suggestions     listes des fenêtres (JSON)
 *   POST /atak/sse/remontees/{source}/{id}/{action}        toutes les actions de traitement (mêmes noms de champs)
 *
 * Droits (matrice complète : docs/dev/REMONTEES-DANS-SSE.md) :
 *   - entrer, traiter (prendre en compte, commenter, état, transmettre, doublon, attribuer, type, thèmes, Discord) :
 *     gérer les fiches du bureau (section « terrain », niveau gérer) OU l'ancien droit du back-office Remontées ;
 *   - convertir : écrire dans les dossiers, ou l'ancien droit ; relier : écrire dans les fiches, ou l'ancien droit ;
 *   - modifier les données, flouter, supprimer, restaurer : l'ancien droit du back-office (inchangé) ;
 *   - caviarder : l'ancien droit et l'habilitation la plus haute ; identifiant Steam : administrateurs de la communauté ;
 *   - notifications Discord (réglage) : administrateurs de la communauté (admin.organization), comme au back-office.
 * Chaque action réussie est notée dans sse_audit_log (action, référence, jamais le texte de la remontée).
 */
final class SseIntakeController
{
    public const BASE = 'atak/sse/remontees';

    /**
     * @param (\Closure(): array<string, bool>)|null $rightsResolver droits de la session (tests)
     * @param (\Closure(array<string, mixed>): void)|null $auditWriter écriture du journal SSE (tests)
     * @param (\Closure(): bool)|null $settingsResolver réglage Discord autorisé ? (tests)
     */
    public function __construct(
        private ?IntelIntakeDesk $desk = null,
        private ?SseAccessCodeService $access = null,
        private ?SseClearanceService $clearance = null,
        private ?IntelPlayerFeedService $feed = null,
        private ?\Closure $rightsResolver = null,
        private ?\Closure $auditWriter = null,
        private ?\Closure $settingsResolver = null,
        private ?IntelIntakeDiscord $discord = null,
        private ?DiscordBotRepository $discordRepo = null,
        private ?string $levelOverride = null,
    ) {
        $this->access ??= new SseAccessCodeService();
    }

    private function desk(): IntelIntakeDesk
    {
        return $this->desk ??= new IntelIntakeDesk();
    }

    private function feed(): IntelPlayerFeedService
    {
        return $this->feed ??= new IntelPlayerFeedService();
    }

    private function discordRepo(): DiscordBotRepository
    {
        return $this->discordRepo ??= new DiscordBotRepository();
    }

    private function discord(): IntelIntakeDiscord
    {
        return $this->discord ??= new IntelIntakeDiscord(null, $this->discordRepo());
    }

    private function tenantId(): int
    {
        $tenantId = $this->access->tenantId();

        return $tenantId > 0 ? $tenantId : (int) Session::get('tenant_id');
    }

    /**
     * Droits de la session sur les remontées, depuis le portail. Qui traitait au back-office garde exactement ses droits.
     *
     * @return array<string, bool>
     */
    public static function sessionRights(bool $guest, int $tenantId, ?string $viewerLevel = null): array
    {
        if ($guest || (int) Session::get('user_id') < 1) {
            return IntelIntakeDesk::rights([]);
        }
        $ctx = ['guest' => false, 'tenant_id' => $tenantId];
        $sse = SseAccessPolicy::sessionAllows('terrain', SseAccessCatalog::LEVEL_MANAGE, $ctx);
        $legacy = AdminIntelIntakeController::allowed();
        $treat = $sse || $legacy;
        $level = $viewerLevel ?? ($legacy ? self::sessionLevel() : SseCaseRepository::CLASS_INTERNAL);

        return IntelIntakeDesk::rights([
            'treat' => $treat,
            'discord' => $treat,
            'convert' => $legacy || ($sse && SseAccessPolicy::sessionAllows('dossiers', SseAccessCatalog::LEVEL_WRITE, $ctx)),
            'link' => $legacy || ($sse && SseAccessPolicy::sessionAllows('terrain', SseAccessCatalog::LEVEL_WRITE, $ctx)),
            'manage' => $legacy,
            'redact' => $legacy && $level === SseCaseRepository::CLASS_RESTRICTED,
            'steam' => $legacy && AdminIntelIntakeController::canSeeMembers(),
        ]);
    }

    /** Habilitation de la session ; sans service, celle que le back-office prêtait (délivrance des accès = la plus haute). */
    private static function sessionLevel(?SseClearanceService $clearance = null): string
    {
        try {
            return ($clearance ?? new SseClearanceService())->maxLevel();
        } catch (\Throwable) {
            return IntelIntakeDesk::fallbackLevel(function_exists('can') && (can('admin.access') || can('atak.sse.grant')));
        }
    }

    /** @return array<string, bool> */
    private function rights(): array
    {
        if ($this->rightsResolver !== null) {
            return IntelIntakeDesk::rights(($this->rightsResolver)());
        }

        return self::sessionRights($this->access->isGuest(), $this->tenantId(), $this->viewerLevel());
    }

    private function viewerLevel(): string
    {
        return $this->levelOverride ?? self::sessionLevel($this->clearance);
    }

    /** Réglage des notifications Discord : la règle de la page « Bot Discord » du back-office (admin.organization). */
    private function canEditSettings(): bool
    {
        if ($this->settingsResolver !== null) {
            return ($this->settingsResolver)();
        }

        return !$this->access->isGuest() && function_exists('can') && can('admin.organization');
    }

    /** @return array{id: int, label: string} */
    private function actor(): array
    {
        $label = '';
        foreach (['callsign', 'display_name', 'username'] as $k) {
            $label = trim((string) (Session::get($k) ?? ''));
            if ($label !== '') {
                break;
            }
        }

        return ['id' => (int) Session::get('user_id'), 'label' => $label !== '' ? $label : 'Bureau SSE'];
    }

    /** Journal du bureau : l'action et la référence, jamais le contenu. */
    private function audit(string $action, string $objectType, int $objectId, string $reason): void
    {
        $actor = $this->actor();
        $entry = [
            'tenant_id' => $this->tenantId(), 'actor_user_id' => $actor['id'], 'actor_label' => $actor['label'],
            'action' => $action, 'object_type' => $objectType, 'object_id' => $objectId, 'reason' => $reason,
        ];
        try {
            if ($this->auditWriter !== null) {
                ($this->auditWriter)($entry);

                return;
            }
            (new SseAuditLogRepository())->write($entry);
        } catch (\Throwable) {
        }
    }

    /** @param array<string, mixed> $data */
    private function view(string $view, array $data): Response
    {
        $rights = $data['intakeRights'];
        $data['isGuest'] = $this->access->isGuest();
        $data['clearanceUntil'] = (int) Session::get(SseAccessCodeService::SESSION_UNTIL, 0);
        $data['guestLabel'] = (string) Session::get('sse_guest_label', '');
        $data['sseTheme'] = function_exists('sse_ui_theme') ? sse_ui_theme() : 'dark';
        $data['canGrant'] = !$this->access->isGuest() && function_exists('can') && (can('atak.sse.grant') || can('admin.access'));
        $data['canManage'] = !empty($rights['treat']);
        $data['canWrite'] = !empty($rights['treat']);
        $data['activeNav'] ??= 'remontees';

        return Response::view($view, $data);
    }

    private function denied(): Response
    {
        Session::flash('error', 'La file des remontées est réservée à qui gère les fiches de renseignement.');

        return Response::redirect(url('atak/sse/fiches'));
    }

    /**
     * Chiffres clés de l'en-tête : à traiter, en retard (file ouverte), traitées sur sept jours, ouvertes, à moi.
     *
     * @param array<string, mixed> $counts compteurs sans filtre
     * @return array{todo: int, late: int, week: int, open: int, mine: int}
     */
    public function keyFigures(int $tenantId, int $me, array $counts, ?int $now = null): array
    {
        $now ??= time();
        $intake = $this->desk()->intake();
        $late = 0;
        try {
            $late = (int) IntelIntakeDesk::refine($intake->listItems($tenantId, ['state' => 'open'], $me, 100000), '', 'recu', 'desc', $now)['counts']['retard'];
        } catch (\Throwable) {
        }

        return [
            'todo' => (int) ($counts['states']['a_traiter'] ?? 0),
            'late' => $late,
            'week' => $intake->closedSince($tenantId, date('Y-m-d H:i:s', $now - 7 * 86400)),
            'open' => (int) ($counts['open'] ?? 0),
            'mine' => (int) ($counts['mine'] ?? 0),
        ];
    }

    public function index(Request $request, array $params = []): Response
    {
        $rights = $this->rights();
        if (!$rights['treat']) {
            return $this->denied();
        }
        $tenantId = $this->tenantId();
        $intake = $this->desk()->intake();
        $ready = $intake->schemaReady();
        $tab = $request->queryString('etat', 'open');
        $tabs = array_merge(['open', 'closed', 'mine', 'all'], $rights['manage'] ? ['deleted'] : []);
        $tab = in_array($tab, $tabs, true) || isset(IntelIntakeService::STATES[$tab]) ? $tab : 'open';
        $source = $request->queryString('source');
        $source = isset(IntelIntakeService::SOURCES[$source]) ? $source : '';
        $assignee = $request->queryString('attribue');
        $assignee = $assignee === 'none' || ($assignee !== '' && ctype_digit($assignee)) ? $assignee : '';
        $filters = [
            'source' => $source,
            'type' => strtoupper(trim($request->queryString('type'))),
            'q' => mb_substr(trim($request->queryString('q')), 0, 80),
            'assignee' => $assignee,
        ];
        $me = (int) Session::get('user_id');
        $listFilters = $filters + [
            'state' => $tab === 'mine' ? 'open' : ($tab === 'deleted' ? 'all' : $tab),
            'deleted' => $tab === 'deleted',
        ];
        if ($tab === 'mine') {
            $listFilters['assignee'] = 'me';
        }
        $items = $ready ? $intake->listItems($tenantId, $listFilters, $me) : [];
        $badge = $request->queryString('pastille');
        $badge = isset(IntelIntakeDesk::BADGE_FILTERS[$badge]) ? $badge : '';
        $sort = $request->queryString('tri', 'recu');
        $sort = isset(IntelIntakeDesk::SORTS[$sort]) ? $sort : 'recu';
        $dir = $request->queryString('ordre', 'desc') === 'asc' ? 'asc' : 'desc';
        // Les extraits de la liste respectent les caviardages.
        $level = $this->viewerLevel();
        foreach (['cr', 'fiche'] as $src) {
            $red = $items !== [] ? $intake->redactions($tenantId, $src, array_column(array_filter($items, static fn (array $i): bool => $i['source'] === $src), 'id')) : [];
            foreach ($items as &$it) {
                if ($it['source'] === $src && isset($red[$it['id']])) {
                    $it['title'] = IntelRedaction::apply((string) $it['title'], $red[$it['id']], $level, $me);
                    $it['redacted'] = true;
                }
            }
            unset($it);
        }
        $refined = IntelIntakeDesk::refine($items, $badge, $sort, $dir);
        $empty = ['open' => 0, 'closed' => 0, 'mine' => 0, 'deleted' => 0, 'states' => []];
        $counts = $ready ? $intake->counts($tenantId, $filters, $me) : $empty;
        $unfiltered = $filters === ['source' => '', 'type' => '', 'q' => '', 'assignee' => ''] ? $counts : ($ready ? $intake->counts($tenantId, [], $me) : $empty);

        return $this->view('atak.sse.intake_index', [
            'title' => 'Remontées',
            'intakeRights' => $rights,
            'intakeReady' => $ready,
            'intakeItems' => $refined['items'],
            'intakeBadgeCounts' => $refined['counts'],
            'intakeCounts' => $counts,
            'intakeFigures' => $ready ? $this->keyFigures($tenantId, $me, $unfiltered) : ['todo' => 0, 'late' => 0, 'week' => 0, 'open' => 0, 'mine' => 0],
            'intakeTab' => $tab,
            'intakeFilters' => $filters,
            'intakeTypes' => $source !== '' ? IntelIntakeService::typeOptions($source) : IntelIntakeService::typeOptions('cr') + IntelIntakeService::typeOptions('fiche'),
            'intakeMembers' => $ready ? $intake->members($tenantId) : [],
            'intakeBadge' => $badge,
            'intakeSort' => $sort,
            'intakeDir' => $dir,
        ]);
    }

    public function show(Request $request, array $params = []): Response
    {
        $rights = $this->rights();
        if (!$rights['treat']) {
            return $this->denied();
        }
        $source = (string) ($params['source'] ?? '');
        $id = (int) ($params['id'] ?? 0);
        $tenantId = $this->tenantId();
        $me = (int) Session::get('user_id');
        $intake = $this->desk()->intake();
        $desk = isset(IntelIntakeService::SOURCES[$source]) && $id > 0 && $intake->schemaReady()
            ? $this->desk()->view($tenantId, $source, $id, ['id' => $me, 'level' => $this->viewerLevel()], $rights)
            : null;
        // Une remontée supprimée ne se lit plus que par qui peut la restaurer.
        if ($desk === null || (!empty($desk['item']['deleted']) && !$rights['manage'])) {
            Session::flash('error', 'Remontée introuvable.');

            return Response::redirect(url(self::BASE));
        }
        $intake->recordRead($tenantId, $source, $id, $me, 'web');
        $item = $desk['item'];
        $senderKey = null;
        try {
            $senderKey = $this->feed()->keyFor($tenantId, $item['sender']['user_id'], $item['sender']['steam'], $item['sender']['callsign']);
        } catch (\Throwable) {
        }

        return $this->view('atak.sse.intake_show', [
            'title' => (string) $item['ref'],
            'intakeRights' => $rights,
            'intakeDesk' => $desk,
            'intakeMembers' => $intake->members($tenantId),
            'intakeTypes' => IntelIntakeService::typeOptions($source),
            'intakeLevels' => SseCaseRepository::CLASSIFICATION_LABELS,
            'intakeSenderKey' => $senderKey,
            'intakeCanSeeMembers' => AdminIntelIntakeController::canSeeMembers(),
            'csrfToken' => Csrf::token(),
        ]);
    }

    public function suggestions(Request $request, array $params = []): Response
    {
        $rights = $this->rights();
        if (!$rights['treat']) {
            return Response::json(['items' => []], 403);
        }

        return Response::json(['items' => AdminIntelIntakeController::suggest(
            $this->desk(), $this->tenantId(), (string) ($params['source'] ?? ''), (int) ($params['id'] ?? 0),
            $request->queryString('genre'), $request->queryString('q'), $rights['link']
        )]);
    }

    public function action(Request $request, array $params = []): Response
    {
        $source = (string) ($params['source'] ?? '');
        $id = (int) ($params['id'] ?? 0);
        $action = (string) ($params['action'] ?? '');
        $valid = isset(IntelIntakeService::SOURCES[$source]) && $id > 0;
        // Depuis l'onglet « Suivi » d'une fiche, retour sur la fiche ; sinon sur le fil de la remontée.
        $back = !$valid ? url(self::BASE)
            : ($source === 'fiche' && $request->inputString('retour') === 'fiche'
                ? url('atak/sse/fiches/' . $id) . '#suivi'
                : url(self::BASE . '/' . rawurlencode($source) . '/' . $id));
        $fetch = $request->header('X-Requested-With') === 'fetch';
        $token = $request->input('_csrf_token');
        if (!is_string($token) || $token === '' || !Csrf::validate($token)) {
            $message = 'Votre page est restée ouverte trop longtemps ou la session a changé : rechargez la page puis recommencez.';
            if ($fetch) {
                return Response::json(['ok' => false, 'message' => $message, 'steam' => ''], 403);
            }
            Session::flash('error', $message);

            return Response::redirect($back);
        }
        if (!isset(IntelIntakeDesk::ACTION_RIGHT[$action])) {
            Session::flash('error', 'Action impossible.');

            return Response::redirect($back);
        }
        $tenantId = $this->tenantId();
        $res = $this->desk()->perform($tenantId, $source, $id, $action, $request->all(), $this->actor(), $this->rights(), $this->viewerLevel());
        if ($res['ok']) {
            $found = $this->desk()->intake()->find($tenantId, $source, $id);
            $this->audit('intake.' . $action, $source === 'fiche' ? 'field_note' : 'tactical_report', $id, (string) ($found['ref'] ?? ''));
        }
        if ($action === 'steam' && $fetch) {
            return Response::json(['ok' => $res['ok'], 'message' => $res['message'], 'steam' => (string) ($res['data']['steam'] ?? '')], $res['ok'] ? 200 : 403);
        }
        Session::flash($res['ok'] ? 'success' : 'error', $res['message']);
        if (!$valid || $res['deleted']) {
            return Response::redirect(url(self::BASE));
        }

        return Response::redirect($back . ($action === 'comment' && !str_contains($back, '#') ? '#fil-fin' : ''));
    }

    /** GET /atak/sse/remontees/joueurs : une ligne par joueur, un compteur par catégorie de remontée. */
    public function players(Request $request, array $params = []): Response
    {
        $rights = $this->rights();
        if (!$rights['treat']) {
            return $this->denied();
        }
        $tenantId = $this->tenantId();
        $period = IntelPlayerFeedService::normalizePeriod($request->queryString('periode', '30j'));
        $q = mb_substr(trim($request->queryString('q')), 0, 80);
        $sort = $request->queryString('tri', 'total');
        $sort = in_array($sort, ['total', 'last', 'name'], true) || isset(IntelPlayerFeedService::CATEGORIES[$sort]) ? $sort : 'total';
        $data = ['players' => [], 'sources' => []];
        try {
            $data = $this->feed()->players($tenantId, $period);
        } catch (\Throwable) {
        }
        $players = AdminIntelIntakeController::sortPlayers(AdminIntelIntakeController::filterPlayers($data['players'], $q), $sort);
        $totals = array_fill_keys(array_keys(IntelPlayerFeedService::CATEGORIES), 0);
        foreach ($players as $p) {
            foreach ($p['counts'] as $cat => $n) {
                $totals[$cat] = ($totals[$cat] ?? 0) + (int) $n;
            }
        }

        return $this->view('atak.sse.intake_players', [
            'title' => 'Remontées par joueur',
            'intakeRights' => $rights,
            'playersRows' => $players,
            'playersTotals' => $totals,
            'playersSources' => $data['sources'],
            'playersPeriod' => $period,
            'playersQuery' => $q,
            'playersSort' => $sort,
            'intakeToTreat' => $this->desk()->intake()->countToTreat($tenantId),
        ]);
    }

    /** GET /atak/sse/remontees/joueurs/{key} : tout ce qu'un joueur a remonté, dans l'ordre chronologique. */
    public function player(Request $request, array $params = []): Response
    {
        $rights = $this->rights();
        if (!$rights['treat']) {
            return $this->denied();
        }
        $key = (string) ($params['key'] ?? '');
        $period = IntelPlayerFeedService::normalizePeriod($request->queryString('periode', 'tout'));
        $cat = $request->queryString('categorie');
        $cat = isset(IntelPlayerFeedService::CATEGORIES[$cat]) ? $cat : '';
        $data = ['player' => null, 'items' => [], 'truncated' => false];
        if (IntelPlayerIdentity::validKey($key)) {
            try {
                $data = $this->feed()->timeline($this->tenantId(), $key, $period);
            } catch (\Throwable) {
            }
        }
        if ($data['player'] === null) {
            Session::flash('error', 'Joueur introuvable ou sans remontée.');

            return Response::redirect(url(self::BASE . '/joueurs'));
        }
        $items = $cat !== '' ? array_values(array_filter($data['items'], static fn (array $i): bool => $i['cat'] === $cat)) : $data['items'];

        return $this->view('atak.sse.intake_player', [
            'title' => $data['player']['callsign'] !== '' ? (string) $data['player']['callsign'] : 'Joueur',
            'intakeRights' => $rights,
            'playerCard' => $data['player'],
            'playerItems' => $items,
            'playerTruncated' => $data['truncated'],
            'playerPeriod' => $period,
            'playerCategory' => $cat,
            'playerCanSeeMembers' => AdminIntelIntakeController::canSeeMembers(),
        ]);
    }

    /**
     * Délais cibles de prise en compte, par urgence (IntelIntakePresenter::ACK_TARGET_MINUTES).
     *
     * @return list<array{level: string, label: string, target: string}>
     */
    public static function delayTargets(): array
    {
        $out = [];
        foreach (IntelIntakePresenter::ACK_TARGET_MINUTES as $level => $minutes) {
            [$fiche, $cr] = IntelIntakePresenter::URGENCY_LEVELS[$level];
            $out[] = [
                'level' => $level,
                'label' => IntelIntakePresenter::urgencyLabel('fiche', $fiche) . ' · ' . IntelIntakePresenter::urgencyLabel('cr', $cr),
                'target' => IntelIntakePresenter::duration($minutes * 60),
            ];
        }

        return $out;
    }

    /** GET /atak/sse/remontees/reglages : délais cibles, états de la file, notifications Discord. */
    public function settings(Request $request, array $params = []): Response
    {
        $rights = $this->rights();
        if (!$rights['treat']) {
            return $this->denied();
        }
        $tenantId = $this->tenantId();
        $ready = false;
        $settings = ['enabled' => false, 'channel_id' => '', 'role_id' => '', 'events' => [], 'enabled_at' => ''];
        $channels = [];
        $roles = [];
        $status = ['ready' => false, 'reason' => '', 'channel' => '', 'linked' => false];
        try {
            $ready = $this->discordRepo()->schemaReady();
            if ($ready) {
                $settings = $this->discord()->settings($tenantId);
                $channels = $this->discordRepo()->channels($tenantId);
                $roles = $this->discordRepo()->roles($tenantId);
            }
            $status = $this->discord()->status($tenantId);
        } catch (\Throwable) {
        }

        return $this->view('atak.sse.intake_settings', [
            'title' => 'Réglages des remontées',
            'intakeRights' => $rights,
            'settingsCanEdit' => $this->canEditSettings(),
            'settingsReady' => $ready,
            'settingsDiscord' => $settings,
            'settingsChannels' => $channels,
            'settingsRoles' => $roles,
            'settingsStatus' => $status,
            'settingsDelays' => self::delayTargets(),
            'csrfToken' => Csrf::token(),
        ]);
    }

    /** POST /atak/sse/remontees/reglages : mêmes champs que l'ancien formulaire du back-office (intake_*). */
    public function settingsSave(Request $request, array $params = []): Response
    {
        $back = url(self::BASE . '/reglages') . '#discord';
        $token = $request->input('_csrf_token');
        if (!is_string($token) || $token === '' || !Csrf::validate($token)) {
            Session::flash('error', 'Votre page est restée ouverte trop longtemps ou la session a changé : rechargez la page puis recommencez.');

            return Response::redirect($back);
        }
        if (!$this->rights()['treat'] || !$this->canEditSettings()) {
            Session::flash('error', 'Le réglage des notifications Discord est réservé aux administrateurs de la communauté.');

            return Response::redirect($back);
        }
        $tenantId = $this->tenantId();
        $events = $request->input('intake_events', []);
        $result = $this->discord()->save($tenantId, [
            'enabled' => $request->input('intake_enabled', ''),
            'channel_id' => $request->inputString('intake_channel_id'),
            'role_id' => $request->inputString('intake_role_id'),
            'events' => is_array($events) ? $events : [],
        ]);
        if ($result['ok']) {
            $this->audit('intake.settings', 'tenant', $tenantId, 'Notifications Discord des remontées');
            Session::flash('success', 'Notifications des remontées enregistrées.');
        } else {
            Session::flash('error', $result['error']);
        }

        return Response::redirect($back);
    }
}
