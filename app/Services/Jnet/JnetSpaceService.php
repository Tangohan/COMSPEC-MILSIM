<?php

declare(strict_types=1);

namespace App\Services\Jnet;

use App\Core\Gate;
use App\Repositories\JnetExchangeRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UnitRepository;
use App\Support\JnetSpaceTree;
use App\Support\PortalAccessChoice;

/**
 * Espaces JNET : l'Organisation et chaque unité de l'ORBAT, avec leurs échanges.
 * Aucun contenu inventé — tout vient de l'ORBAT, du personnel, des opérations et des échanges réels.
 */
final class JnetSpaceService
{
    public const KINDS = [
        'ordre' => 'Ordre',
        'compte_rendu' => 'Compte rendu',
        'renseignement' => 'Renseignement',
        'document' => 'Document',
        'info' => 'Information',
    ];

    private const TITLE_MAX = 160;
    private const BODY_MAX = 4000;

    /** @var array<string, JnetSpaceTree> */
    private array $treeCache = [];

    public function __construct(
        private ?JnetDashboardService $dashboard = null,
        private ?JnetExchangeRepository $exchanges = null,
        private ?UnitRepository $units = null,
        private ?TenantRepository $tenants = null,
    ) {
        $this->dashboard ??= new JnetDashboardService();
        $this->exchanges ??= new JnetExchangeRepository();
        $this->units ??= new UnitRepository();
        $this->tenants ??= new TenantRepository();
    }

    public function exchangesReady(): bool
    {
        return $this->exchanges->schemaReady();
    }

    public function tree(int $tenantId, int $viewerUserId): JnetSpaceTree
    {
        $key = $tenantId . ':' . $viewerUserId;
        if (!isset($this->treeCache[$key])) {
            $tenant = $this->tenants->findById($tenantId) ?: [];
            $orgLabel = is_array($tenant) && $tenant !== [] ? community_display_name($tenant) : 'Organisation';
            $logo = is_array($tenant) ? trim((string) ($tenant['logo_url'] ?? '')) : '';
            $this->treeCache[$key] = JnetSpaceTree::fromOrbat(
                $this->dashboard->orbatForViewer($tenantId, $viewerUserId),
                $orgLabel !== '' ? $orgLabel : 'Organisation',
                $logo !== '' ? $logo : null
            );
        }

        return $this->treeCache[$key];
    }

    /** @return list<int> */
    public function viewerUnitIds(int $tenantId, int $viewerUserId): array
    {
        try {
            return array_values(array_map('intval', $this->units->unitIdsForUser($tenantId, $viewerUserId)));
        } catch (\Throwable) {
            return [];
        }
    }

    public function isOrgWideReader(): bool
    {
        return PortalAccessChoice::canAccessTba() || Gate::getInstance()->allows('organization.orbat.manage');
    }

    public function isOrgWideWriter(): bool
    {
        return PortalAccessChoice::canAccessTba();
    }

