<?php

declare(strict_types=1);

namespace App\Services\Jnet;

use App\Repositories\CommunityEventRepository;
use App\Repositories\JnetPinRepository;
use App\Repositories\JnetUnitWorkspaceRepository;
use App\Support\JnetSpaceTree;
use App\Support\JnetUi;

/**
 * Espace commun de l'organisation (« centre de commandement » à la Discord) : compose les données du salon demandé, et de
 * lui seul (calcul paresseux : un salon qui n'est pas affiché n'est jamais calculé), à partir des services JNET existants.
 *
 * Aucun droit nouveau : l'accès, l'arbre filtré par confidentialité, la visibilité des échanges, les droits de publier et la
 * porte du renseignement SSE sont ceux de JnetSpaceService et de JnetDashboardService. Les épingles sont revérifiées pour
 * ce lecteur à chaque affichage (JnetPinService).
 */
final class JnetCommonSpaceService
{
    private const ANNOUNCE_LIMIT = 60;
    private const INTEL_LIMIT = 40;
    private const TARGET_LIMIT = 80;

    private JnetUnitWorkspaceRepository $work;
    private JnetPinStore $pinStore;
    private ?CommunityEventRepository $events;

    /** @var array<string, array<string, mixed>> */
    private array $boardCache = [];

    public function __construct(
        private readonly JnetSpaceService $spaces,
        ?JnetUnitWorkspaceRepository $work = null,
        ?JnetPinStore $pinStore = null,
        ?CommunityEventRepository $events = null,
    ) {
        $this->work = $work ?? new JnetUnitWorkspaceRepository();
        $this->pinStore = $pinStore ?? new JnetPinRepository();
        $this->events = $events;
    }

    /** Le service des épingles de ce lecteur (aussi utilisé par le contrôleur des écritures). */
    public function pinService(int $tenantId, int $userId): JnetPinService
    {
        $dash = $this->spaces->dashboard();
        $work = $this->work;

        return new JnetPinService(
            $this->pinStore,
            JnetLinkAccess::forViewer($tenantId, $userId, $this->spaces),
            $this->spaces->rights($tenantId, $userId, 0)['publish'],
            static function (string $id) use ($dash, $tenantId): ?array {
                $item = $dash->findIntelItem($tenantId, $id);

                return $item === null ? null : ['title' => $item['title'], 'sub' => $item['kind'], 'href' => $item['href']];
            },
            static fn (array $ids): array => $work->userLabels($tenantId, $ids)
        );
    }

    /**
     * Tout ce que la page de l'espace commun affiche pour ce salon.
     *
     * @param array<string, mixed> $query paramètres de l'adresse (salon, filtres) : lus avec une liste blanche
     * @return array<string, mixed>
     */
    public function page(int $tenantId, int $userId, array $query): array
    {
        $salon = JnetCommonRules::salon((string) ($query['salon'] ?? ''));
        $tree = $this->spaces->tree($tenantId, $userId);
        $dash = $this->spaces->dashboard();
        $intelOpen = $dash->mayReadIntelligence();
        $now = time();

        $pinService = $this->pinService($tenantId, $userId);
        $pins = $pinService->pins();
        $pinIdByRef = [];
        foreach ($pins as $p) {
            $pinIdByRef[$p['ref']] = $p['id'];
        }

        // Lectures groupées, une fois par requête : le bandeau (indicateurs) et la vue d'ensemble les partagent.
        $board = $this->board($tenantId, $userId, $tree, $now);
        $agenda = $this->agenda($tenantId, $userId, $now);

        $view = match ($salon) {
            'apercu' => $this->overview($tenantId, $userId, $board, $agenda, $now),
            'annonces' => $this->announcements($tenantId, $userId, $tree, $query, $pinIdByRef, $now),
            'taches' => $this->unitBoard($board, $query),
            'voie' => $this->hierarchy($tenantId, $userId, $tree, $query),
            'agenda' => $this->agendaView($agenda, $query, $now),
            'renseignements' => $this->intelligence($tenantId, $intelOpen, $pinIdByRef, $now),
            'cibles' => $this->targets($tenantId, $intelOpen, $query, $pinIdByRef),
            default => ['pinsAll' => true],
        };
        if (in_array($salon, ['apercu', 'taches'], true)) {
            $unitPins = [];
            foreach ($board['rows'] as $r) {
                $unitPins[(int) $r['id']] = (int) ($pinIdByRef[JnetPinRules::ref('unite', (string) $r['id'])] ?? 0);
            }
            $view['unitPins'] = $unitPins;
        }

        $org = (array) $tree->node(JnetSpaceTree::ORG);
        $exchanges = $this->spaces->visibleExchangesFor($tenantId, $userId);

        return [
            'salon' => $salon,
            'salonMeta' => JnetCommonRules::SALONS[$salon],
            'salons' => $this->salonList($tenantId, $userId, $salon, $intelOpen, count($pins)),
            'sections' => JnetCommonRules::SECTIONS,
            'orgLabel' => (string) ($org['label'] ?? 'Organisation'),
            'orgBadge' => is_string($org['badge'] ?? null) ? (string) $org['badge'] : '',
            'unitsTotal' => count($tree->unitIds()),
            'posture' => $dash->postureFor($tenantId),
            'hero' => JnetUi::forwardGroup($tenantId),
            'kpis' => $this->kpis($board, $agenda, $now),
            'intelOpen' => $intelOpen,
            'view' => $view,
            'pins' => $pins,
            'pinsReady' => $pinService->ready(),
            'canPin' => $pinService->canManage(),
            'pinsMax' => JnetPinRules::MAX,
            'feed' => JnetCommonOverview::feed($exchanges, $tree),
            'unitIndex' => $this->unitIndex($tree),
            'now' => $now,
        ];
    }

