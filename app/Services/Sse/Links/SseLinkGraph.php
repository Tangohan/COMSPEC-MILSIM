<?php

declare(strict_types=1);

namespace App\Services\Sse\Links;

use App\Core\Database;
use App\Repositories\SseEntityLinkRepository;
use App\Support\SseCerbere;
use App\Support\SseLinkCatalog as C;

/**
 * Chemin de liaison d'un élément du portail SSE : ses voisins directs et son chemin complet (provenance et descendance), en
 * LECTURE SEULE et pour UN lecteur.
 *
 * Parcours en largeur, un niveau à la fois : à chaque niveau, une requête par source de liens (colonnes existantes, table de
 * provenance sse_entity_links, citations JNET) puis UNE requête par type de nouveaux éléments (adaptateurs). Aucun N+1 :
 * le nombre de requêtes ne dépend que du nombre de niveaux (6 au plus) et de types, jamais du nombre d'éléments.
 *
 * Bornes : profondeur 6, 200 nœuds ; un élément n'est visité qu'une fois (les cycles sont donc sans effet, le lien de retour
 * est gardé une seule fois) ; un lien présent sous deux formes (déduit d'une colonne et enregistré) est montré une fois, avec
 * le motif enregistré.
 *
 * Confidentialité : chaque nœud passe SseLinkVisibility avec les droits du lecteur. Un nœud « cut » disparaît du chemin ; un nœud
 * « restricted » devient « Élément restreint » (sans nom, référence, type ni adresse, lien sans motif ni date) et le chemin ne le
 * traverse pas. La communauté est celle du lecteur : jamais une valeur de la requête.
 */
final class SseLinkGraph
{
    private SseLinkAdapters $adapters;

    private SseLinkEdges $edges;

    private ?SseEntityLinkRepository $links;

    public function __construct(
        private readonly SseLinkViewer $viewer,
        ?SseLinkAdapters $adapters = null,
        ?SseEntityLinkRepository $links = null,
        ?SseLinkEdges $edges = null,
        private readonly ?SseLinkJnetBridge $jnet = null,
        private readonly ?Database $db = null,
    ) {
        $this->adapters = $adapters ?? SseLinkAdapters::standard();
        $this->links = $links;
        $this->edges = $edges ?? new SseLinkEdges($db);
    }