    /**
     * Données d'un espace (0 = Organisation). Null si l'unité n'existe pas pour ce lecteur.
     *
     * @return array<string, mixed>|null
     */
    public function buildSpace(int $tenantId, int $viewerUserId, int $spaceId, string $kindFilter = ''): ?array
    {
        $tree = $this->tree($tenantId, $viewerUserId);
        if (!$tree->has($spaceId)) {
            return null;
        }
        $viewerUnits = $this->viewerUnitIds($tenantId, $viewerUserId);
        $orgReader = $this->isOrgWideReader();
        $personnel = $this->dashboard->loadPersonnelCards($tenantId);
        $byUserId = [];
        foreach ($personnel as $p) {
            $byUserId[(int) ($p['id'] ?? 0)] = $p;
        }
        $ops = $this->dashboard->loadOperations($tenantId);
        $exchanges = $this->visibleExchanges($tenantId, $viewerUserId, $tree, $viewerUnits, $orgReader);
        $lastActivity = $this->lastActivityByUnit($exchanges);

        $node = (array) $tree->node($spaceId);
        $subtreeMembers = $tree->memberIdsInSubtree($spaceId);
        $strength = $this->strengthOf($subtreeMembers, $byUserId);
        $spaceOps = $this->opsInSubtree($tree, $spaceId, $ops);

        $space = [
            'id' => $spaceId,
            'isOrg' => $spaceId === JnetSpaceTree::ORG,
            'label' => (string) $node['label'],
            'code' => (string) $node['code'],
            'accent' => (string) $node['accent'],
            'badge' => $node['badge'],
            'motto' => (string) $node['motto'],
            'leader' => (string) $node['leader'],
            'deputy' => (string) $node['deputy'],
            'mission' => (string) $node['mission'],
            'parent' => $this->spaceRef($tree, $tree->parentOf($spaceId)),
            'chain' => array_map(fn (int $id): array => $this->spaceRef($tree, $id), $tree->chain($spaceId)),
            'strength' => $strength,
            'href' => $this->spaceUrl($spaceId),
        ];

        $children = [];
        foreach ($tree->childrenOf($spaceId) as $cid) {
            $children[] = $this->unitCard($tree, $cid, $byUserId, $ops, $lastActivity);
        }

        $data = [
            'space' => $space,
            'spacesRail' => $this->rail($tree, $viewerUnits, $spaceId),
            'subUnits' => $children,
            'spaceOps' => array_slice($spaceOps, 0, 6),
            'spaceOpsTotal' => count($spaceOps),
            'exchangeKinds' => self::KINDS,
            'exchangesReady' => $this->exchangesReady(),
            'canPost' => $this->exchangesReady() && $tree->canPostIn($viewerUnits, $spaceId, $this->isOrgWideWriter()),
            'postTargets' => $this->postTargets($tree, $spaceId),
            'viewerUserId' => $viewerUserId,
        ];

        if ($spaceId === JnetSpaceTree::ORG) {
            $flow = $exchanges;
            if ($kindFilter !== '' && isset(self::KINDS[$kindFilter])) {
                $flow = array_values(array_filter($flow, static fn (array $e): bool => $e['kind'] === $kindFilter));
            }
            $since = time() - 86400;
            $data['flow'] = array_slice($this->decorate($tree, $flow, $viewerUserId, $spaceId), 0, 30);
            $data['flowFilter'] = $kindFilter;
            $data['commands'] = $this->commandGroups($tree, $byUserId, $ops, $lastActivity);
            $data['orgStats'] = [
                'units' => count($tree->unitIds()),
                'members' => count($personnel),
                'available' => count(array_filter($personnel, static fn (array $p): bool => ($p['duty'] ?? '') !== 'off')),
                'ops' => count(array_filter($ops, static fn (array $o): bool => in_array($o['state_key'] ?? '', ['active', 'in_progress'], true))),
                'exchanges24h' => count(array_filter($exchanges, static fn (array $e): bool => strtotime((string) $e['created_at']) >= $since)),
            ];
            $data['posture'] = $this->dashboard->postureFor($tenantId);
        } else {
            $down = $internal = $up = [];
            foreach ($exchanges as $e) {
                $concerned = array_merge([$e['from_unit_id']], $e['targets']);
                $touches = false;
                foreach ($concerned as $c) {
                    if ($c === JnetSpaceTree::ORG || $tree->isWithin($c, $spaceId) || $tree->isWithin($spaceId, $c)) {
                        $touches = true;
                        break;
                    }
                }
                if (!$touches) {
                    continue;
                }
                $dir = $tree->directionFor($spaceId, $e['from_unit_id'], $e['targets']);
                match ($dir) {
                    JnetSpaceTree::DIR_INTERNAL => $internal[] = $e,
                    JnetSpaceTree::DIR_UP => $up[] = $e,
                    default => $down[] = $e,
                };
            }
            $data['received'] = array_slice($this->decorate($tree, $down, $viewerUserId, $spaceId), 0, 12);
            $data['internal'] = array_slice($this->decorate($tree, $internal, $viewerUserId, $spaceId), 0, 15);
            $data['sentUp'] = array_slice($this->decorate($tree, $up, $viewerUserId, $spaceId), 0, 12);
            $data['members'] = $this->membersOf($tree->node($spaceId)['members'] ?? [], $byUserId);
        }

        return $data;
    }