    /**
     * Les salons de la barre, avec leurs pastilles : ordres à accuser par ce lecteur (annonces), accusés attendus dans les
     * unités (tableau), épinglés.
     *
     * @return list<array<string, mixed>>
     */
    private function salonList(int $tenantId, int $userId, string $active, bool $intelOpen, int $pinCount): array
    {
        $unread = $this->spaces->unreadByUnit($tenantId, $userId);
        $unitsUnread = array_sum(array_filter($unread, static fn (int $space): bool => $space !== JnetSpaceTree::ORG, ARRAY_FILTER_USE_KEY));
        $out = [];
        $n = 0;
        foreach (JnetCommonRules::SALONS as $key => $meta) {
            $badge = match ($key) {
                'annonces' => (int) ($unread[JnetSpaceTree::ORG] ?? 0),
                'taches' => (int) $unitsUnread,
                'epingles' => $pinCount,
                default => 0,
            };
            $out[] = $meta + [
                'key' => $key,
                'num' => $meta['sse'] ? '' : str_pad((string) $n, 2, '0', STR_PAD_LEFT),
                'href' => url(JnetCommonRules::path($key)),
                'badge' => $badge,
                'badgeIsUnread' => in_array($key, ['annonces', 'taches'], true),
                'active' => $key === $active,
                'locked' => $meta['sse'] && !$intelOpen,
            ];
            if (!$meta['sse']) {
                $n++;
            }
        }

        return $out;
    }

    /**
     * Les quatre indicateurs du bandeau : accusés attendus, tâches en retard, unités actives sur 7 jours, prochaine opération.
     *
     * @param array<string, mixed> $board
     * @param array{enabled: bool, events: list<array<string, mixed>>} $agenda
     * @return array<string, array<string, mixed>>
     */
    private function kpis(array $board, array $agenda, int $now): array
    {
        $totals = JnetUnitBoard::totals($board['rows']);
        $next = JnetCommonOverview::nextOperation($agenda['events'], $now);

        return [
            'ack' => ['value' => count($board['pending']), 'salon' => 'annonces'],
            'late' => ['value' => (int) $totals['late'], 'salon' => 'taches', 'known' => (bool) $board['workReady']],
            'active' => ['value' => JnetCommonOverview::activeUnits($board['rows'], $now), 'of' => count($board['rows']), 'salon' => 'taches'],
            'next' => $next === null
                ? ['value' => '', 'sub' => '', 'salon' => 'agenda']
                : ['value' => JnetAgenda::countdown((int) $next['days'], !empty($next['ongoing'])), 'sub' => (string) $next['title'], 'salon' => 'agenda'],
        ];
    }

