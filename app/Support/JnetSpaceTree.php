<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Arbre des espaces JNET : un espace par nœud ORBAT, plus l'espace Organisation (id 0).
 *
 * Construit à partir du payload ORBAT déjà filtré pour le lecteur (confidentialité appliquée) :
 * une unité absente du payload n'existe pas pour ce lecteur, donc son espace non plus.
 *
 * Règles de diffusion d'un échange :
 * - chaque unité « concernée » (émettrice ou destinataire) a une portée = ses ancêtres, elle-même
 *   et ses descendants. La chaîne de commandement voit ce qui se passe en dessous, les unités
 *   subordonnées reçoivent ce qui est adressé au-dessus d'elles, les unités latérales ne voient rien.
 * - un échange adressé à l'Organisation (0) est visible de tous.
 */
final class JnetSpaceTree
{
    public const ORG = 0;

    public const DIR_DOWN = 'down';
    public const DIR_INTERNAL = 'internal';
    public const DIR_UP = 'up';
    public const DIR_LATERAL = 'lateral';

    /** @var array<int, array<string, mixed>> */
    private array $nodes = [];

    /** @var array<int, int> enfant => parent */
    private array $parent = [];

    /** @var array<int, list<int>> */
    private array $children = [];

    /**
     * @param array<string, mixed>|null $orbatRoot payload OrbatRosterPayload::buildForTenant
     */
    public static function fromOrbat(?array $orbatRoot, string $orgLabel = 'Organisation'): self
    {
        $tree = new self();
        $tree->nodes[self::ORG] = [
            'id' => self::ORG,
            'label' => $orgLabel,
            'code' => 'ORG',
            'depth' => 0,
            'accent' => '',
            'badge' => null,
            'motto' => '',
            'leader' => '',
            'deputy' => '',
            'strength' => 0,
            'members' => [],
            'mission' => '',
        ];
        $tree->children[self::ORG] = [];

        if ($orbatRoot === null) {
            return $tree;
        }

        // Racine synthétique « Command » (unitId 0) : ses enfants sont les vraies têtes d'arbre.
        $tops = (int) ($orbatRoot['unitId'] ?? 0) === 0
            ? (array) ($orbatRoot['children'] ?? [])
            : [$orbatRoot];
        foreach ($tops as $top) {
            if (is_array($top)) {
                $tree->ingest($top, self::ORG, 1);
            }
        }

        return $tree;
    }

    /** @param array<string, mixed> $node */
    private function ingest(array $node, int $parentId, int $depth): void
    {
        $id = (int) ($node['unitId'] ?? 0);
        if ($id <= 0 || isset($this->nodes[$id])) {
            return;
        }
        $deputy = $node['deputy'] ?? null;
        $members = [];
        foreach ((array) ($node['members'] ?? []) as $m) {
            if (is_array($m) && (int) ($m['user_id'] ?? 0) > 0) {
                $members[] = (int) $m['user_id'];
            }
        }
        $leader = trim((string) ($node['leader'] ?? ''));
        $this->nodes[$id] = [
            'id' => $id,
            'label' => (string) ($node['label'] ?? 'Unité'),
            'code' => (string) ($node['role'] ?? ''),
            'depth' => $depth,
            'accent' => self::safeColor((string) ($node['accentColor'] ?? '')),
            'badge' => self::firstString($node['badgeMediaUrl'] ?? null, $node['chartIconUrl'] ?? null),
            'motto' => trim((string) ($node['motto'] ?? '')),
            'leader' => $leader === '—' ? '' : $leader,
            'deputy' => is_array($deputy) ? trim((string) ($deputy['label'] ?? '')) : '',
            'strength' => (int) ($node['strength'] ?? count($members)),
            'members' => $members,
            'mission' => trim((string) ($node['mission'] ?? '')),
        ];
        $this->parent[$id] = $parentId;
        $this->children[$parentId][] = $id;
        $this->children[$id] ??= [];
        foreach ((array) ($node['children'] ?? []) as $child) {
            if (is_array($child)) {
                $this->ingest($child, $id, $depth + 1);
            }
        }
    }