    /**
     * Publie un échange depuis un espace. Retourne un message d'erreur, ou null si tout va bien.
     *
     * @param list<int> $targets
     */
    public function post(
        int $tenantId,
        int $viewerUserId,
        int $spaceId,
        string $kind,
        string $title,
        string $body,
        string $link,
        array $targets
    ): ?string {
        if (!$this->exchangesReady()) {
            return 'Les échanges ne sont pas encore activés (migration à appliquer).';
        }
        $tree = $this->tree($tenantId, $viewerUserId);
        if (!$tree->has($spaceId)) {
            return 'Espace introuvable.';
        }
        $viewerUnits = $this->viewerUnitIds($tenantId, $viewerUserId);
        if (!$tree->canPostIn($viewerUnits, $spaceId, $this->isOrgWideWriter())) {
            return 'Vous ne pouvez pas publier dans cet espace.';
        }
        if (!isset(self::KINDS[$kind])) {
            $kind = 'info';
        }
        $title = trim(preg_replace('/\s+/u', ' ', $title) ?? '');
        $body = trim($body);
        if ($title === '') {
            return 'Donnez un titre à l’échange.';
        }
        if (mb_strlen($title) > self::TITLE_MAX) {
            return 'Le titre dépasse ' . self::TITLE_MAX . ' caractères.';
        }
        if (mb_strlen($body) > self::BODY_MAX) {
            return 'Le texte dépasse ' . self::BODY_MAX . ' caractères.';
        }
        $link = trim($link);
        if ($link !== '') {
            if (!self::isInternalLink($link)) {
                return 'Le lien doit pointer vers une page d’Athena (chemin commençant par /).';
            }
            $link = self::relativeAppPath($link);
        }

        $allowed = array_column($this->postTargets($tree, $spaceId), 'id');
        $targets = array_values(array_unique(array_filter(
            array_map('intval', $targets),
            static fn (int $t): bool => in_array($t, $allowed, true)
        )));
        if ($targets === []) {
            $targets = [$spaceId];
        }

        try {
            $this->exchanges->create(
                $tenantId,
                $spaceId,
                $viewerUserId,
                $kind,
                $title,
                $body,
                $link !== '' ? $link : null,
                $kind === 'ordre',
                $targets
            );
        } catch (\Throwable) {
            return 'L’échange n’a pas pu être enregistré.';
        }

        return null;
    }

    public function acknowledge(int $tenantId, int $viewerUserId, int $exchangeId): bool
    {
        $row = $this->exchanges->find($tenantId, $exchangeId);
        if ($row === null) {
            return false;
        }
        $tree = $this->tree($tenantId, $viewerUserId);
        $viewerUnits = $this->viewerUnitIds($tenantId, $viewerUserId);
        if (!$tree->canSeeExchange($viewerUnits, (int) $row['from_unit_id'], (array) $row['targets'], $this->isOrgWideReader())) {
            return false;
        }
        $this->exchanges->markRead($exchangeId, $viewerUserId);

        return true;
    }

    public function spaceUrl(int $spaceId): string
    {
        return $spaceId === JnetSpaceTree::ORG ? url('jnet') : url('jnet/u/' . $spaceId);
    }

