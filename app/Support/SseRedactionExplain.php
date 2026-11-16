<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\Sse\SseRedactionService;
use App\Services\Sse\SseSheetFactsService;

/**
 * Explication d'un caviardage de fiche PDF : QUELLE catégorie cache chaque barre, à quel niveau elle redevient lisible, combien de
 * passages sont masqués. Ne porte jamais la valeur masquée ni sa longueur : seulement des catégories, des niveaux et des nombres.
 *
 * ## Étiquette de catégorie dans les données
 *
 * Une barre masquée est une suite de blocs pleins (« █ »). Depuis cette version, la LONGUEUR de la suite écrite dans les données
 * désigne la catégorie (TAG) : un bloc = identité, deux = lieu, trois = biométrie, cinq = source, six = horodatage. SseRedactionService::bar()
 * ne produit que des multiples de quatre : une suite d'un autre multiple vient donc d'un document émis avec cette étiquette, et une
 * suite de quatre ou plus (multiple de quatre) d'un document plus ancien, dont la catégorie n'est pas connue pour un texte libre
 * (elle se déduit du champ pour les données). La largeur dessinée dans le PDF reste fixe : elle ne dépend d'aucune longueur.
 */
final class SseRedactionExplain
{
    public const RUN = "\u{2588}";

    /** Longueur de la suite de blocs par catégorie (jamais un multiple de quatre). */
    public const TAG = ['identite' => 1, 'lieu' => 2, 'biometrie' => 3, 'source' => 5, 'horodatage' => 6];

    /** Libellés anglais des catégories (les français sont ceux de SseRedactionService). */
    private const LABEL_EN = ['identite' => 'Identity', 'lieu' => 'Location', 'biometrie' => 'Biometrics', 'source' => 'Source', 'horodatage' => 'Timestamps'];

    /** Aide courte par catégorie : [français, anglais]. */
    private const HELP = [
        'identite' => ['nom, prénom, alias, naissance, nationalité, pièce, personnes liées', 'name, aliases, birth data, nationality, ID document, linked persons'],
        'lieu' => ['lieux, adresses, zone, références de grille', 'places, addresses, area, grid references'],
        'biometrie' => ['empreintes, iris, ADN, niveau d’identité', 'fingerprints, iris, DNA, identity tier'],
        'source' => ['opérateur ou contrôleur à l’origine de la donnée', 'operator or checker who collected the data'],
        'horodatage' => ['dates et heures précises de recueil', 'precise collection dates and times'],
    ];

    /** Barre de caviardage d'une catégorie : la suite de blocs qui la désigne dans les données. */
    public static function bar(string $category): string
    {
        return str_repeat(self::RUN, self::TAG[$category] ?? 4);
    }

    /** Catégorie désignée par une suite de blocs : clé, '' (suite d'un ancien document, catégorie inconnue). */
    public static function categoryOfRun(string $run): string
    {
        $n = mb_strlen($run, 'UTF-8');

        return (string) (array_search($n, self::TAG, true) ?: '');
    }

    /** Libellé d'une catégorie dans la langue du document. */
    public static function label(string $category, string $lang): string
    {
        if (!isset(SseRedactionService::CATEGORIES[$category])) {
            return $lang === 'en' ? 'Other data' : 'Autres données';
        }

        return $lang === 'en' ? self::LABEL_EN[$category] : SseRedactionService::categoryLabel($category);
    }

    public static function help(string $category, string $lang): string
    {
        return self::HELP[$category][$lang === 'en' ? 1 : 0] ?? '';
    }

    /**
     * Abréviation du niveau qui rend la catégorie lisible : « S ».
     *
     * @param array<string, string>|null $required
     */
    public static function need(string $category, ?array $required = null): string
    {
        return SseUsClassification::abbr(self::needCode($category, $required));
    }

    /**
     * Code du niveau minimal d'une catégorie (réglé pour le document si `$required` est donné).
     *
     * @param array<string, string>|null $required
     */
    public static function needCode(string $category, ?array $required = null): string
    {
        return SseRedactionService::requiredLevel($category, $required);
    }

