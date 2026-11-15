<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TenantMessageRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Services\Auth\AuthService;
use App\Services\Jnet\JnetDashboardService;
use App\Services\Jnet\JnetLinkAccess;
use App\Services\Jnet\JnetLinkRules;
use App\Services\Jnet\JnetLinkService;
use App\Services\Jnet\JnetMemberHub;
use App\Services\Jnet\JnetMessagingService;
use App\Services\Jnet\JnetSpaceRules;
use App\Services\Jnet\JnetSpaceService;
use App\Services\Jnet\JnetUnitSpaceSettings;
use App\Services\Jnet\JnetUnitWorkspaceService;
use App\Services\Rbac\RbacService;
use App\Support\JnetSpaceTree;
use App\Support\PortalAccessChoice;

final class JnetPortalController
{
    public function __construct(
        private ?AuthService $authService = null,
        private ?RbacService $rbacService = null,
        private ?UserRepository $userRepository = null,
        private ?TenantRepository $tenantRepository = null,
        private ?TenantMessageRepository $messageRepository = null,
        private ?JnetDashboardService $jnet = null,
        private ?JnetMessagingService $messaging = null,
        private ?JnetSpaceService $spaces = null,
        private ?JnetUnitSpaceSettings $spaceSettings = null,
        private ?JnetUnitWorkspaceService $workspace = null,
        private ?JnetLinkService $links = null,
    ) {
        $this->authService ??= \App\Core\Container::get(AuthService::class);
        $this->rbacService ??= \App\Core\Container::get(RbacService::class);
        $this->userRepository ??= \App\Core\Container::get(UserRepository::class);
        $this->tenantRepository ??= \App\Core\Container::get(TenantRepository::class);
        $this->messageRepository ??= new TenantMessageRepository();
        $this->jnet ??= new JnetDashboardService();
        $this->messaging ??= new JnetMessagingService();
        $this->spaces ??= new JnetSpaceService($this->jnet);
        $this->spaceSettings ??= new JnetUnitSpaceSettings();
        $this->workspace ??= new JnetUnitWorkspaceService();
        $this->links ??= new JnetLinkService();
    }

    /** Espaces déjà franchis dans cette session (écran d'entrée montré une fois par espace et par session). */
    private const SESSION_ENTERED = 'jnet_spaces_entered';

    public function home(Request $request, array $params = []): Response
    {
        $ctx = $this->ensureAuth();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        // Un membre (pas l'encadrement de l'organisation) arrive sur l'extranet « Une unité. Un espace. » ; l'espace
        // commun reste à ?commun=1 et ?type=. L'encadrement l'y retrouve aussi : l'espace commun est la première ligne du registre.
        if ((string) $request->query('commun', '') === '' && (string) $request->query('type', '') === '') {
            return $this->hubResponse($ctx);
        }
        $dash = $this->jnet->buildHome($ctx['tenant_id'], $ctx['user_id']);
        $kind = (string) $request->query('type', '');
        $space = $this->spaces->buildSpace($ctx['tenant_id'], $ctx['user_id'], 0, $kind) ?? [];

        return $this->render('home', 'Espace commun', array_merge($dash, $space), 'home');
    }

    /** Extranet du membre pour tous les profils (l'encadrement l'ouvre depuis l'onglet « Mon extranet »). */
    public function hub(Request $request, array $params = []): Response
    {
        $ctx = $this->ensureAuth();
        if ($ctx instanceof Response) {
            return $ctx;
        }

        return $this->hubResponse($ctx);
    }

    /**
     * « Une unité. Un espace. » : le registre des unités visibles du lecteur (droit, non-lus, Entrer), puis ses
     * rubriques personnelles. Tout vient de l'arbre ORBAT déjà filtré pour lui et de pages qui se protègent seules.
     *
     * @param array{tenant_id:int,user_id:int} $ctx
     */
    private function hubResponse(array $ctx): Response
    {
        $tenantId = $ctx['tenant_id'];
        $userId = $ctx['user_id'];
        $tenant = $this->tenantRepository->findById($tenantId);
        $tree = $this->spaces->tree($tenantId, $userId);
        $unread = $this->spaces->unreadByUnit($tenantId, $userId);
        $register = JnetMemberHub::register(
            $tree,
            $this->spaces->viewerUnitIds($tenantId, $userId),
            $this->spaceSettings->commandedUnitIds($tenantId, $userId),
            $this->spaces->isOrgWideWriter(),
            $unread,
            $this->spaceSettings->findAll($tenantId),
            $this->spaces->strengthByUnit($tenantId, $userId),
            $this->unitLinkMarks($tenantId, $userId)
        );
        $pilot = false;
        try {
            $pilot = \App\Core\Container::get(\App\Services\Platform\FeatureGateService::class)
                ->allowsWithCatalogDefault($tenantId, \App\Services\Personnel\PilotQualificationService::FEATURE);
        } catch (\Throwable) {
        }
        $unreadMail = $this->unreadMailCount($tenantId, $userId);
        $callsign = trim((string) Session::get('callsign', ''));

        return Response::view('jnet.hub', [
            'title' => 'Mon extranet',
            'hubCommunity' => is_array($tenant) ? community_display_name($tenant) : '',
            'hubOperator' => $callsign !== '' ? $callsign : (string) Session::get('display_name', ''),
            'hubMine' => $register['mine'],
            'hubOthers' => $register['others'],
            'hubTree' => $register['tree'],
            'hubOrg' => [
                'label' => (string) (($tree->node(JnetSpaceTree::ORG) ?? [])['label'] ?? 'Organisation'),
                'href' => url('jnet?commun=1'),
                'unread' => (int) ($unread[JnetSpaceTree::ORG] ?? 0),
            ],
            'hubSections' => JnetMemberHub::sections([
                'tenantType' => is_array($tenant) ? (string) ($tenant['tenant_type'] ?? 'full') : 'full',
                'pilotLogbook' => $pilot,
                'unreadMail' => $unreadMail,
            ]),
            'hubUnreadMail' => $unreadMail,
            'hubCanTba' => PortalAccessChoice::canAccessTba(),
        ]);
    }

    /** Espace d’une unité de l’ORBAT : reçu du commandement, fil interne, remonté vers l’échelon supérieur. */
    public function unitSpace(Request $request, array $params = []): Response
    {
        $ctx = $this->ensureAuth();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $unitId = (int) ($params['id'] ?? 0);
        if ($unitId <= 0) {
            return Response::redirect(url('jnet'));
        }
        $tree = $this->spaces->tree($ctx['tenant_id'], $ctx['user_id']);
        if (!$tree->has($unitId)) {
            Session::flash('error', 'Cet espace n’existe pas ou ne vous est pas accessible.');

            return Response::redirect($this->homeUrl());
        }
        if (!$this->spaces->canRead($ctx['tenant_id'], $ctx['user_id'], $unitId)) {
            Session::flash('error', 'Cet espace est réservé aux unités que son chef a autorisées.');

            return Response::redirect($this->homeUrl());
        }
        $settings = $this->spaceSettings->find($ctx['tenant_id'], $unitId);
        $look = JnetUnitSpaceSettings::look($settings, (array) $tree->node($unitId));
        if ($look['entry_screen'] && !$this->hasEntered($unitId) && (string) $request->query('direct', '') === '') {
            return Response::redirect(url('jnet/u/' . $unitId . '/entree'));
        }
        $space = $this->spaces->buildSpace($ctx['tenant_id'], $ctx['user_id'], $unitId);
        if ($space === null) {
            Session::flash('error', 'Cet espace n’existe pas ou ne vous est pas accessible.');

            return Response::redirect($this->homeUrl());
        }
        $vue = (string) $request->query('vue', 'accueil');
        if ($vue === 'suivi') {
            $vue = 'effectif';
        }
        $vue = in_array($vue, self::UNIT_VIEWS, true) ? $vue : 'accueil';
        $extra = $this->workspaceData($ctx, $unitId, $space, $settings, $vue, $request, $tree);
        // Ordres non lus à prendre en compte avant d'utiliser l'espace (réglage « Prise en compte obligatoire »).
        $extra['usGate'] = $this->spaces->pendingOrdersFor($ctx['tenant_id'], $unitId, array_merge((array) ($space['received'] ?? []), (array) ($space['internal'] ?? [])));

        return $this->unitShell($ctx, $unitId, $space, $look, $vue, $tree->chain($unitId), $extra);
    }

