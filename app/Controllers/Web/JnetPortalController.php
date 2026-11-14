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
use App\Services\Jnet\JnetMemberHub;
use App\Services\Jnet\JnetMessagingService;
use App\Services\Jnet\JnetSpaceService;
use App\Services\Jnet\JnetUnitSpaceSettings;
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
            $this->spaces->strengthByUnit($tenantId, $userId)
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

        return $this->unitShell($ctx, $unitId, $space, $look, in_array($vue, self::UNIT_VIEWS, true) ? $vue : 'accueil', $tree->chain($unitId));
    }

    /** Rubriques de l'espace d'une unité (?vue=). */
    private const UNIT_VIEWS = ['accueil', 'echanges', 'effectif', 'operations', 'sous-unites'];

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
        if ($unitId <= 0 || !$tree->has($unitId)) {
            Session::flash('error', 'Cet espace n’existe pas ou ne vous est pas accessible.');

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
        $isMember = in_array($unitId, $this->spaces->viewerUnitIds($ctx['tenant_id'], $ctx['user_id']), true);

        return Response::view('jnet.entry', [
            'title' => (string) ($node['label'] ?? 'Unité'),
            'entryLook' => $look,
            'entryUnit' => (string) ($node['label'] ?? 'Unité'),
            'entryChain' => array_values(array_filter($chain)),
            'entryCommunity' => is_array($tenant) ? community_display_name($tenant) : '',
            'entryOperator' => trim((string) Session::get('callsign', '')) !== '' ? (string) Session::get('callsign', '') : (string) Session::get('display_name', ''),
            'entryRole' => $isMember ? 'Membre de l’unité' : 'Visiteur autorisé',
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

        return $this->unitShell($ctx, $unitId, $space, JnetUnitSpaceSettings::look($settings, $node), 'personnaliser', $tree->chain($unitId), [
            'customizeUnitId' => $unitId,
            'customizeUnit' => (string) ($node['label'] ?? 'Unité'),
            'customizeSettings' => $settings,
            'customizeLook' => JnetUnitSpaceSettings::look($settings, $node),
            'customizeOrbat' => ['accent' => (string) ($node['accent'] ?? ''), 'motto' => (string) ($node['motto'] ?? ''), 'badge' => $node['badge'] ?? null],
            'customizeReady' => $this->spaceSettings->ready(),
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
            foreach (['emblem_path', 'banner_path'] as $column) {
                $old = (string) ($current[$column] ?? '');
                if ($old !== '' && $old !== $paths[$column]) {
                    JnetUnitSpaceSettings::deleteImage($old, $tenantId);
                }
            }
            Session::flash('success', 'Espace enregistré. Les membres le voient à leur prochaine visite.');
        } catch (\Throwable $e) {
            error_log('[jnet] personnalisation espace ' . $unitId . ' : ' . $e->getMessage());
            Session::flash('error', 'L’espace n’a pas pu être enregistré. Réessayez dans un instant.');
        }

        return Response::redirect($back);
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
        if (!\App\Core\Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Votre session a expiré, réessayez.');

            return Response::redirect($back);
        }
        if (!$this->spaces->acknowledge($ctx['tenant_id'], $ctx['user_id'], (int) ($params['id'] ?? 0))) {
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
        $needle = mb_strtoupper($filter);
        $filtered = array_values(array_filter($all, static function (array $p) use ($filter, $needle): bool {
            if ($filter === '' || strcasecmp($filter, 'all') === 0) {
                return true;
            }
            if (strcasecmp($filter, 'off') === 0) {
                return ($p['duty'] ?? '') === 'off';
            }
            $unit = mb_strtoupper(trim((string) ($p['unit'] ?? '')));
            if ($unit !== '' && $unit === $needle) {
                return true;
            }
            $hay = mb_strtoupper((string) ($p['unit'] ?? '') . ' ' . ($p['function'] ?? '') . ' ' . ($p['role'] ?? '') . ' ' . ($p['name'] ?? '') . ' ' . ($p['callsign'] ?? ''));

            return str_contains($hay, $needle);
        }));

        return $this->render('personnel', 'Personnel', [
            'personnel' => $filtered,
            'personnelFilter' => $filter,
            'personnelFilters' => $this->jnet->personnelFilterOptions($all),
            'personnelTotal' => count($all),
        ], 'personnel');
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

        return $this->render('personnel_show', (string) ($card['name'] ?? 'Personnel'), [
            'person' => $card,
        ], 'personnel');
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
        ], 'operations');
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
        ], 'targets');
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
    private function render(string $view, string $title, array $extra = [], ?string $activeNav = null, array $moreCss = []): Response
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

        $payload = array_merge([
            'content' => 'jnet._bo_content',
            'jnetInnerView' => 'jnet.' . $view,
            'title' => $title . ' — Extranet d’unité',
            'isBackOfficeShell' => true,
            'boPageGroup' => 'Unité',
            'boPageKicker' => 'UNITÉ · EXTRANET',
            'boPageTitle' => $title,
            'boPageSubtitle' => 'Espace commun de l’organisation et de chaque unité : situation, échanges, opérations et renseignement.',
            'boPageQuick' => [],
            'backOfficePageCss' => array_merge([
                'jnet_portal.css',
                'jnet_bo_embed.css',
                'jnet_spaces.css',
            ], $moreCss),
            'activeNav' => $nav,
            'jnetTenantName' => $tenantName,
            'jnetDisplayName' => (string) Session::get('display_name', ''),
            'jnetCallsign' => (string) Session::get('callsign', ''),
            'jnetNodeId' => $nodeId,
            'jnetCanTba' => PortalAccessChoice::canAccessTba(),
            'jnetUnreadMail' => $unread,
            'jnetDtg' => strtoupper(gmdate('dHi') . 'Z ' . gmdate('M y')),
        ], $extra);

        return Response::view('layout.main', $payload);
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
