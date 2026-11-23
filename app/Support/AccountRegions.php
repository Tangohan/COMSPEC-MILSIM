<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Catalogue fermé des régions qu'un compte peut déclarer : régions de France métropolitaine, outre-mer, pays
 * proches ou francophones, et « Autre / hors France ». Seul le code est stocké (user_regions.region_code).
 *
 * Volontairement grossier : une région suffit aux statistiques agrégées et ne localise pas la personne.
 */
final class AccountRegions
{
    public const OTHER = 'XX';

    /** Effectif minimal d'une région pour apparaître dans les statistiques d'administration. */
    public const PRIVACY_THRESHOLD = 5;

    /** @return array<string, array<string, string>> groupe => [code => libellé] */
    public static function groups(): array
    {
        return [
            'France métropolitaine' => [
                'FR-ARA' => 'Auvergne-Rhône-Alpes',
                'FR-BFC' => 'Bourgogne-Franche-Comté',
                'FR-BRE' => 'Bretagne',
                'FR-CVL' => 'Centre-Val de Loire',
                'FR-COR' => 'Corse',
                'FR-GES' => 'Grand Est',
                'FR-HDF' => 'Hauts-de-France',
                'FR-IDF' => 'Île-de-France',
                'FR-NOR' => 'Normandie',
                'FR-NAQ' => 'Nouvelle-Aquitaine',
                'FR-OCC' => 'Occitanie',
                'FR-PDL' => 'Pays de la Loire',
                'FR-PAC' => 'Provence-Alpes-Côte d’Azur',
            ],
            'Outre-mer' => [
                'FR-GP' => 'Guadeloupe',
                'FR-MQ' => 'Martinique',
                'FR-GF' => 'Guyane',
                'FR-RE' => 'La Réunion',
                'FR-YT' => 'Mayotte',
                'FR-COM' => 'Autres collectivités d’outre-mer',
            ],
            'Pays proches' => [
                'BE' => 'Belgique',
                'CH' => 'Suisse',
                'LU' => 'Luxembourg',
                'DE' => 'Allemagne',
                'ES' => 'Espagne',
                'IT' => 'Italie',
                'GB' => 'Royaume-Uni',
                'CA' => 'Canada',
            ],
            'Autre' => [
                self::OTHER => 'Autre / hors France',
            ],
        ];
    }

    /** @return array<string, string> code => libellé */
    public static function all(): array
    {
        $out = [];
        foreach (self::groups() as $items) {
            $out += $items;
        }

        return $out;
    }

    public static function isValid(string $code): bool
    {
        return isset(self::all()[$code]);
    }

    public static function label(string $code): string
    {
        return self::all()[$code] ?? '';
    }

    /**
     * Applique le seuil de confidentialité. Calcul pur.
     *
     * Les régions de moins de PRIVACY_THRESHOLD comptes sont masquées. Leur somme n'est montrée (« Autres régions »)
     * que si elle atteint elle-même le seuil ; sinon elle est rangée avec les comptes sans région, pour qu'aucune
     * soustraction ne permette de retrouver un petit effectif.
     *
     * @param array<string, int> $counts code => nombre de comptes ayant déclaré cette région
     * @return array{rows: list<array{code: string, label: string, count: int}>, others: int, none: int}
     */
    public static function applyThreshold(array $counts, int $totalAccounts): array
    {
        $rows = [];
        $masked = 0;
        $declared = 0;
        foreach ($counts as $code => $n) {
            $n = (int) $n;
            if ($n < 1 || !self::isValid((string) $code)) {
                continue;
            }
            $declared += $n;
            if ($n >= self::PRIVACY_THRESHOLD) {
                $rows[] = ['code' => (string) $code, 'label' => self::label((string) $code), 'count' => $n];
            } else {
                $masked += $n;
            }
        }
        usort($rows, static fn (array $a, array $b): int => $b['count'] <=> $a['count'] ?: strcmp($a['label'], $b['label']));
        $others = $masked >= self::PRIVACY_THRESHOLD ? $masked : 0;
        $none = max(0, $totalAccounts - $declared) + ($others === 0 ? $masked : 0);

        return ['rows' => $rows, 'others' => $others, 'none' => $none];
    }
}
