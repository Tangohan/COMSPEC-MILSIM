<?php

declare(strict_types=1);

namespace App\Services\Jnet;

use App\Repositories\JnetLinksRepository;

/**
 * Liaisons entre unités et liens entre contenus du JNET.
 *
 * Règle de fond, la même pour les deux : relier n'ouvre rien. Un lien est vérifié avec les droits du lecteur sur l'objet
 * LIÉ à chaque affichage (JnetLinkViewer::see) : illisible, il devient « Lien masqué », sans titre ni détail. À la création,
 * l'auteur doit voir la source et la cible. Une liaison d'unités vient du chef de l'espace (même droit que « Personnaliser »)
 * et ne produit d'effet qu'active ; ses effets sont posés dans JnetSpaceService / JnetSpaceRules, jamais ici.
 */
final class JnetLinkService
{
    public function __construct(private ?JnetLinksRepository $repo = null)
    {
        $this->repo ??= new JnetLinksRepository();
    }

    public function repo(): JnetLinksRepository
    {
        return $this->repo;
    }

    public function linksReady(): bool
    {
        return $this->repo->linksReady();
    }

    public function unitLinksReady(): bool
    {
        return $this->repo->unitLinksReady();
    }

    // ——— Liens entre contenus ———

    /**
     * Les liens d'un contenu, pour ce lecteur : chaque autre extrémité n'est montrée que s'il la voit.
     *
     * @return list<array<string, mixed>>
     */
    public function linksOf(JnetLinkViewer $v, string $type, string $id): array
    {
        if (!JnetLinkRules::validId($type, $id)) {
            return [];
        }

        return $this->present($v, $this->repo->linksOf($v->tenantId(), $type, $id, JnetLinkRules::LINKS_PER_OBJECT_MAX), [$type . ':' . $id => true])[$type . ':' . $id] ?? [];
    }

