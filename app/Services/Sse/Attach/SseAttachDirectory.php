<?php

declare(strict_types=1);

namespace App\Services\Sse\Attach;

use App\Core\Database;
use App\Repositories\SseAttachmentRepository;
use App\Repositories\SseEntityLinkRepository;
use App\Services\Sse\Links\SseLinkEdges;
use App\Services\Sse\Links\SseLinkGraph;
use App\Services\Sse\Links\SseLinkViewer;
use App\Services\Sse\Links\SseLinkVisibility;
use App\Support\SseAttachCatalog as A;
use App\Support\SseLinkCatalog as C;

/**
 * Annuaire des cibles de la fenêtre « Rattacher » : pour un lecteur et des pièces, ce qu'il peut choisir dans chaque périmètre
 * (recherche sans accents, tri, filtres, pagination), le détail d'une cible et les cibles proposées par Cerbère.
 *
 * Confidentialité : chaque ligne passe par l'adaptateur de son type et par SseLinkVisibility, comme le widget « Liaisons ».
 * Une cible que le lecteur ne peut pas lire n'est ni listée, ni comptée, ni décrite : la réponse est celle d'une cible
 * inexistante. La communauté est celle du lecteur, jamais une valeur de la requête.
 */
final class SseAttachDirectory
{
    /**
     * Lecture par périmètre : table, colonnes riches (repli sur SELECT * si une colonne manque dans une ancienne base),
     * condition supplémentaire.
     *
     * @var array<string, array{0: string, 1: string, 2?: string}>
     */
    private const TABLES = [
        'case' => ['sse_cases', 'id, reference_code, title, summary, classification, status, created_by, created_at, updated_at'],
        'interest' => ['sse_interest_cases', 'id, reference_code, temporary_designation, suspected_alias, opening_reason, status, created_by, created_at, updated_at'],
        'lead' => ['sse_leads', 'id, reference_code, title, description, status, classification, owner_user_id, created_by_user_id, created_at, updated_at'],
        'mesh' => ['sse_meshes', 'id, reference_code, title, summary, classification, status, created_by, created_at, updated_at'],
        'person' => ['sse_persons', 'id, last_name, first_name, alias, status, affiliation, circumstances, classification, submitter_user_id, submitter_callsign, created_at, updated_at'],
        'identity' => ['sse_identities', 'id, last_name, first_name, kind, user_id, person_id, created_by_user_id, created_at'],
        'paper' => ['sse_id_documents', 'id, doc_type, country_code, doc_number, workflow, identity_id, created_by_user_id, created_at'],
        'document' => ['sse_documents', 'id, reference_code, title, document_type, classification, status, author_label, created_by, created_at, updated_at'],
        'doc_issue' => ['sse_doc_issues', 'id, ref, model, classification, issued_by_user_id, issued_by_label, issued_at'],
        'note' => ['sse_field_notes', 'id, reference_code, title, themes, place_label, classification, status, author_label, author_user_id, created_at, updated_at'],
        'transmission' => ['sse_intel_events', 'id, event_type, summary, author_label, unit_label, event_time, created_at'],
        'notice' => ['sse_wanted_notices', 'id, ref, notice_type, reason, zone, status, created_by_user_id, issued_at, created_at'],
        'site' => ['sse_sites', 'id, name, site_type, status, summary, grid_reference, team_label, created_at, updated_at'],
        'organization' => ['sse_mesh_nodes', 'id, label, detail, kind, mesh_id, created_at', "kind = 'organization'"],
        'vehicle' => ['sse_mesh_nodes', 'id, label, detail, kind, mesh_id, created_at', "kind = 'vehicle'"],
        'equipment' => ['sse_mesh_nodes', 'id, label, detail, kind, mesh_id, created_at', "kind IN ('weapon', 'phone', 'terminal', 'custom')"],
        'campaign' => ['sse_campaigns', 'id, name, type, status, missions, created_at, updated_at'],
    ];

    /** États fermés par périmètre (point gris) ; les autres sont ouverts ou en cours. */
    private const CLOSED = ['clos', 'close', 'archive', 'sans_suite', 'closed', 'lifted', 'expired', 'cancelled', 'annule', 'termine', 'archived', 'ferme'];

    private const PROGRESS = ['en_cours', 'suivie', 'en_collecte', 'en_analyse', 'en_validation', 'transmise', 'exploite', 'draft', 'brouillon', 'a_qualifier', 'en_attente'];

    private SseLinkGraph $graph;

    private Database $db;

