<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\SseInterestCaseLinkRepository;

/**
 * Point d'accès simple aux liens d'un dossier d'intérêt, pour le widget de liaisons (branche feat/sse-liaisons-chemin) : une
 * ligne par élément lié, sans titre ni droit résolus. Le widget doit revérifier le droit du lecteur sur chaque cible avant de
 * l'afficher (SseInterestCaseService::presentLinks le fait pour la fiche du dossier).
 *
 * Ligne d'inclusion à ajouter côté liaisons : `$rows = \App\Services\Sse\SseCaseLinks::forInterestCase($tenantId, $caseId);`
 */
final class SseCaseLinks
{
    /** @return list<array{type: string, id: string, label: string}> */
    public static function forInterestCase(int $tenantId, int $caseId): array
    {
        $out = [];
        foreach ((new SseInterestCaseLinkRepository())->listForCase($tenantId, $caseId) as $row) {
            $out[] = ['type' => (string) $row['link_type'], 'id' => (string) $row['target_id'], 'label' => (string) $row['label']];
        }

        return $out;
    }
}