    /**
     * Lettres de repère des catégories présentes, dans l'ordre fixe des catégories : a, b, c… ; '?' pour une suite sans catégorie.
     *
     * @param list<string> $present
     * @return array<string, string>
     */
    public static function letters(array $present): array
    {
        $out = [];
        $i = 0;
        foreach (array_keys(SseRedactionService::CATEGORIES) as $cat) {
            if (in_array($cat, $present, true)) {
                $out[$cat] = chr(97 + $i++);
            }
        }
        if (in_array('', $present, true)) {
            $out[''] = '?';
        }

        return $out;
    }

    /**
     * Suites de caviardage d'un texte, par catégorie ('' si inconnue).
     *
     * @return array<string, int>
     */
    public static function scanText(string $text): array
    {
        $out = [];
        if (preg_match_all('/' . self::RUN . '+/u', $text, $m) > 0) {
            foreach ($m[0] as $run) {
                $c = self::categoryOfRun($run);
                $out[$c] = ($out[$c] ?? 0) + 1;
            }
        }

        return $out;
    }

    /**
     * Suites de caviardage d'un ensemble de données (récursif sur les textes), par catégorie.
     *
     * @param array<mixed> $data
     * @return array<string, int>
     */
    public static function scanData(array $data): array
    {
        $out = [];
        array_walk_recursive($data, static function (mixed $v) use (&$out): void {
            if (is_string($v) && str_contains($v, self::RUN)) {
                foreach (self::scanText($v) as $c => $n) {
                    $out[$c] = ($out[$c] ?? 0) + $n;
                }
            }
        });

        return $out;
    }

    /**
     * Étiquette les barres des données d'un document émis AVANT cette version (suites de quatre blocs et plus, sans catégorie) :
     * la catégorie se déduit du champ. Les données déjà étiquetées et les textes libres ne sont pas modifiés.
     *
     * @param array<string, mixed> $facts
     * @return array<string, mixed>
     */
    public static function retag(array $facts): array
    {
        $tag = static function (mixed $v, string $category): mixed {
            if (!is_string($v) || !str_contains($v, self::RUN)) {
                return $v;
            }

            return (string) preg_replace_callback('/' . self::RUN . '+/u', static function (array $m) use ($category): string {
                $n = mb_strlen($m[0], 'UTF-8');

                return $n >= 4 && $n % 4 === 0 ? self::bar($category) : $m[0];
            }, $v);
        };
        foreach (SseSheetFactsService::SCALAR_CATEGORY as $field => $category) {
            if (isset($facts[$field])) {
                $facts[$field] = $tag($facts[$field], $category);
            }
        }
        if (is_array($facts['aliases'] ?? null)) {
            $facts['aliases'] = array_map(static fn ($v) => $tag($v, 'identite'), $facts['aliases']);
        }
        if (is_array($facts['bio'] ?? null)) {
            foreach (['prints', 'iris', 'dna'] as $k) {
                if (isset($facts['bio'][$k])) {
                    $facts['bio'][$k] = $tag($facts['bio'][$k], 'biometrie');
                }
            }
        }
        foreach (SseSheetFactsService::LINK_CATEGORY as $kind => $category) {
            if (is_array($facts['links'][$kind] ?? null)) {
                $facts['links'][$kind] = array_map(static fn ($v) => $tag($v, $category), $facts['links'][$kind]);
            }
        }
        foreach ((array) ($facts['checks'] ?? []) as $i => $c) {
            foreach (SseSheetFactsService::CHECK_CATEGORY as $field => $category) {
                if (is_array($c) && isset($c[$field])) {
                    $facts['checks'][$i][$field] = $tag($c[$field], $category);
                }
            }
        }

        return $facts;
    }

