<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Pack UI "Rubans, médailles & frise de carrière".
 * Formes et motifs génériques, inspired by official U.S. Army / NATO references
 * (see footer sources). This is NOT an official reproduction: no precise
 * decoration is depicted, no real award name is used, no government or
 * protected emblem is reproduced.
 *
 * @phpstan-type DecorationFamily 'GENERIC'|'NATO_INSPIRED'
 * @phpstan-type DecorationType 'ribbon'|'medal'
 * @phpstan-type DecorationRow array{
 *     id: string,
 *     name: string,
 *     family: DecorationFamily,
 *     type: DecorationType,
 *     level: string,
 *     colors: list<string>,
 *     pattern: string,
 *     patternClass: string,
 *     dropClass: string,
 *     discClass: string,
 *     glyph: string,
 *     description: string,
 *     referenceUrl: string,
 *     isOfficialReference: false,
 *     sort: int,
 *     aliases: list<string>,
 *     cardWidthPx: int,
 *     cardHeightPx: int,
 *     rackWidthPx: int,
 *     rackHeightPx: int,
 *     medalCardPx: int,
 *     medalFichePx: int
 * }
 * @phpstan-type ResolvedDecoration array{
 *     id: string,
 *     name: string,
 *     family: DecorationFamily,
 *     type: DecorationType,
 *     level: string,
 *     colors: list<string>,
 *     pattern: string,
 *     patternClass: string,
 *     dropClass: string,
 *     discClass: string,
 *     glyph: string,
 *     description: string,
 *     referenceUrl: string,
 *     isOfficialReference: false,
 *     sort: int,
 *     aliases: list<string>,
 *     cardWidthPx: int,
 *     cardHeightPx: int,
 *     rackWidthPx: int,
 *     rackHeightPx: int,
 *     medalCardPx: int,
 *     medalFichePx: int,
 *     isCustom: bool,
 *     rawLabel: string,
 *     device: ?string
 * }
 */
final class DecorationCatalog
{
    public const CAUTION = 'Modèles graphiques inspirés de références U.S. Army et OTAN. Les noms, formes et motifs sont utilisés ici à des fins de démonstration UI et ne constituent pas des reproductions officielles ni une preuve d\'attribution.';

    public const FOOTER = 'Proportions générales informées par, sans reproduire : U.S. Army Institute of Heraldry (tioh.army.mil), U.S. Army veteran medals (veteranmedals.army.mil), NATO official texts (nato.int). Aucun emblème gouvernemental ni logo protégé n\'est reproduit dans ce pack.';

    public const DEVICE_NOTE = 'Les dispositifs affichés (étoile, chiffre) sont génériques et ne représentent aucune attribution réelle.';