    /**
     * Construction directe (tests, scripts) : liste [id, parentId, label].
     *
     * @param list<array{0:int,1:int,2:string}> $rows
     */
    public static function fromRows(array $rows, string $orgLabel = 'Organisation'): self
    {
        $byParent = [];
        foreach ($rows as [$id, $parentId, $label]) {
            $byParent[$parentId][] = ['unitId' => $id, 'label' => $label];
        }
        $build = static function (int $pid) use (&$build, $byParent): array {
            $out = [];
            foreach ($byParent[$pid] ?? [] as $n) {
                $n['children'] = $build((int) $n['unitId']);
                $out[] = $n;
            }

            return $out;
        };

        return self::fromOrbat(['unitId' => 0, 'children' => $build(0)], $orgLabel);
    }

    public function has(int $id): bool
    {
        return isset($this->nodes[$id]);
    }

    /** @return array<string, mixed>|null */
    public function node(int $id): ?array
    {
        return $this->nodes[$id] ?? null;
    }

    /** @return list<int> */
    public function unitIds(): array
    {
        return array_values(array_filter(array_keys($this->nodes), static fn (int $id): bool => $id !== self::ORG));
    }

    public function parentOf(int $id): ?int
    {
        return $this->parent[$id] ?? null;
    }

    /** @return list<int> */
    public function childrenOf(int $id): array
    {
        return $this->children[$id] ?? [];
    }

    /** Ancêtres stricts, du plus proche au plus lointain, Organisation incluse. @return list<int> */
    public function ancestors(int $id): array
    {
        $out = [];
        $cur = $this->parent[$id] ?? null;
        while ($cur !== null) {
            $out[] = $cur;
            $cur = $this->parent[$cur] ?? null;
        }

        return $out;
    }

    /** Fil d'échelons de l'Organisation jusqu'à l'espace. @return list<int> */
    public function chain(int $id): array
    {
        if (!$this->has($id)) {
            return [self::ORG];
        }

        return array_merge(array_reverse($this->ancestors($id)), [$id]);
    }

    /** Descendants stricts. @return list<int> */
    public function descendants(int $id): array
    {
        $out = [];
        $stack = $this->children[$id] ?? [];
        while ($stack !== []) {
            $cur = array_shift($stack);
            $out[] = $cur;
            foreach ($this->children[$cur] ?? [] as $c) {
                $stack[] = $c;
            }
        }

        return $out;
    }

    /** Vrai si $id est $ancestor ou se trouve sous lui. */
    public function isWithin(int $id, int $ancestor): bool
    {
        if ($id === $ancestor) {
            return true;
        }

        return in_array($ancestor, $this->ancestors($id), true);
    }

    /**
     * Membres distincts de l'unité et de ses descendants (Organisation : tous).
     *
     * @return list<int>
     */
    public function memberIdsInSubtree(int $id): array
    {
        $ids = [];
        $scope = $id === self::ORG ? $this->unitIds() : array_merge([$id], $this->descendants($id));
        foreach ($scope as $uid) {
            foreach ($this->nodes[$uid]['members'] ?? [] as $m) {
                $ids[(int) $m] = true;
            }
        }

        return array_map('intval', array_keys($ids));
    }

