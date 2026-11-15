<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Échelle de classification du bureau SSE, en niveaux américains (jeu de rôle : toutes les mentions sont FICTIVES).
 *
 * Les CODES stables de l'échelle (stockés en base : classification des dossiers, niveau de la fiche opérateur, règles d'accès,
 * permissions atak.sse.clearance.*) ne changent pas. Seul l'affichage change, par cette table de correspondance :
 *
 *   code stocké      rang  niveau américain                           marquage de bannière
 *   interne           0    CUI  (Controlled Unclassified Information)  CUI
 *   encadrement       1    C    (Confidential)                         CONFIDENTIAL
 *   confidentiel      2    S    (Secret)                               SECRET//NOFORN
 *   tres_restreint    3    TS   (Top Secret)                           TOP SECRET//SCI//NOFORN
 *
 * Même ordre de sensibilité, mêmes droits : « interne » est le plancher de l'échelle, il correspond à CUI (information
 * sensible mais non classifiée). UNCLASSIFIED n'est pas un cran de l'échelle d'accès : c'est le marquage d'un document sans
 * restriction (publication de jeu), disponible pour les bandeaux seulement (UNCLASSIFIED).
 */
final class SseUsClassification
{
    /** Marquage d'un document non restreint (hors échelle d'accès). */
    public const UNCLASSIFIED = 'unclassified';

    /**
     * code stable => abréviation, libellés FR/EN, marquage de bannière, couleur conventionnelle du bandeau (RGB).
     *
     * @var array<string, array{abbr: string, fr: string, en: string, banner: string, portion: string, rgb: array{0: int, 1: int, 2: int}, caveats: string}>
     */
    public const SCALE = [
        'interne' => [
            'abbr' => 'CUI',
            'fr' => 'Information non classifiée contrôlée (CUI)',
            'en' => 'Controlled Unclassified Information (CUI)',
            'banner' => 'CUI',
            'portion' => 'CUI',
            'rgb' => [80, 40, 120],
            'caveats' => 'CUI',
        ],
        'encadrement' => [
            'abbr' => 'C',
            'fr' => 'Confidentiel (C)',
            'en' => 'Confidential (C)',
            'banner' => 'CONFIDENTIAL',
            'portion' => 'C',
            'rgb' => [0, 51, 160],
            'caveats' => 'CONFIDENTIAL',
        ],
        'confidentiel' => [
            'abbr' => 'S',
            'fr' => 'Secret (S)',
            'en' => 'Secret (S)',
            'banner' => 'SECRET//NOFORN',
            'portion' => 'S//NF',
            'rgb' => [200, 16, 46],
            'caveats' => 'SECRET//NOFORN',
        ],
        'tres_restreint' => [
            'abbr' => 'TS',
            'fr' => 'Très secret (TS)',
            'en' => 'Top Secret (TS)',
            'banner' => 'TOP SECRET//SCI//NOFORN',
            'portion' => 'TS//SI//NF',
            'rgb' => [255, 103, 31],
            'caveats' => 'TOP SECRET//SCI//NOFORN',
        ],
    ];

    /** Mentions de diffusion courantes (fictives), pour le pied de page et le bloc de diffusion. */
    public const CAVEATS = ['NOFORN', 'REL TO USA', 'ORCON', 'SCI', 'SAP'];

    /** Anciens libellés (avant les niveaux américains) : code => libellé, pour la lecture de données ou de textes anciens. */
    public const LEGACY_LABELS = [
        'interne' => 'Diffusion interne',
        'encadrement' => 'Encadrement',
        'confidentiel' => 'Confidentiel',
        'tres_restreint' => 'Diffusion très restreinte',
    ];

    /** @return list<string> codes, du plus large au plus restreint */
    public static function codes(): array
    {
        return array_keys(self::SCALE);
    }

    public static function normalize(?string $code): string
    {
        $c = strtolower(trim((string) $code));

        return isset(self::SCALE[$c]) ? $c : 'encadrement';
    }

    public static function label(string $code, string $lang = 'fr'): string
    {
        $s = self::SCALE[self::normalize($code)];

        return $lang === 'en' ? $s['en'] : $s['fr'];
    }

    public static function abbr(string $code): string
    {
        return self::SCALE[self::normalize($code)]['abbr'];
    }

    /** Marquage de bannière, p. ex. « SECRET//NOFORN ». */
    public static function banner(string $code): string
    {
        return self::SCALE[self::normalize($code)]['banner'];
    }

    /** Marquage de portion (paragraphe), p. ex. « S//NF ». */
    public static function portion(string $code): string
    {
        return self::SCALE[self::normalize($code)]['portion'];
    }

    /** Mentions de diffusion du niveau (fictives). @return list<string> */
    public static function dissemination(string $code): array
    {
        return match (self::normalize($code)) {
            'interne' => ['CUI', 'FEDCON'],
            'encadrement' => ['REL TO USA, SOF'],
            'confidentiel' => ['NOFORN', 'ORCON'],
            default => ['NOFORN', 'ORCON', 'SCI'],
        };
    }

    /** @return array{0: int, 1: int, 2: int} */
    public static function rgb(string $code): array
    {
        return self::SCALE[self::normalize($code)]['rgb'];
    }

    /** Bandeau du portail : « SECRET//NOFORN · Accès réservé au personnel habilité · Journalisation active ». */
    public static function portalLine(string $code, string $lang = 'fr'): string
    {
        $tail = $lang === 'en' ? 'Access restricted to cleared personnel · Logging active' : 'Accès réservé au personnel habilité · Journalisation active';

        return self::banner($code) . ' · ' . $tail;
    }

    /** Couleur du bandeau d'un marquage « UNCLASSIFIED » (vert conventionnel). @return array{0: int, 1: int, 2: int} */
    public static function unclassifiedRgb(): array
    {
        return [0, 122, 51];
    }
}