    /**
     * Ce que l'explication d'un document dit, sans aucune valeur : total, passages par catégorie, niveau de diffusion, niveau requis
     * par catégorie. Un document récent apporte le décompte exact du PDF (`redaction` du snapshot) ; un ancien est compté sur ses
     * données et textes imprimés (même résultat à une barre près : des champs non dessinés peuvent y figurer).
     *
     * @param array<string, mixed> $issue ligne de SseSheetRepository (classification, snapshot)
     * @return array{total: int, by: array<string, int>, level: string, exact: bool}
     */
    public static function forIssue(array $issue): array
    {
        $snap = is_array($issue['snapshot'] ?? null) ? $issue['snapshot'] : [];
        $level = SseUsClassification::normalize((string) ($issue['classification'] ?? ''));
        $stored = $snap['redaction'] ?? null;
        if (is_array($stored) && is_array($stored['by'] ?? null)) {
            $by = [];
            foreach ($stored['by'] as $c => $n) {
                if ((int) $n > 0) {
                    $by[(string) $c] = (int) $n;
                }
            }

            return ['total' => array_sum($by), 'by' => $by, 'level' => $level, 'exact' => true];
        }
        $facts = is_array($snap['data']['facts'] ?? null) ? self::retag($snap['data']['facts']) : [];
        $by = self::scanData($facts);
        foreach (self::scanData((array) ($issue['texts'] ?? [])) as $c => $n) {
            $by[$c] = ($by[$c] ?? 0) + $n;
        }
        ksort($by);

        return ['total' => array_sum($by), 'by' => $by, 'level' => $level, 'exact' => false];
    }

    /**
     * Données d'une personne par catégorie : combien de champs renseignés seraient masqués à un niveau inférieur.
     *
     * @param array<string, mixed> $raw SseSheetFactsService::raw()
     * @return array<string, int>
     */
    public static function fieldCounts(array $raw): array
    {
        $out = array_fill_keys(array_keys(SseRedactionService::CATEGORIES), 0);
        $has = static fn (mixed $v): bool => is_string($v) && trim($v) !== '';
        foreach (SseSheetFactsService::SCALAR_CATEGORY as $field => $category) {
            if ($field !== 'display' && $has($raw[$field] ?? null)) {
                ++$out[$category];
            }
        }
        $out['identite'] += count(array_filter((array) ($raw['aliases'] ?? []), $has));
        foreach (SseSheetFactsService::LINK_CATEGORY as $kind => $category) {
            $out[$category] += count(array_filter((array) ($raw['links'][$kind] ?? []), $has));
        }
        $bio = (array) ($raw['bio'] ?? []);
        foreach (['prints', 'iris', 'dna'] as $k) {
            if ($has($bio[$k] ?? null)) {
                ++$out['biometrie'];
            }
        }
        foreach ((array) ($raw['checks'] ?? []) as $c) {
            foreach (SseSheetFactsService::CHECK_CATEGORY as $field => $category) {
                if (is_array($c) && $has($c[$field] ?? null)) {
                    ++$out[$category];
                }
            }
        }

        return $out;
    }

    /**
     * Aperçu avant émission : pour chaque niveau de l'échelle, catégories masquées et lisibles (avec le nombre de champs de la
     * personne concernés), et niveau des données les plus sensibles de la fiche.
     *
     * @param array<string, mixed> $raw
     * @param list<string> $levels niveaux proposés à l'opérateur
     * @param array<string, string>|null $required niveaux requis réglés pour le document (SseSheetVisibility), sinon ceux livrés
     * @return array{counts: array<string, int>, top: string, levels: array<string, array{hidden: list<string>, visible: list<string>, hidden_fields: int}>}
     */
    public static function preview(array $raw, array $levels, ?array $required = null): array
    {
        $counts = self::fieldCounts($raw);
        $top = 'interne';
        foreach ($counts as $cat => $n) {
            if ($n > 0 && SseRedactionService::levelRank(self::needCode($cat, $required)) > SseRedactionService::levelRank($top)) {
                $top = self::needCode($cat, $required);
            }
        }
        $out = [];
        foreach ($levels as $level) {
            $hidden = [];
            $visible = [];
            $fields = 0;
            foreach ($counts as $cat => $n) {
                if (SseRedactionService::visibleAt($cat, $level, $required)) {
                    $visible[] = $cat;
                } else {
                    $hidden[] = $cat;
                    $fields += $n;
                }
            }
            $out[$level] = ['hidden' => $hidden, 'visible' => $visible, 'hidden_fields' => $fields];
        }

        return ['counts' => $counts, 'top' => $top, 'levels' => $out];
    }
}