    /**
     * Index des unités pour la recherche rapide : identifiant, nom, code et adresse du détail dans le tableau des unités.
     *
     * @return list<array{id: int, label: string, code: string, href: string}>
     */
    private function unitIndex(JnetSpaceTree $tree): array
    {
        $out = [];
        foreach ($tree->unitIds() as $id) {
            $n = (array) $tree->node($id);
            $out[] = [
                'id' => $id,
                'label' => (string) ($n['label'] ?? ''),
                'code' => (string) ($n['code'] ?? ''),
                'href' => url(JnetCommonRules::path('taches', ['unite' => $id])) . '#jc-unit-card',
            ];
            if (count($out) >= 400) {
                break;
            }
        }

        return $out;
    }

    // ——— Lectures partagées : tableau des unités et agenda ———

    /**
     * Lignes du tableau des unités et accusés attendus du lecteur, calculés une seule fois par requête à partir de lectures
     * groupées par type (échanges, accusés, décompte des tâches, tâches ouvertes, réglages), sans requête par unité.
     *
     * @return array{rows: list<array<string, mixed>>, workReady: bool, viewerUnits: list<int>, pending: list<array<string, mixed>>}
     */
    private function board(int $tenantId, int $userId, JnetSpaceTree $tree, int $now): array
    {
        $key = $tenantId . ':' . $userId;
        if (isset($this->boardCache[$key])) {
            return $this->boardCache[$key];
        }
        $orgReader = $this->spaces->isOrgWideReader();
        $viewerUnits = $this->spaces->viewerUnitIds($tenantId, $userId);
        $exchanges = $this->spaces->visibleExchangesFor($tenantId, $userId);
        $ackIds = [];
        foreach ($exchanges as $e) {
            if (!empty($e['requires_ack'])) {
                $ackIds[] = (int) $e['id'];
            }
        }
        $readers = $ackIds === [] ? [] : $this->spaces->exchangesRepo()->readersFor($ackIds);
        $workReady = $this->work->ready();
        $counts = $workReady ? $this->work->taskCountsByUnit($tenantId, JnetUnitBoard::STALE_DAYS) : [];
        $open = $workReady ? $this->work->openTasks($tenantId) : [];
        $settings = $this->spaces->settings()->findAll($tenantId);

        $taskAccess = [];
        $openable = [];
        foreach ($tree->unitIds() as $id) {
            $reach = $orgReader || JnetSpaceRules::inLineage($tree, $viewerUnits, $id);
            $hidden = in_array('taches', (array) ($settings[$id]['hidden_modules'] ?? []), true);
            $taskAccess[$id] = $workReady && $reach && !$hidden;
            $openable[$id] = $reach;
        }

        $rows = JnetUnitBoard::build(
            $tree,
            $exchanges,
            $readers,
            $counts,
            $open,
            $this->spaces->strengthByUnit($tenantId, $userId),
            $taskAccess,
            $openable,
            $now,
            static fn (string $key): string => JnetUi::echelonLabel($key)
        );
        // Un chef sans fiche lisible (identité masquée) n'a pas de lien de messagerie.
        $people = [];
        foreach ($this->spaces->dashboard()->loadPersonnelCards($tenantId) as $p) {
            $people[(int) ($p['id'] ?? 0)] = true;
        }
        foreach ($rows as $i => $r) {
            $rows[$i]['leaderId'] = isset($people[(int) $r['leaderId']]) ? (int) $r['leaderId'] : 0;
        }

        return $this->boardCache[$key] = [
            'rows' => $rows,
            'workReady' => $workReady,
            'viewerUnits' => $viewerUnits,
            'pending' => JnetCommonOverview::pendingForViewer($tree, $exchanges, $readers, $userId),
        ];
    }

