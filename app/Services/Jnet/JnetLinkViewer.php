<?php

declare(strict_types=1);

namespace App\Services\Jnet;

/**
 * Ce que JnetLinkService a besoin de savoir d'UN lecteur : ce qu'il voit, où il écrit, de quels espaces il est le chef.
 *
 * Toute décision de droit passe par ici et par les règles existantes de JNET (ORBAT filtré par confidentialité,
 * JnetSpaceService::rights, mayReadIntelligence, droits du partage de fichiers) : le service des liens n'en invente aucune.
 * L'implémentation réelle est JnetLinkAccess ; les tests en fournissent une en mémoire.
 */
interface JnetLinkViewer
{
    public function tenantId(): int;

    public function userId(): int;

    /** Date du jour (AAAA-MM-JJ) : l'horloge qui décide du statut des liaisons. */
    public function today(): string;

    /**
     * Le contenu tel que CE lecteur peut le voir, ou null s'il n'existe pas ou n'est pas à sa portée (les deux cas sont
     * indiscernables). Clés : type, id, title, sub, href, group, et space (unité propriétaire d'une page ou d'une tâche).
     *
     * @return array{type: string, id: string, title: string, sub: string, href: string, group: string, space?: int}|null
     */
    public function see(string $type, string $id): ?array;

    /** Peut écrire dans les outils de l'espace (tâches, pages, commentaires). */
    public function canWriteIn(int $spaceId): bool;

    /** Chef de l'espace : même droit que « Personnaliser ». */
    public function isChief(int $spaceId): bool;

    /** Chef de l'espace ou « chef de module » désigné par lui : modère les outils de l'espace. */
    public function canModerate(int $spaceId): bool;

    /** Encadrement de l'organisation (gestion de l'ORBAT, administrateur JNET). */
    public function isOrgManager(): bool;

    /** Membre d'au moins une unité de l'ORBAT. */
    public function inOwnUnits(): bool;

    /** Libellé d'une unité si le lecteur la voit dans son arbre ORBAT, sinon null. */
    public function unitLabel(int $unitId): ?string;

    /** Les deux unités sont-elles dans une même filiation (l'une au-dessus de l'autre) ? */
    public function related(int $a, int $b): bool;

    /** Unités du lecteur. @return list<int> */
    public function ownUnitIds(): array;

    /**
     * Recherche de contenus que le lecteur voit déjà ; chaque résultat est un contenu pour lequel see() répond.
     *
     * @param list<string> $types
     * @return list<array{type: string, id: string, title: string, sub: string, href: string, group: string}>
     */
    public function search(string $q, array $types, int $spaceId): array;

    /** Libellés courts des auteurs. @param list<int> $userIds @return array<int, string> */
    public function authors(array $userIds): array;
}