    /**
     * Chemin relatif à l’application (sans domaine ni préfixe d’installation), rendu ensuite via url().
     * « https://athena.example/public/documents/4 », « /public/documents/4 » et « /documents/4 » → « documents/4 ».
     */
    public static function relativeAppPath(string $link): string
    {
        $base = rtrim(url(''), '/');
        if ($base !== '' && str_starts_with($link, $base . '/')) {
            $link = substr($link, strlen($base));
        }
        $prefix = rtrim((string) parse_url($base, PHP_URL_PATH), '/');
        if ($prefix !== '' && str_starts_with($link, $prefix . '/')) {
            $link = substr($link, strlen($prefix));
        }

        return ltrim($link, '/');
    }

    public static function isInternalLink(string $link): bool
    {
        if (str_starts_with($link, '/') && !str_starts_with($link, '//') && !str_contains($link, '\\')) {
            return true;
        }
        $base = rtrim(url(''), '/');

        return $base !== '' && str_starts_with($link, $base . '/');
    }

    /**
     * @param list<int> $viewerUnits
     * @return list<array<string, mixed>>
     */
    private function visibleExchanges(int $tenantId, int $viewerUserId, JnetSpaceTree $tree, array $viewerUnits, bool $orgReader): array
    {
        $out = [];
        foreach ($this->exchanges->recentForTenant($tenantId, 300) as $e) {
            $from = (int) $e['from_unit_id'];
            $targets = array_map('intval', (array) $e['targets']);
            // Unité émettrice ou destinataire masquée pour ce lecteur → l'échange reste hors de sa vue.
            if (($from !== JnetSpaceTree::ORG && !$tree->has($from))) {
                continue;
            }
            $targets = array_values(array_filter($targets, static fn (int $t): bool => $t === JnetSpaceTree::ORG || $tree->has($t)));
            if ($targets === []) {
                continue;
            }
            $isAuthor = (int) $e['author_user_id'] === $viewerUserId;
            if (!$isAuthor && !$tree->canSeeExchange($viewerUnits, $from, $targets, $orgReader)) {
                continue;
            }
            $e['targets'] = $targets;
            $out[] = $e;
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function decorate(JnetSpaceTree $tree, array $rows, int $viewerUserId, int $spaceId): array
    {
        $ids = array_map(static fn (array $r): int => (int) $r['id'], $rows);
        $readers = $this->exchanges->readersFor($ids);
        $out = [];
        foreach ($rows as $r) {
            $from = (int) $r['from_unit_id'];
            $fromNode = $tree->node($from);
            $audience = [];
            foreach ($r['targets'] as $t) {
                foreach ($tree->memberIdsInSubtree((int) $t) as $m) {
                    $audience[$m] = true;
                }
            }
            $readSet = array_flip($readers[(int) $r['id']] ?? []);
            $readCount = 0;
            foreach (array_keys($audience) as $m) {
                if (isset($readSet[$m])) {
                    $readCount++;
                }
            }
            $author = trim((string) ($r['author_callsign'] ?? ''));
            if ($author === '') {
                $author = trim((string) ($r['author_display_name'] ?? ''));
            }
            $out[] = [
                'id' => (int) $r['id'],
                'kind' => (string) $r['kind'],
                'kindLabel' => self::KINDS[(string) $r['kind']] ?? 'Information',
                'title' => (string) $r['title'],
                'body' => (string) ($r['body'] ?? ''),
                'link' => (string) ($r['link_url'] ?? ''),
                'when' => self::relativeTime((string) $r['created_at']),
                'stamp' => self::zuluStamp((string) $r['created_at']),
                'from' => [
                    'id' => $from,
                    'label' => (string) ($fromNode['label'] ?? 'Organisation'),
                    'accent' => (string) ($fromNode['accent'] ?? ''),
                    'href' => $this->spaceUrl($from),
                ],
                'to' => array_map(fn (int $t): array => $this->spaceRef($tree, $t), $r['targets']),
                'author' => $author !== '' ? $author : 'Membre',
                'requiresAck' => (bool) $r['requires_ack'],
                'audience' => count($audience),
                'readCount' => $readCount,
                'readByMe' => isset($readSet[$viewerUserId]),
                'direction' => $tree->directionFor($spaceId, $from, $r['targets']),
            ];
        }

        return $out;
    }

    /** @return array{id:int,label:string,accent:string,href:string,code:string} */
    private function spaceRef(JnetSpaceTree $tree, ?int $id): array
    {
        if ($id === null || !$tree->has($id)) {
            return ['id' => -1, 'label' => '', 'accent' => '', 'href' => '', 'code' => ''];
        }
        $n = (array) $tree->node($id);

        return [
            'id' => $id,
            'label' => (string) $n['label'],
            'accent' => (string) $n['accent'],
            'href' => $this->spaceUrl($id),
            'code' => (string) $n['code'],
        ];
    }

    /**
     * @param list<int> $viewerUnits
     * @return list<array<string, mixed>>
     */
    private function rail(JnetSpaceTree $tree, array $viewerUnits, int $activeId): array
    {
        $ids = $tree->viewerSpaces($viewerUnits);
        if (!in_array($activeId, $ids, true)) {
            // Espace consulté hors de sa filiation (vue d'ensemble) : on l'ajoute avec sa chaîne.
            $ids = array_values(array_unique(array_merge($ids, $tree->chain($activeId))));
        }
        $rows = [];
        foreach ($ids as $id) {
            $n = (array) $tree->node($id);
            $rows[] = [
                'id' => $id,
                'label' => (string) $n['label'],
                'code' => (string) $n['code'],
                'accent' => (string) $n['accent'],
                'depth' => (int) $n['depth'],
                'href' => $this->spaceUrl($id),
                'active' => $id === $activeId,
                'mine' => in_array($id, $viewerUnits, true),
            ];
        }
        usort($rows, static function (array $a, array $b) use ($ids): int {
            return array_search($a['id'], $ids, true) <=> array_search($b['id'], $ids, true);
        });

        return $rows;
    }

    /** @return list<array{id:int,label:string,relation:string}> */
    private function postTargets(JnetSpaceTree $tree, int $spaceId): array
    {
        $out = [];
        foreach ($tree->allowedTargetsFrom($spaceId) as $id) {
            $n = (array) $tree->node($id);
            $relation = match (true) {
                $id === $spaceId => 'Cet espace',
                $id === $tree->parentOf($spaceId) => 'Échelon supérieur',
                default => 'Subordonné',
            };
            $out[] = ['id' => $id, 'label' => (string) $n['label'], 'relation' => $relation];
        }

        return $out;
    }

    /**
     * @param list<int> $memberIds
     * @param array<int, array<string, mixed>> $byUserId
     * @return array{total:int, available:int, off:int}
     */
    private function strengthOf(array $memberIds, array $byUserId): array
    {
        $available = $off = 0;
        foreach ($memberIds as $m) {
            $card = $byUserId[$m] ?? null;
            if ($card === null) {
                continue;
            }
            if (($card['duty'] ?? '') === 'off') {
                $off++;
            } else {
                $available++;
            }
        }

        return ['total' => $available + $off, 'available' => $available, 'off' => $off];
    }

    /**
     * @param list<array<string, mixed>> $ops
     * @return list<array<string, mixed>>
     */
    private function opsInSubtree(JnetSpaceTree $tree, int $spaceId, array $ops): array
    {
        if ($spaceId === JnetSpaceTree::ORG) {
            return $ops;
        }

        return array_values(array_filter($ops, static function (array $o) use ($tree, $spaceId): bool {
            $u = (int) ($o['unit_id'] ?? 0);

            return $u > 0 && $tree->has($u) && $tree->isWithin($u, $spaceId);
        }));
    }

    /**
     * @param array<int, array<string, mixed>> $byUserId
     * @param list<array<string, mixed>> $ops
     * @param array<int, string> $lastActivity
     * @return array<string, mixed>
     */
    private function unitCard(JnetSpaceTree $tree, int $id, array $byUserId, array $ops, array $lastActivity): array
    {
        $n = (array) $tree->node($id);
        $strength = $this->strengthOf($tree->memberIdsInSubtree($id), $byUserId);
        $unitOps = $this->opsInSubtree($tree, $id, $ops);
        $op = $unitOps[0] ?? null;
        $parent = $tree->parentOf($id);

        return [
            'id' => $id,
            'label' => (string) $n['label'],
            'code' => (string) $n['code'],
            'accent' => (string) $n['accent'],
            'leader' => (string) $n['leader'],
            'parentLabel' => $parent !== null && $parent !== JnetSpaceTree::ORG ? (string) ($tree->node($parent)['label'] ?? '') : '',
            'strength' => $strength,
            'fill' => $strength['total'] > 0 ? (int) round($strength['available'] / $strength['total'] * 100) : 0,
            'activity' => $op !== null ? ((string) $op['title'] . ' — ' . (string) $op['state']) : '',
            'lastActivity' => $lastActivity[$id] ?? '',
            'subCount' => count($tree->childrenOf($id)),
            'href' => $this->spaceUrl($id),
        ];
    }

    /**
     * Unités en arbre (têtes d'arbre puis sous-unités), pour la vue d'ensemble de l'Organisation.
     *
     * @param array<int, array<string, mixed>> $byUserId
     * @param list<array<string, mixed>> $ops
     * @param array<int, string> $lastActivity
     * @return list<array<string, mixed>>
     */
    private function commandGroups(JnetSpaceTree $tree, array $byUserId, array $ops, array $lastActivity): array
    {
        $build = function (int $id, int $level) use (&$build, $tree, $byUserId, $ops, $lastActivity): array {
            $card = $this->unitCard($tree, $id, $byUserId, $ops, $lastActivity);
            $card['level'] = $level;
            $card['descendants'] = count($tree->descendants($id));
            $card['children'] = array_map(static fn (int $c): array => $build($c, $level + 1), $tree->childrenOf($id));

            return $card;
        };

        return array_map(static fn (int $top): array => $build($top, 0), $tree->childrenOf(JnetSpaceTree::ORG));
    }

    /**
     * @param list<array<string, mixed>> $exchanges triés du plus récent au plus ancien
     * @return array<int, string>
     */
    private function lastActivityByUnit(array $exchanges): array
    {
        $out = [];
        foreach ($exchanges as $e) {
            $from = (int) $e['from_unit_id'];
            if ($from > 0 && !isset($out[$from])) {
                $out[$from] = self::relativeTime((string) $e['created_at']);
            }
        }

        return $out;
    }

    /**
     * @param list<int> $memberIds
     * @param array<int, array<string, mixed>> $byUserId
     * @return list<array<string, mixed>>
     */
    private function membersOf(array $memberIds, array $byUserId): array
    {
        $out = [];
        foreach ($memberIds as $m) {
            if (isset($byUserId[(int) $m])) {
                $out[] = $byUserId[(int) $m];
            }
        }

        return $out;
    }

    public static function relativeTime(string $raw): string
    {
        $ts = strtotime($raw);
        if (!$ts) {
            return '';
        }
        $d = time() - $ts;

        return match (true) {
            $d < 60 => 'à l’instant',
            $d < 3600 => 'il y a ' . (int) floor($d / 60) . ' min',
            $d < 86400 => 'il y a ' . (int) floor($d / 3600) . ' h',
            $d < 172800 => 'hier',
            default => 'il y a ' . (int) floor($d / 86400) . ' j',
        };
    }

    public static function zuluStamp(string $raw): string
    {
        $ts = strtotime($raw);
        if (!$ts) {
            return '';
        }

        return time() - $ts < 86400 ? gmdate('H:i', $ts) . 'Z' : gmdate('d/m', $ts);
    }
}