    /**
     * Événements à venir de la communauté (la table de la page « Événements »), avec la réponse du lecteur. Un module
     * désactivé ou une table absente donnent une liste vide, jamais une erreur.
     *
     * @return array{enabled: bool, events: list<array<string, mixed>>}
     */
    private function agenda(int $tenantId, int $userId, int $now): array
    {
        try {
            $gate = \App\Core\Container::get(\App\Services\Platform\FeatureGateService::class);
            if (!$gate instanceof \App\Services\Platform\FeatureGateService || !$gate->allowsLimitedFeatureModule($tenantId, 'events')) {
                return ['enabled' => false, 'events' => []];
            }
            $repo = $this->events ??= new CommunityEventRepository();
            $rows = $repo->upcomingForTenantWithUserRsvp($tenantId, $userId, JnetAgenda::LIMIT);
            $sums = $repo->rsvpSummariesForEvents(array_map(static fn (array $r): int => (int) $r['id'], $rows));
            $events = [];
            foreach ($rows as $r) {
                $s = $sums[(int) $r['id']] ?? ['yes' => [], 'maybe' => [], 'no' => []];
                $events[] = JnetAgenda::normalize($r, $now, ['yes' => count($s['yes']), 'maybe' => count($s['maybe']), 'no' => count($s['no'])]);
            }

            return ['enabled' => true, 'events' => $events];
        } catch (\Throwable) {
            return ['enabled' => false, 'events' => []];
        }
    }

    // ——— 0) Vue d'ensemble ———

    /**
     * @param array{rows: list<array<string, mixed>>, workReady: bool, viewerUnits: list<int>, pending: list<array<string, mixed>>} $board
     * @param array{enabled: bool, events: list<array<string, mixed>>} $agenda
     * @return array<string, mixed>
     */
    private function overview(int $tenantId, int $userId, array $board, array $agenda, int $now): array
    {
        $orders = [];
        foreach ($this->spaces->visibleExchangesFor($tenantId, $userId) as $e) {
            $orgLevel = (int) $e['from_unit_id'] === JnetSpaceTree::ORG || in_array(JnetSpaceTree::ORG, array_map('intval', (array) $e['targets']), true);
            if ($orgLevel && (string) $e['kind'] === 'ordre') {
                $orders[] = $e;
            }
            if (count($orders) >= 8) {
                break;
            }
        }
        $order = $orders === [] ? null : JnetCommonOverview::orderOfTheDay($this->spaces->decorateExchanges($tenantId, $userId, $orders));
        $mine = array_flip($board['viewerUnits']);
        $lateRows = array_values(array_filter($board['rows'], static fn (array $r): bool => isset($mine[(int) $r['id']]) && (int) $r['tasks']['late'] > 0));
        $href = static fn (string $salon): string => url(JnetCommonRules::path($salon));
        $upcoming = array_values(array_filter($agenda['events'], static fn (array $e): bool => (int) $e['endTs'] >= $now));
        $rowsById = [];
        foreach ($board['rows'] as $r) {
            $rowsById[(int) $r['id']] = $r;
        }

        return [
            'order' => $order,
            'pulse' => JnetCommonOverview::pulse($board['rows']),
            'pulseTotal' => count($board['rows']),
            'rowsById' => $rowsById,
            'todo' => JnetCommonOverview::todo($board['pending'], $lateRows, $upcoming, $now, $href),
            'events' => array_slice($upcoming, 0, 3),
            'agendaEnabled' => $agenda['enabled'],
            'states' => JnetUnitBoard::STATES,
            'workReady' => $board['workReady'],
        ];
    }

    // ——— a) Annonces & ordres du jour ———

