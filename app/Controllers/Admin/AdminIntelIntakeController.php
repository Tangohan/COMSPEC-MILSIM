<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\SseCaseRepository;
use App\Services\Intel\IntelIntakeDesk;
use App\Services\Intel\IntelIntakePresenter;
use App\Services\Intel\IntelIntakeService;
use App\Services\Intel\IntelPlayerFeedService;
use App\Services\Intel\IntelPlayerIdentity;
use App\Services\Intel\IntelRedaction;
use App\Services\Sse\SseClearanceService;
use App\Services\Sse\SseRedactionService;
use App\Services\Sse\SseAccessPolicy;
use App\Support\SseAccessCatalog;

/**
 * Back-office « Remontées » : tous les comptes rendus et toutes les fiches de renseignement dans un fil de suivi,
 * à la manière des pull requests (liste ouverte / close, fil de discussion, attribution, état d'exploitation).
 *   GET  /back-office/remontees                        liste (onglets, filtres)
 *   GET  /back-office/remontees/joueurs                tableau par joueur (un compteur par catégorie de remontée)
 *   GET  /back-office/remontees/joueurs/{key}          fil chronologique de tout ce qu'un joueur a remonté
 *   GET  /back-office/remontees/{source}/{id}          fil d'une remontée
 *   GET  /back-office/remontees/{source}/{id}/suggestions  listes des fenêtres (destinataires, doublons, éléments à relier)
 *   POST /back-office/remontees/{source}/{id}/{action} prise en compte, commentaire, état, attribution, type, thèmes, données,
 *                                                      caviardage, flou, suppression, restauration, conversion, lien,
 *                                                      notification Discord, lecture de l'identifiant Steam
 * Le traitement lui-même est dans IntelIntakeDesk, partagé avec le bureau SSE (SseIntakeController).
 * Accès : administrateurs de la communauté et responsables SSE (gestion des dossiers ou délivrance des accès).
 */
final class AdminIntelIntakeController
{
    public function __construct(
        private ?IntelIntakeService $intake = null,
        private ?SseClearanceService $clearance = null,
        private ?IntelPlayerFeedService $feed = null,
        private ?IntelIntakeDesk $desk = null,
    ) {
        $this->intake ??= new IntelIntakeService();
    }

    private function desk(): IntelIntakeDesk
    {
        return $this->desk ??= new IntelIntakeDesk($this->intake);
    }

    /**
     * Droits du back-office : qui entre traite et gère (règle d'origine de la page) ; caviarder demande la plus haute
     * habilitation ; l'identifiant Steam et la fiche du membre sont réservés aux administrateurs de la communauté.
     *
     * @return array<string, bool>
     */
    private function rights(): array
    {
        return [
            'treat' => true, 'manage' => true, 'convert' => true, 'link' => true, 'discord' => true,
            'redact' => $this->redactAllowed(),
            'steam' => self::canSeeMembers(),
        ];
    }

    public static function allowed(): bool
    {
        if (!function_exists('can')) {
            return false;
        }
        $legacy = false;
        foreach (['admin.system', 'admin.organization', 'admin.access', 'atak.sse.case.manage', 'atak.sse.grant'] as $perm) {
            if (can($perm)) {
                $legacy = true;
                break;
            }
        }

        // Remontées du renseignement : section Dossiers d'enquête, niveau « écrire » (SseAccessPolicy), garde d'origine ci-dessus.
        return SseAccessPolicy::sessionAllows('dossiers', SseAccessCatalog::LEVEL_WRITE, ['guest' => false, 'legacy' => $legacy, 'door_mode' => SseAccessPolicy::DOOR_ENTRY]);
    }

