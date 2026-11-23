<?php

declare(strict_types=1);

namespace App\Services\Sse;

/**
 * Fabrique des identifiants SSE, sans base de données : formats, alphabet, mots des noms de code.
 * L'unicité (par communauté, jamais de réutilisation) est garantie par SseIdentifierService ; ici, uniquement la forme.
 *
 *   numéro d'identification  ID-2026-0042      (année, séquence de la communauté, au moins 4 chiffres)
 *   nom de code              FAUCON-12         (mot neutre lisible à la radio + nombre)
 *   nom générique de fiche   Fiche personne ID-2026-0042
 *   DSE (Dossier Synchronisé Eden)  DSE-K7M4QX (5 à 8 caractères, sans 0, O, 1, I)
 */
final class SseIdentifierGenerator
{
    /** 32 caractères : lettres sans I ni O, chiffres 2 à 9. */
    public const DSE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    public const DSE_PREFIX = 'DSE-';
    public const DSE_MIN = 5;
    public const DSE_MAX = 8;
    public const DSE_DEFAULT = 6;

    /**
     * Mots des noms de code : un mot courant (animal, arbre, pierre, lieu naturel), sans accent, facile à épeler à la radio,
     * sans connotation violente, sexuelle, politique ou injurieuse.
     *
     * @var list<string>
     */
    public const WORDS = [
        'FAUCON', 'HERON', 'CIGOGNE', 'MARTIN', 'PINSON', 'FAUVETTE', 'ROITELET', 'GRIVE', 'MERLE', 'ALOUETTE',
        'HIRONDELLE', 'ROSSIGNOL', 'PIVERT', 'CORMORAN', 'GOELAND', 'MOUETTE', 'PELICAN', 'FLAMANT', 'CYGNE', 'CANARD',
        'RENARD', 'LIEVRE', 'CHEVREUIL', 'DAIM', 'CERF', 'ELAN', 'BISON', 'CASTOR', 'LOUTRE', 'BLAIREAU',
        'ECUREUIL', 'HERISSON', 'MARMOTTE', 'CHAMOIS', 'BOUQUETIN', 'MOUFLON', 'ZEBRE', 'GIRAFE', 'PANDA', 'KOALA',
        'DAUPHIN', 'BALEINE', 'OTARIE', 'PHOQUE', 'TORTUE', 'SALAMANDRE', 'GRENOUILLE', 'LIBELLULE', 'PAPILLON', 'COCCINELLE',
        'CHENE', 'HETRE', 'ERABLE', 'BOULEAU', 'FRENE', 'TILLEUL', 'SAULE', 'PEUPLIER', 'CEDRE', 'SAPIN',
        'MELEZE', 'OLIVIER', 'NOYER', 'NOISETIER', 'CHATAIGNIER', 'LAURIER', 'ROMARIN', 'THYM', 'LAVANDE', 'BRUYERE',
        'LILAS', 'JASMIN', 'IRIS', 'TULIPE', 'MUGUET', 'PIVOINE', 'BLEUET', 'COQUELICOT', 'MARGUERITE', 'PRIMEVERE',
        'GRANIT', 'QUARTZ', 'BASALTE', 'CALCAIRE', 'MARBRE', 'ARDOISE', 'AMBRE', 'CORAIL', 'JADE', 'OPALE',
        'TOPAZE', 'SAPHIR', 'RUBIS', 'ONYX', 'AGATE', 'CUIVRE', 'ARGENT', 'COBALT', 'NICKEL', 'ZINC',
        'COLLINE', 'VALLON', 'PLATEAU', 'CRETE', 'FALAISE', 'DUNE', 'LAGUNE', 'ESTUAIRE', 'RIVIERE', 'CASCADE',
        'PRAIRIE', 'FORET', 'BOCAGE', 'VERGER', 'CLAIRIERE', 'SENTIER', 'GUE', 'PONT', 'PHARE', 'MOULIN',
        'ZEPHYR', 'MISTRAL', 'AURORE', 'CREPUSCULE', 'ETOILE', 'COMETE', 'ORION', 'POLARIS', 'SIRIUS', 'VEGA',
    ];

    /**
     * Fragments interdits dans un nom de code (français et anglais, sans accent, en majuscules) : la liste de mots ci-dessus
     * n'en contient aucun (test), et un nom de code saisi à la main qui en contient est refusé.
     *
     * @var list<string>
     */
    public const FORBIDDEN_FRAGMENTS = [
        'MORT', 'TUER', 'TUE', 'SANG', 'VIOL', 'NAZI', 'HITLER', 'ISIS', 'DAESH', 'JIHAD', 'TERROR', 'BOMB', 'KILL', 'DEAD',
        'DEATH', 'RAPE', 'SEX', 'PORN', 'CUL', 'PUTE', 'PUTAIN', 'MERDE', 'CON', 'SALOP', 'ENCULE', 'BATARD', 'NEGRE',
        'NIGG', 'PEDE', 'PEDO', 'FUCK', 'SHIT', 'BITCH', 'SLUT', 'CUNT', 'DICK', 'COCK', 'HEIL', 'KKK', 'SUICID', 'HAINE',
        'SATAN', 'DEMON', 'ARABE', 'JUIF', 'TRAITRE', 'GENOCID', 'MASSACR', 'TORTURE', 'ALLAH',
    ];