    /**
     * @param array<string, mixed> $query
     * @param array<string, int> $pinIdByRef
     * @return array<string, mixed>
     */
    private function announcements(int $tenantId, int $userId, JnetSpaceTree $tree, array $query, array $pinIdByRef, int $now): array
    {
        $ready = $this->spaces->exchangesReady();
        $type = (string) ($query['type'] ?? '');
        $type = isset(JnetSpaceService::KINDS[$type]) ? $type : '';
        // Portée : « » = l'organisation, « tout » = toutes les unités visibles, « unite » = celles du lecteur.
        // Ancienne adresse « ?type=compte_rendu » : un type hors de ce salon ouvre d'office toutes les unités.
        $scope = (string) ($query['portee'] ?? '');
        $scope = in_array($scope, ['tout', 'unite'], true) ? $scope : '';
        if ($scope === '' && $type !== '' && !in_array($type, JnetCommonRules::ANNOUNCE_KINDS, true)) {
            $scope = 'tout';
        }
        $mine = $this->spaces->viewerUnitIds($tenantId, $userId);

        // Les échanges de la portée choisie ; les compteurs par type sont ceux de cette portée, avant le filtre de type.
        $scoped = [];
        $counts = [];
        foreach ($this->spaces->visibleExchangesFor($tenantId, $userId) as $e) {
            $targets = array_map('intval', (array) $e['targets']);
            if ($scope === '') {
                $orgLevel = (int) $e['from_unit_id'] === JnetSpaceTree::ORG || in_array(JnetSpaceTree::ORG, $targets, true);
                if (!$orgLevel || !in_array((string) $e['kind'], JnetCommonRules::ANNOUNCE_KINDS, true)) {
                    continue;
                }
            } elseif ($scope === 'unite' && !self::concernsUnits($tree, $mine, (int) $e['from_unit_id'], $targets)) {
                continue;
            }
            $scoped[] = $e;
            $counts[(string) $e['kind']] = ($counts[(string) $e['kind']] ?? 0) + 1;
        }
        $rows = [];
        foreach ($scoped as $e) {
            if ($type !== '' && (string) $e['kind'] !== $type) {
                continue;
            }
            $rows[] = $e;
            if (count($rows) >= self::ANNOUNCE_LIMIT) {
                break;
            }
        }
        $items = $this->spaces->decorateExchanges($tenantId, $userId, $rows);
        // « Relayer » : le formulaire existe dans l'espace de l'unité du lecteur (qui a des sous-unités et où il peut publier).
        $lowest = JnetCommonRules::lowestUnit($tree, $mine);
        $relayUnit = $lowest > 0 && $tree->childrenOf($lowest) !== [] && $this->spaces->canPost($tenantId, $userId, $lowest) ? $lowest : 0;
        foreach ($items as $i => $item) {
            $items[$i]['pinId'] = (int) ($pinIdByRef[JnetPinRules::ref('echange', (string) $item['id'])] ?? 0);
            $targetIds = array_map(static fn (array $t): int => (int) $t['id'], (array) $item['to']);
            $items[$i]['relayHref'] = $relayUnit > 0 && $tree->directionFor($relayUnit, (int) $item['from']['id'], $targetIds) === JnetSpaceTree::DIR_DOWN
                ? url('jnet/u/' . $relayUnit . '?vue=echanges&direct=1') . '#ex-' . (int) $item['id']
                : '';
        }

        return [
            'ready' => $ready,
            'type' => $type,
            'scope' => $scope,
            'scopeAll' => $scope === 'tout',
            'kinds' => JnetSpaceService::KINDS,
            'counts' => $counts,
            'total' => count($scoped),
            'days' => JnetCommonRules::groupByDay($items, 'createdAt', $now),
            'count' => count($items),
            'canPost' => $ready && $this->spaces->rights($tenantId, $userId, 0)['publish'],
            'postTargets' => $this->spaces->publishTargets($tenantId, $userId),
        ];
    }

    /**
     * Un échange concerne-t-il les unités du lecteur : émis ou reçu par l'une d'elles, par un échelon au-dessus ou par
     * une de leurs sous-unités ? (L'organisation entière ne compte pas : c'est la portée « Organisation ».)
     *
     * @param list<int> $viewerUnits
     * @param list<int> $targets
     */
    public static function concernsUnits(JnetSpaceTree $tree, array $viewerUnits, int $from, array $targets): bool
    {
        foreach (array_merge([$from], $targets) as $u) {
            if ($u <= 0 || !$tree->has($u)) {
                continue;
            }
            foreach ($viewerUnits as $v) {
                if ($tree->has($v) && ($tree->isWithin($v, $u) || $tree->isWithin($u, $v))) {
                    return true;
                }
            }
        }

        return false;
    }

    // ——— b) Ordres et tâches par unité ———

