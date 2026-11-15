<?php

declare(strict_types=1);

namespace App\Services\DataQuality;

/**
 * Lectures de la base dont les tableaux de la qualité des données ont besoin. Une seule requête par jeu de données,
 * toujours bornée à la communauté (tenant) : jamais de lecture par membre.
 */
interface DataQualitySource
{
    /**
     * Membres actifs de la communauté (comptes techniques exclus) avec les valeurs brutes des champs de la fiche.
     *
     * @return list<array<string, mixed>>
     */
    public function memberRows(int $tenantId, int $limit): array;

    /**
     * Qualifications aux dates impossibles, par membre.
     *
     * @return array<int, array{bad_range: int, future: int}>
     */
    public function qualificationDateIssues(int $tenantId): array;

    /**
     * Dernière confirmation de fiche (toutes campagnes) : user_id => date.
     *
     * @return array<int, string>
     */
    public function lastConfirmations(int $tenantId): array;

    /**
     * Unités de la communauté : id => nom.
     *
     * @return array<int, string>
     */
    public function unitNames(int $tenantId): array;

    /**
     * Campagne de fiabilisation à suivre (ouverte, sinon la plus récente) et ses membres, ou null s'il n'y en a pas.
     *
     * @return array{campaign: array<string, mixed>, members: list<array<string, mixed>>}|null
     */
    public function campaign(int $tenantId): ?array;
}