    /**
     * @return list<DecorationRow>
     */
    public static function all(): array
    {
        $rows = [];
        foreach (self::definitions() as $row) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @return list<DecorationRow>
     */
    public static function ribbons(): array
    {
        return array_values(array_filter(self::all(), static fn (array $row): bool => $row['type'] === 'ribbon'));
    }

    /**
     * @return list<DecorationRow>
     */
    public static function medals(): array
    {
        return array_values(array_filter(self::all(), static fn (array $row): bool => $row['type'] === 'medal'));
    }

    /**
     * @return DecorationRow|null
     */
    public static function find(string $id): ?array
    {
        $id = trim($id);
        if ($id === '') {
            return null;
        }
        foreach (self::definitions() as $row) {
            if ($row['id'] === $id) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @param list<string> $lines
     * @return list<ResolvedDecoration>
     */
    public static function resolveLines(array $lines): array
    {
        $out = [];
        $seen = [];
        foreach ($lines as $line) {
            $raw = trim((string) $line);
            if ($raw === '') {
                continue;
            }
            $device = null;
            $lookup = $raw;
            if (str_contains($raw, '|')) {
                [$lookup, $deviceRaw] = array_pad(explode('|', $raw, 2), 2, '');
                $lookup = trim((string) $lookup);
                $deviceRaw = strtolower(trim((string) $deviceRaw));
                if (in_array($deviceRaw, ['star', 'numeral', 'oak'], true)) {
                    $device = $deviceRaw;
                }
            }
            $hit = self::match($lookup);
            if ($hit !== null) {
                $key = $hit['id'];
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $out[] = self::hydrateResolved($hit, false, $raw, $device);
                continue;
            }
            $customKey = 'custom:' . self::fold($raw);
            if (isset($seen[$customKey])) {
                continue;
            }
            $seen[$customKey] = true;
            $out[] = self::hydrateResolved(self::customFallback($raw), true, $raw, $device);
        }
        usort($out, static function (array $a, array $b): int {
            if ($a['isCustom'] !== $b['isCustom']) {
                return $a['isCustom'] ? 1 : -1;
            }

            return $a['sort'] <=> $b['sort'];
        });

        return array_values($out);
    }

    /**
     * @param list<mixed> $catalogIds
     * @return list<string>
     */
    public static function mergeRackInput(array $catalogIds, string $customText, int $maxItems = 24, int $maxLen = 160): array
    {
        $ids = [];
        foreach ($catalogIds as $rawId) {
            $id = trim((string) $rawId);
            if (self::find($id) === null) {
                continue;
            }
            if (!in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }
        $custom = [];
        $lines = preg_split('/\r\n|\r|\n/', $customText) ?: [];
        foreach ($lines as $line) {
            $value = trim((string) $line);
            if ($value === '') {
                continue;
            }
            if (function_exists('mb_strlen') && mb_strlen($value) > $maxLen) {
                $value = mb_substr($value, 0, $maxLen);
            } elseif (strlen($value) > $maxLen) {
                $value = substr($value, 0, $maxLen);
            }
            if (self::find($value) !== null) {
                if (!in_array($value, $ids, true)) {
                    $ids[] = $value;
                }
                continue;
            }
            if (!in_array($value, $custom, true)) {
                $custom[] = $value;
            }
        }
        $merged = array_merge($ids, $custom);
        if (count($merged) > $maxItems) {
            $merged = array_slice($merged, 0, $maxItems);
        }

        return array_values($merged);
    }

    /**
     * @param list<string> $stored
     * @return array{catalogIds: list<string>, customLines: list<string>}
     */
    public static function splitStored(array $stored): array
    {
        $catalogIds = [];
        $customLines = [];
        foreach ($stored as $line) {
            $value = trim((string) $line);
            if ($value === '') {
                continue;
            }
            $lookup = $value;
            if (str_contains($value, '|')) {
                $lookup = trim((string) explode('|', $value, 2)[0]);
            }
            if (self::find($lookup) !== null) {
                if (!in_array($lookup, $catalogIds, true)) {
                    $catalogIds[] = $lookup;
                }
                continue;
            }
            $customLines[] = $value;
        }

        return ['catalogIds' => $catalogIds, 'customLines' => $customLines];
    }

    public static function glyphSvg(string $glyph, bool $small = false): string
    {
        $glyph = strtolower(trim($glyph));
        $stroke = $small ? '1.6' : '1.4';
        return match ($glyph) {
            'star' => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l2.8 6.6L22 9.3l-5 4.9 1.2 7.1L12 17.9l-6.2 3.4L7 14.2 2 9.3l7.2-.7z"/></svg>',
            'cross' => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M9,9 L7.2,2.8 L16.8,2.8 L15,9 L21.2,7.2 L21.2,16.8 L15,15 L16.8,21.2 L7.2,21.2 L9,15 L2.8,16.8 L2.8,7.2 Z"/></svg>',
            'wreath' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="' . $stroke . '" stroke-linecap="round" aria-hidden="true"><path d="M6 15c1-4 2-7 6-7s5 3 6 7"/><path d="M12 4v3"/><circle cx="12" cy="12" r="1.4" fill="currentColor" stroke="none"/></svg>',
            'circle' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="12" cy="12" r="6"/></svg>',
            'numeral' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M12 4v16"/></svg>',
            default => '',
        };
    }

    public static function deviceSvg(string $device): string
    {
        return match ($device) {
            'star' => self::glyphSvg('star'),
            'numeral' => '<span class="dk-device-num" aria-hidden="true">2</span>',
            'oak' => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 3c2.4 2.2 3.5 4.6 3.2 7.1 1.8-.3 3.3.6 4.3 2.1-1.6.6-3.1.5-4.4-.2.4 2.2-.3 4.2-2.1 5.9-.4-1.8-.2-3.4.5-4.8-1.5 1.1-3.3 1.3-5.2.6 1.3-1.4 2.8-2 4.5-1.8C11.4 9.4 10.6 7.2 12 3z"/></svg>',
            default => '',
        };
    }

    /**
     * @return DecorationRow|null
     */
    private static function match(string $raw): ?array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        $folded = self::fold($raw);
        foreach (self::definitions() as $row) {
            if ($row['id'] === $raw || self::fold($row['id']) === $folded) {
                return $row;
            }
            if (self::fold($row['name']) === $folded) {
                return $row;
            }
            foreach ($row['aliases'] as $alias) {
                if (self::fold($alias) === $folded) {
                    return $row;
                }
            }
        }

        return null;
    }

    /**
     * @param DecorationRow $row
     * @return ResolvedDecoration
     */
    private static function hydrateResolved(array $row, bool $isCustom, string $rawLabel, ?string $device): array
    {
        $row['isCustom'] = $isCustom;
        $row['rawLabel'] = $rawLabel;
        $row['device'] = $device;

        return $row;
    }

    /**
     * @return DecorationRow
     */
    private static function customFallback(string $label): array
    {
        $base = self::ribbon(
            'rbn_custom',
            $label,
            'GENERIC',
            'service',
            ['#6b7278', '#dfe3e6'],
            'vertical-stripes-silver',
            'dk-rb-svc2',
            'Fines rayures gris argenté, motif générique de repli pour une mention libre.',
            900
        );
        $base['id'] = 'rbn_custom';
        $base['name'] = $label;

        return $base;
    }

    private static function fold(string $value): string
    {
        $value = trim($value);
        if (function_exists('mb_strtolower')) {
            $value = mb_strtolower($value, 'UTF-8');
        } else {
            $value = strtolower($value);
        }
        $map = [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'œ' => 'oe', 'æ' => 'ae',
            '’' => "'", '‘' => "'",
        ];
        $value = strtr($value, $map);
        $value = preg_replace('/[^a-z0-9]+/', '', $value) ?? $value;

        return $value;
    }

    /**
     * @return list<DecorationRow>
     */
    private static function definitions(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $cache = [
            self::ribbon(
                'rbn_service_distingue',
                'Service distingué',
                'GENERIC',
                'service',
                ['#6e1c1c', '#e8c77e'],
                'vertical-stripes',
                'dk-rb-svc',
                'Bandes verticales rouge sombre / or, catégorie générique « service distingué ».',
                10
            ),
            self::ribbon(
                'rbn_campagne_exterieure',
                'Campagne extérieure',
                'GENERIC',
                'campaign',
                ['#17335e', '#ffffff'],
                'vertical-stripes-navy',
                'dk-rb-camp',
                'Bandes verticales bleu marine / blanc, catégorie générique « campagne ou service extérieur ».',
                20
            ),
            self::ribbon(
                'rbn_conduite_service',
                'Conduite / service',
                'GENERIC',
                'conduct',
                ['#0f6d72', '#f4e3c1'],
                'solid-center-stripe',
                'dk-rb-cond',
                'Bleu-vert uni avec liseré central, catégorie générique « bonne conduite ».',
                30
            ),
            self::ribbon(
                'rbn_action_combat',
                'Action de combat',
                'GENERIC',
                'combat',
                ['#d4a017', '#171717'],
                'yellow-black-stripes',
                'dk-rb-cbt',
                'Rayures jaune/noir, catégorie générique « décoration liée au combat ».',
                40
            ),
            self::ribbon(
                'rbn_unite_citee',
                'Unité citée',
                'GENERIC',
                'unit',
                ['#1d3f9e', '#ffffff'],
                'royal-edge-stripes',
                'dk-rb-unit',
                'Bleu royal à liserés blancs, catégorie générique « citation collective d’unité ».',
                50
            ),
            self::ribbon(
                'rbn_qualification_speciale',
                'Qualification spéciale',
                'GENERIC',
                'qualification',
                ['#2f7d4f', '#0b3a24'],
                'diagonal-hatch',
                'dk-rb-qual',
                'Vert à diagonales noires, catégorie générique « qualification spécialisée ».',
                60
            ),
            self::ribbon(
                'rbn_merite',
                'Mérite',
                'GENERIC',
                'merit',
                ['#8a6a2c', '#e3c878'],
                'horizontal-bronze-gold',
                'dk-rb-merit',
                'Dégradé bronze/or horizontal, catégorie générique « décoration de mérite ».',
                70
            ),
            self::ribbon(
                'rbn_anciennete_service',
                'Ancienneté de service',
                'GENERIC',
                'service',
                ['#6b7278', '#dfe3e6'],
                'vertical-stripes-silver',
                'dk-rb-svc2',
                'Fines rayures gris argenté, catégorie générique « ancienneté ».',
                80
            ),
            self::ribbon(
                'rbn_service_multinational_nato',
                'Service multinational',
                'NATO_INSPIRED',
                'service',
                ['#173d73', '#ffffff', '#c1272d'],
                'nato-bands',
                'dk-rb-nato',
                'Bleu OTAN avec bandes blanches et rouges — traité comme « NATO-inspired », pas la reproduction d’une mission précise.',
                90,
                'https://www.nato.int/en/about-us/official-texts-and-resources'
            ),
            self::medal(
                'med_etoile_bravoure',
                'Étoile de bravoure',
                'GENERIC',
                'gold',
                ['#c7a33e', '#6e1c1c'],
                'star',
                'dk-rb-svc',
                'dk-drop-gold',
                'dk-disc-gold',
                'star',
                'Étoile à cinq branches, échelon or, forme héraldique générique.',
                100
            ),
            self::medal(
                'med_croix_merite_or',
                'Croix du mérite',
                'GENERIC',
                'gold',
                ['#c7a33e', '#6e1c1c'],
                'cross-pattee',
                'dk-rb-svc',
                'dk-drop-gold',
                'dk-disc-gold',
                'cross',
                'Croix pattée, échelon or, forme héraldique générique.',
                110,
                ['Croix du mérite — échelon or', 'Croix du mérite or']
            ),
            self::medal(
                'med_croix_merite_argent',
                'Croix du mérite',
                'GENERIC',
                'silver',
                ['#9aa2a8', '#dfe3e6'],
                'cross-pattee',
                'dk-rb-svc2',
                'dk-drop-silver',
                'dk-disc-silver',
                'cross',
                'Croix pattée, échelon argent, forme héraldique générique.',
                120,
                ['Croix du mérite — échelon argent', 'Croix du mérite argent']
            ),
            self::medal(
                'med_croix_merite_bronze',
                'Croix du mérite',
                'GENERIC',
                'bronze',
                ['#a06a3c', '#1a1a1a'],
                'cross-pattee',
                'dk-rb-cbt',
                'dk-drop-bronze',
                'dk-disc-bronze',
                'cross',
                'Croix pattée, échelon bronze, forme héraldique générique.',
                130,
                ['Croix du mérite — échelon bronze', 'Croix du mérite bronze']
            ),
            self::medal(
                'med_service_multinational_nato',
                'Service multinational',
                'NATO_INSPIRED',
                'service',
                ['#173d73'],
                'stylised-wreath',
                'dk-rb-nato',
                'dk-drop-nato',
                'dk-disc-nato',
                'wreath',
                'Couronne stylisée sur disque bleu OTAN, non-officiel.',
                140,
                [],
                'https://www.nato.int/en/news-and-events/articles/news/2012/11/19/ims-civilians-awarded-the-non-article-5-medal-for-service-in-afghanistan'
            ),
            self::medal(
                'med_medaille_service',
                'Médaille de service',
                'GENERIC',
                'service',
                ['#9ca3a9', '#e5e8ea'],
                'polished-disc',
                'dk-rb-svc2',
                'dk-drop-svc',
                'dk-disc-svc',
                'circle',
                'Disque poli, catégorie générique « médaille de service ».',
                150
            ),
        ];

        return $cache;
    }

    /**
     * @param list<string> $colors
     * @return DecorationRow
     */
    private static function ribbon(
        string $id,
        string $name,
        string $family,
        string $level,
        array $colors,
        string $pattern,
        string $patternClass,
        string $description,
        int $sort,
        string $referenceUrl = 'https://tioh.army.mil/'
    ): array {
        return self::row(
            $id,
            $name,
            $family,
            'ribbon',
            $level,
            $colors,
            $pattern,
            $patternClass,
            '',
            '',
            '',
            $description,
            $sort,
            [],
            $referenceUrl
        );
    }

    /**
     * @param list<string> $colors
     * @param list<string> $aliases
     * @return DecorationRow
     */
    private static function medal(
        string $id,
        string $name,
        string $family,
        string $level,
        array $colors,
        string $pattern,
        string $patternClass,
        string $dropClass,
        string $discClass,
        string $glyph,
        string $description,
        int $sort,
        array $aliases = [],
        string $referenceUrl = 'https://tioh.army.mil/FAQs/FaqsInsignia.aspx?SectionID=104'
    ): array {
        return self::row(
            $id,
            $name,
            $family,
            'medal',
            $level,
            $colors,
            $pattern,
            $patternClass,
            $dropClass,
            $discClass,
            $glyph,
            $description,
            $sort,
            $aliases,
            $referenceUrl
        );
    }

    /**
     * @param list<string> $colors
     * @param list<string> $aliases
     * @return DecorationRow
     */
    private static function row(
        string $id,
        string $name,
        string $family,
        string $type,
        string $level,
        array $colors,
        string $pattern,
        string $patternClass,
        string $dropClass,
        string $discClass,
        string $glyph,
        string $description,
        int $sort,
        array $aliases,
        string $referenceUrl
    ): array {
        return [
            'id' => $id,
            'name' => $name,
            'family' => $family === 'NATO_INSPIRED' ? 'NATO_INSPIRED' : 'GENERIC',
            'type' => $type === 'medal' ? 'medal' : 'ribbon',
            'level' => $level,
            'colors' => $colors,
            'pattern' => $pattern,
            'patternClass' => $patternClass,
            'dropClass' => $dropClass,
            'discClass' => $discClass,
            'glyph' => $glyph,
            'description' => $description,
            'referenceUrl' => $referenceUrl,
            'isOfficialReference' => false,
            'sort' => $sort,
            'aliases' => $aliases,
            'cardWidthPx' => 46,
            'cardHeightPx' => 16,
            'rackWidthPx' => 52,
            'rackHeightPx' => 18,
            'medalCardPx' => 34,
            'medalFichePx' => 62,
        ];
    }
}
