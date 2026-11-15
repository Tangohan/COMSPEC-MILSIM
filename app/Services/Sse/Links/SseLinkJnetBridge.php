<?php

declare(strict_types=1);

namespace App\Services\Sse\Links;

/**
 * Passerelle vers les comptes rendus et pages JNET qui citent un élément SSE (« cité dans : compte rendu X »). Elle ne rend
 * que ce que le lecteur a le droit de voir côté JNET ; le graphe n'en sait rien d'autre.
 */
interface SseLinkJnetBridge
{
    /**
     * @param list<string> $refs références SSE « type:identifiant »
     * @return list<array{ref: string, id: string, title: string, summary: string, href: string, date: string, by: string}> un contenu JNET visible par ligne, avec la référence SSE citée
     */
    public function citations(int $tenantId, array $refs): array;
}