    /** Libellé de la fiche selon le type de donnée (nom générique). */
    public const SHEET_LABELS = [
        'person' => 'Fiche personne',
        'identity' => 'Fiche identité',
        'field_note' => 'Fiche de renseignement',
        'report' => 'Compte rendu',
        'document' => 'Fiche document',
    ];

    /** Numéro d'identification : année (4 chiffres) et séquence de la communauté, au moins 4 chiffres. */
    public static function idNumber(int $year, int $sequence): string
    {
        return sprintf('ID-%04d-%04d', $year, max(1, $sequence));
    }

    /** @return array{year: int, seq: int}|null */
    public static function parseIdNumber(string $value): ?array
    {
        if (preg_match('/^ID-(\d{4})-(\d{4,})$/', strtoupper(trim($value)), $m) !== 1) {
            return null;
        }

        return ['year' => (int) $m[1], 'seq' => (int) $m[2]];
    }

    /** Nom générique d'une fiche : « Fiche personne ID-2026-0042 ». */
    public static function genericName(string $type, string $idNumber): string
    {
        return (self::SHEET_LABELS[$type] ?? 'Fiche') . ' ' . $idNumber;
    }

    /** DSE aléatoire (cryptographique) de $length caractères utiles, préfixé « DSE- ». */
    public static function dse(int $length = self::DSE_DEFAULT): string
    {
        $length = max(self::DSE_MIN, min(self::DSE_MAX, $length));
        $max = strlen(self::DSE_ALPHABET) - 1;
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= self::DSE_ALPHABET[random_int(0, $max)];
        }

        return self::DSE_PREFIX . $out;
    }

    /** Forme canonique d'un DSE saisi (casse, espaces, préfixe facultatif) ; null s'il n'est pas valide. */
    public static function normalizeDse(string $value): ?string
    {
        $v = strtoupper(preg_replace('/[\s_]+/', '', trim($value)) ?? '');
        if (str_starts_with($v, 'DSE')) {
            $v = substr($v, 3);
        }
        $v = ltrim($v, '-');
        if (preg_match('/^[' . self::DSE_ALPHABET . ']{' . self::DSE_MIN . ',' . self::DSE_MAX . '}$/', $v) !== 1) {
            return null;
        }

        return self::DSE_PREFIX . $v;
    }

    public static function isValidDse(string $value): bool
    {
        return preg_match('/^DSE-[' . self::DSE_ALPHABET . ']{' . self::DSE_MIN . ',' . self::DSE_MAX . '}$/', $value) === 1;
    }

    /**
     * Nom de code « MOT-NN » ; $attempt (0, 1, 2…) fait grandir le nombre (2 chiffres, puis 3, puis 4) quand la communauté
     * a épuisé les combinaisons courtes.
     */
    public static function codename(int $attempt = 0): string
    {
        $digits = min(4, 2 + intdiv(max(0, $attempt), 60));
        $word = self::WORDS[random_int(0, count(self::WORDS) - 1)];
        $number = random_int(10 ** ($digits - 1), (10 ** $digits) - 1);

        return $word . '-' . $number;
    }

    /** Forme canonique d'un nom de code saisi à la main ; null s'il est invalide ou contient un fragment interdit. */
    public static function normalizeCodename(string $value): ?string
    {
        $v = mb_strtoupper(trim(preg_replace('/\s+/', ' ', $value) ?? ''), 'UTF-8');
        $v = strtr($v, ['É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'À' => 'A', 'Â' => 'A', 'Ï' => 'I', 'Î' => 'I', 'Ô' => 'O', 'Ù' => 'U', 'Û' => 'U', 'Ç' => 'C']);
        if (preg_match('/^[A-Z0-9][A-Z0-9 \-]{1,38}[A-Z0-9]$/', $v) !== 1 || self::containsForbidden($v)) {
            return null;
        }

        return $v;
    }

    public static function containsForbidden(string $text): bool
    {
        $t = strtoupper($text);
        foreach (self::FORBIDDEN_FRAGMENTS as $fragment) {
            // Fragments courts (CON, CUL, TUE, SEX) : seulement comme mot entier, pour ne pas refuser CONDOR par erreur.
            if (strlen($fragment) <= 3) {
                if (preg_match('/(^|[^A-Z])' . preg_quote($fragment, '/') . '([^A-Z]|$)/', $t) === 1) {
                    return true;
                }
                continue;
            }
            if (str_contains($t, $fragment)) {
                return true;
            }
        }

        return false;
    }
}