    /** @var array<string, list<array<string, mixed>>> lignes visibles par périmètre, pour la durée de la requête */
    private array $cache = [];

    public function __construct(
        private readonly SseLinkViewer $viewer,
        ?SseLinkGraph $graph = null,
        ?Database $db = null,
        private ?SseAttachmentRepository $attachments = null,
    ) {
        $this->db = $db ?? Database::getInstance();
        $this->graph = $graph ?? new SseLinkGraph($viewer, null, null, null, null, $db);
    }

    public function graph(): SseLinkGraph
    {
        return $this->graph;
    }

    private function attachments(): ?SseAttachmentRepository
    {
        if (!SseAttachmentRepository::ready()) {
            return null;
        }

        return $this->attachments ??= new SseAttachmentRepository($this->db);
    }

    // ------------------------------------------------------------------ Pièces

    /**
     * Les pièces demandées telles que le lecteur les voit (une pièce illisible disparaît : même réponse qu'inexistante).
     *
     * @param list<string> $refs « type:id »
     * @return list<array<string, mixed>>
     */
    public function sources(array $refs): array
    {
        $out = [];
        foreach (array_slice(array_values(array_unique($refs)), 0, A::BATCH_SOURCES) as $ref) {
            $p = C::parseRef((string) $ref);
            if ($p === null || !A::isSource($p['type'])) {
                continue;
            }
            $node = $this->graph->see($p['type'], $p['id']);
            if ($node === null || !$this->personAllowed($p['type'], [$p['id']])[$p['id']]) {
                continue;
            }
            $level = $this->levelOf($p['type'], $p['id']);
            $out[] = [
                'key' => $node['key'], 'type' => $p['type'], 'id' => $p['id'], 'type_label' => $node['type_label'], 'ref' => $node['ref'], 'title' => $node['title'],
                'href' => $node['href'], 'classifiable' => isset(A::CLASSIFIED[$p['type']]), 'level' => $level, 'level_abbr' => A::levelAbbr($level), 'level_label' => A::levelLabel($level),
                'can_write' => $this->viewer->canWrite(C::TYPES[$p['type']]['section']),
            ];
        }

        return $out;
    }

    /** Classification portée par un élément (null : type sans classification, ou élément non classifié). */
    public function levelOf(string $type, string $id): ?string
    {
        if (!isset(A::CLASSIFIED[$type])) {
            return null;
        }
        [$table, $col] = A::CLASSIFIED[$type];
        try {
            $row = $this->db->fetchOne("SELECT {$col} AS c FROM {$table} WHERE tenant_id = :t AND id = :id", ['t' => $this->viewer->tenantId(), 'id' => (int) $id]);
        } catch (\Throwable) {
            return null;
        }
        $c = (string) ($row['c'] ?? '');

        return A::isLevel($c) ? $c : null;
    }

    /** Habilitation du lecteur : le plus haut niveau que sa session lit. */
    public function viewerLevel(): string
    {
        $max = A::LEVELS[0];
        foreach (A::LEVELS as $level) {
            if ($this->viewer->clearanceAllows($level)) {
                $max = $level;
            }
        }

        return $max;
    }

    // ------------------------------------------------------------------ Recherche

    /**
     * @param list<array<string, mixed>> $sources pièces (sources())
     * @param array<string, mixed> $opts scope, q, sort, status, class, tag, mine, linked, page
     * @return array<string, mixed> scopes (onglets avec compteurs), scope, items, total, page, more
     */
    public function search(array $sources, array $opts): array
    {
        $allowed = A::commonTargets(array_column($sources, 'type'));
        $scope = (string) ($opts['scope'] ?? '');
        if (!in_array($scope, $allowed, true)) {
            $scope = $allowed[0] ?? '';
        }
        $q = A::fold(mb_substr((string) ($opts['q'] ?? ''), 0, 80));
        $ready = SseAttachmentRepository::ready() && SseEntityLinkRepository::ready();
        $linked = $this->linkedKeys($sources);
        $tabs = [];
        $items = [];
        foreach ($allowed as $s) {
            $enabled = $ready || $this->legacyOnly($sources, $s);
            $rows = $enabled ? $this->filter($this->visibleRows($s, $sources), $s, $q, $opts, $linked, count($sources)) : [];
            $tabs[] = ['key' => $s, 'label' => A::SCOPES[$s]['label'], 'count' => count($rows), 'enabled' => $enabled];
            if ($s === $scope) {
                $items = $rows;
            }
        }
        $items = $this->sort($items, (string) ($opts['sort'] ?? 'relevance'), $q);
        $total = count($items);
        $page = max(1, (int) ($opts['page'] ?? 1));
        $slice = array_slice($items, 0, $page * A::PAGE_SIZE);
        $slice = array_slice($slice, ($page - 1) * A::PAGE_SIZE);

        return [
            'scopes' => $tabs, 'scope' => $scope, 'scope_label' => A::SCOPES[$scope]['label'] ?? '', 'empty' => A::SCOPES[$scope]['empty'] ?? '',
            'items' => $this->present($slice), 'total' => $total, 'page' => $page, 'more' => $total > $page * A::PAGE_SIZE,
            'statuses' => $this->facet($items, 'status', 'status_label'),
        ];
    }

