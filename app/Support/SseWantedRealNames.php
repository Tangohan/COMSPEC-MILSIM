<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Garde-fou de la notice de recherche : l'autorité émettrice et le service demandeur sont FICTIFS. Un nom d'organisation
 * policière, judiciaire ou gouvernementale réelle est refusé à la saisie des réglages (le gabarit du PDF, lui, n'écrit
 * aucun nom ni emblème réel). La liste n'est pas exhaustive : elle écarte les erreurs courantes, la règle reste celle du
 * jeu de rôle (personnages et organisations imaginaires).
 */
final class SseWantedRealNames
{
    /** Noms (ou sigles) refusés, comparés sans accent ni casse, en mot entier. */
    private const FORBIDDEN = [
        'interpol', 'europol', 'afripol', 'ameripol', 'aseanapol', 'fbi', 'cia', 'nsa', 'atf', 'dhs',
        'us marshals', 'marshals service', 'homeland security', 'secret service', 'scotland yard', 'metropolitan police',
        'nypd', 'lapd', 'gendarmerie nationale', 'police nationale', 'police judiciaire', 'dgse', 'dgsi', 'bundeskriminalamt',
        'bka', 'mi5', 'mi6', 'mossad', 'fsb', 'kgb', 'united nations', 'nations unies', 'onu', 'nato', 'otan',
        // Agences, commandements et unités américains ou alliés réels (forces spéciales, renseignement, justice, frontières).
        'dia', 'jsoc', 'dea', 'ncis', 'afosi', 'army cid', 'nctc', 'odni', 'dni', 'nga', 'nro', 'dtra', 'socom', 'centcom', 'africom',
        'eucom', 'southcom', 'northcom', 'indopacom', 'sofcom', 'devgru', 'delta force', 'navy seals', 'seal team', 'green berets',
        'special forces group', 'ranger regiment', 'marsoc', 'afsoc', 'arsof', 'usss', 'usms', 'dod', 'pentagon', 'department of defense',
        'department of state', 'state department', 'department of justice', 'department of the treasury', 'ofac', 'fincen', 'irs', 'cbp',
        'uscis', 'immigration and customs', 'border patrol', 'coast guard', 'tsa', 'swat', 'gign', 'gsg 9', 'gchq', 'asis',
        'asio', 'csis', 'rcmp', 'bnd', 'aivd', 'svr', 'gru', 'shin bet', 'special air service', 'special boat service',
        'red cross', 'croix rouge', 'icrc', 'unhcr', 'unicef', 'world bank', 'banque mondiale', 'imf', 'osce', 'unodc', 'wfp', 'oms',
        'nsc', 'cisa', 'eurojust', 'frontex', 'dgfip',
    ];

    /** Premier nom réel trouvé dans le texte, ou null. */
    public static function find(string $text): ?string
    {
        $norm = self::normalize($text);
        if ($norm === '') {
            return null;
        }
        foreach (self::FORBIDDEN as $name) {
            if (preg_match('/(?<![a-z0-9])' . preg_quote($name, '/') . '(?![a-z0-9])/', $norm) === 1) {
                return $name;
            }
        }

        return null;
    }

    /**
     * Les noms refusés, pour les tests qui vérifient que le gabarit n'en cite aucun.
     *
     * @return list<string>
     */
    public static function forbidden(): array
    {
        return self::FORBIDDEN;
    }

    private static function normalize(string $text): string
    {
        $s = strtr(mb_strtolower($text, 'UTF-8'), [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ç' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'í' => 'i', 'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ú' => 'u', 'ÿ' => 'y', 'œ' => 'oe', 'æ' => 'ae', 'ß' => 'ss',
        ]);

        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $s));
    }
}