    /**
     * Les liens de plusieurs contenus d'un même type (les échanges d'un écran) en une requête.
     *
     * @param list<int|string> $ids
     * @return array<string, list<array<string, mixed>>> identifiant => liens
     */
    public function linksOfMany(JnetLinkViewer $v, string $type, array $ids): array
    {
        $ids = array_values(array_filter(array_map('strval', $ids), static fn (string $i): bool => JnetLinkRules::validId($type, $i)));
        if ($ids === []) {
            return [];
        }
        $wanted = [];
        foreach ($ids as $i) {
            $wanted[$type . ':' . $i] = true;
        }
        $byRef = $this->present($v, $this->repo->linksOfMany($v->tenantId(), $type, $ids), $wanted);
        $out = [];
        foreach ($byRef as $ref => $items) {
            $out[substr($ref, strlen($type) + 1)] = $items;
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param array<string, true> $wanted références (« type:id ») dont on veut les liens
     * @return array<string, list<array<string, mixed>>>
     */
    private function present(JnetLinkViewer $v, array $rows, array $wanted): array
    {
        $authors = $v->authors(array_map(static fn (array $r): int => (int) $r['created_by'], $rows));
        $moderate = [];
        $out = [];
        foreach ($rows as $row) {
            $src = $row['src_type'] . ':' . $row['src_id'];
            $dst = $row['dst_type'] . ':' . $row['dst_id'];
            $space = (int) $row['space_id'];
            if ($space > 0 && !isset($moderate[$space])) {
                $moderate[$space] = $v->canModerate($space);
            }
            $canRemove = JnetLinkRules::canRemove($row, $v->userId(), $space > 0 && $moderate[$space], $v->isOrgManager());
            $when = JnetSpaceService::relativeTime((string) $row['created_at']);
            foreach ([[$src, $row['dst_type'], $row['dst_id']], [$dst, $row['src_type'], $row['src_id']]] as [$self, $otherType, $otherId]) {
                if (!isset($wanted[$self])) {
                    continue;
                }
                $seen = $v->see((string) $otherType, (string) $otherId);
                $out[$self][] = JnetLinkRules::present((int) $row['id'], $seen, $canRemove, $seen !== null ? (string) ($authors[(int) $row['created_by']] ?? '') : '', $when);
            }
        }

        return $out;
    }

    /**
     * Vue d'ensemble d'un contenu (« Tout ce qui touche cette opération ») : les liens regroupés par rubrique, les liens
     * masqués à part.
     *
     * @return array{groups: array<string, list<array<string, mixed>>>, hidden: list<array<string, mixed>>, total: int}
     */
    public function overview(JnetLinkViewer $v, string $type, string $id): array
    {
        $groups = [];
        $hidden = [];
        $total = 0;
        foreach ($this->linksOf($v, $type, $id) as $item) {
            ++$total;
            if (!empty($item['hidden'])) {
                $hidden[] = $item;
                continue;
            }
            $groups[(string) $item['group']][] = $item;
        }
        $ordered = [];
        foreach (array_keys(JnetLinkRules::GROUPS) as $key) {
            if (isset($groups[$key])) {
                $ordered[$key] = $groups[$key];
            }
        }
        foreach ($groups as $key => $items) {
            $ordered[$key] ??= $items;
        }

        return ['groups' => $ordered, 'hidden' => $hidden, 'total' => $total];
    }

    /**
     * Pose un lien. L'auteur doit voir la source ET la cible, et pouvoir écrire dans l'espace où il pose le lien ;
     * hors espace (fiche d'opération, de cible), être membre d'une unité ou de l'encadrement. L'espace du lien est celui
     * de la page ou de la tâche source (jamais celui que le client prétend) ; le client ne fournit que l'espace de contexte
     * pour les autres sources, et il doit y écrire.
     *
     * @return array{error: ?string, id: int}
     */
    public function create(JnetLinkViewer $v, string $srcRef, string $dstRef, int $contextSpace): array
    {
        $fail = static fn (string $m): array => ['error' => $m, 'id' => 0];
        if (!$this->repo->linksReady()) {
            return $fail('Les liens ne sont pas encore activés (migration à appliquer).');
        }
        $s = JnetLinkRules::parseRef($srcRef);
        $d = JnetLinkRules::parseRef($dstRef);
        if ($s === null || $d === null || !JnetLinkRules::canPair($s['type'], $s['id'], $d['type'], $d['id'])) {
            return $fail('Ces deux éléments ne peuvent pas être reliés.');
        }
        $seenSrc = $v->see($s['type'], $s['id']);
        $seenDst = $v->see($d['type'], $d['id']);
        if ($seenSrc === null || $seenDst === null) {
            // Même réponse pour « n'existe pas » et « vous ne le voyez pas ».
            return $fail('Élément introuvable.');
        }
        $space = in_array($s['type'], ['page', 'tache'], true) ? (int) ($seenSrc['space'] ?? 0) : max(0, $contextSpace);
        if ($space > 0) {
            if (!$v->canWriteIn($space)) {
                return $fail('Vous n’avez pas l’écriture dans cet espace : vous ne pouvez pas y poser de lien.');
            }
        } elseif (!$v->inOwnUnits() && !$v->isOrgManager()) {
            return $fail('Seuls les membres d’une unité posent un lien.');
        }
        if ($this->repo->pairExists($v->tenantId(), $s['type'], $s['id'], $d['type'], $d['id'])) {
            return $fail('Ces deux éléments sont déjà liés.');
        }
        if ($this->repo->countOf($v->tenantId(), $s['type'], $s['id']) >= JnetLinkRules::LINKS_PER_OBJECT_MAX) {
            return $fail('Cet élément porte déjà ' . JnetLinkRules::LINKS_PER_OBJECT_MAX . ' liens.');
        }
        try {
            $id = $this->repo->addLink($v->tenantId(), $s['type'], $s['id'], $d['type'], $d['id'], $space, $v->userId());
        } catch (\Throwable) {
            return $fail('Le lien n’a pas pu être enregistré.');
        }

        return ['error' => null, 'id' => $id];
    }

    /** Retire un lien : son auteur, le chef de l'espace d'où il a été posé, ou l'encadrement pour un lien posé hors espace. */
    public function remove(JnetLinkViewer $v, int $linkId): ?string
    {
        $row = $this->repo->link($v->tenantId(), $linkId);
        if ($row === null) {
            return 'Lien introuvable.';
        }
        $space = (int) $row['space_id'];
        if (!JnetLinkRules::canRemove($row, $v->userId(), $space > 0 && $v->canModerate($space), $v->isOrgManager())) {
            return 'Seuls l’auteur du lien et le chef de l’espace le retirent.';
        }
        try {
            $this->repo->removeLink($v->tenantId(), $linkId);
        } catch (\Throwable) {
            return 'Le lien n’a pas pu être retiré.';
        }

        return null;
    }

    /**
     * Citations d'un ou plusieurs textes de page, résolues pour ce lecteur.
     *
     * @param list<string> $texts
     * @return array<string, array{title: string, href: string}|null>
     */
    public function resolveMentions(JnetLinkViewer $v, array $texts): array
    {
        $out = [];
        foreach ($texts as $text) {
            foreach (JnetLinkRules::mentions((string) $text) as $m) {
                $ref = $m['type'] . ':' . $m['id'];
                if (isset($out[$ref]) || count($out) >= 80) {
                    continue;
                }
                $seen = in_array($m['type'], JnetLinkRules::MENTION_TYPES, true) ? $v->see($m['type'], $m['id']) : null;
                $out[$ref] = $seen === null ? null : ['title' => $seen['title'], 'href' => $seen['href']];
            }
        }

        return $out;
    }

    /** Texte d'une page à l'enregistrement : les citations que l'auteur ne peut pas voir redeviennent du texte simple. */
    public function sanitizeMentions(JnetLinkViewer $v, string $text): string
    {
        return JnetLinkRules::sanitizeMentions($text, static fn (string $type, string $id): bool => in_array($type, JnetLinkRules::MENTION_TYPES, true) && $v->see($type, $id) !== null);
    }

    // ——— Liaisons d'unités ———

    /**
     * Les liaisons d'un espace vues par ce lecteur. Une liaison à venir ou active se voit comme tout l'espace ; les
     * liaisons échues ou terminées sont réservées au chef de l'espace (historique). Une autre unité que le lecteur ne voit
     * pas dans son ORBAT reste « Unité liée masquée », sans nom.
     *
     * @return list<array<string, mixed>>
     */
    public function unitLinksOf(JnetLinkViewer $v, int $spaceId): array
    {
        $today = $v->today();
        $chief = $v->isChief($spaceId);
        $label = $v->unitLabel($spaceId) ?? 'Cette unité';
        $out = [];
        foreach ($this->repo->unitLinksOf($v->tenantId(), $spaceId) as $row) {
            $status = JnetLinkRules::statusOf($row, $today);
            if ($status === 'expired' && !$chief) {
                continue;
            }
            $mine = (int) $row['unit_id'] === $spaceId;
            $otherId = $mine ? (int) $row['partner_unit_id'] : (int) $row['unit_id'];
            $role = $mine ? (string) $row['direction'] : JnetLinkRules::oppositeRole((string) $row['direction']);
            $otherLabel = $v->unitLabel($otherId);
            $item = [
                'id' => (int) $row['id'],
                'kind' => (string) $row['kind'],
                'kindLabel' => JnetLinkRules::UNIT_KINDS[(string) $row['kind']] ?? 'Liaison',
                'status' => $status,
                'statusLabel' => $status === 'expired' && $row['ended_at'] !== null ? 'Terminée' : JnetLinkRules::STATUS_LABELS[$status],
                'hidden' => $otherLabel === null,
                'otherId' => $otherLabel === null ? 0 : $otherId,
                'canEnd' => $status !== 'expired' && ($v->isChief((int) $row['unit_id']) || $v->isChief((int) $row['partner_unit_id'])),
            ];
            if ($otherLabel !== null) {
                $item += [
                    'otherLabel' => $otherLabel,
                    'role' => $role,
                    'sentence' => JnetLinkRules::sentence((string) $row['kind'], $role, $label, $otherLabel),
                    'period' => JnetLinkRules::periodLabel($row['starts_on'], $row['ends_on']),
                    'startsOn' => (string) ($row['starts_on'] ?? ''),
                    'endsOn' => (string) ($row['ends_on'] ?? ''),
                    'note' => (string) $row['note'],
                    // Lecture de l'espace accordée par le chef de l'unité qui a créé la liaison.
                    'grantRead' => (bool) $row['grant_read'] && $status === 'active',
                    'grantFrom' => $mine ? $label : $otherLabel,
                    'grantTo' => $mine ? $otherLabel : $label,
                    'mine' => $mine,
                ];
            }
            $out[] = $item;
        }

        return $out;
    }

    /**
     * Crée une liaison depuis l'espace $spaceId vers $partnerId. Réservé au chef de l'espace ; le partenaire doit être
     * visible de l'auteur et hors de la filiation (la hiérarchie ORBAT règle déjà ces cas).
     *
     * @param array<string, mixed> $input kind, direction, starts_on, ends_on, note, grant_read
     */
    public function createUnitLink(JnetLinkViewer $v, int $spaceId, int $partnerId, array $input): ?string
    {
        if (!$this->repo->unitLinksReady()) {
            return 'Les liaisons d’unités arrivent avec la prochaine mise à jour du site (étape jnet_links).';
        }
        if ($spaceId <= 0 || $partnerId <= 0 || $spaceId === $partnerId || $v->unitLabel($spaceId) === null || $v->unitLabel($partnerId) === null) {
            return 'Choisissez une autre unité de l’organisation.';
        }
        if (!$v->isChief($spaceId)) {
            return 'Seuls le chef de l’unité et l’encadrement de l’organisation créent une liaison.';
        }
        if ($v->related($spaceId, $partnerId)) {
            return 'Cette unité est déjà dans la filiation de celle-ci : la hiérarchie de l’ORBAT s’applique, une liaison n’y ajoute rien.';
        }
        $norm = JnetLinkRules::normalizeUnitLink($input, $v->today());
        if ($norm['error'] !== null) {
            return $norm['error'];
        }
        $today = $v->today();
        $open = 0;
        foreach ($this->repo->unitLinksOf($v->tenantId(), $spaceId) as $row) {
            if (JnetLinkRules::statusOf($row, $today) === 'expired') {
                continue;
            }
            if ((int) $row['unit_id'] === $spaceId) {
                ++$open;
            }
            $samePair = ((int) $row['unit_id'] === $spaceId && (int) $row['partner_unit_id'] === $partnerId)
                || ((int) $row['unit_id'] === $partnerId && (int) $row['partner_unit_id'] === $spaceId);
            if ($samePair && (string) $row['kind'] === $norm['values']['kind']) {
                return 'Cette liaison existe déjà.';
            }
        }
        if ($open >= JnetLinkRules::UNIT_LINKS_PER_SPACE_MAX) {
            return 'Cette unité porte déjà ' . JnetLinkRules::UNIT_LINKS_PER_SPACE_MAX . ' liaisons ouvertes : terminez-en avant d’en créer.';
        }
        try {
            $this->repo->addUnitLink($v->tenantId(), $spaceId, $partnerId, $norm['values'], $v->userId());
        } catch (\Throwable) {
            return 'La liaison n’a pas pu être enregistrée.';
        }

        return null;
    }

    /** Met fin à une liaison : le chef de l'une ou l'autre des deux unités peut la terminer (jamais un simple membre). */
    public function endUnitLink(JnetLinkViewer $v, int $linkId): ?string
    {
        $row = $this->repo->unitLink($v->tenantId(), $linkId);
        if ($row === null) {
            return 'Liaison introuvable.';
        }
        if (!$v->isChief((int) $row['unit_id']) && !$v->isChief((int) $row['partner_unit_id'])) {
            return 'Seul le chef de l’une des deux unités met fin à une liaison.';
        }
        if (JnetLinkRules::statusOf($row, $v->today()) === 'expired') {
            return 'Cette liaison est déjà échue.';
        }
        try {
            $this->repo->endUnitLink($v->tenantId(), $linkId, $v->userId());
        } catch (\Throwable) {
            return 'La liaison n’a pas pu être terminée.';
        }

        return null;
    }

    /**
     * Marquage des unités dans le registre du hub : pour chaque unité, ses liaisons actives avec une unité que le lecteur
     * voit. Une liaison avec une unité masquée ne laisse aucune trace.
     *
     * @return array<int, list<array{kind: string, kindLabel: string, other: string, otherMine: bool, sentence: string}>>
     */
    public function hubMarks(JnetLinkViewer $v): array
    {
        $today = $v->today();
        $mineIds = $v->ownUnitIds();
        $out = [];
        foreach ($this->repo->openUnitLinks($v->tenantId()) as $row) {
            if (!JnetLinkRules::isEffective(JnetLinkRules::statusOf($row, $today))) {
                continue;
            }
            $a = (int) $row['unit_id'];
            $b = (int) $row['partner_unit_id'];
            $la = $v->unitLabel($a);
            $lb = $v->unitLabel($b);
            if ($la === null || $lb === null) {
                continue;
            }
            $kind = (string) $row['kind'];
            $dir = (string) $row['direction'];
            $out[$a][] = ['kind' => $kind, 'kindLabel' => JnetLinkRules::UNIT_KINDS[$kind] ?? 'Liaison', 'other' => $lb, 'otherMine' => in_array($b, $mineIds, true), 'sentence' => JnetLinkRules::sentence($kind, $dir, $la, $lb)];
            $out[$b][] = ['kind' => $kind, 'kindLabel' => JnetLinkRules::UNIT_KINDS[$kind] ?? 'Liaison', 'other' => $la, 'otherMine' => in_array($a, $mineIds, true), 'sentence' => JnetLinkRules::sentence($kind, JnetLinkRules::oppositeRole($dir), $lb, $la)];
        }

        return $out;
    }
}