    /**
     * @param array{rows: list<array<string, mixed>>, workReady: bool, viewerUnits: list<int>, pending: list<array<string, mixed>>} $board
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    private function unitBoard(array $board, array $query): array
    {
        $rows = $board['rows'];
        $echelons = [];
        foreach ($rows as $r) {
            if ($r['echelon'] !== '' && $r['echelonLabel'] !== '') {
                $echelons[$r['echelon']] = $r['echelonLabel'];
            }
        }
        $sort = in_array((string) ($query['tri'] ?? ''), JnetUnitBoard::SORTS, true) ? (string) $query['tri'] : 'unite';
        $dir = (string) ($query['sens'] ?? '');
        $desc = $dir === 'desc' || ($dir === '' && $sort !== 'unite');
        $filters = [
            'echelon' => isset($echelons[(string) ($query['echelon'] ?? '')]) ? (string) $query['echelon'] : '',
            'etat' => isset(JnetUnitBoard::STATES[(string) ($query['etat'] ?? '')]) ? (string) $query['etat'] : '',
            'retard' => (string) ($query['retard'] ?? '') === '1',
            'q' => mb_substr(trim((string) ($query['q'] ?? '')), 0, 80),
        ];
        $shown = JnetUnitBoard::apply($rows, $filters, $sort, $desc);
        $rowsById = [];
        foreach ($rows as $r) {
            $rowsById[(int) $r['id']] = $r;
        }

        return [
            'rows' => $shown,
            'rowsById' => $rowsById,
            'totals' => JnetUnitBoard::totals($shown),
            'allCount' => count($rows),
            'echelons' => $echelons,
            'filters' => $filters,
            'sort' => $sort,
            'desc' => $desc,
            'open' => (int) ($query['unite'] ?? 0),
            'workReady' => $board['workReady'],
            'exchangesReady' => $this->spaces->exchangesReady(),
            'staleDays' => JnetUnitBoard::STALE_DAYS,
            'recentDays' => JnetUnitBoard::RECENT_DAYS,
            'windowDays' => JnetUnitBoard::ORDER_WINDOW_DAYS,
            'states' => JnetUnitBoard::STATES,
            // Arbre repliable : seulement l'ordre de l'organigramme, sans filtre.
            'asTree' => $sort === 'unite' && !$desc && $filters['echelon'] === '' && $filters['etat'] === '' && !$filters['retard'] && $filters['q'] === '',
        ];
    }

    // ——— c) Voie hiérarchique & contacts ———

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    private function hierarchy(int $tenantId, int $userId, JnetSpaceTree $tree, array $query): array
    {
        $people = [];
        foreach ($this->spaces->dashboard()->loadPersonnelCards($tenantId) as $p) {
            $people[(int) ($p['id'] ?? 0)] = $p;
        }
        $q = mb_substr(trim((string) ($query['q'] ?? '')), 0, 80);
        $rows = JnetCommonRules::hierarchy(
            $tree,
            $people,
            $this->spaces->strengthByUnit($tenantId, $userId),
            $q,
            static fn (string $key): string => JnetUi::echelonLabel($key)
        );
        $orgReader = $this->spaces->isOrgWideReader();
        $viewerUnits = array_values(array_filter($this->spaces->viewerUnitIds($tenantId, $userId), static fn (int $u): bool => $tree->has($u)));
        foreach ($rows as $i => $r) {
            $rows[$i]['openable'] = $orgReader || JnetSpaceRules::inLineage($tree, $viewerUnits, (int) $r['id']);
            $rows[$i]['href'] = $this->spaces->spaceUrl((int) $r['id']);
        }

        // « Ma voie » : de l'unité la plus basse du lecteur jusqu'à la tête de la chaîne ; « Ma branche » : cette voie et tout ce qui est dessous.
        $mineIds = JnetCommonRules::branchIds($tree, $viewerUnits);
        $mine = [];
        $lowest = JnetCommonRules::lowestUnit($tree, $viewerUnits);
        if ($lowest > 0) {
            foreach (array_reverse($tree->chain($lowest)) as $id) {
                if ($id === JnetSpaceTree::ORG) {
                    continue;
                }
                $n = (array) $tree->node($id);
                $mine[] = [
                    'id' => $id,
                    'label' => (string) ($n['label'] ?? ''),
                    'accent' => (string) ($n['accent'] ?? ''),
                    'badge' => is_string($n['badge'] ?? null) ? (string) $n['badge'] : '',
                    'leader' => JnetCommonRules::person((int) ($n['leaderId'] ?? 0), (string) ($n['leader'] ?? ''), $people),
                ];
            }
        }
        $branch = (string) ($query['branche'] ?? '') === '1' && $mineIds !== [];
        if ($branch) {
            $rows = array_values(array_filter($rows, static fn (array $r): bool => isset($mineIds[(int) $r['id']])));
        }
        $org = (array) $tree->node(JnetSpaceTree::ORG);

        return [
            'rows' => $rows,
            'q' => $q,
            'branch' => $branch,
            'hasBranch' => $mineIds !== [],
            'mine' => $mine,
            'orgLabel' => (string) ($org['label'] ?? 'Organisation'),
            'total' => count($tree->unitIds()),
            'viewerId' => $userId,
        ];
    }

    // ——— Agenda ———

    /**
     * @param array{enabled: bool, events: list<array<string, mixed>>} $agenda
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    private function agendaView(array $agenda, array $query, int $now): array
    {
        $raw = (string) ($query['genre'] ?? '');
        $type = isset(JnetAgenda::TYPES[$raw]) ? $raw : '';
        $upcoming = array_values(array_filter($agenda['events'], static fn (array $e): bool => (int) $e['endTs'] >= $now));
        $counts = [];
        foreach ($upcoming as $e) {
            $counts[(string) $e['typeKey']] = ($counts[(string) $e['typeKey']] ?? 0) + 1;
        }

        return [
            'enabled' => $agenda['enabled'],
            'type' => $type,
            'types' => JnetAgenda::TYPES,
            'counts' => $counts,
            'total' => count($upcoming),
            'groups' => JnetAgenda::group($upcoming, $type, $now),
            'rsvp' => JnetAgenda::RSVP,
        ];
    }

    // ——— d) Renseignements ———

    /**
     * @param array<string, int> $pinIdByRef
     * @return array<string, mixed>
     */
    private function intelligence(int $tenantId, bool $open, array $pinIdByRef, int $now): array
    {
        // Sans la porte du renseignement : aucune lecture, ni nombre ni titre.
        if (!$open) {
            return ['locked' => true];
        }
        $items = $this->spaces->dashboard()->intelItems($tenantId, self::INTEL_LIMIT);
        foreach ($items as $i => $item) {
            $items[$i]['pinId'] = (int) ($pinIdByRef[JnetPinRules::ref('renseignement', $item['ref'])] ?? 0);
        }

        return ['locked' => false, 'days' => JnetCommonRules::groupByDay($items, 'at', $now), 'count' => count($items)];
    }

