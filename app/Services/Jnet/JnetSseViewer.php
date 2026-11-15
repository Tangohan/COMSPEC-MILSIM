<?php

declare(strict_types=1);

namespace App\Services\Jnet;

/**
 * Lecteur JNET capable de résoudre un élément du portail SSE cité dans un contenu (« [[sse:person-12|IDN-00012]] »), avec ses droits
 * SSE. Séparé de JnetLinkViewer pour ne rien imposer aux autres lecteurs.
 */
interface JnetSseViewer
{
    /**
     * Chemin de liaison résumé de l'élément (une à deux lignes « CTRL-3 → IDN-00001 → … »), tel que ce lecteur le voit : un
     * maillon qu'il n'a pas le droit de lire est « Élément restreint ». Vide si l'élément lui-même est illisible.
     *
     * @return list<string>
     */
    public function ssePath(string $id): array;
}
