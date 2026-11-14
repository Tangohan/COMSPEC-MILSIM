<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Espace d'unité du back-office : onglets de la fiche d'unité (`?onglet=`), droits par onglet et pagination.
 *
 * Chaque onglet garde le droit de la page d'origine dont il reprend les données : aucun droit nouveau.
 *  - « sheet »     : la fiche d'unité elle-même (fiche, postes) : l'accès à la page suffit, l'écriture reste
 *                    réservée à organization.orbat.manage / admin.organization comme avant ;
 *  - « effectifs » : membres, chaîne de commandement, qualifications : accès au bureau des effectifs
 *                    (EffectifsLmsAccess::allows), le droit des pages Effectifs correspondantes.
 */
final class UnitSpaceTabs
{
    public const DEFAULT = 'fiche';

    /** Membres affichés par page dans un onglet. */
    public const PER_PAGE = 25;

    /** Plafond de membres chargés pour une unité (unité et sous-unités) : au-delà, le tableur filtré prend le relais. */
    public const MEMBER_CAP = 400;

    private const TABS = [
        'fiche' => ['label' => 'Fiche', 'needs' => 'sheet'],
        'postes' => ['label' => 'Postes', 'needs' => 'sheet'],
        'membres' => ['label' => 'Membres', 'needs' => 'effectifs'],
        'chaine' => ['label' => 'Chaîne de commandement', 'needs' => 'effectifs'],
        'qualifications' => ['label' => 'Qualifications', 'needs' => 'effectifs'],
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::TABS);
    }

    public static function isKnown(string $key): bool
    {
        return isset(self::TABS[$key]);
    }

    public static function label(string $key): string
    {
        return self::TABS[$key]['label'] ?? '';
    }

    /** L'onglet est-il ouvert à ce lecteur ? (droit de la page d'origine) */
    public static function allows(string $key, bool $effectifsAccess): bool
    {
        $needs = self::TABS[$key]['needs'] ?? null;
        if ($needs === null) {
            return false;
        }

        return $needs === 'sheet' || $effectifsAccess;
    }

    /**
     * Onglets visibles pour ce lecteur, dans l'ordre d'affichage.
     *
     * @return list<string>
     */
    public static function visible(bool $effectifsAccess): array
    {
        return array_values(array_filter(self::keys(), static fn (string $k): bool => self::allows($k, $effectifsAccess)));
    }

    /**
     * Onglet à afficher pour la valeur de `?onglet=`.
     * Valeur absente ou inconnue : la fiche. Onglet connu mais fermé à ce lecteur : la fiche, avec `denied`.
     *
     * @return array{tab: string, denied: string}
     */
    public static function resolve(string $requested, bool $effectifsAccess): array
    {
        $requested = trim($requested);
        if (!self::isKnown($requested)) {
            return ['tab' => self::DEFAULT, 'denied' => ''];
        }
        if (!self::allows($requested, $effectifsAccess)) {
            return ['tab' => self::DEFAULT, 'denied' => $requested];
        }

        return ['tab' => $requested, 'denied' => ''];
    }

    /**
     * Chemin (à passer à url()) d'un onglet de l'unité. La fiche garde son adresse historique, sans paramètre.
     */
    public static function path(int $unitId, string $tab = self::DEFAULT, int $page = 1): string
    {
        $base = 'back-office/organisation/unites/' . max(1, $unitId);
        if (!self::isKnown($tab) || $tab === self::DEFAULT) {
            return $base;
        }
        $query = ['onglet' => $tab];
        if ($page > 1) {
            $query['page'] = $page;
        }

        return $base . '?' . http_build_query($query);
    }

    /**
     * @return array{page: int, pages: int, offset: int, per: int}
     */
    public static function paginate(mixed $rawPage, int $total, int $per = self::PER_PAGE): array
    {
        $per = max(1, $per);
        $pages = max(1, (int) ceil(max(0, $total) / $per));
        $page = is_numeric($rawPage) ? (int) $rawPage : 1;
        $page = min($pages, max(1, $page));

        return ['page' => $page, 'pages' => $pages, 'offset' => ($page - 1) * $per, 'per' => $per];
    }
}