    public function index(Request $request, array $params = []): Response
    {
        $guard = $this->guard();
        if ($guard instanceof Response) {
            return $guard;
        }
        $tenantId = $guard;
        $ready = $this->intake->schemaReady();
        $tab = (string) ($request->query('etat') ?? 'open');
        $tab = in_array($tab, ['open', 'closed', 'mine', 'deleted', 'all'], true) || isset(IntelIntakeService::STATES[$tab]) ? $tab : 'open';
        $source = (string) ($request->query('source') ?? '');
        $source = isset(IntelIntakeService::SOURCES[$source]) ? $source : '';
        $filters = [
            'source' => $source,
            'type' => strtoupper(trim((string) ($request->query('type') ?? ''))),
            'q' => mb_substr(trim((string) ($request->query('q') ?? '')), 0, 80),
            'assignee' => (string) ($request->query('attribue') ?? ''),
        ];
        $me = (int) Session::get('user_id');
        $listFilters = $filters + [
            'state' => $tab === 'mine' ? 'open' : ($tab === 'deleted' ? 'all' : $tab),
            'deleted' => $tab === 'deleted',
        ];
        if ($tab === 'mine') {
            $listFilters['assignee'] = 'me';
        }
        $items = $ready ? $this->intake->listItems($tenantId, $listFilters, $me) : [];
        $badge = (string) ($request->query('pastille') ?? '');
        $badge = isset(IntelIntakeDesk::BADGE_FILTERS[$badge]) ? $badge : '';
        $sort = (string) ($request->query('tri') ?? 'recu');
        $sort = isset(IntelIntakeDesk::SORTS[$sort]) ? $sort : 'recu';
        $dir = (string) ($request->query('ordre') ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $refined = IntelIntakeDesk::refine($items, $badge, $sort, $dir);
        $items = $refined['items'];
        // Les extraits de la liste respectent aussi les caviardages.
        if ($items !== []) {
            $level = $this->viewerLevel();
            foreach (['cr', 'fiche'] as $src) {
                $ids = array_column(array_filter($items, static fn (array $i): bool => $i['source'] === $src), 'id');
                $red = $this->intake->redactions($tenantId, $src, $ids);
                foreach ($items as &$it) {
                    if ($it['source'] === $src && isset($red[$it['id']])) {
                        $it['title'] = IntelRedaction::apply((string) $it['title'], $red[$it['id']], $level, $me);
                        $it['redacted'] = true;
                    }
                }
                unset($it);
            }
        }

        return Response::view('layout.main', [
            'content' => 'admin.intel.intake_index',
            'title' => 'Remontées',
            'pageTitle' => 'Remontées',
            'intakeReady' => $ready,
            'intakeItems' => $items,
            'intakeCounts' => $ready ? $this->intake->counts($tenantId, $filters, $me) : ['open' => 0, 'closed' => 0, 'mine' => 0, 'deleted' => 0, 'states' => []],
            'intakeTab' => $tab,
            'intakeFilters' => $filters,
            'intakeTypes' => $source !== '' ? IntelIntakeService::typeOptions($source) : IntelIntakeService::typeOptions('cr') + IntelIntakeService::typeOptions('fiche'),
            'intakeMembers' => $ready ? $this->intake->members($tenantId) : [],
            'intakeBadge' => $badge,
            'intakeBadgeCounts' => $refined['counts'],
            'intakeSort' => $sort,
            'intakeDir' => $dir,
        ]);
    }

    public function show(Request $request, array $params = []): Response
    {
        $guard = $this->guard();
        if ($guard instanceof Response) {
            return $guard;
        }
        $tenantId = $guard;
        if (!$this->intake->schemaReady()) {
            return Response::redirect(url('back-office/remontees'));
        }
        $source = (string) ($params['source'] ?? '');
        $item = $this->intake->find($tenantId, $source, (int) ($params['id'] ?? 0));
        if ($item === null) {
            Session::flash('error', 'Remontée introuvable.');

            return Response::redirect(url('back-office/remontees'));
        }
        $me = (int) Session::get('user_id');
        $this->intake->recordRead($tenantId, $source, (int) $item['id'], $me, 'bo');
        $desk = $this->desk()->view($tenantId, $source, (int) $item['id'], ['id' => $me, 'level' => $this->viewerLevel()], $this->rights());
        if ($desk === null) {
            return Response::redirect(url('back-office/remontees'));
        }
        $senderKey = null;
        try {
            $senderKey = $this->feed()->keyFor($tenantId, $item['sender']['user_id'], $item['sender']['steam'], $item['sender']['callsign']);
        } catch (\Throwable) {
        }
        $ssePortal = false;
        try {
            $ssePortal = SseAccessPolicy::sessionAllows('terrain', SseAccessCatalog::LEVEL_READ, ['guest' => false, 'tenant_id' => $tenantId]);
        } catch (\Throwable) {
        }

        return Response::view('layout.main', [
            'content' => 'admin.intel.intake_show',
            'title' => $item['ref'] . ' · Remontées',
            'pageTitle' => 'Remontées',
            'intakeDesk' => $desk,
            'intakeTypes' => IntelIntakeService::typeOptions($source),
            'intakeMembers' => $this->intake->members($tenantId),
            'intakeLevels' => SseCaseRepository::CLASSIFICATION_LABELS,
            'intakeSenderKey' => $senderKey,
            'intakeCanSeeMembers' => self::canSeeMembers(),
            'intakeSsePortal' => $ssePortal,
            'csrfToken' => Csrf::token(),
        ]);
    }

    /**
     * GET /back-office/remontees/{source}/{id}/suggestions?genre=transmettre|doublon|lien&q=…
     * Listes des fenêtres « Transmettre », « Doublon » et « Relier » (JSON) : toujours dans la communauté du demandeur.
     */
    public function suggestions(Request $request, array $params = []): Response
    {
        $guard = $this->guard();
        if ($guard instanceof Response) {
            return Response::json(['items' => []], 403);
        }

        return Response::json(['items' => self::suggest($this->desk(), $guard, (string) ($params['source'] ?? ''), (int) ($params['id'] ?? 0), (string) ($request->query('genre') ?? ''), (string) ($request->query('q') ?? ''), true)]);
    }

    /**
     * @return list<array{value: string, label: string, kind: string}>
     */
    public static function suggest(IntelIntakeDesk $desk, int $tenantId, string $source, int $id, string $kind, string $query, bool $canLink): array
    {
        $query = mb_substr(trim($query), 0, 80);
        $item = $desk->intake()->schemaReady() ? $desk->intake()->find($tenantId, $source, $id) : null;
        if ($item === null) {
            return [];
        }
        if ($kind === 'transmettre') {
            return $desk->advisor()->forwardTargets($tenantId, $query);
        }
        if ($kind === 'lien') {
            return $canLink && $source === 'fiche' ? $desk->linkSearch($query) : [];
        }
        if ($kind !== 'doublon' || mb_strlen($query) < 2) {
            return [];
        }
        $out = [];
        foreach ($desk->intake()->listItems($tenantId, ['state' => 'all', 'q' => $query], 0, 9) as $it) {
            if ($it['source'] === $source && $it['id'] === $id) {
                continue;
            }
            // Référence et type seulement : le titre peut porter un passage caviardé.
            $out[] = ['value' => $it['source'] . '/' . $it['id'], 'label' => $it['ref'] . ' — ' . $it['type_label'] . ' · ' . IntelIntakePresenter::when((string) $it['at']), 'kind' => IntelIntakeService::SOURCES[$it['source']]];
        }

        return $out;
    }

    /**
     * GET /back-office/remontees/joueurs : une ligne par joueur, un compteur par catégorie de remontée.
     */
    public function players(Request $request, array $params = []): Response
    {
        $guard = $this->guard();
        if ($guard instanceof Response) {
            return $guard;
        }
        $tenantId = $guard;
        $period = IntelPlayerFeedService::normalizePeriod((string) ($request->query('periode') ?? '30j'));
        $q = mb_substr(trim((string) ($request->query('q') ?? '')), 0, 80);
        $sort = (string) ($request->query('tri') ?? 'total');
        $sort = in_array($sort, ['total', 'last', 'name'], true) || isset(IntelPlayerFeedService::CATEGORIES[$sort]) ? $sort : 'total';
        $data = ['players' => [], 'sources' => []];
        try {
            $data = $this->feed()->players($tenantId, $period);
        } catch (\Throwable) {
        }
        $players = self::filterPlayers($data['players'], $q);
        $players = self::sortPlayers($players, $sort);
        $totals = array_fill_keys(array_keys(IntelPlayerFeedService::CATEGORIES), 0);
        foreach ($players as $p) {
            foreach ($p['counts'] as $cat => $n) {
                $totals[$cat] += (int) $n;
            }
        }

        return Response::view('layout.main', [
            'content' => 'admin.intel.intake_players',
            'title' => 'Remontées par joueur',
            'pageTitle' => 'Remontées',
            'playersRows' => $players,
            'playersTotals' => $totals,
            'playersSources' => $data['sources'],
            'playersPeriod' => $period,
            'playersQuery' => $q,
            'playersSort' => $sort,
            'intakeToTreat' => $this->intake->countToTreat($tenantId),
            'backOfficePageCss' => ['remontees-joueurs.css'],
        ]);
    }

    /**
     * GET /back-office/remontees/joueurs/{key} : tout ce qu'un joueur a remonté, dans l'ordre chronologique.
     */
    public function player(Request $request, array $params = []): Response
    {
        $guard = $this->guard();
        if ($guard instanceof Response) {
            return $guard;
        }
        $tenantId = $guard;
        $key = (string) ($params['key'] ?? '');
        $period = IntelPlayerFeedService::normalizePeriod((string) ($request->query('periode') ?? 'tout'));
        $cat = (string) ($request->query('categorie') ?? '');
        $cat = isset(IntelPlayerFeedService::CATEGORIES[$cat]) ? $cat : '';
        $data = ['player' => null, 'items' => [], 'truncated' => false];
        if (IntelPlayerIdentity::validKey($key)) {
            try {
                $data = $this->feed()->timeline($tenantId, $key, $period);
            } catch (\Throwable) {
            }
        }
        if ($data['player'] === null) {
            Session::flash('error', 'Joueur introuvable ou sans remontée.');

            return Response::redirect(url('back-office/remontees/joueurs'));
        }
        $items = $cat !== '' ? array_values(array_filter($data['items'], static fn (array $i): bool => $i['cat'] === $cat)) : $data['items'];

        return Response::view('layout.main', [
            'content' => 'admin.intel.intake_player',
            'title' => ($data['player']['callsign'] !== '' ? $data['player']['callsign'] : 'Joueur') . ' · Remontées',
            'pageTitle' => 'Remontées',
            'playerCard' => $data['player'],
            'playerItems' => $items,
            'playerTruncated' => $data['truncated'],
            'playerPeriod' => $period,
            'playerCategory' => $cat,
            'playerCanSeeMembers' => self::canSeeMembers(),
        ]);
    }

    /**
     * Recherche sur indicatif (courant ou vu), compte, Steam ou clé.
     *
     * @param list<array<string, mixed>> $players
     * @return list<array<string, mixed>>
     */
    public static function filterPlayers(array $players, string $q): array
    {
        $q = mb_strtolower(trim($q));
        if ($q === '') {
            return $players;
        }

        return array_values(array_filter($players, static function (array $p) use ($q): bool {
            $hay = mb_strtolower(implode(' ', array_merge(
                [(string) $p['callsign'], (string) $p['account'], (string) $p['steam'], (string) $p['key']],
                array_map('strval', (array) ($p['callsigns_seen'] ?? []))
            )));

            return str_contains($hay, $q);
        }));
    }

    /**
     * @param list<array<string, mixed>> $players
     * @return list<array<string, mixed>>
     */
    public static function sortPlayers(array $players, string $sort): array
    {
        $name = static fn (array $p): string => mb_strtolower((string) ($p['callsign'] !== '' ? $p['callsign'] : $p['account']));
        usort($players, static function (array $a, array $b) use ($sort, $name): int {
            $cmp = match (true) {
                $sort === 'name' => strcmp($name($a), $name($b)),
                $sort === 'last' => strcmp((string) $b['last'], (string) $a['last']),
                $sort === 'total' => $b['total'] <=> $a['total'],
                default => ($b['counts'][$sort] ?? 0) <=> ($a['counts'][$sort] ?? 0),
            };

            return $cmp !== 0 ? $cmp : (($b['total'] <=> $a['total']) ?: strcmp($name($a), $name($b)));
        });

        return $players;
    }

    /** Badge du menu latéral : nombre de remontées « À traiter » (null si zéro, inaccessible ou en erreur). */
    public static function asideBadge(): ?string
    {
        try {
            $tenantId = (int) Session::get('tenant_id');
            if ($tenantId < 1 || !self::allowed()) {
                return null;
            }
            $n = (new IntelIntakeService())->countToTreat($tenantId);

            return $n > 0 ? ($n > 99 ? '99+' : (string) $n) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Lien vers la fiche du membre : réservé aux administrateurs de la communauté. */
    private static function canSeeMembers(): bool
    {
        return function_exists('can') && (can('admin.organization') || can('admin.access') || can('admin.system'));
    }

    private function feed(): IntelPlayerFeedService
    {
        return $this->feed ??= new IntelPlayerFeedService();
    }

    public function action(Request $request, array $params = []): Response
    {
        $guard = $this->guard();
        if ($guard instanceof Response) {
            return $guard;
        }
        $tenantId = $guard;
        $source = (string) ($params['source'] ?? '');
        $id = (int) ($params['id'] ?? 0);
        $action = (string) ($params['action'] ?? '');
        $back = url('back-office/remontees/' . rawurlencode($source) . '/' . $id);
        $res = $this->desk()->perform($tenantId, $source, $id, $action, $request->all(), $this->actor(), $this->rights(), $this->viewerLevel());
        if ($action === 'steam' && $request->header('X-Requested-With') === 'fetch') {
            return Response::json(['ok' => $res['ok'], 'message' => $res['message'], 'steam' => (string) ($res['data']['steam'] ?? '')], $res['ok'] ? 200 : 403);
        }
        Session::flash($res['ok'] ? 'success' : 'error', $res['message']);
        if (!isset(IntelIntakeService::SOURCES[$source]) || $id < 1 || $res['deleted']) {
            return Response::redirect(url('back-office/remontees'));
        }

        return Response::redirect($back . ($action === 'comment' ? '#fil-fin' : ''));
    }

    /** Caviarder : réservé au plus haut niveau, sinon on pourrait cacher à soi-même… ou lever ce qu'on ne lit pas. */
    private function redactAllowed(): bool
    {
        return $this->viewerLevel() === SseCaseRepository::CLASS_RESTRICTED;
    }

    private function viewerLevel(): string
    {
        try {
            $this->clearance ??= new SseClearanceService();

            return $this->clearance->maxLevel();
        } catch (\Throwable) {
            return function_exists('can') && (can('admin.access') || can('atak.sse.grant'))
                ? SseCaseRepository::CLASS_RESTRICTED
                : SseCaseRepository::CLASS_INTERNAL;
        }
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

        return ['id' => (int) Session::get('user_id'), 'label' => $label !== '' ? $label : 'Administrateur'];
    }

    private function guard(): int|Response
    {
        if (!Session::get('user_id')) {
            return Response::redirect(url('login'));
        }
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1 || !self::allowed()) {
            Session::flash('error', 'Accès réservé aux administrateurs et aux responsables du renseignement.');

            return Response::redirect(url('dashboard'));
        }

        return $tenantId;
    }

    /** Libellé d'un niveau, pour les vues. */
    public static function levelLabel(string $level): string
    {
        return SseRedactionService::levelLabel($level);
    }
}
