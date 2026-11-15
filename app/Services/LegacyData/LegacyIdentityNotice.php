<?php

declare(strict_types=1);

namespace App\Services\LegacyData;

/**
 * Avis « N membres n'ont pas d'état civil renseigné : leur nom s'affiche sous l'indicatif ».
 *
 * Ne touche PAS à la règle d'affichage du nom (états civil, indicatif, compte, « Membre » : elle appartient à l'unification
 * des identités) : il détecte seulement les membres que cette règle ne peut pas nommer par leur état civil, pour que les
 * responsables le sachent au lieu de voir un nom « changer » sans explication. Lecture seule, chiffre mis en cache avec
 * l'avis général (LegacyDataNotice).
 */
final class LegacyIdentityNotice
{
    /** Nombre de membres actifs sans prénom ni nom d'état civil (0 si la base n'a pas la table des états civils). */
    public static function countWithoutCivilName(int $tenantId, ?LegacyDataCounter $counter = null): int
    {
        return (int) (LegacyDataNotice::counts($tenantId, $counter)['no_civil_name'] ?? 0);
    }

    public static function message(int $count, string $lang = 'fr'): string
    {
        if ($count < 1) {
            return '';
        }
        if ($lang === 'en') {
            return $count === 1
                ? '1 member has no civil identity filled in (no first or last name): their name is shown under their callsign.'
                : $count . ' members have no civil identity filled in (no first or last name): their name is shown under their callsign.';
        }

        return $count === 1
            ? '1 membre n’a pas d’état civil renseigné (ni prénom ni nom) : son nom s’affiche sous son indicatif.'
            : $count . ' membres n’ont pas d’état civil renseigné (ni prénom ni nom) : leur nom s’affiche sous leur indicatif.';
    }
}