    /** Rubriques de l'espace d'une unité (?vue=). « page » est une page écrite, ouverte depuis la rubrique Pages. */
    private const UNIT_VIEWS = ['accueil', 'echanges', 'effectif', 'taches', 'pages', 'page', 'photos', 'documents', 'operations', 'sous-unites', 'partager', 'liaisons'];

    /**
     * Données des outils de l'espace pour la rubrique demandée : seules les lectures utiles à cette rubrique sont faites.
     *
     * @param array{tenant_id:int,user_id:int} $ctx
     * @param array<string, mixed> $space
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    private function workspaceData(array $ctx, int $unitId, array $space, array $settings, string $view, Request $request, JnetSpaceTree $tree): array
    {
        $tenantId = $ctx['tenant_id'];
        $userId = $ctx['user_id'];
        $wsReady = $this->workspace->ready();
        $hidden = is_array($settings['hidden_modules'] ?? null) ? $settings['hidden_modules'] : [];
        $filesReady = false;
        try {
            $filesReady = (new \App\Repositories\FileShareRepository())->schemaReady();
        } catch (\Throwable) {
        }
        // Photos et documents : partage de fichiers, mais aussi photos terrain et pièces jointes des échanges, bibliothèque.
        $mediaReady = $filesReady || $this->spaces->exchangesReady();
        $shown = [
            'taches' => $wsReady && !in_array('taches', $hidden, true),
            'pages' => $wsReady && !in_array('pages', $hidden, true),
            'photos' => $mediaReady && !in_array('photos', $hidden, true),
            'documents' => $mediaReady && !in_array('documents', $hidden, true),
        ];
        $rights = $this->spaces->rights($tenantId, $userId, $unitId);
        $canPost = $rights['write'];
        $isChief = $this->canCustomize($tenantId, $userId, $tree->chain($unitId));
        $memberCards = is_array($space['members'] ?? null) ? $space['members'] : [];
        $memberIds = array_values(array_map('intval', (array) (($tree->node($unitId) ?? [])['members'] ?? [])));
        $repo = $this->workspace->repo();
        $w = [
            'shown' => $shown,
            'canPost' => $canPost,
            'canPublish' => $rights['publish'],
            'isChief' => $isChief,
            'canModerate' => $isChief || $rights['lead'],
            'shareTargets' => [],
            'shareItem' => null,
            'viewerId' => $userId,
            'event' => null,
            'tasks' => [],
            'pages' => [],
            'page' => null,
            'files' => ['ready' => $filesReady, 'photos' => [], 'documents' => [], 'folders' => []],
            'tracking' => [],
            'assignees' => [],
            'album' => trim((string) $request->query('album', '')),
            'links' => ['ready' => false, 'unitReady' => false, 'canLink' => false, 'units' => [], 'partners' => [], 'exchanges' => [], 'tasks' => [], 'page' => [], 'mentions' => [], 'attached' => 0],
        ];

        if (in_array($view, ['accueil', 'effectif'], true)) {
            $w['event'] = $this->workspace->nextEvent($tenantId, $userId, $memberIds);
        }
        if ($view === 'effectif' && $canPost) {
            $w['tracking'] = $this->workspace->tracking($tenantId, $userId, $memberCards, array_merge((array) ($space['received'] ?? []), (array) ($space['internal'] ?? [])), $w['event'], $isChief);
        }
        if (in_array($view, ['accueil', 'taches'], true) && $shown['taches']) {
            $w['tasks'] = $repo->tasks($tenantId, $unitId);
            foreach ($memberCards as $p) {
                $w['assignees'][(int) $p['id']] = (string) (($p['callsign'] ?? '') !== '' ? $p['callsign'] : ($p['name'] ?? ''));
            }
        }
        if (in_array($view, ['accueil', 'pages'], true) && $shown['pages']) {
            $w['pages'] = $repo->pages($tenantId, $unitId);
        }
        if ($view === 'page' && $shown['pages']) {
            $page = $repo->page($tenantId, $unitId, (int) $request->query('p', 0));
            if ($page !== null) {
                $pageId = (int) $page['id'];
                $voters = [];
                $votes = $repo->votes($pageId);
                foreach ($votes as $options) {
                    foreach ($options as $ids) {
                        $voters = array_merge($voters, $ids);
                    }
                }
                $w['page'] = $page + [
                    'blocksList' => JnetUnitWorkspaceService::blocksFromJson((string) $page['blocks']),
                    'votes' => $votes,
                    'reactions' => $repo->reactions($pageId, $userId),
                    'comments' => $repo->comments($pageId),
                    'edit' => $canPost && (string) $request->query('edit', '') === '1',
                    'event' => $this->workspace->nextEvent($tenantId, $userId, $memberIds),
                    'voterLabels' => $repo->userLabels($tenantId, $voters),
                ];
            }
        }
        if (in_array($view, ['accueil', 'photos', 'documents'], true) && ($shown['photos'] || $shown['documents'])) {
            $w['files'] = \App\Services\Jnet\JnetUnitFiles::merge(
                (new \App\Services\Jnet\JnetUnitFiles())->forUnit($tenantId, $userId, $unitId),
                $this->spaces->unitMedia($tenantId, $userId, $unitId)
            );
        }
        // « Partager » : unités où le lecteur peut publier, seulement là où un lien de partage est affiché.
        if (in_array($view, ['echanges', 'page', 'photos', 'documents', 'partager'], true)) {
            $w['shareTargets'] = $this->spaces->shareTargets($tenantId, $userId, $unitId);
        }
        if ($view === 'partager') {
            $w['shareItem'] = [
                'kind' => in_array((string) $request->query('kind', ''), ['exchange', 'page', 'file'], true) ? (string) $request->query('kind', '') : 'exchange',
                'id' => (int) $request->query('item', 0),
                'title' => mb_substr(trim((string) $request->query('titre', '')), 0, 160),
            ];
        }

        $this->linksData($w, $ctx, $unitId, $view, $space, $tree, $rights, $isChief);

        return ['usw' => $w];
    }

    /**
     * Liaisons et liens que montre la rubrique demandée : seules les lectures utiles à cette rubrique sont faites, et
     * chaque lien passe par le contrôle de droits du lecteur (JnetLinkService / JnetLinkAccess). Les écrans tolèrent
     * l'absence des tables : « ready » reste faux et les blocs ne s'affichent pas.
     *
     * @param array<string, mixed> $w
     * @param array{tenant_id:int,user_id:int} $ctx
     * @param array<string, mixed> $space
     * @param array{read: bool, write: bool, lead: bool, publish: bool, chief: bool} $rights
     */
    private function linksData(array &$w, array $ctx, int $unitId, string $view, array $space, JnetSpaceTree $tree, array $rights, bool $isChief): void
    {
        $links = ['ready' => $this->links->linksReady(), 'unitReady' => $this->links->unitLinksReady(), 'canLink' => $rights['write'], 'units' => [], 'partners' => [], 'exchanges' => [], 'tasks' => [], 'page' => [], 'mentions' => [], 'attached' => 0, 'viewerChief' => $isChief, 'hasAny' => false];
        $viewer = JnetLinkAccess::forViewer($ctx['tenant_id'], $ctx['user_id'], $this->spaces);
        if ($links['unitReady'] && in_array($view, ['accueil', 'liaisons', 'effectif', 'echanges'], true)) {
            $links['units'] = $this->links->unitLinksOf($viewer, $unitId);
            foreach ($links['units'] as $l) {
                if ($l['kind'] === 'rattachement' && $l['status'] === 'active' && empty($l['hidden'])) {
                    ++$links['attached'];
                }
            }
        }
        if ($links['unitReady'] && $view === 'liaisons' && $isChief) {
            // Partenaires proposables : unités visibles du chef, hors espace lui-même et hors filiation (la hiérarchie règle déjà ces cas).
            foreach ($tree->unitIds() as $uid) {
                if ($uid !== $unitId && !$viewer->related($unitId, $uid)) {
                    $links['partners'][] = ['id' => $uid, 'label' => (string) ($tree->node($uid)['label'] ?? 'Unité'), 'depth' => (int) ($tree->node($uid)['depth'] ?? 1)];
                }
            }
        }
        if ($links['ready']) {
            if ($view === 'echanges') {
                $ids = [];
                foreach (['received', 'internal', 'sentUp', 'linked'] as $key) {
                    foreach ((array) ($space[$key] ?? []) as $e) {
                        $ids[] = (int) $e['id'];
                    }
                }
                $links['exchanges'] = $this->links->linksOfMany($viewer, 'echange', $ids);
            }
            if ($view === 'taches') {
                $links['tasks'] = $this->links->linksOfMany($viewer, 'tache', array_map(static fn (array $t): int => (int) $t['id'], (array) ($w['tasks'] ?? [])));
            }
            if ($view === 'page' && is_array($w['page'] ?? null)) {
                $links['page'] = $this->links->linksOf($viewer, 'page', (string) (int) $w['page']['id']);
            }
        }
        // Les citations d'une page sont du texte : elles se résolvent même si les tables de liens ne sont pas installées.
        if ($view === 'page' && is_array($w['page'] ?? null)) {
            $texts = array_map(static fn (array $b): string => (string) ($b['text'] ?? ''), (array) ($w['page']['blocksList'] ?? []));
            $links['mentions'] = $this->links->resolveMentions($viewer, $texts);
        }
        $links['hasAny'] = $links['units'] !== [] || $links['ready'];
        $w['links'] = $links;
    }