    /**
     * Un lecteur voit-il un échange ?
     *
     * @param list<int> $viewerUnitIds unités d'appartenance du lecteur
     * @param list<int> $targetUnitIds destinataires (0 = Organisation)
     */
    public function canSeeExchange(array $viewerUnitIds, int $fromUnitId, array $targetUnitIds, bool $orgWideReader = false): bool
    {
        if ($orgWideReader || in_array(self::ORG, $targetUnitIds, true)) {
            return true;
        }
        $concerned = array_values(array_unique(array_merge([$fromUnitId], $targetUnitIds)));
        foreach ($viewerUnitIds as $v) {
            if (!$this->has($v)) {
                continue;
            }
            foreach ($concerned as $c) {
                if ($c === self::ORG) {
                    // Émis par l'Organisation mais adressé à des unités : seuls leurs périmètres voient.
                    continue;
                }
                if (!$this->has($c)) {
                    continue;
                }
                if ($this->isWithin($v, $c) || $this->isWithin($c, $v)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Sens d'un échange vu depuis un espace.
     *
     * - down     : reçu de l'échelon supérieur (émetteur au-dessus de l'espace, l'espace ou son ascendance est destinataire)
     * - up       : émis depuis l'espace ou son sous-arbre vers un échelon supérieur
     * - internal : émis et adressé dans le sous-arbre de l'espace
     * - lateral  : tout le reste (partenaires, autres branches)
     *
     * @param list<int> $targetUnitIds
     */
    public function directionFor(int $spaceId, int $fromUnitId, array $targetUnitIds): string
    {
        $fromInside = $this->isWithin($fromUnitId, $spaceId);
        $targetsAbove = false;
        $targetsInside = true;
        $reachesSpace = false;
        foreach ($targetUnitIds as $t) {
            $inside = $this->isWithin($t, $spaceId);
            if (!$inside) {
                $targetsInside = false;
                if ($t === self::ORG || in_array($t, $this->ancestors($spaceId), true)) {
                    $targetsAbove = true;
                }
            }
            if ($inside || $t === self::ORG || $this->isWithin($spaceId, $t)) {
                $reachesSpace = true;
            }
        }

        if ($fromInside) {
            if ($targetUnitIds === [] || $targetsInside) {
                return self::DIR_INTERNAL;
            }

            return $targetsAbove ? self::DIR_UP : self::DIR_LATERAL;
        }
        if ($reachesSpace && in_array($fromUnitId, $this->ancestors($spaceId), true)) {
            return self::DIR_DOWN;
        }

        return self::DIR_LATERAL;
    }

    /**
     * Destinataires qu'un auteur peut choisir depuis un espace : l'espace lui-même,
     * son échelon supérieur, ses subordonnés directs.
     *
     * @return list<int>
     */
    public function allowedTargetsFrom(int $spaceId): array
    {
        $out = [$spaceId];
        $p = $this->parentOf($spaceId);
        if ($p !== null) {
            $out[] = $p;
        }
        foreach ($this->childrenOf($spaceId) as $c) {
            $out[] = $c;
        }

        return array_values(array_unique($out));
    }

    /**
     * Un lecteur peut-il publier au nom d'un espace ? Il doit en être membre, ou appartenir
     * à un échelon supérieur (la chaîne de commandement peut s'exprimer dans ses subordonnés).
     *
     * @param list<int> $viewerUnitIds
     */
    public function canPostIn(array $viewerUnitIds, int $spaceId, bool $orgWideWriter = false): bool
    {
        if ($orgWideWriter) {
            return true;
        }
        if ($spaceId === self::ORG) {
            return false;
        }
        foreach ($viewerUnitIds as $v) {
            if ($this->has($v) && $this->isWithin($spaceId, $v)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Espaces affichés dans le rail « Mes espaces » : la filiation de chaque unité du lecteur.
     *
     * @param list<int> $viewerUnitIds
     * @return list<int>
     */
    public function viewerSpaces(array $viewerUnitIds): array
    {
        $ids = [self::ORG => true];
        foreach ($viewerUnitIds as $v) {
            if (!$this->has($v)) {
                continue;
            }
            foreach ($this->chain($v) as $c) {
                $ids[$c] = true;
            }
        }
        // Ordre d'arbre (parcours en profondeur depuis l'Organisation).
        $ordered = [];
        $walk = function (int $id) use (&$walk, &$ordered, $ids): void {
            if (isset($ids[$id])) {
                $ordered[] = $id;
            }
            foreach ($this->childrenOf($id) as $c) {
                $walk($c);
            }
        };
        $walk(self::ORG);

        return $ordered;
    }

    private static function safeColor(string $raw): string
    {
        $raw = trim($raw);

        return preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $raw) === 1 ? strtolower($raw) : '';
    }

    private static function firstString(mixed ...$values): ?string
    {
        foreach ($values as $v) {
            if (is_string($v) && trim($v) !== '') {
                return trim($v);
            }
        }

        return null;
    }
}