    public function adapters(): SseLinkAdapters
    {
        return $this->adapters;
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(string $type, string $id, int $maxDepth = C::MAX_DEPTH, int $maxNodes = C::MAX_NODES): array
    {
        $tenant = $this->viewer->tenantId();
        $maxDepth = max(1, min(C::MAX_DEPTH, $maxDepth));
        $maxNodes = max(2, min(C::MAX_NODES, $maxNodes));
        $empty = ['found' => false, 'restricted' => false, 'degraded' => !SseEntityLinkRepository::ready(), 'truncated' => false, 'root' => null, 'nodes' => [], 'edges' => [], 'direct' => [], 'thread' => [], 'chains' => [], 'counts' => ['direct' => 0, 'total' => 0], 'depth' => 0, 'queries' => 0];
        $adapter = $this->adapters->get($type);
        if ($tenant < 1 || $adapter === null || $id === '' || !isset(C::TYPES[$type])) {
            return $empty;
        }
        try {
            $rootDesc = $adapter->describe($tenant, [$id])[$id] ?? null;
        } catch (\Throwable) {
            $rootDesc = null;
        }
        if ($rootDesc === null) {
            return $empty;
        }
        $verdict = SseLinkVisibility::judge($type, $id, $rootDesc, $this->viewer);
        if ($verdict !== SseLinkVisibility::OK) {
            return ['restricted' => $verdict === SseLinkVisibility::RESTRICTED] + $empty;
        }

        $rootKey = C::ref($type, $id);
        /** @var array<string, array<string, mixed>> $nodes */
        $nodes = [$rootKey => $this->node($type, $id, $rootDesc, 0)];
        /** @var array<string, string> $decided clé réelle => ok|restricted|cut */
        $decided = [$rootKey => SseLinkVisibility::OK];
        /** @var array<string, string> $opaque clé réelle d'un restreint => clé opaque */
        $opaque = [];
        /** @var array<string, array<string, mixed>> $edgesByPair */
        $edgesByPair = [];
        /** @var array<string, array{from: string, edge: string}> $parent */
        $parent = [];
        $frontier = [$rootKey];
        $truncated = false;
        $depth = 0;
        $queries = 0;
        $provenance = $this->links ?? (SseEntityLinkRepository::ready() ? new SseEntityLinkRepository($this->db) : null);

        for ($level = 1; $level <= $maxDepth && $frontier !== []; ++$level) {
            $byType = [];
            foreach ($frontier as $key) {
                $n = $nodes[$key];
                if ($n['type'] !== 'jnet') {
                    $byType[$n['type']][] = (string) $n['id'];
                }
            }
            $raw = $this->edges->touching($tenant, $byType, C::QUERY_LIMIT);
            ++$queries;
            if ($provenance !== null) {
                try {
                    foreach ($provenance->touching($tenant, $byType) as $row) {
                        $raw[] = $this->provenanceEdge($row);
                    }
                } catch (\Throwable) {
                    // La table disparaît en cours de route : on continue avec les colonnes.
                }
                ++$queries;
            }
            if ($this->jnet !== null) {
                $refs = array_map(static fn (string $k): string => $k, array_values(array_filter($frontier, fn (string $k): bool => $nodes[$k]['type'] !== 'jnet')));
                try {
                    foreach ($this->jnet->citations($tenant, $refs) as $c) {
                        $raw[] = $this->jnetEdge($c);
                    }
                } catch (\Throwable) {
                }
                ++$queries;
            }

            // Éléments voisins encore inconnus, regroupés par type (une description par type).
            $unknown = [];
            foreach ($raw as $e) {
                foreach ([$e['a'], $e['b']] as [$t, $i]) {
                    $k = C::ref($t, $i);
                    if (!isset($decided[$k]) && $t !== 'jnet') {
                        $unknown[$t][$i] = true;
                    }
                }
            }
            foreach ($unknown as $t => $ids) {
                $ad = $this->adapters->get((string) $t);
                $descs = [];
                if ($ad !== null) {
                    try {
                        $descs = $ad->describe($tenant, array_map('strval', array_keys($ids)));
                        ++$queries;
                    } catch (\Throwable) {
                        $descs = [];
                    }
                }
                foreach (array_keys($ids) as $i) {
                    $i = (string) $i;
                    $k = C::ref((string) $t, $i);
                    $desc = $descs[$i] ?? null;
                    $decided[$k] = $desc === null ? SseLinkVisibility::CUT : SseLinkVisibility::judge((string) $t, $i, $desc, $this->viewer);
                    if ($decided[$k] === SseLinkVisibility::OK && count($nodes) < $maxNodes) {
                        $nodes[$k] = $this->node((string) $t, $i, $desc, $level) + ['_new' => true];
                    } elseif ($decided[$k] === SseLinkVisibility::RESTRICTED && count($nodes) < $maxNodes) {
                        $opaque[$k] = 'r' . (count($opaque) + 1);
                        $nodes[$opaque[$k]] = $this->restrictedNode($opaque[$k], $level) + ['_new' => true];
                    } elseif ($decided[$k] !== SseLinkVisibility::CUT) {
                        $decided[$k] = 'overflow';
                        $truncated = true;
                    }
                }
            }
            // Contenus JNET cités : feuilles du chemin, déjà filtrées par les droits JNET du lecteur.
            foreach ($raw as $e) {
                foreach ([$e['a'], $e['b']] as [$t, $i]) {
                    if ($t !== 'jnet') {
                        continue;
                    }
                    $k = C::ref('jnet', $i);
                    if (isset($decided[$k])) {
                        continue;
                    }
                    if (count($nodes) < $maxNodes) {
                        $decided[$k] = SseLinkVisibility::OK;
                        $nodes[$k] = $this->jnetNode($i, $e['jnet'] ?? [], $level) + ['_new' => true];
                    } else {
                        $decided[$k] = 'overflow';
                        $truncated = true;
                    }
                }
            }

            // Liens retenus : les deux bouts sont visibles ou restreints, aucun n'est coupé ni hors plafond.
            foreach ($raw as $e) {
                $ka = C::ref($e['a'][0], $e['a'][1]);
                $kb = C::ref($e['b'][0], $e['b'][1]);
                if (!in_array($decided[$ka] ?? 'cut', [SseLinkVisibility::OK, SseLinkVisibility::RESTRICTED], true)
                    || !in_array($decided[$kb] ?? 'cut', [SseLinkVisibility::OK, SseLinkVisibility::RESTRICTED], true)) {
                    continue;
                }
                $ra = $opaque[$ka] ?? $ka;
                $rb = $opaque[$kb] ?? $kb;
                if ($ra === $rb || (isset($opaque[$ka]) && isset($opaque[$kb]))) {
                    continue;
                }
                $pair = $ra < $rb ? $ra . '|' . $rb : $rb . '|' . $ra;
                $e['from'] = $ra;
                $e['to'] = $rb;
                if (isset($opaque[$ka]) || isset($opaque[$kb])) {
                    $e = $this->hideEdge($e);
                }
                if (isset($edgesByPair[$pair])) {
                    if ($this->rank($e) > $this->rank($edgesByPair[$pair])) {
                        $edgesByPair[$pair] = $e + ['pair' => $pair];
                    }
                    continue;
                }
                $edgesByPair[$pair] = $e + ['pair' => $pair];
            }

            // Niveau suivant : seuls les nouveaux éléments visibles s'étendent (un restreint ne se traverse pas).
            $next = [];
            foreach ($nodes as $key => $n) {
                if (!empty($n['_new'])) {
                    unset($nodes[$key]['_new']);
                    if (!$n['restricted'] && $n['type'] !== 'jnet') {
                        $next[] = $key;
                    }
                    if (!isset($parent[$key])) {
                        foreach ($edgesByPair as $pair => $e) {
                            $other = $e['from'] === $key ? $e['to'] : ($e['to'] === $key ? $e['from'] : null);
                            if ($other !== null && isset($nodes[$other]) && $nodes[$other]['depth'] < $n['depth'] && !isset($parent[$key])) {
                                $parent[$key] = ['from' => $other, 'edge' => $pair];
                            }
                        }
                    }
                    $depth = max($depth, (int) $n['depth']);
                }
            }
            $frontier = $next;
        }

        return $this->assemble($rootKey, $nodes, $edgesByPair, $parent, $truncated, $depth, $queries);
    }

    /** Recherche d'éléments à relier, filtrée par les droits du lecteur (jamais un élément restreint ou coupé). */
    public function search(string $type, string $query, int $limit = 8): array
    {
        $ad = $this->adapters->get($type);
        $tenant = $this->viewer->tenantId();
        if ($ad === null || $tenant < 1 || $type === 'jnet') {
            return [];
        }
        $out = [];
        try {
            foreach ($ad->search($tenant, $query, $limit * 2) as $hit) {
                $desc = $hit['_desc'] ?? null;
                if (!is_array($desc) || SseLinkVisibility::judge($type, (string) $hit['id'], $desc, $this->viewer) !== SseLinkVisibility::OK) {
                    continue;
                }
                $out[] = ['type' => $type, 'type_label' => C::typeLabel($type), 'id' => (string) $hit['id'], 'ref' => (string) $hit['ref'], 'title' => (string) $hit['title'], 'summary' => (string) $hit['summary'], 'key' => C::ref($type, (string) $hit['id'])];
                if (count($out) >= $limit) {
                    break;
                }
            }
        } catch (\Throwable) {
            return [];
        }

        return $out;
    }

    /**
     * Un élément pour ce lecteur, ou null s'il n'existe pas ou n'est pas à sa portée (les deux cas sont indiscernables).
     *
     * @return array<string, mixed>|null
     */
    public function see(string $type, string $id): ?array
    {
        $ad = $this->adapters->get($type);
        $tenant = $this->viewer->tenantId();
        if ($ad === null || $tenant < 1 || $id === '') {
            return null;
        }
        try {
            $desc = $ad->describe($tenant, [$id])[$id] ?? null;
        } catch (\Throwable) {
            return null;
        }
        if ($desc === null || SseLinkVisibility::judge($type, $id, $desc, $this->viewer) !== SseLinkVisibility::OK) {
            return null;
        }

        return $this->node($type, $id, $desc, 0);
    }

    // ------------------------------------------------------------------------------------------------------------

    /** @param array<string, mixed> $desc @return array<string, mixed> */
    private function node(string $type, string $id, array $desc, int $depth): array
    {
        $href = (string) ($desc['href'] ?? '');

        return [
            'key' => C::ref($type, $id), 'type' => $type, 'type_label' => C::typeLabel($type), 'icon' => C::TYPES[$type]['icon'] ?? 'tag', 'id' => $id,
            'ref' => (string) ($desc['ref'] ?? ''), 'title' => (string) ($desc['title'] ?? ''), 'summary' => (string) ($desc['summary'] ?? ''),
            'href' => $href === '' ? '' : $this->url($href), 'date' => (string) ($desc['date'] ?? ''), 'depth' => $depth, 'restricted' => false,
        ];
    }

    /** @return array<string, mixed> */
    private function restrictedNode(string $key, int $depth): array
    {
        return ['key' => $key, 'type' => 'restricted', 'type_label' => 'Élément restreint', 'icon' => 'lock', 'id' => '', 'ref' => '', 'title' => 'Élément restreint', 'summary' => '', 'href' => '', 'date' => '', 'depth' => $depth, 'restricted' => true];
    }

    /** @param array<string, mixed> $c @return array<string, mixed> */
    private function jnetNode(string $id, array $c, int $depth): array
    {
        return [
            'key' => C::ref('jnet', $id), 'type' => 'jnet', 'type_label' => C::typeLabel('jnet'), 'icon' => 'jnet', 'id' => $id, 'ref' => '',
            'title' => (string) ($c['title'] ?? ''), 'summary' => (string) ($c['summary'] ?? ''), 'href' => (string) ($c['href'] ?? ''), 'date' => (string) ($c['date'] ?? ''),
            'depth' => $depth, 'restricted' => false, 'by' => (string) ($c['by'] ?? ''),
        ];
    }

    private function url(string $path): string
    {
        if (str_starts_with($path, 'http') || str_starts_with($path, '/')) {
            return $path;
        }

        return function_exists('url') ? url($path) : '/' . $path;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function provenanceEdge(array $row): array
    {
        $user = (string) ($row['created_by'] ?? '') === SseCerbere::ACTOR_USER;
        $rule = ($row['rule_code'] ?? null) !== null ? (string) $row['rule_code'] : null;

        return [
            'a' => [(string) $row['source_type'], (string) $row['source_id']],
            'b' => [(string) $row['target_type'], (string) $row['target_id']],
            'relation' => (string) ($row['relation'] ?? 'related'),
            'reason' => (string) ($row['reason'] ?? ''),
            'reasoned' => trim((string) ($row['reason'] ?? '')) !== '',
            'rule' => $rule,
            'origin' => $user ? 'manual' : 'cerbere',
            'actor_user' => $user && (int) ($row['created_by_user_id'] ?? 0) > 0 ? (int) $row['created_by_user_id'] : null,
            'actor_text' => $user ? '' : SseCerbere::actorLabel($rule),
            'at' => (string) ($row['created_at'] ?? ''),
            'source' => 'sse_entity_links',
            'link_id' => (int) $row['id'],
            'removable' => $user,
        ];
    }

    /**
     * @param array<string, mixed> $c
     * @return array<string, mixed>
     */
    private function jnetEdge(array $c): array
    {
        $ref = (string) $c['ref'];
        [$t, $i] = explode(':', $ref, 2) + ['', ''];

        return [
            'a' => [$t, $i], 'b' => ['jnet', (string) $c['id']], 'relation' => 'cited_in',
            'reason' => 'Cité dans ce contenu JNET.', 'reasoned' => true, 'rule' => null, 'origin' => 'manual', 'actor_user' => null,
            'actor_text' => (string) ($c['by'] ?? ''), 'at' => (string) ($c['date'] ?? ''), 'source' => 'jnet_links', 'link_id' => null, 'removable' => false, 'jnet' => $c,
        ];
    }

    /**
     * Un lien vers un élément restreint ne dit rien : ni motif, ni auteur, ni date, ni nature.
     *
     * @param array<string, mixed> $e
     * @return array<string, mixed>
     */
    private function hideEdge(array $e): array
    {
        return ['relation' => 'related', 'reason' => '', 'reasoned' => false, 'rule' => null, 'origin' => 'hidden', 'actor_user' => null, 'actor_text' => '', 'at' => '', 'source' => '', 'link_id' => null, 'removable' => false] + $e;
    }

    /** @param array<string, mixed> $e */
    private function rank(array $e): int
    {
        return ($e['link_id'] ?? null) !== null ? 3 : (($e['reasoned'] ?? false) ? 2 : 1);
    }

    /**
     * @param array<string, array<string, mixed>> $nodes
     * @param array<string, array<string, mixed>> $edgesByPair
     * @param array<string, array{from: string, edge: string}> $parent
     * @return array<string, mixed>
     */
    private function assemble(string $rootKey, array $nodes, array $edgesByPair, array $parent, bool $truncated, int $depth, int $queries): array
    {
        $names = $this->userNames(array_values(array_filter(array_map(static fn (array $e): ?int => $e['actor_user'] ?? null, $edgesByPair))));
        $edges = [];
        foreach ($edgesByPair as $pair => $e) {
            $origin = (string) $e['origin'];
            $actor = $e['actor_user'] !== null ? ($names[(int) $e['actor_user']] ?? 'Compte supprimé') : (string) ($e['actor_text'] ?? '');
            $edges[$pair] = [
                'key' => $pair, 'from' => $e['from'], 'to' => $e['to'], 'relation' => $e['relation'],
                'label' => C::relationLabel((string) $e['relation'], true), 'inverse' => C::relationLabel((string) $e['relation'], false),
                'reason' => (string) $e['reason'], 'reasoned' => (bool) $e['reasoned'], 'origin' => $origin, 'origin_label' => SseLinkEdges::originLabel($origin, $e['rule'] ?? null),
                'rule' => $e['rule'] ?? null, 'treatment' => SseCerbere::isCerbereRule($e['rule'] ?? null) || $origin === 'cerbere' ? SseCerbere::treatmentLabel($e['rule'] ?? null) : '',
                'actor' => $actor, 'at' => (string) $e['at'], 'source' => (string) $e['source'], 'link_id' => $e['link_id'] ?? null, 'removable' => (bool) ($e['removable'] ?? false),
            ];
        }

        // Fil : parcours en profondeur depuis la racine, frères triés par date.
        $children = [];
        foreach ($parent as $child => $p) {
            $children[$p['from']][] = $child;
        }
        $when = static fn (string $k) => ($nodes[$k]['date'] ?? '') !== '' ? (string) $nodes[$k]['date'] : '9999';
        foreach ($children as $k => $list) {
            usort($list, static fn (string $x, string $y): int => [$when($x), $x] <=> [$when($y), $y]);
            $children[$k] = $list;
        }
        // Liens hors de l'arbre (un contrôle lié à la fois à l'identité et au papier) : rattachés au maillon le plus lointain des deux.
        $also = [];
        foreach ($edges as $key => $e) {
            if ((isset($parent[$e['from']]) && $parent[$e['from']]['edge'] === $key) || (isset($parent[$e['to']]) && $parent[$e['to']]['edge'] === $key)) {
                continue;
            }
            if (!isset($nodes[$e['from']], $nodes[$e['to']])) {
                continue;
            }
            $late = $nodes[$e['from']]['depth'] >= $nodes[$e['to']]['depth'] ? $e['from'] : $e['to'];
            $also[$late][] = ['edge' => $key, 'other' => $late === $e['from'] ? $e['to'] : $e['from']];
        }
        $thread = [];
        $chains = [];
        $walk = function (string $key, int $level, array $trail) use (&$walk, &$thread, &$chains, $children, $parent, $nodes, $edges, $also): void {
            $trail[] = $key;
            $thread[] = ['key' => $key, 'level' => $level, 'edge' => $parent[$key]['edge'] ?? null, 'parent' => $parent[$key]['from'] ?? null, 'also' => $also[$key] ?? []];
            if (empty($children[$key])) {
                if (count($trail) > 1 && count($chains) < 60) {
                    $chains[] = [count($trail), implode(' → ', array_map(static fn (string $k): string => $nodes[$k]['restricted'] ? 'Élément restreint' : ($nodes[$k]['ref'] !== '' ? $nodes[$k]['ref'] : $nodes[$k]['title']), $trail))];
                }

                return;
            }
            foreach ($children[$key] as $c) {
                $walk($c, $level + 1, $trail);
            }
        };
        $walk($rootKey, 0, []);
        // Les chemins les plus longs d'abord (ce sont eux qui racontent d'où vient l'élément et où il mène), puis l'ordre du fil.
        $ranked = $chains;
        usort($ranked, static fn (array $a, array $b): int => $b[0] <=> $a[0]);
        $chains = array_slice(array_values(array_unique(array_map(static fn (array $c): string => $c[1], $ranked))), 0, 12);

        // Liens directs : groupés par type, du plus récent au plus ancien.
        $direct = [];
        $nDirect = 0;
        foreach ($edges as $e) {
            $other = $e['from'] === $rootKey ? $e['to'] : ($e['to'] === $rootKey ? $e['from'] : null);
            if ($other === null || !isset($nodes[$other])) {
                continue;
            }
            ++$nDirect;
            $direct[$nodes[$other]['type']][] = ['node' => $other, 'edge' => $e['key'], 'forward' => $e['from'] === $rootKey];
        }
        foreach ($direct as $t => $list) {
            usort($list, static fn (array $x, array $y): int => [(string) ($edges[$y['edge']]['at'] ?? ''), $y['node']] <=> [(string) ($edges[$x['edge']]['at'] ?? ''), $x['node']]);
            $direct[$t] = $list;
        }

        return [
            'found' => true, 'restricted' => false, 'degraded' => !SseEntityLinkRepository::ready(), 'truncated' => $truncated, 'root' => $rootKey,
            'nodes' => $nodes, 'edges' => $edges, 'direct' => $direct, 'thread' => $thread, 'chains' => $chains,
            'counts' => ['direct' => $nDirect, 'total' => count($nodes) - 1], 'depth' => $depth, 'queries' => $queries,
        ];
    }

    /**
     * @param list<int> $ids
     * @return array<int, string>
     */
    private function userNames(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids, static fn (int $i): bool => $i > 0)));
        if ($ids === []) {
            return [];
        }
        $params = [];
        $marks = [];
        foreach ($ids as $n => $id) {
            $params['u' . $n] = $id;
            $marks[] = ':u' . $n;
        }
        try {
            $out = [];
            foreach (($this->db ?? Database::getInstance())->fetchAll('SELECT id, display_name FROM users WHERE id IN (' . implode(',', $marks) . ')', $params) as $r) {
                $out[(int) $r['id']] = trim((string) $r['display_name']) ?: 'Compte #' . (int) $r['id'];
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }
}
