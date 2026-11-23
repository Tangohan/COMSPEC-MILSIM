<?php

declare(strict_types=1);

namespace App\Services\Tactical;

/**
 * Nom libre et référence automatique d'un brief air (9-LINE, 5-LINE, MEDEVAC) : pilote et JTAC se les annoncent à la radio.
 *
 * - Référence : « CAS-23-04 » (jour du mois sur deux chiffres, numéro du jour), « MED-… » pour une évacuation sanitaire.
 *   Numéro suivant celui du registre atak_brief_refs (jamais purgé) ; l'unicité du registre départage deux créations
 *   simultanées (la perdante prend le numéro suivant) et empêche qu'une référence resserve, même après suppression du brief
 *   ou au même jour du mois suivant (le numéro avance alors jusqu'au premier libre).
 * - Nom : 40 caractères au plus, sans balise ni barre verticale, jamais confondu avec une référence ou un identifiant.
 */
final class BriefReference
{
    public const NAME_MAX = 40;

    /** Briefs clos : le nom ne se change plus. */
    public const CLOSED_STATUSES = ['COMPLETE', 'ABORTED', 'CANCELLED'];

    /** Tentatives avant d'abandonner (le brief existe alors sans référence). */
    private const TRIES = 60;

    public static function prefix(string $kind): string
    {
        return strtolower($kind) === 'medevac' ? 'MED' : 'CAS';
    }

    public static function format(string $kind, \DateTimeInterface $day, int $seq): string
    {
        return sprintf('%s-%s-%02d', self::prefix($kind), $day->format('d'), max(1, $seq));
    }

    /** Référence canonique d'un texte dit ou tapé (« cas 23 4 », « CAS-23-04 », « med.23.12 »), sinon null. */
    public static function parse(mixed $raw): ?string
    {
        $t = strtoupper(trim(is_scalar($raw) ? (string) $raw : ''));
        if (preg_match('/^(CAS|MED)[\s._-]*(\d{2})[\s._-]*(\d{1,4})$/', $t, $m) !== 1
            && preg_match('/^(CAS|MED)[\s._-]+(\d{1,2})[\s._-]+(\d{1,4})$/', $t, $m) !== 1) {
            return null;
        }

        return sprintf('%s-%02d-%02d', $m[1], (int) $m[2], (int) $m[3]);
    }

    /** Nom nettoyé : caractères de contrôle, balises, « | » et « \ » retirés, espaces réduits, 40 caractères. */
    public static function cleanName(mixed $raw): string
    {
        $t = is_scalar($raw) ? (string) $raw : '';
        $t = (string) preg_replace('/[\x00-\x1F\x7F<>|\\\\]+/u', ' ', $t);
        $t = trim((string) preg_replace('/\s+/u', ' ', $t));

        return trim(mb_substr($t, 0, self::NAME_MAX));
    }

    /** Raison du refus d'un nom (déjà nettoyé), ou null : un nom ne doit pas se faire passer pour une référence ou un numéro. */
    public static function nameError(string $name): ?string
    {
        if ($name === '') {
            return null;
        }
        if (preg_match('/^A?\d+$/i', $name) === 1 || self::parse($name) !== null) {
            return 'Ce nom ressemble à une référence ou à un numéro : choisissez-en un autre.';
        }

        return null;
    }

    /**
     * Attribue la référence suivante.
     *
     * @param \Closure(): int $lastSeq plus grand numéro déjà attribué ce jour pour la communauté (0 si aucun)
     * @param \Closure(string, int): bool $reserve inscrit (référence, numéro) au registre ; false si déjà pris
     */
    public static function allocate(string $kind, \DateTimeInterface $day, \Closure $lastSeq, \Closure $reserve): ?string
    {
        $seq = $lastSeq() + 1;
        for ($i = 0; $i < self::TRIES; $i++, $seq++) {
            $ref = self::format($kind, $day, $seq);
            if ($reserve($ref, $seq)) {
                return $ref;
            }
        }

        return null;
    }

    public static function isClosed(array $row): bool
    {
        return in_array(strtoupper((string) ($row['status'] ?? '')), self::CLOSED_STATUSES, true);
    }
}
