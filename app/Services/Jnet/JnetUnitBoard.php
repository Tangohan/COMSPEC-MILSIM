<?php

declare(strict_types=1);

namespace App\Services\Jnet;

use App\Support\JnetSpaceTree;

/**
 * Tableau « Ordres et tâches par unité » de l'espace commun : calcul pur (testé sans base) à partir de lectures déjà
 * groupées (échanges visibles, accusés, décompte des tâches de toute la communauté), sans aucune requête par unité.
 *
 * Définitions, toutes tirées de données réelles :
 * - ordres reçus : les ordres des 30 derniers jours qui descendent jusqu'à l'unité (adressés à elle, à un échelon au-dessus
 *   d'elle ou à toute l'organisation) ;
 * - en cours : ceux de ces ordres reçus depuis 7 jours ;
 * - à accuser : ceux de ces ordres qui demandent un accusé de lecture dont au moins un membre de l'unité manque encore ;
 * - tâches en retard : tâches encore ouvertes sans aucun changement depuis 14 jours (une tâche n'a pas d'échéance) ;
 * - dernier échange : le plus récent échange visible qui part de l'unité ou lui est adressé.
 */
final class JnetUnitBoard
{
    public const ORDER_WINDOW_DAYS = 30;
    public const RECENT_DAYS = 7;
    public const STALE_DAYS = 14;
    public const TOP = 3;

    public const STATES = [
        'late' => 'Retards',
        'pending' => 'Accusés attendus',
        'active' => 'Actif',
        'calm' => 'Calme',
    ];

    public const SORTS = ['unite', 'ordres', 'accuser', 'taches', 'retard', 'dernier', 'etat'];

