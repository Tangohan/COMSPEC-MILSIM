<?php

declare(strict_types=1);

namespace App\Services\Sse\Links;

/**
 * Un adaptateur par type d'élément : il sait dire ce qu'est l'élément (référence, titre, résumé, adresse, date) et ce qui
 * décide de sa confidentialité. Il ne regarde jamais les droits du lecteur (c'est le rôle de SseLinkVisibility) et ne lit jamais
 * la communauté ailleurs que dans son paramètre : toute requête est bornée à `tenant_id`.
 *
 * Un nouvel élément du portail se branche en une classe qui implémente cette interface, enregistrée dans
 * SseLinkAdapters::register().
 */
interface SseLinkAdapter
{
    /** Clé du type (SseLinkCatalog::TYPES). */
    public function type(): string;

    /**
     * Les éléments demandés, en UNE requête.
     *
     * Chaque entrée : ref, title, summary, href (relatif au site, sans domaine), date, et les marques de confidentialité :
     * secret (identité de couverture, d'emprunt ou falsifiée), member (compte du membre dont l'identité est en jeu),
     * classification (niveau d'un dossier ou document), interest (true : diffusion restreinte par dossier d'intérêt).
     *
     * @param list<string> $ids
     * @return array<string, array<string, mixed>> identifiant => description
     */
    public function describe(int $tenantId, array $ids): array;

    /**
     * Recherche par référence ou nom (sélecteur « Ajouter un lien » et « Citer une entité SSE »).
     *
     * @return list<array{id: string, ref: string, title: string, summary: string}>
     */
    public function search(int $tenantId, string $query, int $limit = 8): array;
}
