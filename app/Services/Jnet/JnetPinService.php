<?php

declare(strict_types=1);

namespace App\Services\Jnet;

/**
 * Éléments épinglés de l'espace commun : lecture revérifiée pour chaque lecteur, épinglage, retrait et ordre.
 *
 * Aucun droit n'est décidé ici qui ne le soit déjà ailleurs : « ce que le lecteur voit » vient de JnetLinkViewer (arbre ORBAT
 * filtré, droits des espaces, porte du renseignement SSE) et, pour un renseignement, du résolveur donné au constructeur
 * (porte du renseignement SSE). « Qui gère les épingles » est le droit de publier au niveau de l'organisation, calculé par
 * l'appelant. En cas de doute, la réponse est « masqué ».
 */
final class JnetPinService
{
    /**
     * @param \Closure(string): (array{title: string, sub: string, href: string}|null) $intel résout un renseignement (« ev-3 », « fn-8 »)
     *        pour CE lecteur, null s'il est inconnu ou derrière la porte SSE
     * @param \Closure(list<int>): array<int, string> $labels indicatifs des auteurs d'épingles
     */
    public function __construct(
        private readonly JnetPinStore $store,
        private readonly JnetLinkViewer $viewer,
        private readonly bool $canManage,
        private readonly \Closure $intel,
        private readonly \Closure $labels,
    ) {
    }

    public function ready(): bool
    {
        return $this->store->ready();
    }

    public function canManage(): bool
    {
        return JnetPinRules::canManage($this->canManage);
    }

    /**
     * Épingles dans l'ordre, chacune revérifiée avec les droits du lecteur. Un élément invisible reste dans la liste (pour
     * que ceux qui gèrent puissent le retirer) mais sans titre, type ni lien.
     *
     * @return list<array{id: int, ref: string, type: string, typeLabel: string, visible: bool, title: string, sub: string, href: string, salon: string, by: string, at: string}>
     */
    public function pins(): array
    {
        $rows = $this->store->all($this->viewer->tenantId());
        if ($rows === []) {
            return [];
        }
        $names = (array) ($this->labels)(array_map(static fn (array $r): int => $r['pinned_by'], $rows));
        $out = [];
        foreach ($rows as $r) {
            $seen = $this->resolve($r['target_type'], $r['target_id']);
            $visible = $seen !== null;
            $out[] = [
                'id' => $r['id'],
                'ref' => JnetPinRules::ref($r['target_type'], $r['target_id']),
                // Masqué : ni titre, ni type, ni lien, ni salon (rien ne trahit la nature de l'élément).
                'type' => $visible ? $r['target_type'] : '',
                'typeLabel' => $visible ? (JnetPinRules::TYPES[$r['target_type']] ?? '') : '',
                'visible' => $visible,
                'title' => $visible ? $seen['title'] : '',
                'sub' => $visible ? $seen['sub'] : '',
                'href' => $visible ? $seen['href'] : '',
                'salon' => $visible ? JnetPinRules::salonOf($r['target_type']) : 'epingles',
                'by' => (string) ($names[$r['pinned_by']] ?? ''),
                'at' => $r['pinned_at'],
            ];
        }

        return $out;
    }

    /** @return array{title: string, sub: string, href: string}|null */
    private function resolve(string $type, string $id): ?array
    {
        if (!JnetPinRules::validId($type, $id)) {
            return null;
        }
        try {
            if ($type === 'renseignement') {
                $found = ($this->intel)($id);

                return is_array($found) ? ['title' => (string) $found['title'], 'sub' => (string) $found['sub'], 'href' => (string) $found['href']] : null;
            }
            $seen = $this->viewer->see($type, $id);

            return $seen === null ? null : ['title' => $seen['title'], 'sub' => $seen['sub'], 'href' => $seen['href']];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Références déjà épinglées : « type:identifiant » vers l'identifiant de l'épingle.
     *
     * @return array<string, int>
     */
    public function pinnedRefs(): array
    {
        $out = [];
        foreach ($this->store->all($this->viewer->tenantId()) as $r) {
            $out[JnetPinRules::ref($r['target_type'], $r['target_id'])] = $r['id'];
        }

        return $out;
    }

    /** Épingle un élément. Retourne un message d'erreur, ou null si tout va bien. */
    public function pin(string $type, string $id): ?string
    {
        if (!$this->canManage()) {
            return 'Seul l’encadrement qui publie pour toute l’organisation épingle des éléments.';
        }
        if (!$this->store->ready()) {
            return 'Les épingles ne sont pas encore activées : un administrateur doit appliquer les migrations.';
        }
        // Même message pour « inexistant », « invalide » et « non visible » : rien ne distingue les trois.
        if (!JnetPinRules::validId($type, $id) || $this->resolve($type, $id) === null) {
            return 'Élément introuvable.';
        }
        $tenant = $this->viewer->tenantId();
        $current = $this->store->all($tenant);
        foreach ($current as $r) {
            if ($r['target_type'] === $type && $r['target_id'] === $id) {
                return 'Cet élément est déjà épinglé.';
            }
        }
        if (count($current) >= JnetPinRules::MAX) {
            return 'Vous avez atteint la limite de ' . JnetPinRules::MAX . ' éléments épinglés : retirez-en un avant d’en ajouter.';
        }

        return $this->store->add($tenant, $type, $id, $this->viewer->userId()) > 0 ? null : 'Cet élément est déjà épinglé.';
    }

    /** Retire une épingle (par son identifiant). Retourne un message d'erreur, ou null. */
    public function unpin(int $pinId): ?string
    {
        if (!$this->canManage()) {
            return 'Seul l’encadrement qui publie pour toute l’organisation retire des éléments épinglés.';
        }
        $tenant = $this->viewer->tenantId();
        foreach ($this->store->all($tenant) as $r) {
            if ($r['id'] === $pinId) {
                $this->store->remove($tenant, $pinId);

                return null;
            }
        }

        return 'Épingle introuvable.';
    }

    /** Monte ($direction -1) ou descend (1) une épingle. */
    public function move(int $pinId, int $direction): ?string
    {
        if (!$this->canManage()) {
            return 'Seul l’encadrement qui publie pour toute l’organisation ordonne les éléments épinglés.';
        }
        $tenant = $this->viewer->tenantId();
        $ids = array_map(static fn (array $r): int => $r['id'], $this->store->all($tenant));
        if (!in_array($pinId, $ids, true)) {
            return 'Épingle introuvable.';
        }
        $this->store->setOrder($tenant, JnetPinRules::swap($ids, $pinId, $direction));

        return null;
    }

    /**
     * Applique un ordre complet (glisser-déposer) : il doit reprendre exactement les épingles existantes.
     *
     * @param list<mixed> $wanted
     */
    public function reorder(array $wanted): ?string
    {
        if (!$this->canManage()) {
            return 'Seul l’encadrement qui publie pour toute l’organisation ordonne les éléments épinglés.';
        }
        $tenant = $this->viewer->tenantId();
        $ids = array_map(static fn (array $r): int => $r['id'], $this->store->all($tenant));
        $next = JnetPinRules::reorder($ids, $wanted);
        if ($next === null) {
            return 'Ordre refusé : la liste des épingles a changé, rechargez la page.';
        }
        $this->store->setOrder($tenant, $next);

        return null;
    }
}
