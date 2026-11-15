<?php

declare(strict_types=1);

namespace App\Services\Sse;

/**
 * Retrouve ce qu'un dossier d'intérêt peut lier (personne, identité, site, transmission, fiche, document, dossier) et le décrit
 * pour le lecteur courant : titre, repère, adresse, et un drapeau « masqué » quand il n'a pas le droit de voir l'élément.
 */
interface SseInterestLinkResolver
{
    /**
     * Recherche dans la communauté de l'acteur, limitée à ce qu'il peut lire.
     *
     * @return list<array{id: string, label: string, hint: string}>
     */
    public function search(SseInterestActor $actor, string $type, string $query, int $limit = 8): array;

    /**
     * @return array{label: string, hint: string, href: ?string, masked: bool}|null null : la cible n'existe pas (ou plus) dans la communauté
     */
    public function describe(SseInterestActor $actor, string $type, string $id): ?array;
}