    /** Marquage des liaisons d'unités dans le registre du hub : jamais plus que ce que le lecteur voit déjà. @return array<int, list<array<string, mixed>>> */
    private function unitLinkMarks(int $tenantId, int $userId): array
    {
        if (!$this->links->unitLinksReady()) {
            return [];
        }
        try {
            return $this->links->hubMarks(JnetLinkAccess::forViewer($tenantId, $userId, $this->spaces));
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Espace d'une unité dans son interface propre (views/jnet/unit_space.php) : page autonome au thème de l'unité,
     * hors de la coque du back-office.
     *
     * @param array{tenant_id:int,user_id:int} $ctx
     * @param array<string, mixed> $space données de JnetSpaceService::buildSpace
     * @param array<string, mixed> $look
     * @param list<int> $chain
     * @param array<string, mixed> $extra
     */
    private function unitShell(array $ctx, int $unitId, array $space, array $look, string $view, array $chain, array $extra = []): Response
    {
        $tenant = $this->tenantRepository->findById($ctx['tenant_id']);
        $callsign = trim((string) Session::get('callsign', ''));

        return Response::view('jnet.unit_space', array_merge($space, [
            'title' => (string) ($space['space']['label'] ?? 'Unité'),
            'spaceLook' => $look,
            'usView' => $view,
            'usCommunity' => is_array($tenant) ? community_display_name($tenant) : '',
            'usOperator' => $callsign !== '' ? $callsign : (string) Session::get('display_name', ''),
            'canCustomize' => $this->canCustomize($ctx['tenant_id'], $ctx['user_id'], $chain),
            'customizeUrl' => url('jnet/u/' . $unitId . '/personnaliser'),
            'usHome' => $this->homeUrl(),
            'usHomeLabel' => 'Mon extranet',
        ], $extra));
    }

    /**
     * Recherche dans l'espace d'une unité (champ de l'en-tête, touche « / »). Lecture seule : ne renvoie que ce que
     * l'espace montre déjà au lecteur (échanges, membres, sous-unités, opérations, tâches, pages, fichiers partagés).
     */
    public function unitSearch(Request $request, array $params = []): Response
    {
        $ctx = $this->ensureAuth();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $unitId = (int) ($params['id'] ?? 0);
        $tree = $this->spaces->tree($ctx['tenant_id'], $ctx['user_id']);
        $q = trim((string) $request->query('q', ''));
        if ($unitId <= 0 || !$tree->has($unitId) || mb_strlen($q) < 2 || !$this->spaces->canRead($ctx['tenant_id'], $ctx['user_id'], $unitId)) {
            return Response::json(['results' => []]);
        }
        $q = mb_substr($q, 0, 80);
        $space = $this->spaces->buildSpace($ctx['tenant_id'], $ctx['user_id'], $unitId);
        if ($space === null) {
            return Response::json(['results' => []]);
        }
        $settings = $this->spaceSettings->find($ctx['tenant_id'], $unitId);
        $hidden = is_array($settings['hidden_modules'] ?? null) ? $settings['hidden_modules'] : [];
        $base = url('jnet/u/' . $unitId);
        $pool = [];
        foreach (['received' => 'Reçu', 'internal' => 'Fil de l’unité', 'sentUp' => 'Remonté'] as $key => $label) {
            foreach ((array) ($space[$key] ?? []) as $e) {
                $pool[] = [$label, (string) $e['title'], (string) $e['author'], $base . '?vue=echanges&direct=1#ex-' . (int) $e['id'], (string) ($e['body'] ?? '')];
            }
        }
        foreach ((array) ($space['members'] ?? []) as $p) {
            $pool[] = ['Membre', (string) $p['name'], trim((string) ($p['grade'] ?? '') . ' ' . (string) ($p['function'] ?? '')), (string) $p['href'], (string) ($p['callsign'] ?? '')];
        }
        foreach ((array) ($space['subUnits'] ?? []) as $u) {
            $pool[] = ['Sous-unité', (string) $u['label'], (string) ($u['leader'] ?? ''), (string) $u['href'], ''];
        }
        foreach ((array) ($space['spaceOps'] ?? []) as $o) {
            $pool[] = ['Opération', (string) $o['title'], (string) ($o['state'] ?? ''), url('jnet/operations/' . (int) $o['id']), ''];
        }
        if ($this->workspace->ready()) {
            if (!in_array('taches', $hidden, true)) {
                foreach ($this->workspace->repo()->tasks($ctx['tenant_id'], $unitId) as $t) {
                    $pool[] = ['Tâche', (string) $t['title'], (string) (JnetUnitWorkspaceService::TASK_LABELS[(string) $t['status']] ?? ''), $base . '?vue=taches&direct=1', (string) ($t['assignee_label'] ?? '')];
                }
            }
            if (!in_array('pages', $hidden, true)) {
                foreach ($this->workspace->repo()->pages($ctx['tenant_id'], $unitId) as $pg) {
                    $pool[] = ['Page', (string) ($pg['title'] !== '' ? $pg['title'] : 'Sans titre'), '', $base . '?vue=page&p=' . (int) $pg['id'] . '&direct=1', ''];
                }
            }
        }
        if (!in_array('photos', $hidden, true) || !in_array('documents', $hidden, true)) {
            $files = (new \App\Services\Jnet\JnetUnitFiles())->forUnit($ctx['tenant_id'], $ctx['user_id'], $unitId);
            foreach ($files['documents'] as $f) {
                if (!in_array('documents', $hidden, true)) {
                    $pool[] = ['Document', (string) $f['title'], (string) $f['album'], (string) $f['href'], ''];
                }
            }
            foreach ($files['photos'] as $f) {
                if (!in_array('photos', $hidden, true)) {
                    $pool[] = ['Photo', (string) $f['title'], (string) $f['album'], (string) $f['preview'], ''];
                }
            }
        }
        $out = [];
        foreach ($pool as [$type, $label, $detail, $href, $hay]) {
            if (mb_stripos($label . ' ' . $detail . ' ' . $hay, $q) === false) {
                continue;
            }
            $out[] = ['type' => $type, 'label' => $label, 'detail' => $detail, 'href' => $href];
            if (count($out) >= 12) {
                break;
            }
        }

        return Response::json(['results' => $out]);
    }

    /**
     * Écran d'entrée d'un espace d'unité : page plein écran aux couleurs de l'unité (insigne, nom, devise, liaison),
     * puis l'espace. Une fois par espace et par session ; l'unité peut le couper.
     */
    public function unitEntry(Request $request, array $params = []): Response
    {
        $ctx = $this->ensureAuth();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $unitId = (int) ($params['id'] ?? 0);
        $tree = $this->spaces->tree($ctx['tenant_id'], $ctx['user_id']);
        if ($unitId <= 0 || !$tree->has($unitId) || !$this->spaces->canRead($ctx['tenant_id'], $ctx['user_id'], $unitId)) {
            Session::flash('error', 'Cet espace n’existe pas, ne vous est pas accessible ou est réservé aux unités que son chef a autorisées.');

            return Response::redirect($this->homeUrl());
        }
        $node = (array) $tree->node($unitId);
        $look = JnetUnitSpaceSettings::look($this->spaceSettings->find($ctx['tenant_id'], $unitId), $node);
        $entered = Session::get(self::SESSION_ENTERED, []);
        $entered = is_array($entered) ? $entered : [];
        $entered[$unitId] = time();
        Session::set(self::SESSION_ENTERED, $entered);
        $tenant = $this->tenantRepository->findById($ctx['tenant_id']);
        $chain = [];
        foreach ($tree->chain($unitId) as $id) {
            if ($id > 0 && $id !== $unitId) {
                $chain[] = (string) (($tree->node($id) ?? [])['label'] ?? '');
            }
        }
        $viewerUnits = $this->spaces->viewerUnitIds($ctx['tenant_id'], $ctx['user_id']);
        $isMember = in_array($unitId, $viewerUnits, true);
        // Les lignes de la séquence d'entrée ne disent que ce que le lecteur sait déjà : son droit, ses non-lus, l'effectif.
        $right = JnetMemberHub::rightFor($tree, $unitId, $viewerUnits, $this->spaceSettings->commandedUnitIds($ctx['tenant_id'], $ctx['user_id']), $this->spaces->isOrgWideWriter());
        $unread = (int) ($this->spaces->unreadByUnit($ctx['tenant_id'], $ctx['user_id'])[$unitId] ?? 0);
        $strength = $this->spaces->strengthByUnit($ctx['tenant_id'], $ctx['user_id'])[$unitId] ?? [];
        $desc = JnetMemberHub::describe($node, (int) ($strength['available'] ?? 0), (int) ($strength['total'] ?? 0) ?: (int) ($node['strength'] ?? 0), $chain !== [] ? (string) end($chain) : '', $strength !== []);

        return Response::view('jnet.entry', [
            'title' => (string) ($node['label'] ?? 'Unité'),
            'entryLook' => $look,
            'entryUnit' => (string) ($node['label'] ?? 'Unité'),
            'entryChain' => array_values(array_filter($chain)),
            'entryCommunity' => is_array($tenant) ? community_display_name($tenant) : '',
            'entryOperator' => trim((string) Session::get('callsign', '')) !== '' ? (string) Session::get('callsign', '') : (string) Session::get('display_name', ''),
            'entryRole' => $isMember ? 'Membre de l’unité' : 'Visiteur autorisé',
            'entryUnitId' => $unitId,
            'entryRight' => $right['label'],
            'entryUnread' => $unread,
            'entryKind' => $desc['kind'],
            'entryFacts' => $desc['facts'],
            'entryNext' => url('jnet/u/' . $unitId . '?direct=1'),
            'entryLeave' => $this->homeUrl(),
            'entryLeaveLabel' => 'Mon extranet',
        ]);
    }

    /** Personnalisation d'un espace d'unité : chef de l'unité (ou au-dessus) et organisation. */
    public function customizeSpace(Request $request, array $params = []): Response
    {
        $ctx = $this->ensureAuth();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $unitId = (int) ($params['id'] ?? 0);
        $tree = $this->spaces->tree($ctx['tenant_id'], $ctx['user_id']);
        if ($unitId <= 0 || !$tree->has($unitId)) {
            return Response::redirect($this->homeUrl());
        }
        if (!$this->canCustomize($ctx['tenant_id'], $ctx['user_id'], $tree->chain($unitId))) {
            Session::flash('error', 'Seuls le chef de l’unité et l’encadrement de l’organisation personnalisent cet espace.');

            return Response::redirect(url('jnet/u/' . $unitId . '?direct=1'));
        }
        $node = (array) $tree->node($unitId);
        $settings = $this->spaceSettings->find($ctx['tenant_id'], $unitId);
        $draft = Session::getFlash('jnet_space_draft');
        if (is_array($draft)) {
            $settings = array_merge($settings, $draft);
        }

        $space = $this->spaces->buildSpace($ctx['tenant_id'], $ctx['user_id'], $unitId);
        if ($space === null) {
            return Response::redirect($this->homeUrl());
        }
        $rules = $this->spaceSettings->rules($ctx['tenant_id'], $unitId);
        $units = [];
        foreach ($tree->unitIds() as $uid) {
            if ($uid !== $unitId) {
                $units[] = ['id' => $uid, 'label' => (string) (($tree->node($uid) ?? [])['label'] ?? 'Unité'), 'depth' => (int) (($tree->node($uid) ?? [])['depth'] ?? 1)];
            }
        }
        $managerCallsigns = $this->spaceSettings->callsigns((array) $rules['managers']);
        $managers = [];
        foreach ((array) $rules['managers'] as $mid) {
            $managers[] = ['id' => (int) $mid, 'label' => ($managerCallsigns[(int) $mid] ?? '') !== '' ? (string) $managerCallsigns[(int) $mid] : 'Membre ' . (int) $mid];
        }

        return $this->unitShell($ctx, $unitId, $space, JnetUnitSpaceSettings::look($settings, $node), 'personnaliser', $tree->chain($unitId), [
            'customizeRules' => $rules,
            'customizeRulesReady' => $this->spaceSettings->rulesColumnsReady() && $this->spaceSettings->rulesTablesReady(),
            'customizeUnits' => $units,
            'customizeManagers' => $managers,
            'customizeUnitId' => $unitId,
            'customizeUnit' => (string) ($node['label'] ?? 'Unité'),
            'customizeSettings' => $settings,
            'customizeLook' => JnetUnitSpaceSettings::look($settings, $node),
            'customizeOrbat' => ['accent' => (string) ($node['accent'] ?? ''), 'motto' => (string) ($node['motto'] ?? ''), 'badge' => $node['badge'] ?? null],
            'customizeReady' => $this->spaceSettings->ready(),
            'customizeModulesReady' => $this->spaceSettings->modulesReady(),
            'customizeAction' => url('jnet/u/' . $unitId . '/personnaliser'),
            'customizeBack' => url('jnet/u/' . $unitId . '?direct=1'),
            'customizePreview' => url('jnet/u/' . $unitId . '/entree'),
        ]);
    }

    public function customizeSpaceSave(Request $request, array $params = []): Response
    {
        $ctx = $this->ensureAuth();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $unitId = (int) ($params['id'] ?? 0);
        $tree = $this->spaces->tree($ctx['tenant_id'], $ctx['user_id']);
        $back = url('jnet/u/' . $unitId . '/personnaliser');
        if ($unitId <= 0 || !$tree->has($unitId)) {
            return Response::redirect($this->homeUrl());
        }
        if (!\App\Core\Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Votre session a expiré, rien n’a été enregistré.');

            return Response::redirect($back);
        }
        if (!$this->canCustomize($ctx['tenant_id'], $ctx['user_id'], $tree->chain($unitId))) {
            Session::flash('error', 'Seuls le chef de l’unité et l’encadrement de l’organisation personnalisent cet espace.');

            return Response::redirect(url('jnet/u/' . $unitId . '?direct=1'));
        }
        if (!$this->spaceSettings->ready()) {
            Session::flash('error', 'La personnalisation des espaces arrive avec la prochaine mise à jour du site (étape jnet_unit_spaces).');

            return Response::redirect($back);
        }
        $tenantId = $ctx['tenant_id'];
        try {
            if ((string) $request->input('action', '') === 'reset') {
                $this->spaceSettings->reset($tenantId, $unitId);
                Session::flash('success', 'Apparence par défaut rétablie.');

                return Response::redirect($back);
            }
            $values = JnetUnitSpaceSettings::normalize([
                'theme' => $request->input('theme', ''),
                'accent_custom' => $request->input('accent_custom', ''),
                'accent_color' => $request->input('accent_color', ''),
                'tagline' => $request->input('tagline', ''),
                'welcome_text' => $request->input('welcome_text', ''),
                'access_label' => $request->input('access_label', ''),
                'entry_screen' => $request->input('entry_screen', ''),
            ]);
            $current = $this->spaceSettings->find($tenantId, $unitId);
            $paths = [];
            foreach (['emblem' => 'emblem_path', 'banner' => 'banner_path'] as $kind => $column) {
                $old = (string) ($current[$column] ?? '');
                $paths[$column] = $old;
                $upload = JnetUnitSpaceSettings::storeImage($tenantId, is_array($_FILES[$kind] ?? null) ? $_FILES[$kind] : null, $kind);
                if ($upload['error'] !== '') {
                    Session::flash('error', ($kind === 'banner' ? 'Bannière' : 'Insigne') . ' : ' . $upload['error']);
                    Session::flash('jnet_space_draft', $values);

                    return Response::redirect($back);
                }
                if ($upload['path'] !== '') {
                    $paths[$column] = $upload['path'];
                } elseif ((string) $request->input('remove_' . $kind, '') !== '') {
                    $paths[$column] = '';
                }
            }
            $this->spaceSettings->save($tenantId, $unitId, $values, $paths['emblem_path'], $paths['banner_path'], $ctx['user_id']);
            if ((string) $request->input('modules_present', '') !== '') {
                $this->spaceSettings->saveHiddenModules($tenantId, $unitId, JnetUnitWorkspaceService::hiddenFromShown($request->input('modules_shown', [])), $ctx['user_id']);
            }
            foreach (['emblem_path', 'banner_path'] as $column) {
                $old = (string) ($current[$column] ?? '');
                if ($old !== '' && $old !== $paths[$column]) {
                    JnetUnitSpaceSettings::deleteImage($old, $tenantId);
                }
            }
            $unknown = [];
            if ((string) $request->input('rules_present', '') !== '' && $this->spaceSettings->rulesColumnsReady() && $this->spaceSettings->rulesTablesReady()) {
                $unknown = $this->saveRules($request, $tenantId, $unitId, $ctx['user_id'], $tree);
            }
            Session::flash('success', 'Espace enregistré. Les membres le voient à leur prochaine visite.'
                . ($unknown !== [] ? ' Indicatif introuvable parmi les membres de l’unité et de son encadrement : ' . implode(', ', $unknown) . '.' : ''));
        } catch (\Throwable $e) {
            error_log('[jnet] personnalisation espace ' . $unitId . ' : ' . $e->getMessage());
            Session::flash('error', 'L’espace n’a pas pu être enregistré. Réessayez dans un instant.');
        }

        return Response::redirect($back);
    }

    /**
     * Réglages d'ordres, accès par unité et gestionnaires, envoyés par le formulaire « Personnaliser » (déjà réservé au
     * chef de l'espace). Un réglage ne peut qu'ouvrir les outils de cet espace à des gens qui voient déjà l'unité, ou
     * restreindre : voir JnetSpaceRules. Retourne les indicatifs de gestionnaires qui n'ont pas été reconnus.
     *
     * @return list<string>
     */
    private function saveRules(Request $request, int $tenantId, int $unitId, int $userId, JnetSpaceTree $tree): array
    {
        $this->spaceSettings->saveRules(
            $tenantId,
            $unitId,
            (string) $request->input('require_ack', '') !== '',
            (string) $request->input('auto_relay', '') !== '',
            JnetSpaceRules::postMin($request->input('post_min', 'member')),
            $userId
        );
        $this->spaceSettings->saveAccess($tenantId, $unitId, JnetSpaceRules::accessFromForm(
            $request->input('access', []),
            $request->input('access_add_unit', ''),
            $request->input('access_add_level', ''),
            $tree,
            $unitId
        ), $userId);
        // Gestionnaires : seulement des membres de l'unité, de ses sous-unités ou de son encadrement.
        $pool = $tree->memberIdsInSubtree($unitId);
        foreach ($tree->ancestors($unitId) as $above) {
            if ($above !== JnetSpaceTree::ORG) {
                $pool = array_merge($pool, array_map('intval', (array) (($tree->node($above) ?? [])['members'] ?? [])));
            }
        }
        $pool = array_values(array_unique(array_map('intval', $pool)));
        $keep = array_values(array_intersect(array_map('intval', (array) $request->input('managers_keep', [])), $pool));
        $found = JnetSpaceRules::managersFromCallsigns((string) $request->input('managers_add', ''), $this->spaceSettings->callsigns($pool));
        $this->spaceSettings->saveManagers($tenantId, $unitId, array_slice(array_values(array_unique(array_merge($keep, $found['ids']))), 0, JnetSpaceRules::MANAGERS_MAX), $userId);

        return $found['unknown'];
    }

    private function hasEntered(int $unitId): bool
    {
        $entered = Session::get(self::SESSION_ENTERED, []);

        return is_array($entered) && isset($entered[$unitId]);
    }

    /** Où ramène « retour » : l'extranet du membre, ou l'espace commun pour l'encadrement de l'organisation. */
    private function homeUrl(): string
    {
        return url('jnet');
    }

    /** @param list<int> $chain de l'organisation à l'unité */
    private function canCustomize(int $tenantId, int $userId, array $chain): bool
    {
        if ($this->spaces->isOrgWideWriter() || \App\Core\Gate::getInstance()->allows('organization.orbat.manage')) {
            return true;
        }

        return $this->spaceSettings->commandsWithin($tenantId, $userId, $chain);
    }

    /** Publication d’un échange depuis un espace (0 = Organisation). */
    public function postExchange(Request $request, array $params = []): Response
    {
        $ctx = $this->ensureAuth();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $spaceId = max(0, (int) ($params['id'] ?? 0));
        $back = $this->spaces->spaceUrl($spaceId);
        if (!\App\Core\Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Votre session a expiré, l’échange n’a pas été publié.');

            return Response::redirect($back);
        }
        $error = $this->spaces->post(
            $ctx['tenant_id'],
            $ctx['user_id'],
            $spaceId,
            (string) $request->input('kind', 'info'),
            (string) $request->input('title', ''),
            (string) $request->input('body', ''),
            (string) $request->input('link', ''),
            array_map('intval', (array) $request->input('targets', []))
        );
        if ($error !== null) {
            Session::flash('error', $error);
            Session::flash('jnet_exchange_draft', [
                'kind' => (string) $request->input('kind', 'info'),
                'title' => (string) $request->input('title', ''),
                'body' => (string) $request->input('body', ''),
                'link' => (string) $request->input('link', ''),
            ]);
        } else {
            Session::flash('success', 'Échange publié.');
        }

        return Response::redirect($back);
    }

    /** Accusé de lecture d’un échange. */
    public function acknowledgeExchange(Request $request, array $params = []): Response
    {
        $ctx = $this->ensureAuth();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $back = (string) $request->input('back', '');
        if ($back === '' || !JnetSpaceService::isInternalLink($back)) {
            $back = url('jnet');
        }
        // Accusé envoyé sans rechargement (fenêtre « prise en compte ») : réponse JSON, sinon redirection en ligne.
        $json = str_contains(strtolower((string) $request->header('Accept')), 'application/json');
        if (!\App\Core\Csrf::validate($request->input('_csrf_token'))) {
            if ($json) {
                return Response::json(['ok' => false, 'message' => 'Votre session a expiré, réessayez.'], 419);
            }
            Session::flash('error', 'Votre session a expiré, réessayez.');

            return Response::redirect($back);
        }
        $acknowledged = $this->spaces->acknowledge($ctx['tenant_id'], $ctx['user_id'], (int) ($params['id'] ?? 0));
        if ($json) {
            return Response::json(['ok' => $acknowledged, 'message' => $acknowledged ? 'Accusé de réception enregistré.' : 'Échange introuvable.'], $acknowledged ? 200 : 404);
        }
        if (!$acknowledged) {
            Session::flash('error', 'Échange introuvable.');
        }

        return Response::redirect($back);
    }

    public function unit(Request $request, array $params = []): Response
    {
        $ctx = $this->ensureAuth();
        if ($ctx instanceof Response) {
            return $ctx;
        }

        return $this->render('unit', 'Unité', $this->jnet->buildUnitPage($ctx['tenant_id'], $ctx['user_id']), 'unit');
    }

    public function personnel(Request $request, array $params = []): Response
    {
        $ctx = $this->ensureAuth();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $filter = trim((string) $request->query('filtre', 'all'));
        $all = $this->jnet->loadPersonnelCards($ctx['tenant_id']);
        $fils = $this->unitFils($ctx['tenant_id'], $ctx['user_id']);
        // Tous les membres sont rendus : l'annuaire se filtre et se trie dans la page ; « match » reprend le filtre de l'adresse
        // (?filtre=) pour que la page reste juste sans JavaScript.
        $cards = [];
        foreach ($all as $p) {
            $p['match'] = $this->personnelMatches($p, $filter);
            $p['fil'] = $fils[(int) ($p['unit_id'] ?? 0)] ?? null;
            $cards[] = $p;
        }

        return $this->render('personnel', 'Personnel', [
            'personnel' => $cards,
            'personnelFilter' => $filter,
            'personnelFilters' => $this->jnet->personnelFilterOptions($all),
            'personnelTotal' => count($all),
        ], 'personnel', ['jnet_personnel.css'], ['jnet_personnel.js']);
    }

    /** @param array<string, mixed> $p */
    private function personnelMatches(array $p, string $filter): bool
    {
        if ($filter === '' || strcasecmp($filter, 'all') === 0) {
            return true;
        }
        if (strcasecmp($filter, 'off') === 0) {
            return ($p['duty'] ?? '') === 'off';
        }
        $needle = mb_strtoupper($filter);
        $unit = mb_strtoupper(trim((string) ($p['unit'] ?? '')));
        if ($unit !== '' && $unit === $needle) {
            return true;
        }
        $hay = mb_strtoupper((string) ($p['unit'] ?? '') . ' ' . ($p['function'] ?? '') . ' ' . ($p['role'] ?? '') . ' ' . ($p['name'] ?? '') . ' ' . ($p['callsign'] ?? ''));

        return str_contains($hay, $needle);
    }

    /**
     * Fil hiérarchique et lien de chaque unité que le lecteur voit dans l'ORBAT (arbre déjà filtré pour lui, confidentialité
     * comprise) : une unité absente de son arbre s'affiche sans fil ni lien.
     *
     * @return array<int, array{chain: list<string>, href: string}>
     */
    private function unitFils(int $tenantId, int $userId): array
    {
        $out = [];
        try {
            $tree = $this->spaces->tree($tenantId, $userId);
            foreach ($tree->unitIds() as $id) {
                $labels = [];
                foreach ($tree->chain($id) as $step) {
                    if ($step === JnetSpaceTree::ORG || $step === $id) {
                        continue;
                    }
                    $labels[] = (string) (($tree->node($step) ?? [])['label'] ?? '');
                }
                $out[$id] = ['chain' => array_values(array_filter($labels, static fn (string $l): bool => $l !== '')), 'href' => url('jnet/u/' . $id)];
            }
        } catch (\Throwable) {
            return [];
        }

        return $out;
    }

    public function personnelShow(Request $request, array $params = []): Response
    {
        $ctx = $this->ensureAuth();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $id = (int) ($params['id'] ?? 0);
        $card = $this->jnet->findPersonnelCard($ctx['tenant_id'], $id);
        if ($card === null) {
            Session::flash('error', 'Fiche personnel introuvable.');

            return Response::redirect(url('jnet/personnel'));
        }
        $card['fil'] = $this->unitFils($ctx['tenant_id'], $ctx['user_id'])[(int) ($card['unit_id'] ?? 0)] ?? null;

        return $this->render('personnel_show', (string) ($card['name'] ?? 'Personnel'), [
            'person' => $card,
        ], 'personnel', ['jnet_personnel.css']);
    }

    public function operations(Request $request, array $params = []): Response
    {
        $ctx = $this->ensureAuth();
        if ($ctx instanceof Response) {
            return $ctx;
        }

        return $this->render('operations', 'Opérations', [
            'operations' => $this->jnet->loadOperations($ctx['tenant_id']),
        ], 'operations');
    }

    public function operationShow(Request $request, array $params = []): Response
    {
        $ctx = $this->ensureAuth();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $op = $this->jnet->findOperation($ctx['tenant_id'], (int) ($params['id'] ?? 0));
        if ($op === null) {
            Session::flash('error', 'Opération introuvable.');

            return Response::redirect(url('jnet/operations'));
        }

        return $this->render('operation_show', (string) ($op['title'] ?? 'Opération'), [
            'operation' => $op,
            'linkBlock' => $this->linkBlock($ctx, 'operation', (string) (int) $op['id'], true),
        ], 'operations', ['jnet_links.css'], ['jnet_links.js']);
    }

    /**
     * Bloc « Liés à » d'un contenu hors espace d'unité (fiche d'opération, dossier de cible) : liens vérifiés pour ce
     * lecteur, et vue d'ensemble pour une opération. Null si les liens ne sont pas encore activés (migration).
     *
     * @param array{tenant_id:int,user_id:int} $ctx
     * @return array<string, mixed>|null
     */
    private function linkBlock(array $ctx, string $type, string $id, bool $overview): ?array
    {
        if (!$this->links->linksReady()) {
            return null;
        }
        $viewer = JnetLinkAccess::forViewer($ctx['tenant_id'], $ctx['user_id'], $this->spaces);
        $ref = JnetLinkRules::ref($type, $id);
        $items = $this->links->linksOf($viewer, $type, $id);

        return [
            'ref' => $ref,
            'items' => $items,
            'overview' => $overview ? $this->links->overview($viewer, $type, $id) : null,
            'canAdd' => $viewer->inOwnUnits() || $viewer->isOrgManager(),
            'space' => 0,
            'newUrl' => url('jnet/liens/nouveau?src=' . rawurlencode($ref)),
            'searchUrl' => url('jnet/liens/recherche'),
            'createUrl' => url('jnet/liens'),
            'removeBase' => url('jnet/liens'),
            'types' => $type === 'operation' ? 'echange,page,tache,cible,document,unite,membre' : 'echange,page,tache,operation,document,unite,membre',
        ];
    }

    /**
     * Page « Lier à… » sans JavaScript : recherche dans ce que le lecteur voit déjà, puis un bouton par résultat (POST avec
     * jeton). Avec JavaScript, la même recherche s'ouvre en fenêtre.
     */
    public function linkNew(Request $request, array $params = []): Response
    {
        $ctx = $this->ensureAuth();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $viewer = JnetLinkAccess::forViewer($ctx['tenant_id'], $ctx['user_id'], $this->spaces);
        $src = JnetLinkRules::parseRef((string) $request->query('src', ''));
        $seen = $src !== null ? $viewer->see($src['type'], $src['id']) : null;
        if ($seen === null) {
            Session::flash('error', 'Élément introuvable.');

            return Response::redirect(url('jnet'));
        }
        $q = trim(mb_substr((string) $request->query('q', ''), 0, 80));
        $space = max(0, (int) $request->query('space', 0));
        $types = array_values(array_filter(array_map('trim', explode(',', (string) $request->query('types', ''))), static fn (string $t): bool => isset(JnetLinkRules::TYPES[$t])));
        $results = [];
        if (mb_strlen($q) >= 2) {
            foreach ($viewer->search($q, $types, $space) as $r) {
                if (JnetLinkRules::canPair($src['type'], $src['id'], $r['type'], $r['id'])) {
                    $results[] = $r + ['ref' => JnetLinkRules::ref($r['type'], $r['id']), 'typeLabel' => JnetLinkRules::TYPES[$r['type']] ?? ''];
                }
            }
        }

        return $this->render('link_new', 'Lier à…', [
            'linkNew' => [
                'src' => JnetLinkRules::ref($src['type'], $src['id']),
                'srcTitle' => $seen['title'],
                'srcType' => JnetLinkRules::TYPES[$src['type']] ?? '',
                'srcHref' => $seen['href'],
                'q' => $q,
                'space' => $space,
                'types' => implode(',', $types),
                'results' => $results,
                'ready' => $this->links->linksReady(),
                'createUrl' => url('jnet/liens'),
            ],
        ], $src['type'] === 'operation' ? 'operations' : null, ['jnet_links.css']);
    }

    public function intelligence(Request $request, array $params = []): Response
    {
        $ctx = $this->ensureAuth();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $home = $this->jnet->buildHome($ctx['tenant_id'], $ctx['user_id']);

        return $this->render('intelligence', 'Renseignement', [
            'intelFeed' => $home['intelFeed'],
            'priorityTargets' => $home['priorityTargets'],
            'viewerLens' => $home['viewerLens'],
        ], 'intelligence');
    }

    public function targets(Request $request, array $params = []): Response
    {
        $ctx = $this->ensureAuth();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $targets = $this->jnet->loadTargets($ctx['tenant_id']);

        return $this->render('targets', 'Cibles prioritaires', [
            'targets' => $targets,
            'targetsTotal' => count($targets),
            'intelLocked' => !$this->jnet->mayReadIntelligence(),
        ], 'targets');
    }

    public function targetShow(Request $request, array $params = []): Response
    {
        $ctx = $this->ensureAuth();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $id = rawurldecode((string) ($params['id'] ?? ''));
        $target = $this->jnet->findTarget($ctx['tenant_id'], $id);
        if ($target === null) {
            Session::flash('error', 'Dossier cible introuvable.');

            return Response::redirect(url('jnet/cibles'));
        }
        $tab = strtolower(trim((string) $request->query('onglet', 'profil')));

        return $this->render('target_show', (string) ($target['name'] ?? 'Cible'), [
            'target' => $target,
            'targetTab' => $tab,
            'linkBlock' => $this->linkBlock($ctx, 'cible', $id, false),
        ], 'targets', ['jnet_links.css'], ['jnet_links.js']);
    }

    public function exploitation(Request $request, array $params = []): Response
    {
        $ctx = $this->ensureAuth();
        if ($ctx instanceof Response) {
            return $ctx;
        }

        return $this->render('exploitation', 'Exploitation', $this->jnet->buildExploitation($ctx['tenant_id']), 'exploitation');
    }

    public function library(Request $request, array $params = []): Response
    {
        $ctx = $this->ensureAuth();
        if ($ctx instanceof Response) {
            return $ctx;
        }

        return $this->render('library', 'Bibliothèque', $this->jnet->buildLibrary($ctx['tenant_id']), 'library');
    }

    public function mail(Request $request, array $params = []): Response
    {
        $ctx = $this->ensureAuth();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $tenantId = $ctx['tenant_id'];
        $userId = $ctx['user_id'];

        $box = (string) $request->query('boite', 'reception');
        if (!in_array($box, ['reception', 'non-lus', 'envoyes'], true)) {
            $box = 'reception';
        }
        $repoBox = match ($box) {
            'non-lus' => 'unread',
            'envoyes' => 'sent',
            default => 'inbox',
        };

        $threads = $this->messageRepository->listThreadsForUserByBox($tenantId, $userId, $repoBox);
        $selected = null;
        $messages = [];
        $participants = [];

        $threadId = (int) $request->query('fil', 0);
        if ($threadId <= 0 && $threads !== []) {
            $threadId = (int) ($threads[0]['id'] ?? 0);
        }
        if ($threadId > 0 && $this->messageRepository->userInThread($threadId, $userId)) {
            $selected = $this->messageRepository->findThread($threadId, $tenantId);
            if ($selected !== null) {
                $this->messageRepository->markThreadRead($threadId, $userId);
                $messages = $this->messageRepository->listMessages($threadId);
                $participants = $this->messageRepository->listThreadParticipants($threadId);
            }
        }

        return $this->render('mail', 'Messagerie', [
            'mailBox' => $box,
            'mailBoxes' => $this->mailBoxes($tenantId, $userId),
            'mailThreads' => $threads,
            'mailSelected' => $selected,
            'mailMessages' => $messages,
            'mailParticipants' => $participants,
            'mailCurrentUserId' => $userId,
            'mailFullUrl' => url('messages'),
            'mailCanSend' => $this->canSendMessages($tenantId, $userId),
            'mailPrecedences' => $this->messaging->precedences(),
        ], 'inbox');
    }

    public function mailCompose(Request $request, array $params = []): Response
    {
        $ctx = $this->ensureAuth();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $tenantId = $ctx['tenant_id'];
        $userId = $ctx['user_id'];

        if (!$this->canSendMessages($tenantId, $userId)) {
            Session::flash('error', 'Vous ne pouvez pas envoyer de message pour le moment.');

            return Response::redirect(url('jnet/courrier'));
        }

        $draft = Session::getFlash('jnet_mail_draft');
        $draft = is_array($draft) ? $draft : [];
        $replyTo = (int) $request->query('repondre', 0);
        if ($replyTo > 0 && $this->messageRepository->userInThread($replyTo, $userId)) {
            $thread = $this->messageRepository->findThread($replyTo, $tenantId);
            if ($thread !== null && ($draft['subject'] ?? '') === '') {
                $draft['subject'] = 'RE : ' . (string) ($thread['subject'] ?? '');
            }
        }

        return $this->render('mail_compose', 'Nouveau message', [
            'mailBoxes' => $this->mailBoxes($tenantId, $userId),
            'mailBox' => 'redaction',
            'mailGroups' => $this->messaging->groups($tenantId),
            'mailDirectory' => $this->messaging->directory($tenantId, $userId),
            'mailPrecedences' => $this->messaging->precedences(),
            'mailDraft' => [
                'subject' => (string) ($draft['subject'] ?? ''),
                'body' => (string) ($draft['body'] ?? ''),
                'precedence' => (string) ($draft['precedence'] ?? 'routine'),
                'groups' => array_map('strval', (array) ($draft['groups'] ?? [])),
                'members' => array_map('intval', (array) ($draft['members'] ?? [])),
            ],
            'mailEmailLimit' => $this->messaging->emailNotificationLimit(),
        ], 'inbox');
    }

    public function mailSend(Request $request, array $params = []): Response
    {
        $ctx = $this->ensureAuth();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $tenantId = $ctx['tenant_id'];
        $userId = $ctx['user_id'];

        if (!\App\Core\Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Votre session a expiré, le message n’a pas été envoyé.');

            return Response::redirect(url('jnet/courrier/nouveau'));
        }
        if (!$this->canSendMessages($tenantId, $userId)) {
            Session::flash('error', 'Vous ne pouvez pas envoyer de message pour le moment.');

            return Response::redirect(url('jnet/courrier'));
        }

        $subject = trim((string) $request->input('subject', ''));
        $body = trim((string) $request->input('body', ''));
        $precedence = (string) $request->input('precedence', 'routine');
        if (!array_key_exists($precedence, $this->messaging->precedences())) {
            $precedence = 'routine';
        }
        $groupKeys = array_map('strval', (array) $request->input('groups', []));
        $memberIds = array_map('intval', (array) $request->input('members', []));

        $keepDraft = static function () use ($subject, $body, $precedence, $groupKeys, $memberIds): void {
            Session::flash('jnet_mail_draft', [
                'subject' => $subject,
                'body' => $body,
                'precedence' => $precedence,
                'groups' => $groupKeys,
                'members' => $memberIds,
            ]);
        };

        if ($body === '') {
            Session::flash('error', 'Le corps du message est vide.');
            $keepDraft();

            return Response::redirect(url('jnet/courrier/nouveau'));
        }

        $resolved = $this->messaging->resolveSelection($tenantId, $userId, $groupKeys, $memberIds);
        if ($resolved['error'] !== null) {
            Session::flash('error', $resolved['error']);
            $keepDraft();

            return Response::redirect(url('jnet/courrier/nouveau'));
        }

        $threadId = $this->messageRepository->createDiffusionThread(
            $tenantId,
            $userId,
            $subject !== '' ? $subject : 'Sans objet',
            $resolved['recipients'],
            $precedence,
            $resolved['summary'],
            'jnet'
        );
        $this->messageRepository->addMessage($threadId, $userId, $body);
        $this->messageRepository->markThreadRead($threadId, $userId);
        $this->notifyThread($tenantId, $threadId, $userId, $body, count($resolved['recipients']));

        $count = count($resolved['recipients']);
        Session::flash('success', 'Message transmis à ' . $count . ' destinataire' . ($count > 1 ? 's' : '') . '.');

        return Response::redirect(url('jnet/courrier') . '?fil=' . $threadId);
    }

    public function mailReply(Request $request, array $params = []): Response
    {
        $ctx = $this->ensureAuth();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        $tenantId = $ctx['tenant_id'];
        $userId = $ctx['user_id'];
        $threadId = (int) ($params['id'] ?? 0);

        if (!\App\Core\Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Votre session a expiré, la réponse n’a pas été envoyée.');

            return Response::redirect(url('jnet/courrier'));
        }
        if (!$this->canSendMessages($tenantId, $userId)) {
            Session::flash('error', 'Vous ne pouvez pas envoyer de message pour le moment.');

            return Response::redirect(url('jnet/courrier'));
        }
        if ($threadId <= 0 || !$this->messageRepository->userInThread($threadId, $userId)) {
            Session::flash('error', 'Conversation introuvable.');

            return Response::redirect(url('jnet/courrier'));
        }
        $body = trim((string) $request->input('body', ''));
        if ($body === '') {
            Session::flash('error', 'La réponse est vide.');

            return Response::redirect(url('jnet/courrier') . '?fil=' . $threadId);
        }

        $this->messageRepository->addMessage($threadId, $userId, $body);
        $this->messageRepository->markThreadRead($threadId, $userId);
        $recipients = max(0, count($this->messageRepository->listParticipantUserIds($threadId)) - 1);
        $this->notifyThread($tenantId, $threadId, $userId, $body, $recipients);
        Session::flash('success', 'Réponse envoyée.');

        return Response::redirect(url('jnet/courrier') . '?fil=' . $threadId);
    }

    public function mailMarkAllRead(Request $request, array $params = []): Response
    {
        $ctx = $this->ensureAuth();
        if ($ctx instanceof Response) {
            return $ctx;
        }
        if (!\App\Core\Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Votre session a expiré.');

            return Response::redirect(url('jnet/courrier'));
        }
        $this->messageRepository->markAllThreadsReadForUser($ctx['tenant_id'], $ctx['user_id']);
        Session::flash('success', 'Tous les messages sont marqués comme lus.');

        return Response::redirect(url('jnet/courrier'));
    }

    /**
     * @return list<array{key: string, label: string, count: int, href: string}>
     */
    private function mailBoxes(int $tenantId, int $userId): array
    {
        $inbox = $this->messageRepository->listThreadsForUserByBox($tenantId, $userId, 'inbox');
        $unread = 0;
        foreach ($inbox as $thread) {
            if (!empty($thread['has_unread'])) {
                $unread++;
            }
        }
        $sent = $this->messageRepository->listThreadsForUserByBox($tenantId, $userId, 'sent');

        return [
            ['key' => 'reception', 'label' => 'Réception', 'count' => count($inbox), 'href' => url('jnet/courrier')],
            ['key' => 'non-lus', 'label' => 'Non lus', 'count' => $unread, 'href' => url('jnet/courrier') . '?boite=non-lus'],
            ['key' => 'envoyes', 'label' => 'Messages envoyés', 'count' => count($sent), 'href' => url('jnet/courrier') . '?boite=envoyes'],
        ];
    }

    private function canSendMessages(int $tenantId, int $userId): bool
    {
        try {
            $featureGate = \App\Core\Container::get(\App\Services\Platform\FeatureGateService::class);
            if (!$featureGate->allows($tenantId, 'messages')) {
                return false;
            }
        } catch (\Throwable) {
        }
        try {
            $resolver = \App\Core\Container::get(\App\Services\Moderation\ModerationRestrictionResolver::class);

            return $resolver->canSendMessages($tenantId, $userId);
        } catch (\Throwable) {
            return true;
        }
    }

    /** Au-delà d’une petite diffusion, le message reste dans la messagerie sans doublon par e-mail. */
    private function notifyThread(int $tenantId, int $threadId, int $senderId, string $body, int $recipientCount): void
    {
        if ($recipientCount > $this->messaging->emailNotificationLimit()) {
            return;
        }
        try {
            $notifier = \App\Core\Container::get(\App\Services\Community\TenantInternalMessageNotificationService::class);
            $notifier->notifyAfterMessage($tenantId, $threadId, $senderId, $body);
        } catch (\Throwable) {
        }
    }

    public function system(Request $request, array $params = []): Response
    {
        return $this->render('system', 'Système', [
            'canTba' => PortalAccessChoice::canAccessTba(),
            'preferredPortal' => PortalAccessChoice::remembered(),
        ], 'system');
    }

    /** Guide de l'extranet : ce que JNET propose, rubrique par rubrique, rédigé pour les membres (views/jnet/guide.php). */
    public function guide(Request $request, array $params = []): Response
    {
        return $this->render('guide', 'Guide de l’extranet JNET', [], 'guide', ['jnet_guide.css'], ['jnet_guide.js']);
    }

    public function switchPortal(Request $request, array $params = []): Response
    {
        if (!$request->isPost()) {
            return Response::redirect(url('jnet/systeme'));
        }
        if (!\App\Core\Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('jnet/systeme'));
        }
        $action = (string) $request->input('action', '');
        if ($action === 'clear_preference') {
            PortalAccessChoice::clearRemembered();
            Session::flash('success', 'Le choix d’espace mémorisé a été effacé.');

            return Response::redirect(url('login/choisir-espace'));
        }
        if ($action === 'tba') {
            if (!PortalAccessChoice::canAccessTba()) {
                Session::flash('error', 'Accès administratif indisponible pour votre compte.');

                return Response::redirect(url('jnet/systeme'));
            }
            PortalAccessChoice::remember(PortalAccessChoice::PORTAL_TBA, false);

            return Response::redirect(url('back-office'));
        }
        if ($action === 'chooser') {
            return Response::redirect(url('login/choisir-espace'));
        }

        return Response::redirect(url('jnet/systeme'));
    }

    /** @deprecated keep redirects for old bookmarks */
    public function theatre(Request $request, array $params = []): Response
    {
        return Response::redirect(url('jnet/operations'));
    }

    public function sources(Request $request, array $params = []): Response
    {
        return Response::redirect(url('jnet/renseignement'));
    }

    public function intents(Request $request, array $params = []): Response
    {
        return Response::redirect(url('jnet/renseignement'));
    }

    public function roster(Request $request, array $params = []): Response
    {
        return Response::redirect(url('jnet/personnel'));
    }

    public function apps(Request $request, array $params = []): Response
    {
        return Response::redirect(url('jnet/bibliotheque'));
    }

    public function sync(Request $request, array $params = []): Response
    {
        return Response::redirect(url('jnet'));
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function render(string $view, string $title, array $extra = [], ?string $activeNav = null, array $moreCss = [], array $moreJs = []): Response
    {
        $ctx = $this->ensureAuth();
        if ($ctx instanceof Response) {
            return $ctx;
        }

        $tenant = $this->tenantRepository->findById($ctx['tenant_id']);
        $tenantName = is_array($tenant) ? community_display_name($tenant) : 'Communauté';
        $nodeId = $this->nodeIdForTenant($ctx['tenant_id'], is_array($tenant) ? (string) ($tenant['slug'] ?? '') : '');
        $nav = $activeNav ?? $view;
        $unread = $this->unreadMailCount($ctx['tenant_id'], $ctx['user_id']);

        // Page autonome au même habillage que l'extranet (views/jnet/_page.php), hors de la coque du back-office.
        $payload = array_merge([
            'jnetInnerView' => 'jnet.' . $view,
            'title' => $title . ' — Extranet d’unité',
            'jnetPageTitle' => $title,
            'jnetPageCss' => array_merge([
                'jnet_portal.css',
                'jnet_spaces.css',
            ], $moreCss),
            'jnetPageJs' => $moreJs,
            'activeNav' => $nav,
            'jnetTenantName' => $tenantName,
            'jnetDisplayName' => (string) Session::get('display_name', ''),
            'jnetCallsign' => (string) Session::get('callsign', ''),
            'jnetNodeId' => $nodeId,
            'jnetCanTba' => PortalAccessChoice::canAccessTba(),
            'jnetUnreadMail' => $unread,
            'jnetDtg' => strtoupper(gmdate('dHi') . 'Z ' . gmdate('M y')),
        ], $extra);

        return Response::view('jnet._page', $payload);
    }

    private function ensureAuth(): array|Response
    {
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        $ctx = $this->sessionContext();
        if ($ctx === null) {
            return Response::redirect(url('login'));
        }
        $user = $this->authService->user();
        if ($user) {
            $this->rbacService->setPermissionsForGateFromUserRow($user, $this->userRepository);
        }
        // Compte sans organisation : JNET n’a pas de sens — dashboard classique uniquement.
        if (PortalAccessChoice::isNoOrganizationContext()) {
            return Response::redirect(url('dashboard'));
        }

        return $ctx;
    }

    /** @return array{tenant_id:int,user_id:int}|null */
    private function sessionContext(): ?array
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId <= 0 || $userId <= 0) {
            return null;
        }

        return ['tenant_id' => $tenantId, 'user_id' => $userId];
    }

    private function unreadMailCount(int $tenantId, int $userId): int
    {
        try {
            return $this->messageRepository->unreadCountForUser($tenantId, $userId);
        } catch (\Throwable) {
            return 0;
        }
    }

    private function nodeIdForTenant(int $tenantId, string $slug): string
    {
        $base = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $slug) ?? '');
        if ($base === '') {
            $base = 'NODE';
        }
        $base = substr($base, 0, 4);
        $suffix = strtoupper(substr(dechex(($tenantId * 7919) % 65535), -3));

        return $base . $suffix;
    }
}