    /**
     * Détail d'une cible, ou null (inexistante ou hors de portée : indiscernable).
     *
     * @param list<array<string, mixed>> $sources
     * @return array<string, mixed>|null
     */
    public function detail(array $sources, string $type, string $id): ?array
    {
        if (!isset(A::SCOPES[$type]) || preg_match('/^[1-9]\d{0,9}$/', $id) !== 1) {
            return null;
        }
        foreach ($this->visibleRows($type, $sources) as $row) {
            if ((string) $row['id'] !== $id) {
                continue;
            }
            $linked = $this->linkedKeys($sources);
            $row['linked'] = $linked[$row['key']] ?? 0;
            $out = $this->present([$row])[0];
            $out['description'] = (string) $row['desc_full'];
            $out['updated'] = self::day((string) ($row['updated'] ?? ''));
            $out['readers'] = $this->readers($type, $row);
            $out['attached'] = $this->attachedPreview($type, $id);

            return $out;
        }

        return null;
    }

    /**
     * Cibles probables proposées par Cerbère : éléments à un saut des liens existants de la pièce, même lieu, même numéro.
     *
     * @param list<array<string, mixed>> $sources
     * @return list<array<string, mixed>>
     */
    public function suggestions(array $sources, int $limit = 5): array
    {
        $allowed = $this->enabledScopes($sources);
        if ($allowed === [] || $sources === []) {
            return [];
        }
        $linked = $this->linkedKeys($sources);
        $own = array_fill_keys(array_column($sources, 'key'), true);
        /** @var array<string, array{why: string, score: int}> $found */
        $found = [];
        $push = static function (string $key, string $why, int $score) use (&$found, $linked, $own, $allowed): void {
            [$t] = explode(':', $key, 2);
            if (isset($own[$key]) || isset($linked[$key]) || !in_array($t, $allowed, true)) {
                return;
            }
            if (!isset($found[$key]) || $found[$key]['score'] < $score) {
                $found[$key] = ['why' => $why, 'score' => $score];
            }
        };
        foreach (array_slice($sources, 0, 6) as $src) {
            $g = $this->graph->resolve((string) $src['type'], (string) $src['id'], 2, 80);
            foreach ((array) ($g['nodes'] ?? []) as $key => $n) {
                if ((int) ($n['depth'] ?? 0) !== 2 || !empty($n['restricted']) || ($n['type'] ?? '') === 'jnet') {
                    continue;
                }
                foreach ((array) ($g['edges'] ?? []) as $e) {
                    $other = $e['from'] === $key ? $e['to'] : ($e['to'] === $key ? $e['from'] : null);
                    $via = $other !== null ? ($g['nodes'][$other] ?? null) : null;
                    if ($via === null || (int) $via['depth'] !== 1 || !empty($via['restricted'])) {
                        continue;
                    }
                    $push((string) $key, self::sameWhy((string) $via['type']) . ' : ' . ((string) $via['ref'] !== '' ? $via['ref'] : $via['title']), 3);
                    break;
                }
            }
            foreach ($this->placeMatches($src) as $key) {
                $push($key, 'Même lieu que cette fiche', 2);
            }
            foreach ($this->numberMatches($src) as $key) {
                $push($key, 'Même numéro de papier', 4);
            }
        }
        if ($found === []) {
            return [];
        }
        uasort($found, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);
        $byScope = [];
        foreach (array_keys($found) as $key) {
            [$t, $i] = explode(':', $key, 2);
            $byScope[$t][$i] = true;
        }
        $rows = [];
        foreach ($byScope as $t => $ids) {
            foreach ($this->visibleRows((string) $t, $sources) as $row) {
                if (isset($ids[(string) $row['id']])) {
                    $rows[$row['key']] = $row;
                }
            }
        }
        $out = [];
        foreach ($found as $key => $f) {
            if (!isset($rows[$key])) {
                continue;
            }
            $item = $this->present([$rows[$key]])[0];
            $item['why'] = $f['why'];
            $out[] = $item;
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /**
     * Cibles par leurs clés (« Récents » : la mémoire locale du navigateur ne garde que des clés, le serveur redit ce que le
     * lecteur a encore le droit de voir).
     *
     * @param list<array<string, mixed>> $sources
     * @param list<string> $keys
     * @return list<array<string, mixed>>
     */
    public function byKeys(array $sources, array $keys): array
    {
        $allowed = $this->enabledScopes($sources);
        $want = [];
        foreach (array_slice($keys, 0, 8) as $key) {
            $p = C::parseRef((string) $key);
            if ($p !== null && in_array($p['type'], $allowed, true)) {
                $want[$p['type']][$p['id']] = true;
            }
        }
        $linked = $this->linkedKeys($sources);
        $out = [];
        foreach ($want as $t => $ids) {
            foreach ($this->visibleRows((string) $t, $sources) as $row) {
                if (isset($ids[(string) $row['id']])) {
                    $row['linked'] = $linked[$row['key']] ?? 0;
                    $out[] = $row;
                }
            }
        }

        return $this->present($out);
    }

    // ------------------------------------------------------------------ Lignes visibles

    /**
     * @param list<array<string, mixed>> $sources
     * @return list<array<string, mixed>>
     */
    private function visibleRows(string $scope, array $sources): array
    {
        if (!isset($this->cache[$scope])) {
            $this->cache[$scope] = $this->loadScope($scope);
        }
        $own = [];
        foreach ($sources as $s) {
            $own[$s['key']] = true;
        }

        return array_values(array_filter($this->cache[$scope], static fn (array $r): bool => !isset($own[$r['key']])));
    }

    /** @return list<array<string, mixed>> */
    private function loadScope(string $scope): array
    {
        $def = self::TABLES[$scope] ?? null;
        $adapter = $this->graph->adapters()->get($scope);
        $tenant = $this->viewer->tenantId();
        if ($def === null || $adapter === null || $tenant < 1 || !$this->viewer->canRead(C::TYPES[$scope]['section'] ?? '')) {
            return [];
        }
        $where = 'tenant_id = :t' . (isset($def[2]) ? ' AND ' . $def[2] : '');
        $tail = " FROM {$def[0]} WHERE {$where} ORDER BY id DESC LIMIT " . A::SCAN_LIMIT;
        try {
            $rows = $this->db->fetchAll('SELECT ' . $def[1] . $tail, ['t' => $tenant]);
        } catch (\Throwable) {
            try {
                $rows = $this->db->fetchAll('SELECT *' . $tail, ['t' => $tenant]);
            } catch (\Throwable) {
                return [];
            }
        }
        if ($rows === []) {
            return [];
        }
        $ids = array_map(static fn (array $r): string => (string) $r['id'], $rows);
        try {
            $descs = $adapter->describe($tenant, $ids);
        } catch (\Throwable) {
            return [];
        }
        $personOk = $this->personAllowed($scope, $ids);
        $out = [];
        foreach ($rows as $row) {
            $id = (string) $row['id'];
            $desc = $descs[$id] ?? null;
            if ($desc === null || !$personOk[$id] || SseLinkVisibility::judge($scope, $id, $desc, $this->viewer) !== SseLinkVisibility::OK) {
                continue;
            }
            $out[] = $this->normalize($scope, $id, $row, $desc);
        }
        $this->decorate($scope, $out);

        return $out;
    }

    /**
     * Fiches personnes classifiées (étape sse_persons_work) : au-dessus de l'habilitation du lecteur, elles ne sont pas proposées.
     *
     * @param list<string> $ids
     * @return array<string, bool>
     */
    private function personAllowed(string $type, array $ids): array
    {
        $ok = array_fill_keys($ids, true);
        if ($type !== 'person' || $ids === []) {
            return $ok;
        }
        try {
            foreach (array_chunk($ids, 200) as $chunk) {
                $marks = implode(',', array_map('intval', $chunk));
                foreach ($this->db->fetchAll("SELECT id, classification FROM sse_persons WHERE tenant_id = :t AND id IN ({$marks}) AND classification IS NOT NULL", ['t' => $this->viewer->tenantId()]) as $r) {
                    $c = (string) $r['classification'];
                    if (A::isLevel($c) && !$this->viewer->clearanceAllows($c)) {
                        $ok[(string) $r['id']] = false;
                    }
                }
            }
        } catch (\Throwable) {
            // Colonne absente (étape sse_persons_work non passée) : aucune fiche n'est classifiée.
        }

        return $ok;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $desc
     * @return array<string, mixed>
     */
    private function normalize(string $scope, string $id, array $row, array $desc): array
    {
        $s = static fn (string $k) => trim((string) ($row[$k] ?? ''));
        $status = $s('status');
        $statusLabel = match ($scope) {
            'case' => (string) (\App\Repositories\SseCaseRepository::STATUS_LABELS[$status] ?? $status),
            'interest' => (string) (\App\Repositories\SseInterestCaseRepository::STATUSES[$status] ?? $status),
            'lead' => (string) (A::LEAD_STATUSES[$status] ?? $status),
            'document' => (string) (\App\Repositories\SseDocumentRepository::STATUS_LABELS[$status] ?? $status),
            'person' => (string) (\App\Repositories\SsePersonRepository::STATUS_LABELS[$status] ?? $status),
            'paper' => (string) ($desc['summary'] ?? ''),
            'identity', 'doc_issue', 'transmission', 'organization', 'vehicle', 'equipment' => '',
            'notice' => $status === 'active' ? 'Actif' : ($status === '' ? '' : 'Levé'),
            default => $status === '' ? '' : mb_strtoupper(mb_substr($status, 0, 1)) . mb_substr(str_replace('_', ' ', $status), 1),
        };
        if ($scope === 'paper') {
            $status = $s('workflow');
        }
        $tone = $status === '' ? 'none' : (in_array($status, self::CLOSED, true) ? 'closed' : (in_array($status, self::PROGRESS, true) ? 'progress' : 'open'));
        $descText = match ($scope) {
            'case', 'mesh', 'site' => $s('summary'),
            'interest' => $s('opening_reason'),
            'lead' => $s('description'),
            'person' => trim($s('affiliation') . ($s('circumstances') !== '' ? ' — ' . $s('circumstances') : ''), ' —'),
            'note' => trim($s('place_label') . ($s('themes') !== '' ? ' · ' . $s('themes') : ''), ' ·'),
            'notice' => $s('reason'),
            'transmission' => $s('summary'),
            'organization', 'vehicle', 'equipment' => $s('detail'),
            default => (string) ($desc['summary'] ?? ''),
        };
        $class = (string) ($desc['classification'] ?? '');
        if ($scope === 'person') {
            $class = $s('classification');
        }
        $class = A::isLevel($class) ? $class : null;
        $ownerId = 0;
        foreach (['owner_user_id', 'created_by', 'created_by_user_id', 'author_user_id', 'issued_by_user_id', 'submitter_user_id'] as $col) {
            if ((int) ($row[$col] ?? 0) > 0) {
                $ownerId = (int) $row[$col];
                break;
            }
        }
        $ownerLabel = '';
        foreach (['author_label', 'issued_by_label', 'submitter_callsign', 'team_label'] as $col) {
            if ($s($col) !== '') {
                $ownerLabel = $s($col);
                break;
            }
        }
        $ref = (string) ($desc['ref'] ?? '');
        $title = (string) ($desc['title'] ?? '');
        $alias = match ($scope) {
            'person' => $s('alias'),
            'interest' => $s('suspected_alias'),
            'paper' => $s('doc_number'),
            'site' => $s('grid_reference'),
            default => '',
        };
        $date = (string) ($desc['date'] ?? '');
        $updated = $s('updated_at') !== '' ? $s('updated_at') : $date;

        return [
            'key' => C::ref($scope, $id), 'type' => $scope, 'id' => $id, 'ref' => $ref, 'title' => $title !== '' ? $title : $ref, 'alias' => $alias,
            'desc_full' => $descText, 'status' => $status, 'status_label' => $statusLabel, 'tone' => $tone, 'class' => $class,
            'owner_id' => $ownerId, 'owner_label' => $ownerLabel, 'date' => $date, 'updated' => $updated, 'href' => (string) ($desc['href'] ?? ''),
            'hay' => A::fold($ref . ' ' . $title . ' ' . $alias . ' ' . $descText), 'tags' => [], 'count' => 0, 'linked' => 0,
        ];
    }

    /**
     * Nombre d'éléments déjà rattachés et étiquettes posées, pour tout le périmètre (deux requêtes).
     *
     * @param list<array<string, mixed>> $rows
     */
    private function decorate(string $scope, array &$rows): void
    {
        $repo = $this->attachments();
        if ($repo === null || $rows === []) {
            return;
        }
        try {
            $counts = $repo->countsByTarget($this->viewer->tenantId(), $scope);
            $tags = $repo->tagsByTarget($this->viewer->tenantId(), $scope);
            $names = [];
            foreach ($repo->tags($this->viewer->tenantId()) as $t) {
                $names[$t['id']] = $t;
            }
        } catch (\Throwable) {
            return;
        }
        foreach ($rows as &$row) {
            $row['count'] = $counts[$row['id']] ?? 0;
            foreach ($tags[$row['id']] ?? [] as $tid) {
                if (isset($names[$tid])) {
                    $row['tags'][] = $names[$tid];
                    $row['hay'] .= ' ' . A::fold($names[$tid]['name']);
                }
            }
        }
        unset($row);
    }

    /**
     * Clés des éléments déjà liés aux pièces (liens enregistrés et colonnes historiques) => nombre de pièces liées.
     *
     * @param list<array<string, mixed>> $sources
     * @return array<string, int>
     */
    public function linkedKeys(array $sources): array
    {
        if ($sources === []) {
            return [];
        }
        $frontier = [];
        $own = [];
        foreach ($sources as $s) {
            $frontier[(string) $s['type']][] = (string) $s['id'];
            $own[(string) $s['key']] = true;
        }
        $tenant = $this->viewer->tenantId();
        $pairs = [];
        try {
            foreach ((new SseLinkEdges($this->db))->touching($tenant, $frontier, C::QUERY_LIMIT) as $e) {
                $pairs[] = [C::ref($e['a'][0], $e['a'][1]), C::ref($e['b'][0], $e['b'][1])];
            }
        } catch (\Throwable) {
        }
        if (SseEntityLinkRepository::ready()) {
            try {
                foreach ((new SseEntityLinkRepository($this->db))->touching($tenant, $frontier) as $row) {
                    $pairs[] = [C::ref((string) $row['source_type'], (string) $row['source_id']), C::ref((string) $row['target_type'], (string) $row['target_id'])];
                }
            } catch (\Throwable) {
            }
        }
        $seen = [];
        foreach ($pairs as [$a, $b]) {
            foreach ([[$a, $b], [$b, $a]] as [$src, $other]) {
                if (isset($own[$src])) {
                    $seen[$other][$src] = true;
                }
            }
        }

        return array_map('count', $seen);
    }

    // ------------------------------------------------------------------ Filtres, tri, présentation

    /**
     * @param list<array<string, mixed>> $rows
     * @param array<string, mixed> $opts
     * @param array<string, int> $linked
     * @return list<array<string, mixed>>
     */
    private function filter(array $rows, string $scope, string $q, array $opts, array $linked, int $sourceCount): array
    {
        $words = $q === '' ? [] : explode(' ', $q);
        $status = (string) ($opts['status'] ?? '');
        $class = (string) ($opts['class'] ?? '');
        $tag = (int) ($opts['tag'] ?? 0);
        $mine = !empty($opts['mine']);
        $uid = (int) $this->viewer->userId();
        $out = [];
        foreach ($rows as $row) {
            foreach ($words as $w) {
                if ($w !== '' && !str_contains((string) $row['hay'], $w)) {
                    continue 2;
                }
            }
            if ($status !== '' && $row['tone'] !== $status && $row['status'] !== $status) {
                continue;
            }
            if ($class !== '' && ($class === 'none' ? $row['class'] !== null : $row['class'] !== $class)) {
                continue;
            }
            if ($tag > 0 && !in_array($tag, array_column($row['tags'], 'id'), true)) {
                continue;
            }
            if ($mine && ($uid < 1 || (int) $row['owner_id'] !== $uid)) {
                continue;
            }
            $row['linked'] = $linked[$row['key']] ?? 0;
            $row['linked_all'] = $row['linked'] >= $sourceCount && $sourceCount > 0;
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function sort(array $rows, string $sort, string $q): array
    {
        $score = static function (array $r) use ($q): int {
            if ($q === '') {
                return 0;
            }
            $ref = A::fold((string) $r['ref']);
            $title = A::fold((string) $r['title']);

            return match (true) {
                $ref === $q => 100,
                str_starts_with($ref, $q) => 80,
                str_starts_with($title, $q) => 60,
                str_contains($ref, $q) => 50,
                str_contains($title, $q) => 40,
                default => 10,
            };
        };
        $recent = static fn (array $a, array $b): int => [(string) $b['updated'], (int) $b['id']] <=> [(string) $a['updated'], (int) $a['id']];
        usort($rows, match ($sort) {
            'recent' => $recent,
            'alpha' => static fn (array $a, array $b): int => [A::fold((string) $a['title']), (int) $a['id']] <=> [A::fold((string) $b['title']), (int) $b['id']],
            'class' => static fn (array $a, array $b): int => [A::rank($b['class']), (string) $b['updated']] <=> [A::rank($a['class']), (string) $a['updated']],
            'linked' => static fn (array $a, array $b): int => [(int) $b['linked'], (int) $b['count'], (string) $b['updated']] <=> [(int) $a['linked'], (int) $a['count'], (string) $a['updated']],
            default => static fn (array $a, array $b): int => [$score($b), $a['tone'] === 'closed' ? 1 : 0, (string) $b['updated']] <=> [$score($a), $b['tone'] === 'closed' ? 1 : 0, (string) $a['updated']],
        });

        return $rows;
    }

    /**
     * Lignes prêtes pour la fenêtre : noms des responsables résolus, extrait de description, classification en clair.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function present(array $rows): array
    {
        $names = $this->userNames(array_column($rows, 'owner_id'));
        $out = [];
        foreach ($rows as $r) {
            $desc = (string) $r['desc_full'];
            $out[] = [
                'key' => $r['key'], 'type' => $r['type'], 'type_label' => A::SCOPES[$r['type']]['one'] ?? C::typeLabel((string) $r['type']), 'id' => $r['id'],
                'ref' => $r['ref'], 'title' => $r['title'], 'alias' => $r['alias'],
                'excerpt' => mb_strlen($desc) > 150 ? rtrim(mb_substr($desc, 0, 148)) . '…' : $desc,
                'status' => $r['status'], 'status_label' => $r['status_label'], 'tone' => $r['tone'],
                'level' => $r['class'], 'level_abbr' => $r['class'] !== null ? A::levelAbbr($r['class']) : '', 'level_label' => $r['class'] !== null ? A::levelLabel($r['class']) : '',
                'owner' => $r['owner_id'] > 0 ? ($names[(int) $r['owner_id']] ?? '') : (string) $r['owner_label'],
                'date' => self::day((string) $r['date']), 'tags' => $r['tags'], 'count' => (int) $r['count'],
                'linked' => (int) $r['linked'], 'linked_all' => !empty($r['linked_all']),
                'href' => (string) $r['href'] !== '' && function_exists('url') ? url((string) $r['href']) : '',
                'creatable' => in_array($r['type'], A::CREATABLE, true),
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array{value: string, label: string}>
     */
    private function facet(array $rows, string $key, string $labelKey): array
    {
        $seen = [];
        foreach ($rows as $r) {
            if ((string) $r[$key] !== '' && (string) $r[$labelKey] !== '') {
                $seen[(string) $r[$key]] = (string) $r[$labelKey];
            }
        }
        asort($seen);
        $out = [];
        foreach ($seen as $value => $label) {
            $out[] = ['value' => $value, 'label' => $label];
        }

        return $out;
    }

    /** Qui peut voir la cible, en clair (rôles, jamais des noms). @param array<string, mixed> $row */
    private function readers(string $type, array $row): string
    {
        if ($type === 'interest') {
            return 'Diffusion restreinte : destinataires du dossier d’intérêt';
        }
        if ($row['class'] !== null) {
            return \App\Services\Sse\SseClearanceService::whoCanOpen((string) $row['class']);
        }

        return 'Lecteurs de la section « ' . (string) (\App\Support\SseAccessCatalog::SECTIONS[C::TYPES[$type]['section'] ?? '']['label'] ?? 'bureau SSE') . ' »';
    }

    /** @return list<array{ref: string, label: string, type_label: string}> les six derniers éléments rattachés, visibles du lecteur */
    private function attachedPreview(string $type, string $id): array
    {
        $repo = $this->attachments();
        if ($repo === null) {
            return [];
        }
        $out = [];
        try {
            foreach ($repo->forTarget($this->viewer->tenantId(), $type, $id, 12) as $att) {
                $node = $this->graph->see((string) $att['source_type'], (string) $att['source_id']);
                $out[] = $node === null
                    ? ['ref' => '', 'label' => 'Élément restreint', 'type_label' => '']
                    : ['ref' => (string) $node['ref'], 'label' => (string) $att['label'] !== '' ? (string) $att['label'] : (string) $node['title'], 'type_label' => (string) $node['type_label']];
                if (count($out) >= 6) {
                    break;
                }
            }
        } catch (\Throwable) {
        }

        return $out;
    }

    /** @param array<string, mixed> $src @return list<string> dossiers des fiches relevées au même endroit */
    private function placeMatches(array $src): array
    {
        if ($src['type'] !== 'note') {
            return [];
        }
        try {
            $me = $this->db->fetchOne('SELECT place_label, grid_reference FROM sse_field_notes WHERE tenant_id = :t AND id = :id', ['t' => $this->viewer->tenantId(), 'id' => (int) $src['id']]);
            $place = trim((string) ($me['place_label'] ?? ''));
            $grid = trim((string) ($me['grid_reference'] ?? ''));
            if ($place === '' && $grid === '') {
                return [];
            }
            $rows = $this->db->fetchAll(
                'SELECT DISTINCT case_id FROM sse_field_notes WHERE tenant_id = :t AND id <> :id AND case_id IS NOT NULL AND case_id > 0 AND ((:p <> \'\' AND place_label = :p2) OR (:g <> \'\' AND grid_reference = :g2)) LIMIT 5',
                ['t' => $this->viewer->tenantId(), 'id' => (int) $src['id'], 'p' => $place, 'p2' => $place, 'g' => $grid, 'g2' => $grid]
            );
        } catch (\Throwable) {
            return [];
        }

        return array_map(static fn (array $r): string => C::ref('case', (string) (int) $r['case_id']), $rows);
    }

    /** @param array<string, mixed> $src @return list<string> papiers et identités portant le numéro relevé au contrôle */
    private function numberMatches(array $src): array
    {
        if ($src['type'] !== 'check') {
            return [];
        }
        try {
            $me = $this->db->fetchOne('SELECT doc_number FROM sse_id_checks WHERE tenant_id = :t AND id = :id', ['t' => $this->viewer->tenantId(), 'id' => (int) $src['id']]);
            $num = trim((string) ($me['doc_number'] ?? ''));
            if ($num === '') {
                return [];
            }
            $rows = $this->db->fetchAll('SELECT id, identity_id FROM sse_id_documents WHERE tenant_id = :t AND doc_number = :n LIMIT 5', ['t' => $this->viewer->tenantId(), 'n' => $num]);
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $out[] = C::ref('paper', (string) (int) $r['id']);
            if ((int) ($r['identity_id'] ?? 0) > 0) {
                $out[] = C::ref('identity', (string) (int) $r['identity_id']);
            }
        }

        return $out;
    }

    private static function sameWhy(string $viaType): string
    {
        return match ($viaType) {
            'person', 'identity' => 'Même personne',
            'site' => 'Même lieu',
            'paper', 'check' => 'Même papier',
            'case', 'interest', 'lead' => 'Même affaire',
            default => 'Déjà lié à',
        };
    }

    /**
     * Périmètres où l'on peut rattacher aujourd'hui : tous ceux de la matrice, ou les seuls rattachements historiques tant que
     * la base n'est pas à jour.
     *
     * @param list<array<string, mixed>> $sources
     * @return list<string>
     */
    private function enabledScopes(array $sources): array
    {
        $all = A::commonTargets(array_column($sources, 'type'));
        if (SseAttachmentRepository::ready() && SseEntityLinkRepository::ready()) {
            return $all;
        }

        return array_values(array_filter($all, fn (string $s): bool => $this->legacyOnly($sources, $s)));
    }

    /** Sans la table des liens, seuls les rattachements historiques à un dossier restent possibles. @param list<array<string, mixed>> $sources */
    private function legacyOnly(array $sources, string $scope): bool
    {
        foreach ($sources as $s) {
            if (!in_array([(string) $s['type'], $scope], A::LEGACY_PAIRS, true)) {
                return false;
            }
        }

        return $sources !== [];
    }

    /**
     * @param list<int> $ids
     * @return array<int, string>
     */
    public function userNames(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $i): bool => $i > 0)));
        if ($ids === []) {
            return [];
        }
        try {
            $out = [];
            foreach ($this->db->fetchAll('SELECT id, display_name FROM users WHERE id IN (' . implode(',', $ids) . ')') as $r) {
                $out[(int) $r['id']] = trim((string) $r['display_name']) ?: 'Compte #' . (int) $r['id'];
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    public static function day(string $date): string
    {
        $t = $date !== '' ? strtotime($date) : false;

        return $t !== false ? date('d/m/Y', $t) : '';
    }
}