    // ——— e) Cibles ———

    /**
     * @param array<string, mixed> $query
     * @param array<string, int> $pinIdByRef
     * @return array<string, mixed>
     */
    private function targets(int $tenantId, bool $open, array $query, array $pinIdByRef): array
    {
        if (!$open) {
            return ['locked' => true];
        }
        $all = $this->spaces->dashboard()->loadTargets($tenantId);
        $prio = (string) ($query['priorite'] ?? '');
        $prio = in_array($prio, ['critical', 'high', 'medium', 'low'], true) ? $prio : '';
        $needle = JnetCommonRules::fold(mb_substr((string) ($query['q'] ?? ''), 0, 80));
        $rows = [];
        foreach ($all as $t) {
            if ($prio !== '' && (string) ($t['priority_key'] ?? '') !== $prio) {
                continue;
            }
            if ($needle !== '' && !str_contains(JnetCommonRules::fold(implode(' ', [(string) $t['name'], (string) $t['code'], (string) $t['alias'], (string) $t['org'], (string) $t['lastKnown']])), $needle)) {
                continue;
            }
            $t['pinId'] = (int) ($pinIdByRef[JnetPinRules::ref('cible', (string) $t['id'])] ?? 0);
            $rows[] = $t;
        }
        // Les plus récemment mises à jour d'abord.
        usort($rows, static fn (array $a, array $b): int => (strtotime((string) $b['lastSeen']) ?: 0) <=> (strtotime((string) $a['lastSeen']) ?: 0));

        return [
            'locked' => false,
            'rows' => array_slice($rows, 0, self::TARGET_LIMIT),
            'total' => count($all),
            'shown' => count($rows),
            'priorite' => $prio,
            'q' => mb_substr(trim((string) ($query['q'] ?? '')), 0, 80),
        ];
    }
}
