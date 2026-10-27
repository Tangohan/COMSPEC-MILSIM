<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\SseCaseRepository;
use App\Services\Intel\IntelIntakeService;
use App\Services\Intel\IntelPlayerFeedService;
use App\Services\Intel\IntelPlayerIdentity;
use App\Services\Intel\IntelRedaction;
use App\Services\Sse\SseClearanceService;
use App\Services\Sse\SseRedactionService;

/**
 * Back-office « Remontées » : tous les comptes rendus et toutes les fiches de renseignement dans un fil de suivi,
 * à la manière des pull requests (liste ouverte / close, fil de discussion, attribution, état d'exploitation).
 *   GET  /back-office/remontees                        liste (onglets, filtres)
 *   GET  /back-office/remontees/joueurs                tableau par joueur (un compteur par catégorie de remontée)
 *   GET  /back-office/remontees/joueurs/{key}          fil chronologique de tout ce qu'un joueur a remonté
 *   GET  /back-office/remontees/{source}/{id}          fil d'une remontée
 *   POST /back-office/remontees/{source}/{id}/{action} commentaire, état, attribution, type, données,
 *                                                      caviardage, flou, suppression, restauration
 * Accès : administrateurs de la communauté et responsables SSE (gestion des dossiers ou délivrance des accès).
 */
final class AdminIntelIntakeController
{
    private const ACTIONS = ['comment', 'state', 'assign', 'type', 'edit', 'redact', 'unredact', 'blur', 'delete', 'restore'];

    public function __construct(
        private ?IntelIntakeService $intake = null,
        private ?SseClearanceService $clearance = null,
        private ?IntelPlayerFeedService $feed = null,
    ) {
        $this->intake ??= new IntelIntakeService();
    }

    public static function allowed(): bool
    {
        if (!function_exists('can')) {
            return false;
        }
        foreach (['admin.system', 'admin.organization', 'admin.access', 'atak.sse.case.manage', 'atak.sse.grant'] as $perm) {
            if (can($perm)) {
                return true;
            }
        }

        return false;
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
        $senderKey = null;
        try {
            $senderKey = $this->feed()->keyFor($tenantId, $item['sender']['user_id'], $item['sender']['steam'], $item['sender']['callsign']);
        } catch (\Throwable) {
        }

        return Response::view('layout.main', [
            'content' => 'admin.intel.intake_show',
            'title' => $item['ref'] . ' · Remontées',
            'pageTitle' => 'Remontées',
            'intake' => $item,
            'intakeViewerLevel' => $this->viewerLevel(),
            'intakeViewerId' => $me,
            'intakeTypes' => IntelIntakeService::typeOptions($source),
            'intakeMembers' => $this->intake->members($tenantId),
            'intakeLevels' => SseCaseRepository::CLASSIFICATION_LABELS,
            'intakeSenderKey' => $senderKey,
            'intakeCanSeeMembers' => self::canSeeMembers(),
            'csrfToken' => Csrf::token(),
        ]);
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
        if (!in_array($action, self::ACTIONS, true) || !$this->intake->schemaReady() || $this->intake->find($tenantId, $source, $id) === null) {
            Session::flash('error', 'Action impossible.');

            return Response::redirect(url('back-office/remontees'));
        }
        $actor = $this->actor();
        $in = static fn (string $k, string $d = ''): string => trim((string) $request->input($k, $d));
        $ok = match ($action) {
            'comment' => $this->intake->comment($tenantId, $source, $id, $actor, $in('body')),
            'state' => $this->intake->setState($tenantId, $source, $id, $actor, $in('state'), $in('note'), $in('target')),
            'assign' => $this->intake->assign($tenantId, $source, $id, $actor, (int) $in('user_id', '0') ?: null),
            'type' => $this->intake->changeType($tenantId, $source, $id, $actor, $in('type')),
            'edit' => $this->intake->updateData($tenantId, $source, $id, $actor, array_intersect_key($request->all(), IntelIntakeService::EDITABLE[$source] ?? []), $this->viewerLevel()) !== [],
            'redact' => $this->redactAllowed() && $this->intake->addRedaction(
                $tenantId, $source, $id, $actor, $in('field', 'body'), (string) $request->input('phrase', ''), $in('level'),
                array_map('intval', (array) $request->input('allowed', [])), $in('reason')
            ),
            'unredact' => $this->redactAllowed() && $this->intake->removeRedaction($tenantId, $source, $id, $actor, (int) $in('redaction_id', '0')),
            'blur' => $source === 'fiche' && $this->intake->setBlur($tenantId, $id, $actor, (int) $in('attachment_id', '0'), $in('blurred') === '1'),
            'delete' => $this->intake->delete($tenantId, $source, $id, $actor, $in('reason')),
            'restore' => $this->intake->restore($tenantId, $source, $id, $actor),
        };
        $messages = [
            'comment' => ['Commentaire ajouté.', 'Le commentaire est vide.'],
            'state' => ['État mis à jour.', match ($in('state')) {
                'doublon' => 'Remontée d’origine introuvable : indiquez sa référence (CR-12, FRM-…, ou le lien cr/12), autre que celle-ci.',
                'transmise' => 'Indiquez à qui la remontée est transmise (échelon, cellule ou référence du dossier SSE).',
                default => 'État inchangé.',
            }],
            'assign' => ['Attribution mise à jour.', 'Membre introuvable.'],
            'type' => ['Type de fiche changé.', 'Type inchangé ou inconnu.'],
            'edit' => ['Données enregistrées.', 'Aucune modification.'],
            'redact' => ['Passage caviardé.', 'Passage introuvable dans le champ choisi (copiez-le tel qu’il est écrit), ou caviardage réservé aux habilités « Très restreint ».'],
            'unredact' => ['Caviardage levé.', 'Caviardage introuvable ou réservé aux habilités « Très restreint ».'],
            'blur' => ['Pièce jointe mise à jour.', 'Pièce jointe introuvable.'],
            'delete' => ['Remontée supprimée. Elle reste restaurable depuis l’onglet Supprimées.', 'Suppression impossible.'],
            'restore' => ['Remontée restaurée.', 'Restauration impossible.'],
        ];
        Session::flash($ok ? 'success' : 'error', $messages[$action][$ok ? 0 : 1]);

        return Response::redirect($action === 'delete' && $ok ? url('back-office/remontees') : $back . ($action === 'comment' ? '#fil-fin' : ''));
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
