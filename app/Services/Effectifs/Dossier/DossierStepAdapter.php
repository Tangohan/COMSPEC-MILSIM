<?php

declare(strict_types=1);

namespace App\Services\Effectifs\Dossier;

/**
 * Un adaptateur lit, en lecture seule, les étapes d'un parcours depuis la table qui les porte déjà
 * (intégration, recrutement, qualifications, campagne de fiabilisation…). Il ne copie rien et n'écrit jamais.
 */
interface DossierStepAdapter
{
    /** Parcours principal servi (un adaptateur peut en servir d'autres : intégration et documents). */
    public function track(): string;

    /**
     * Étapes des membres demandés, dans cette communauté seulement.
     *
     * @param list<int> $userIds
     * @return array<int, list<array<string, mixed>>> étapes par identifiant de membre
     */
    public function stepsFor(int $tenantId, array $userIds): array;
}
