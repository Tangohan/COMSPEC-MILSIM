<?php

declare(strict_types=1);

namespace App\Services\Jnet;

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

    public function __construct(
        private readonly JnetSpaceService $spaces,
        ?JnetUnitWorkspaceRepository $work = null,
        ?JnetPinStore $pinStore = null,
    ) {
        $this->work = $work ?? new JnetUnitWorkspaceRepository();
        $this->pinStore = $pinStore ?? new JnetPinRepository();
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

        $view = match ($salon) {
            'annonces' => $this->announcements($tenantId, $userId, $query, $pinIdByRef, $now),
            'taches' => $this->unitBoard($tenantId, $userId, $tree, $query, $now),
            'voie' => $this->hierarchy($tenantId, $userId, $tree, $query),
            'renseignements' => $this->intelligence($tenantId, $intelOpen, $pinIdByRef, $now),
            'cibles' => $this->targets($tenantId, $intelOpen, $query, $pinIdByRef),
            default => ['pinsAll' => true],
        };

        $org = (array) $tree->node(JnetSpaceTree::ORG);

        return [
            'salon' => $salon,
            'salonMeta' => JnetCommonRules::SALONS[$salon],
            'salons' => $this->salonList($tenantId, $userId, $salon, $intelOpen, count($pins)),
            'sections' => JnetCommonRules::SECTIONS,
            'orgLabel' => (string) ($org['label'] ?? 'Organisation'),
            'orgBadge' => is_string($org['badge'] ?? null) ? (string) $org['badge'] : '',
            'unitsTotal' => count($tree->unitIds()),
            'posture' => $dash->postureFor($tenantId),
            'intelOpen' => $intelOpen,
            'view' => $view,
            'pins' => $pins,
            'pinsReady' => $pinService->ready(),
            'canPin' => $pinService->canManage(),
            'pinsMax' => JnetPinRules::MAX,
            'recent' => $this->recentUnits($tenantId, $userId, $tree),
            'now' => $now,
        ];
    }

    /**
     * Les salons de la barre latérale, avec leurs pastilles de non-lus (ordres à accuser par ce lecteur).
     *
     * @return list<array<string, mixed>>
     */
    private function salonList(int $tenantId, int $userId, string $active, bool $intelOpen, int $pinCount): array
    {
        $unread = $this->spaces->unreadByUnit($tenantId, $userId);
        $unitsUnread = array_sum(array_filter($unread, static fn (int $space): bool => $space !== JnetSpaceTree::ORG, ARRAY_FILTER_USE_KEY));
        $out = [];
        foreach (JnetCommonRules::SALONS as $key => $meta) {
            $badge = match ($key) {
                'annonces' => (int) ($unread[JnetSpaceTree::ORG] ?? 0),
                'taches' => (int) $unitsUnread,
                'epingles' => $pinCount,
                default => 0,
            };
            $out[] = $meta + [
                'key' => $key,
                'href' => url(JnetCommonRules::path($key)),
                'badge' => $badge,
                'badgeIsUnread' => in_array($key, ['annonces', 'taches'], true),
                'active' => $key === $active,
                'locked' => $meta['sse'] && !$intelOpen,
            ];
        }

        return $out;
    }

    // ——— a) Annonces & ordres du jour ———

    /**
     * @param array<string, mixed> $query
     * @param array<string, int> $pinIdByRef
     * @return array<string, mixed>
     */
    private function announcements(int $tenantId, int $userId, array $query, array $pinIdByRef, int $now): array
    {
        $ready = $this->spaces->exchangesReady();
        $type = (string) ($query['type'] ?? '');
        $type = isset(JnetSpaceService::KINDS[$type]) ? $type : '';
        // Ancienne adresse « ?type=compte_rendu » : un type hors de ce salon ouvre d'office toutes les unités.
        $scopeAll = (string) ($query['portee'] ?? '') === 'tout' || ($type !== '' && !in_array($type, JnetCommonRules::ANNOUNCE_KINDS, true));

        $rows = [];
        foreach ($this->spaces->visibleExchangesFor($tenantId, $userId) as $e) {
            if (!$scopeAll) {
                $orgLevel = (int) $e['from_unit_id'] === JnetSpaceTree::ORG || in_array(JnetSpaceTree::ORG, (array) $e['targets'], true);
                if (!$orgLevel || !in_array((string) $e['kind'], JnetCommonRules::ANNOUNCE_KINDS, true)) {
                    continue;
                }
            }
            if ($type !== '' && (string) $e['kind'] !== $type) {
                continue;
            }
            $rows[] = $e;
            if (count($rows) >= self::ANNOUNCE_LIMIT) {
                break;
            }
        }
        $items = $this->spaces->decorateExchanges($tenantId, $userId, $rows);
        foreach ($items as $i => $item) {
            $items[$i]['pinId'] = (int) ($pinIdByRef[JnetPinRules::ref('echange', (string) $item['id'])] ?? 0);
        }

        return [
            'ready' => $ready,
            'type' => $type,
            'scopeAll' => $scopeAll,
            'kinds' => JnetSpaceService::KINDS,
            'days' => JnetCommonRules::groupByDay($items, 'createdAt', $now),
            'count' => count($items),
            'canPost' => $ready && $this->spaces->rights($tenantId, $userId, 0)['publish'],
            'postTargets' => $this->spaces->publishTargets($tenantId, $userId),
        ];
    }

    // ——— b) Ordres et tâches par unité ———

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    private function unitBoard(int $tenantId, int $userId, JnetSpaceTree $tree, array $query, int $now): array
    {
        $orgReader = $this->spaces->isOrgWideReader();
        $viewerUnits = $this->spaces->viewerUnitIds($tenantId, $userId);
        $exchanges = $this->spaces->visibleExchangesFor($tenantId, $userId);
        $orderIds = [];
        foreach ($exchanges as $e) {
            if ((string) $e['kind'] === 'ordre' && !empty($e['requires_ack'])) {
                $orderIds[] = (int) $e['id'];
            }
        }
        // Une lecture groupée par type : accusés, décompte des tâches, tâches ouvertes, réglages des espaces.
        $readers = $this->spaces->exchangesRepo()->readersFor($orderIds);
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

        return [
            'rows' => $shown,
            'totals' => JnetUnitBoard::totals($shown),
            'allCount' => count($rows),
            'echelons' => $echelons,
            'filters' => $filters,
            'sort' => $sort,
            'desc' => $desc,
            'open' => (int) ($query['unite'] ?? 0),
            'workReady' => $workReady,
            'exchangesReady' => $this->spaces->exchangesReady(),
            'staleDays' => JnetUnitBoard::STALE_DAYS,
            'recentDays' => JnetUnitBoard::RECENT_DAYS,
            'windowDays' => JnetUnitBoard::ORDER_WINDOW_DAYS,
            'states' => JnetUnitBoard::STATES,
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
        $viewerUnits = $this->spaces->viewerUnitIds($tenantId, $userId);
        foreach ($rows as $i => $r) {
            $rows[$i]['openable'] = $orgReader || JnetSpaceRules::inLineage($tree, $viewerUnits, (int) $r['id']);
            $rows[$i]['href'] = $this->spaces->spaceUrl((int) $r['id']);
        }
        $org = (array) $tree->node(JnetSpaceTree::ORG);

        return ['rows' => $rows, 'q' => $q, 'orgLabel' => (string) ($org['label'] ?? 'Organisation'), 'total' => count($tree->unitIds())];
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

    // ——— Panneau de droite : unités à l'activité récente ———

    /**
     * Unités ayant eu un échange récent (dernière activité connue) : rien d'inventé, vide s'il n'y en a pas.
     *
     * @return list<array{id: int, label: string, accent: string, badge: string, when: string, href: string}>
     */
    private function recentUnits(int $tenantId, int $userId, JnetSpaceTree $tree): array
    {
        $seen = [];
        foreach ($this->spaces->visibleExchangesFor($tenantId, $userId) as $e) {
            foreach (array_merge([(int) $e['from_unit_id']], array_map('intval', (array) $e['targets'])) as $unit) {
                if ($unit > 0 && $tree->has($unit) && !isset($seen[$unit])) {
                    $seen[$unit] = (string) $e['created_at'];
                }
            }
            if (count($seen) >= 6) {
                break;
            }
        }
        $out = [];
        foreach ($seen as $unit => $at) {
            $n = (array) $tree->node($unit);
            $out[] = [
                'id' => $unit,
                'label' => (string) ($n['label'] ?? ''),
                'accent' => (string) ($n['accent'] ?? ''),
                'badge' => is_string($n['badge'] ?? null) ? (string) $n['badge'] : '',
                'when' => JnetSpaceService::relativeTime($at),
                'href' => $this->spaces->spaceUrl($unit),
            ];
        }

        return array_slice($out, 0, 6);
    }
}
