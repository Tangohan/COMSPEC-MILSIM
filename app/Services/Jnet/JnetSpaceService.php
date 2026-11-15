<?php

declare(strict_types=1);

namespace App\Services\Jnet;

use App\Core\Gate;
use App\Repositories\JnetExchangeRepository;
use App\Repositories\JnetLinksRepository;
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

    /** @var array<string, array{read: bool, write: bool, lead: bool, publish: bool, chief: bool}> */
    private array $rightsCache = [];

    /** @var array<string, list<array<string, mixed>>> liaisons d'unités effectives, par communauté et espace */
    private array $linkCache = [];

    private ?JnetLinksRepository $links = null;

    public function __construct(
        private ?JnetDashboardService $dashboard = null,
        private ?JnetExchangeRepository $exchanges = null,
        private ?UnitRepository $units = null,
        private ?TenantRepository $tenants = null,
        private ?JnetUnitSpaceSettings $settings = null,
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

    public function dashboard(): JnetDashboardService
    {
        return $this->dashboard;
    }

    public function exchangesRepo(): JnetExchangeRepository
    {
        return $this->exchanges;
    }

    public function linksRepo(): JnetLinksRepository
    {
        return $this->links ??= new JnetLinksRepository();
    }

    /**
     * Liaisons d'unités effectives de l'espace (statut « active » aujourd'hui), vues de cet espace : l'autre unité, le
     * type, le rôle de l'espace (out, in, mutual) et si le chef a accordé la lecture de son espace. Ni à venir, ni
     * échue, ni terminée : seule une liaison active produit un effet.
     *
     * @return list<array{id: int, other: int, kind: string, role: string, owner: int, grant_read: bool}>
     */
    public function activeUnitLinks(int $tenantId, int $spaceId): array
    {
        $key = $tenantId . ':' . $spaceId;
        if (isset($this->linkCache[$key])) {
            return $this->linkCache[$key];
        }
        $out = [];
        $today = date('Y-m-d');
        try {
            foreach ($this->linksRepo()->unitLinksOf($tenantId, $spaceId) as $l) {
                if (!JnetLinkRules::isEffective(JnetLinkRules::statusOf($l, $today))) {
                    continue;
                }
                $mine = (int) $l['unit_id'] === $spaceId;
                $out[] = [
                    'id' => (int) $l['id'],
                    'other' => $mine ? (int) $l['partner_unit_id'] : (int) $l['unit_id'],
                    'kind' => (string) $l['kind'],
                    'role' => $mine ? (string) $l['direction'] : JnetLinkRules::oppositeRole((string) $l['direction']),
                    'owner' => (int) $l['unit_id'],
                    'grant_read' => (bool) $l['grant_read'],
                ];
            }
        } catch (\Throwable) {
            $out = [];
        }

        return $this->linkCache[$key] = $out;
    }

    /** Autres extrémités des liaisons de l'espace, quel que soit leur statut (pour classer les échanges déjà partagés). @return list<int> */
    private function linkedUnitIdsEver(int $tenantId, int $spaceId): array
    {
        try {
            $ids = [];
            foreach ($this->linksRepo()->unitLinksOf($tenantId, $spaceId) as $l) {
                $ids[] = (int) $l['unit_id'] === $spaceId ? (int) $l['partner_unit_id'] : (int) $l['unit_id'];
            }

            return array_values(array_unique($ids));
        } catch (\Throwable) {
            return [];
        }
    }

    /** Unités dont les membres lisent l'espace parce que son chef l'a coché sur une liaison active. @return list<int> */
    private function linkReadGrantees(int $tenantId, int $spaceId): array
    {
        $out = [];
        foreach ($this->activeUnitLinks($tenantId, $spaceId) as $l) {
            if ($l['grant_read'] && $l['owner'] === $spaceId) {
                $out[] = $l['other'];
            }
        }

        return $out;
    }

    public function tree(int $tenantId, int $viewerUserId): JnetSpaceTree
    {
        $key = $tenantId . ':' . $viewerUserId;
        if (!isset($this->treeCache[$key])) {
            $tenant = $this->tenants->findById($tenantId) ?: [];
            $orgLabel = is_array($tenant) && $tenant !== [] ? community_display_name($tenant) : 'Organisation';
            $this->treeCache[$key] = JnetSpaceTree::fromOrbat(
                $this->dashboard->orbatForViewer($tenantId, $viewerUserId),
                $orgLabel !== '' ? $orgLabel : 'Organisation'
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
            'echelon' => (string) ($node['echelon'] ?? ''),
            'function' => (string) ($node['function'] ?? ''),
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
            'canPost' => $this->exchangesReady() && $this->rights($tenantId, $viewerUserId, $spaceId)['publish'],
            'postTargets' => $this->postTargets($tree, $spaceId),
            'viewerUserId' => $viewerUserId,
        ];

        if ($spaceId === JnetSpaceTree::ORG) {
            $flow = $exchanges;
            if ($kindFilter !== '' && isset(self::KINDS[$kindFilter])) {
                $flow = array_values(array_filter($flow, static fn (array $e): bool => $e['kind'] === $kindFilter));
            }
            $since = time() - 86400;
            $data['flow'] = array_slice($this->decorate($tree, $flow, $viewerUserId, $spaceId, $byUserId), 0, 30);
            $data['flowFilter'] = $kindFilter;
            $data['unitTree'] = $this->unitTree($tree, JnetSpaceTree::ORG, $byUserId, $ops, $lastActivity, 1);
            $data['orgStats'] = [
                'units' => count($tree->unitIds()),
                'members' => count($personnel),
                'available' => count(array_filter($personnel, static fn (array $p): bool => ($p['duty'] ?? '') !== 'off')),
                'ops' => count(array_filter($ops, static fn (array $o): bool => in_array($o['state_key'] ?? '', ['active', 'in_progress'], true))),
                'exchanges24h' => count(array_filter($exchanges, static fn (array $e): bool => strtotime((string) $e['created_at']) >= $since)),
            ];
            $data['posture'] = $this->dashboard->postureFor($tenantId);
        } else {
            $down = $internal = $up = $linked = [];
            // Unités liées à cet espace (même terminées) : leurs échanges latéraux ne sont pas « reçus du commandement ».
            $linkedIds = $this->linkedUnitIdsEver($tenantId, $spaceId);
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
                if ($dir === JnetSpaceTree::DIR_LATERAL && $linkedIds !== []
                    && (in_array((int) $e['from_unit_id'], $linkedIds, true) || array_intersect(array_map('intval', $e['targets']), $linkedIds) !== [])) {
                    $linked[] = $e;
                    continue;
                }
                match ($dir) {
                    JnetSpaceTree::DIR_INTERNAL => $internal[] = $e,
                    JnetSpaceTree::DIR_UP => $up[] = $e,
                    default => $down[] = $e,
                };
            }
            $data['received'] = array_slice($this->decorate($tree, $down, $viewerUserId, $spaceId, $byUserId), 0, 12);
            $data['internal'] = array_slice($this->decorate($tree, $internal, $viewerUserId, $spaceId, $byUserId), 0, 15);
            $data['sentUp'] = array_slice($this->decorate($tree, $up, $viewerUserId, $spaceId, $byUserId), 0, 12);
            $data['linked'] = array_slice($this->decorate($tree, $linked, $viewerUserId, $spaceId, $byUserId), 0, 12);
            $data['members'] = $this->membersOf($tree->node($spaceId)['members'] ?? [], $byUserId);
            $data['subTree'] = $this->unitTree($tree, $spaceId, $byUserId, $ops, $lastActivity, 1);
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
        if (!$this->rights($tenantId, $viewerUserId, $spaceId)['publish']) {
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
            $newId = $this->exchanges->create(
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
        if ($kind === 'ordre') {
            try {
                $this->autoRelay($tenantId, $viewerUserId, $newId, $spaceId, $targets, $title, $body, $link, 0);
            } catch (\Throwable) {
                // Le relais automatique ne doit jamais faire échouer la publication de l'ordre d'origine.
            }
        }

        return null;
    }

    /** Réglages d'espace (prise en compte obligatoire, relais automatique, accès par unité, gestionnaires). */
    public function settings(): JnetUnitSpaceSettings
    {
        return $this->settings ??= new JnetUnitSpaceSettings();
    }

    /**
     * Le lecteur est-il chef de l'espace (même droit que « Personnaliser ») : administrateur du JNET, gestionnaire de
     * l'ORBAT, ou commandant de l'unité ou d'une unité au-dessus.
     */
    public function isSpaceChief(int $tenantId, int $userId, int $spaceId): bool
    {
        if ($this->isOrgWideWriter() || Gate::getInstance()->allows('organization.orbat.manage')) {
            return true;
        }
        $tree = $this->tree($tenantId, $userId);
        foreach ($this->settings()->commandedUnitIds($tenantId, $userId) as $c) {
            if ($tree->has($c) && $tree->isWithin($spaceId, $c)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Droits du lecteur dans un espace, réglages de l'espace appliqués (JnetSpaceRules::rights) : read, write (outils),
     * lead (modération), publish (échanges au nom de l'unité), chief. L'Organisation (0) n'a pas de réglages.
     *
     * @return array{read: bool, write: bool, lead: bool, publish: bool, chief: bool}
     */
    public function rights(int $tenantId, int $viewerUserId, int $spaceId): array
    {
        $key = $tenantId . ':' . $viewerUserId . ':' . $spaceId;
        if (isset($this->rightsCache[$key])) {
            return $this->rightsCache[$key];
        }
        $tree = $this->tree($tenantId, $viewerUserId);
        $viewerUnits = $this->viewerUnitIds($tenantId, $viewerUserId);
        if ($spaceId <= 0) {
            $can = $tree->canPostIn($viewerUnits, JnetSpaceTree::ORG, $this->isOrgWideWriter());

            return $this->rightsCache[$key] = ['read' => true, 'write' => $can, 'lead' => false, 'publish' => $can, 'chief' => $this->isOrgWideWriter()];
        }

        return $this->rightsCache[$key] = JnetSpaceRules::rights(
            $tree,
            $spaceId,
            $viewerUserId,
            $viewerUnits,
            $this->settings()->commandedUnitIds($tenantId, $viewerUserId),
            $this->isOrgWideWriter(),
            $this->isOrgWideReader(),
            $this->isSpaceChief($tenantId, $viewerUserId, $spaceId),
            $this->settings()->rules($tenantId, $spaceId) + ['link_read' => $this->linkReadGrantees($tenantId, $spaceId)]
        );
    }

    /** Peut ouvrir l'espace (réglage d'accès par unité compris). */
    public function canRead(int $tenantId, int $viewerUserId, int $spaceId): bool
    {
        return $this->rights($tenantId, $viewerUserId, $spaceId)['read'];
    }

    /** Le lecteur peut-il écrire dans les outils de l'espace (tâches, pages, commentaires, réponses) ? */
    public function canPost(int $tenantId, int $viewerUserId, int $spaceId): bool
    {
        return $this->rights($tenantId, $viewerUserId, $spaceId)['write'];
    }

    /**
     * Ordres à prendre en compte à l'entrée de l'espace, quand l'espace l'exige : vide si le réglage est coupé.
     *
     * @param list<array<string, mixed>> $exchanges échanges reçus puis internes, décorés
     * @return list<array<string, mixed>>
     */
    public function pendingOrdersFor(int $tenantId, int $spaceId, array $exchanges): array
    {
        if (!$this->settings()->rules($tenantId, $spaceId)['require_ack']) {
            return [];
        }

        return JnetSpaceRules::pendingOrders($exchanges);
    }

    /**
     * Unités où le lecteur peut partager un élément : celles où il peut publier (réglages compris), sauf l'espace d'où
     * part l'élément.
     *
     * @return list<array{id: int, label: string, via?: string, linkKind?: string}>
     */
    public function shareTargets(int $tenantId, int $viewerUserId, int $fromSpaceId): array
    {
        $tree = $this->tree($tenantId, $viewerUserId);
        $viewerUnits = $this->viewerUnitIds($tenantId, $viewerUserId);
        $orgWriter = $this->isOrgWideWriter();
        $out = [];
        foreach ($tree->unitIds() as $id) {
            if ($id === $fromSpaceId || !$tree->canPostIn($viewerUnits, $id, $orgWriter)) {
                continue;
            }
            if (!$orgWriter && !$this->rights($tenantId, $viewerUserId, $id)['publish']) {
                continue;
            }
            $out[] = ['id' => $id, 'label' => (string) ($tree->node($id)['label'] ?? 'Unité')];
        }
        // Unités liées par une liaison active : on peut leur partager une information depuis cet espace (jamais un ordre),
        // à condition de pouvoir déjà publier dans cet espace. Marquées « via » pour que l'envoi suive la règle des liaisons.
        if ($fromSpaceId > 0 && $tree->has($fromSpaceId) && $this->rights($tenantId, $viewerUserId, $fromSpaceId)['publish']) {
            $have = array_column($out, 'id');
            foreach ($this->activeUnitLinks($tenantId, $fromSpaceId) as $l) {
                if ($l['other'] === $fromSpaceId || in_array($l['other'], $have, true) || !$tree->has($l['other'])) {
                    continue;
                }
                $have[] = $l['other'];
                $out[] = ['id' => $l['other'], 'label' => (string) ($tree->node($l['other'])['label'] ?? 'Unité'), 'via' => 'liaison', 'linkKind' => JnetLinkRules::UNIT_KINDS[$l['kind']] ?? 'Liaison'];
            }
        }

        return $out;
    }

    /**
     * Partage d'une information vers une unité liée (liaison active) depuis un espace où le lecteur peut publier : un
     * échange « information », émis au nom de l'espace et adressé à l'unité liée. Jamais un ordre, jamais d'accusé de
     * lecture exigé, jamais de relais automatique. Retourne un message d'erreur, ou null.
     */
    public function shareViaLink(int $tenantId, int $viewerUserId, int $fromSpaceId, int $targetId, string $title, string $body, string $link): ?string
    {
        if (!$this->exchangesReady()) {
            return 'Les échanges ne sont pas encore activés (migration à appliquer).';
        }
        $tree = $this->tree($tenantId, $viewerUserId);
        if ($fromSpaceId <= 0 || !$tree->has($fromSpaceId) || !$tree->has($targetId)) {
            return 'Espace introuvable.';
        }
        if (!$this->rights($tenantId, $viewerUserId, $fromSpaceId)['publish']) {
            return 'Vous ne pouvez pas publier dans cet espace.';
        }
        $linked = false;
        foreach ($this->activeUnitLinks($tenantId, $fromSpaceId) as $l) {
            if ($l['other'] === $targetId) {
                $linked = true;
                break;
            }
        }
        if (!$linked) {
            return 'Aucune liaison active avec cette unité.';
        }
        $title = trim(preg_replace('/\s+/u', ' ', $title) ?? '');
        $body = trim($body);
        if ($title === '' || mb_strlen($title) > self::TITLE_MAX || mb_strlen($body) > self::BODY_MAX) {
            return 'Le titre ou le texte de l’information n’est pas valable.';
        }
        $link = trim($link);
        if ($link !== '') {
            if (!self::isInternalLink($link)) {
                return 'Le lien doit pointer vers une page d’Athena (chemin commençant par /).';
            }
            $link = self::relativeAppPath($link);
        }
        try {
            $this->exchanges->create($tenantId, $fromSpaceId, $viewerUserId, 'info', $title, $body, $link !== '' ? $link : null, false, [$targetId]);
        } catch (\Throwable) {
            return 'L’information n’a pas pu être enregistrée.';
        }

        return null;
    }

    /**
     * Un échange que le lecteur voit, avec les mêmes filtres que les écrans (unité émettrice et destinataires visibles
     * pour lui, ORBAT confidentiel compris) ; son auteur le voit toujours. Sert à décider d'un lien : null = masqué.
     *
     * @return array<string, mixed>|null
     */
    public function viewerExchange(int $tenantId, int $viewerUserId, int $exchangeId): ?array
    {
        $row = $this->exchanges->find($tenantId, $exchangeId);
        if ($row === null) {
            return null;
        }
        $tree = $this->tree($tenantId, $viewerUserId);
        $from = (int) $row['from_unit_id'];
        if ($from !== JnetSpaceTree::ORG && !$tree->has($from)) {
            return null;
        }
        $targets = array_values(array_filter(array_map('intval', (array) $row['targets']), static fn (int $t): bool => $t === JnetSpaceTree::ORG || $tree->has($t)));
        if ($targets === []) {
            return null;
        }
        if ((int) $row['author_user_id'] !== $viewerUserId && !$tree->canSeeExchange($this->viewerUnitIds($tenantId, $viewerUserId), $from, $targets, $this->isOrgWideReader())) {
            return null;
        }
        $row['targets'] = $targets;

        return $row;
    }

    /**
     * Photos et documents d'un espace tirés des échanges et de la bibliothèque, pour les rubriques Photos et Documents.
     *
     * - Photos : les photos terrain (source « Photo terrain ») publiées dans les échanges que le lecteur voit et qui
     *   concernent l'espace ; seule la vignette déjà publiée est utilisée, un cliché confidentiel ou du bureau SSE (sans
     *   vignette : lien protégé) n'apparaît jamais ici.
     * - Documents : le lien joint à un ordre ou à un document publié dans l'espace (pièce jointe), et les documents publiés
     *   de la bibliothèque, qui sont ceux de la page « Bibliothèque ».
     * Mêmes filtres de visibilité que les échanges (arbre ORBAT du lecteur) ; rien n'est inventé.
     *
     * @return array{photos: list<array<string, mixed>>, documents: list<array<string, mixed>>}
     */
    public function unitMedia(int $tenantId, int $viewerUserId, int $spaceId): array
    {
        $out = ['photos' => [], 'documents' => []];
        $tree = $this->tree($tenantId, $viewerUserId);
        if ($spaceId <= 0 || !$tree->has($spaceId)) {
            return $out;
        }
        if ($this->exchangesReady()) {
            $rows = $this->visibleExchanges($tenantId, $viewerUserId, $tree, $this->viewerUnitIds($tenantId, $viewerUserId), $this->isOrgWideReader());
            foreach ($rows as $e) {
                $touches = false;
                foreach (array_merge([(int) $e['from_unit_id']], (array) $e['targets']) as $c) {
                    $c = (int) $c;
                    if ($c === JnetSpaceTree::ORG || $tree->isWithin($c, $spaceId) || $tree->isWithin($spaceId, $c)) {
                        $touches = true;
                        break;
                    }
                }
                if (!$touches) {
                    continue;
                }
                $by = trim((string) ($e['author_callsign'] ?? ''));
                if ($by === '') {
                    $by = trim((string) ($e['author_display_name'] ?? ''));
                }
                $source = (string) ($e['source_kind'] ?? '');
                $image = trim((string) ($e['image_url'] ?? ''));
                $link = trim((string) ($e['link_url'] ?? ''));
                $title = (string) $e['title'];
                $when = (string) $e['created_at'];
                if ($source === JnetFieldReportBridge::SOURCE_IMAGE) {
                    if ($image !== '' && count($out['photos']) < 60) {
                        $out['photos'][] = JnetUnitFiles::mediaItem((int) $e['id'], $title, '', JnetFieldReportBridge::SOURCE_LABELS[$source], $by, $when, $link !== '' ? url($link) : $image, $image, 'exchange', 'exchange');
                    }
                    continue;
                }
                if ($source === '' && $link !== '' && in_array((string) $e['kind'], ['ordre', 'document'], true) && count($out['documents']) < 40) {
                    $out['documents'][] = JnetUnitFiles::mediaItem((int) $e['id'], $title, $e['kind'] === 'ordre' ? 'ORDRE' : 'LIEN', 'Pièces jointes des ordres', $by, $when, url($link), '', 'exchange', 'exchange');
                }
            }
        }
        try {
            foreach ($this->dashboard->libraryDocuments($tenantId, 40) as $doc) {
                $out['documents'][] = JnetUnitFiles::mediaItem(0, (string) $doc['title'], 'DOC', trim((string) $doc['category']) !== '' ? (string) $doc['category'] : 'Bibliothèque', '', (string) $doc['when'], (string) $doc['href'], '', 'library', '');
            }
        } catch (\Throwable) {
        }

        return $out;
    }

    /** Un échange que le lecteur voit (pour le partager), ou null. @return array<string, mixed>|null */
    public function visibleExchange(int $tenantId, int $viewerUserId, int $exchangeId): ?array
    {
        $row = $this->exchanges->find($tenantId, $exchangeId);
        if ($row === null) {
            return null;
        }
        $tree = $this->tree($tenantId, $viewerUserId);
        $targets = array_map('intval', (array) $row['targets']);

        return $tree->canSeeExchange($this->viewerUnitIds($tenantId, $viewerUserId), (int) $row['from_unit_id'], $targets, $this->isOrgWideReader()) ? $row : null;
    }

    /**
     * Relais automatique d'un ordre reçu : si l'espace $space l'a demandé (réglage « Relais automatique aux sous-unités »),
     * l'ordre repart tel quel vers ses sous-unités directes, une seule fois (journal par espace et par échange), sans
     * revenir en arrière (seulement vers le bas) et sans doubler les sous-unités déjà destinataires. Mêmes règles que le
     * relais manuel : l'ordre d'origine est transmis sans modification, avec une mention d'origine. Retourne le nombre
     * d'échanges créés (en cascade, si les sous-unités relaient aussi).
     *
     * @param list<int> $targets destinataires de l'échange source
     */
    private function autoRelay(int $tenantId, int $authorId, int $sourceId, int $fromUnit, array $targets, string $title, string $body, string $link, int $depth = 0): int
    {
        if ($depth > 12 || $sourceId <= 0) {
            return 0;
        }
        $tree = $this->tree($tenantId, $authorId);
        $created = 0;
        foreach ($targets as $space) {
            if ($space <= 0 || $space === $fromUnit || !$tree->has($space) || $tree->directionFor($space, $fromUnit, $targets) !== JnetSpaceTree::DIR_DOWN) {
                continue;
            }
            if (!$this->settings()->rules($tenantId, $space)['auto_relay'] || !$this->settings()->rulesTablesReady()) {
                continue;
            }
            $children = array_values(array_filter($tree->childrenOf($space), static fn (int $c): bool => $c !== $fromUnit && !in_array($c, $targets, true)));
            if ($children === [] || !$this->settings()->claimAutoRelay($tenantId, $space, $sourceId)) {
                continue;
            }
            $origin = (string) ($tree->node($fromUnit)['label'] ?? 'l’échelon supérieur');
            $text = 'Relayé automatiquement par ' . (string) ($tree->node($space)['label'] ?? 'l’unité') . ', depuis ' . $origin . "\n\n" . trim($body);
            try {
                $relayId = $this->exchanges->create($tenantId, $space, $authorId, 'ordre', $title, mb_substr(rtrim($text), 0, self::BODY_MAX), $link !== '' ? $link : null, true, $children);
            } catch (\Throwable) {
                continue;
            }
            $this->settings()->noteAutoRelay($tenantId, $space, $sourceId, $relayId);
            $created++;
            $created += $this->autoRelay($tenantId, $authorId, $relayId, $space, $children, $title, $text, $link, $depth + 1);
        }

        return $created;
    }

    /**
     * Relaie un ordre ou une consigne reçus aux sous-unités directes de l'espace : un nouvel échange part de l'espace,
     * avec le texte d'origine, sans le modifier, et un mot du chef en tête. Mêmes contrôles que la publication.
     *
     * @param list<int> $subUnitIds
     */
    public function relay(int $tenantId, int $viewerUserId, int $spaceId, int $exchangeId, array $subUnitIds, string $note): ?string
    {
        if (!$this->exchangesReady()) {
            return 'Les échanges ne sont pas encore activés (migration à appliquer).';
        }
        $tree = $this->tree($tenantId, $viewerUserId);
        $row = $this->exchanges->find($tenantId, $exchangeId);
        $viewerUnits = $this->viewerUnitIds($tenantId, $viewerUserId);
        if ($row === null || !$tree->has($spaceId) || !$tree->canSeeExchange($viewerUnits, (int) $row['from_unit_id'], (array) $row['targets'], $this->isOrgWideReader())) {
            return 'Échange introuvable.';
        }
        if ($tree->directionFor($spaceId, (int) $row['from_unit_id'], array_map('intval', (array) $row['targets'])) !== JnetSpaceTree::DIR_DOWN) {
            return 'Seul un échange reçu du commandement se relaie.';
        }
        $children = $tree->childrenOf($spaceId);
        $subUnitIds = array_values(array_filter(array_unique(array_map('intval', $subUnitIds)), static fn (int $id): bool => in_array($id, $children, true)));
        if ($subUnitIds === []) {
            return 'Choisissez au moins une sous-unité.';
        }
        $note = trim($note);
        $origin = (string) ($tree->node((int) $row['from_unit_id'])['label'] ?? 'l’échelon supérieur');
        $body = 'Relayé depuis ' . $origin . ($note !== '' ? ' — mot du chef : ' . $note : '') . "

" . trim((string) ($row['body'] ?? ''));
        $kind = isset(self::KINDS[(string) $row['kind']]) ? (string) $row['kind'] : 'info';

        $link = trim((string) ($row['link_url'] ?? ''));

        return $this->post($tenantId, $viewerUserId, $spaceId, $kind, (string) $row['title'], mb_substr(rtrim($body), 0, self::BODY_MAX), $link !== '' ? '/' . ltrim($link, '/') : '', $subUnitIds);
    }

    /**
     * Effectif de chaque unité visible du lecteur (sous-unités comprises), pour le registre des espaces.
     *
     * @return array<int, array{total:int, available:int, off:int}>
     */
    public function strengthByUnit(int $tenantId, int $viewerUserId): array
    {
        try {
            $tree = $this->tree($tenantId, $viewerUserId);
            $byUserId = [];
            foreach ($this->dashboard->loadPersonnelCards($tenantId) as $p) {
                $byUserId[(int) ($p['id'] ?? 0)] = $p;
            }
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($tree->unitIds() as $id) {
            $out[$id] = $this->strengthOf($tree->memberIdsInSubtree($id), $byUserId);
        }

        return $out;
    }

    /**
     * Échanges à accuser que le lecteur n'a pas encore lus, par espace de sa filiation (0 = Organisation).
     * Un échange compte pour chaque espace où il apparaît comme « reçu » ou « interne » ; ceux que le lecteur a
     * publiés lui-même ne comptent pas.
     *
     * @return array<int, int>
     */
    public function unreadByUnit(int $tenantId, int $viewerUserId): array
    {
        if (!$this->exchangesReady()) {
            return [];
        }
        $tree = $this->tree($tenantId, $viewerUserId);
        $viewerUnits = $this->viewerUnitIds($tenantId, $viewerUserId);
        $pending = array_values(array_filter(
            $this->visibleExchanges($tenantId, $viewerUserId, $tree, $viewerUnits, $this->isOrgWideReader()),
            static fn (array $e): bool => !empty($e['requires_ack']) && (int) $e['author_user_id'] !== $viewerUserId
        ));
        if ($pending === []) {
            return [];
        }
        $readers = $this->exchanges->readersFor(array_map(static fn (array $e): int => (int) $e['id'], $pending));
        $spaces = $tree->viewerSpaces($viewerUnits);
        $out = [];
        foreach ($pending as $e) {
            if (in_array($viewerUserId, $readers[(int) $e['id']] ?? [], true)) {
                continue;
            }
            $from = (int) $e['from_unit_id'];
            $targets = array_map('intval', (array) $e['targets']);
            foreach ($spaces as $spaceId) {
                if ($spaceId === JnetSpaceTree::ORG) {
                    if (in_array(JnetSpaceTree::ORG, $targets, true)) {
                        $out[$spaceId] = ($out[$spaceId] ?? 0) + 1;
                    }
                    continue;
                }
                $touches = false;
                foreach (array_merge([$from], $targets) as $c) {
                    if ($c === JnetSpaceTree::ORG || $tree->isWithin($c, $spaceId) || $tree->isWithin($spaceId, $c)) {
                        $touches = true;
                        break;
                    }
                }
                if ($touches && in_array($tree->directionFor($spaceId, $from, $targets), [JnetSpaceTree::DIR_DOWN, JnetSpaceTree::DIR_INTERNAL], true)) {
                    $out[$spaceId] = ($out[$spaceId] ?? 0) + 1;
                }
            }
        }

        return $out;
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
     * @param array<int, array<string, mixed>> $byUserId fiches personnel (photo d'opérateur, indicatif)
     * @return list<array<string, mixed>>
     */
    private function decorate(JnetSpaceTree $tree, array $rows, int $viewerUserId, int $spaceId, array $byUserId = []): array
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
                'authorPhoto' => $byUserId[(int) $r['author_user_id']]['photo'] ?? null,
                'authorInitials' => (string) ($byUserId[(int) $r['author_user_id']]['initials'] ?? mb_strtoupper(mb_substr($author !== '' ? $author : 'M', 0, 2))),
                'authorHref' => isset($byUserId[(int) $r['author_user_id']]) ? url('jnet/personnel/' . (int) $r['author_user_id']) : '',
                'requiresAck' => (bool) $r['requires_ack'],
                'source' => (string) ($r['source_kind'] ?? ''),
                'sourceLabel' => JnetFieldReportBridge::SOURCE_LABELS[(string) ($r['source_kind'] ?? '')] ?? '',
                'image' => trim((string) ($r['image_url'] ?? '')),
                'protectedImage' => ($r['source_kind'] ?? '') === JnetFieldReportBridge::SOURCE_IMAGE
                    && trim((string) ($r['image_url'] ?? '')) === '',
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
        $subtreeMembers = $tree->memberIdsInSubtree($id);
        $strength = $this->strengthOf($subtreeMembers, $byUserId);
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
            'faces' => $this->facesOf($subtreeMembers, $byUserId),
            'href' => $this->spaceUrl($id),
            'confidential' => is_array($n['confidential'] ?? null) ? $n['confidential'] : null,
        ];
    }

    /**
     * Visages d'une unité pour les cartes « équipe » : disponibles d'abord, au plus $limit.
     *
     * @param list<int> $memberIds
     * @param array<int, array<string, mixed>> $byUserId
     * @return list<array{name:string, initials:string, photo:?string, duty:string, href:string}>
     */
    private function facesOf(array $memberIds, array $byUserId, int $limit = 5): array
    {
        $faces = [];
        foreach ($this->membersOf($memberIds, $byUserId) as $p) {
            $faces[] = [
                'name' => (string) ($p['name'] ?? ''),
                'initials' => (string) ($p['initials'] ?? '?'),
                'photo' => is_string($p['photo'] ?? null) && $p['photo'] !== '' ? (string) $p['photo'] : null,
                'duty' => (string) ($p['duty'] ?? ''),
                'href' => (string) ($p['href'] ?? ''),
            ];
        }
        usort($faces, static fn (array $a, array $b): int => ($a['duty'] === 'off') <=> ($b['duty'] === 'off'));

        return array_slice($faces, 0, $limit);
    }

    /**
     * Arbre des unités sous un espace, pour la vue d'ensemble : chaque nœud porte sa carte et ses enfants.
     *
     * @param array<int, array<string, mixed>> $byUserId
     * @param list<array<string, mixed>> $ops
     * @param array<int, string> $lastActivity
     * @return list<array<string, mixed>>
     */
    private function unitTree(JnetSpaceTree $tree, int $parentId, array $byUserId, array $ops, array $lastActivity, int $level): array
    {
        $out = [];
        foreach ($tree->childrenOf($parentId) as $id) {
            $card = $this->unitCard($tree, $id, $byUserId, $ops, $lastActivity);
            $card['level'] = $level;
            $card['children'] = $this->unitTree($tree, $id, $byUserId, $ops, $lastActivity, $level + 1);
            $card['descendantCount'] = count($tree->descendants($id));
            $out[] = $card;
        }

        return $out;
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