    /**
     * @param list<array<string, mixed>> $exchanges échanges visibles du lecteur (id, kind, title, from_unit_id, targets, requires_ack, created_at)
     * @param array<int, list<int>> $readers lecteurs par échange (accusés de lecture)
     * @param array<int, array{todo: int, doing: int, done: int, late: int}> $taskCounts décompte des tâches par unité
     * @param list<array{id: int, unit_id: int, title: string, status: string, updated_at: string, assignee_label: string}> $openTasks tâches ouvertes
     * @param array<int, array{total: int, available: int, off?: int}> $strength effectif par unité
     * @param array<int, bool> $taskAccess unités dont le lecteur peut voir les tâches
     * @param array<int, bool> $openable unités dont le lecteur peut ouvrir l'espace
     * @param \Closure(string): string $echelonLabel
     * @return list<array<string, mixed>> une ligne par unité, dans l'ordre de l'ORBAT
     */
    public static function build(
        JnetSpaceTree $tree,
        array $exchanges,
        array $readers,
        array $taskCounts,
        array $openTasks,
        array $strength,
        array $taskAccess,
        array $openable,
        int $now,
        \Closure $echelonLabel
    ): array {
        $orderFrom = $now - self::ORDER_WINDOW_DAYS * 86400;
        $recentFrom = $now - self::RECENT_DAYS * 86400;
        $staleBefore = $now - self::STALE_DAYS * 86400;

        // Ordres des 30 derniers jours, une seule fois.
        $orders = [];
        foreach ($exchanges as $e) {
            $ts = strtotime((string) ($e['created_at'] ?? '')) ?: 0;
            if ((string) ($e['kind'] ?? '') === 'ordre' && $ts >= $orderFrom) {
                $orders[] = ['e' => $e, 'ts' => $ts, 'targets' => array_map('intval', (array) ($e['targets'] ?? [])), 'from' => (int) ($e['from_unit_id'] ?? 0)];
            }
        }

        // Dernier échange par unité (émettrice ou destinataire), une seule passe.
        $last = [];
        foreach ($exchanges as $e) {
            $ts = strtotime((string) ($e['created_at'] ?? '')) ?: 0;
            $concerned = array_merge([(int) ($e['from_unit_id'] ?? 0)], array_map('intval', (array) ($e['targets'] ?? [])));
            foreach ($concerned as $u) {
                if ($u > 0 && $ts > ($last[$u]['ts'] ?? 0)) {
                    $last[$u] = ['ts' => $ts, 'at' => (string) ($e['created_at'] ?? '')];
                }
            }
        }

        // Tâches ouvertes les plus urgentes de chaque unité : en retard d'abord, puis en cours, puis à faire.
        $tasksBy = [];
        foreach ($openTasks as $t) {
            $unit = (int) $t['unit_id'];
            if (!($taskAccess[$unit] ?? false)) {
                continue;
            }
            $ts = strtotime($t['updated_at']) ?: 0;
            $stale = $ts > 0 && $ts < $staleBefore;
            $rank = $stale ? 0 : ($t['status'] === 'doing' ? 1 : 2);
            $tasksBy[$unit][] = ['id' => $t['id'], 'title' => $t['title'], 'status' => $t['status'], 'late' => $stale, 'at' => $t['updated_at'], 'who' => $t['assignee_label'], 'rank' => $rank, 'ts' => $ts];
        }

        $rows = [];
        $order = [];
        $walk = static function (int $id) use (&$walk, &$order, $tree): void {
            foreach ($tree->childrenOf($id) as $child) {
                $order[] = $child;
                $walk($child);
            }
        };
        $walk(JnetSpaceTree::ORG);

        foreach ($order as $id) {
            $n = (array) $tree->node($id);
            $members = $tree->memberIdsInSubtree($id);
            $received = [];
            foreach ($orders as $o) {
                if ($tree->directionFor($id, $o['from'], $o['targets']) === JnetSpaceTree::DIR_DOWN) {
                    $readSet = array_flip($readers[(int) $o['e']['id']] ?? []);
                    $missing = 0;
                    if (!empty($o['e']['requires_ack'])) {
                        foreach ($members as $m) {
                            if (!isset($readSet[$m])) {
                                $missing++;
                            }
                        }
                    }
                    $received[] = ['id' => (int) $o['e']['id'], 'title' => (string) $o['e']['title'], 'at' => (string) $o['e']['created_at'], 'ts' => $o['ts'], 'missing' => $missing, 'ack' => !empty($o['e']['requires_ack'])];
                }
            }
            $pending = count(array_filter($received, static fn (array $r): bool => $r['missing'] > 0));
            $recent = count(array_filter($received, static fn (array $r): bool => $r['ts'] >= $recentFrom));
            usort($received, static fn (array $a, array $b): int => [$b['missing'] > 0, $b['ts']] <=> [$a['missing'] > 0, $a['ts']]);

            $canTasks = (bool) ($taskAccess[$id] ?? false);
            $counts = $canTasks ? ($taskCounts[$id] ?? ['todo' => 0, 'doing' => 0, 'done' => 0, 'late' => 0]) : ['todo' => 0, 'doing' => 0, 'done' => 0, 'late' => 0];
            $tasks = $tasksBy[$id] ?? [];
            usort($tasks, static fn (array $a, array $b): int => [$a['rank'], $a['ts']] <=> [$b['rank'], $b['ts']]);

            $lastAt = $last[$id]['at'] ?? '';
            $lastTs = (int) ($last[$id]['ts'] ?? 0);
            $state = match (true) {
                $counts['late'] > 0 => 'late',
                $pending > 0 => 'pending',
                $counts['doing'] > 0 || $recent > 0 || ($lastTs > 0 && $lastTs >= $recentFrom) => 'active',
                default => 'calm',
            };
            $parent = $tree->parentOf($id);
            $chain = array_map(static fn (int $c): string => (string) ($tree->node($c)['label'] ?? ''), array_slice($tree->chain($id), 0, -1));

            $rows[] = [
                'id' => $id,
                'label' => (string) ($n['label'] ?? ''),
                'code' => (string) ($n['code'] ?? ''),
                'accent' => (string) ($n['accent'] ?? ''),
                'badge' => is_string($n['badge'] ?? null) ? (string) $n['badge'] : '',
                'echelon' => (string) ($n['echelon'] ?? ''),
                'echelonLabel' => $echelonLabel((string) ($n['echelon'] ?? '')),
                'depth' => max(0, (int) ($n['depth'] ?? 1) - 1),
                'chain' => $chain,
                'parentId' => $parent !== null && $parent !== JnetSpaceTree::ORG ? $parent : 0,
                'childIds' => $tree->childrenOf($id),
                'leaderId' => (int) ($n['leaderId'] ?? 0),
                'parentLabel' => $parent !== null && $parent !== JnetSpaceTree::ORG ? (string) ($tree->node($parent)['label'] ?? '') : '',
                'leader' => (string) ($n['leader'] ?? ''),
                'strength' => ['total' => (int) ($strength[$id]['total'] ?? 0), 'available' => (int) ($strength[$id]['available'] ?? 0)],
                'openable' => (bool) ($openable[$id] ?? false),
                'orders' => ['received' => count($received), 'recent' => $recent, 'pending' => $pending, 'top' => array_slice($received, 0, self::TOP)],
                'tasksVisible' => $canTasks,
                'tasks' => ['todo' => $counts['todo'], 'doing' => $counts['doing'], 'done' => $counts['done'], 'late' => $counts['late'], 'top' => array_slice($tasks, 0, self::TOP)],
                'lastAt' => $lastAt,
                'lastTs' => $lastTs,
                'state' => $state,
            ];
        }

        return $rows;
    }

    /**
     * Filtre (échelon, état, retards seulement, recherche), puis trie. Le tri « unite » garde l'ordre de l'ORBAT.
     *
     * @param list<array<string, mixed>> $rows
     * @param array{echelon?: string, etat?: string, retard?: bool, q?: string} $filters
     * @return list<array<string, mixed>>
     */
    public static function apply(array $rows, array $filters, string $sort = 'unite', bool $desc = false): array
    {
        $echelon = (string) ($filters['echelon'] ?? '');
        $state = (string) ($filters['etat'] ?? '');
        $lateOnly = !empty($filters['retard']);
        $needle = JnetCommonRules::fold((string) ($filters['q'] ?? ''));

        $rows = array_values(array_filter($rows, static function (array $r) use ($echelon, $state, $lateOnly, $needle): bool {
            if ($echelon !== '' && $r['echelon'] !== $echelon) {
                return false;
            }
            if ($state !== '' && $r['state'] !== $state) {
                return false;
            }
            if ($lateOnly && (int) $r['tasks']['late'] <= 0) {
                return false;
            }
            if ($needle !== '' && !str_contains(JnetCommonRules::fold($r['label'] . ' ' . $r['code'] . ' ' . $r['leader'] . ' ' . $r['echelonLabel']), $needle)) {
                return false;
            }

            return true;
        }));

        $sort = in_array($sort, self::SORTS, true) ? $sort : 'unite';
        if ($sort === 'unite') {
            return $desc ? array_reverse($rows) : $rows;
        }
        $key = static fn (array $r): int|string => match ($sort) {
            'ordres' => $r['orders']['recent'],
            'accuser' => $r['orders']['pending'],
            'taches' => $r['tasks']['todo'] + $r['tasks']['doing'],
            'retard' => $r['tasks']['late'],
            'dernier' => $r['lastTs'],
            default => 3 - (int) array_search($r['state'], array_keys(self::STATES), true),
        };
        // Tri stable : à valeur égale, l'ordre de l'ORBAT.
        $indexed = [];
        foreach ($rows as $i => $r) {
            $indexed[] = [$key($r), $i, $r];
        }
        // $desc : la plus grande valeur d'abord ; à valeur égale, toujours l'ordre de l'ORBAT.
        usort($indexed, static fn (array $a, array $b): int => $desc ? [$b[0], $a[1]] <=> [$a[0], $b[1]] : [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        return array_map(static fn (array $x): array => $x[2], $indexed);
    }

    /**
     * Totaux du pied de tableau, sur les lignes affichées.
     *
     * @param list<array<string, mixed>> $rows
     * @return array{units: int, received: int, recent: int, pending: int, todo: int, doing: int, done: int, late: int}
     */
    public static function totals(array $rows): array
    {
        $t = ['units' => count($rows), 'received' => 0, 'recent' => 0, 'pending' => 0, 'todo' => 0, 'doing' => 0, 'done' => 0, 'late' => 0];
        foreach ($rows as $r) {
            $t['received'] += (int) $r['orders']['received'];
            $t['recent'] += (int) $r['orders']['recent'];
            $t['pending'] += (int) $r['orders']['pending'];
            $t['todo'] += (int) $r['tasks']['todo'];
            $t['doing'] += (int) $r['tasks']['doing'];
            $t['done'] += (int) $r['tasks']['done'];
            $t['late'] += (int) $r['tasks']['late'];
        }

        return $t;
    }
}
