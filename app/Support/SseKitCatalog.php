<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Kits de contenu SSE (config/sse_kits) : un kit par pays des papiers (mêmes codes ISO 3166 alpha-3 que le registre du mod,
 * tools/countries/countries.json), et des thèmes de réseau (organisations inventées) qui se posent sur ces kits.
 *
 * Tout est FICTIF : personnes, organisations, numéros, plaques et adresses inventés. Les viviers de noms sont ceux du
 * registre du mod (test SseKitCatalogTest : mêmes prénoms et noms des deux côtés), les régions narratives sont celles de
 * comspec_sse_fnc_getNarrativePools (IRAQ, RUSSIA, AFRICA_SAHEL…).
 */
final class SseKitCatalog
{
    public const LANGS = ['fr', 'en'];

    /** Thème de fond, majoritaire par défaut. */
    public const CIVIL_THEME = 'civil_population';

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $kits = null;

    /** @var array<string, mixed>|null */
    private static ?array $catalog = null;

    public static function directory(): string
    {
        return dirname(__DIR__, 2) . '/config/sse_kits';
    }

    /** @return array<string, array<string, mixed>> kits par code pays, dans l'ordre d'affichage */
    public static function kits(): array
    {
        if (self::$kits === null) {
            /** @var array<string, array<string, mixed>> $data */
            $data = self::read('kits.json');
            uasort($data, static fn (array $a, array $b): int => ((int) $a['order']) <=> ((int) $b['order']));
            self::$kits = $data;
        }

        return self::$kits;
    }

    /** @return array<string, mixed>|null */
    public static function kit(string $code): ?array
    {
        return self::kits()[strtoupper(trim($code))] ?? null;
    }

    /** @return list<string> */
    public static function codes(): array
    {
        return array_keys(self::kits());
    }

    /** @return array<string, array<string, mixed>> thèmes de réseau par clé */
    public static function themes(): array
    {
        /** @var array<string, array<string, mixed>> $themes */
        $themes = self::catalog()['themes'];

        return $themes;
    }

    /** @return array<string, mixed>|null */
    public static function theme(string $key): ?array
    {
        return self::themes()[$key] ?? null;
    }

    /** @return list<string> thèmes de réseau (sans la population civile) */
    public static function networkThemes(): array
    {
        return array_values(array_filter(array_keys(self::themes()), static fn (string $k): bool => $k !== self::CIVIL_THEME));
    }

    /** @return array<string, mixed> professions, signes, couleurs, pièces annexes, phrases communes, homonymes écartés */
    public static function catalog(): array
    {
        return self::$catalog ??= self::read('themes.json');
    }

    /** Homonymes de personnalités : « prénom nom » jamais tirés ensemble. */
    public static function isBlocked(string $first, string $last): bool
    {
        static $set = null;
        if ($set === null) {
            $set = array_fill_keys(array_map('mb_strtolower', self::catalog()['blocked']), true);
        }
        $first = mb_strtolower(trim($first));
        // Double nom de famille : seul le premier compte.
        $last = mb_strtolower(trim($last));
        $firstLast = (preg_split('/\s+/', $last) ?: [''])[0];

        return isset($set[$first . ' ' . $last]) || isset($set[$first . ' ' . $firstLast]);
    }

    /**
     * Tailles des viviers d'un kit (page « Générer du contenu », rapport).
     *
     * @return array{male: int, female: int, last: int, nicknames: int, vehicles: int, documents: int, places: int, streets: int}
     */
    public static function sizes(string $code): array
    {
        $k = self::kit($code) ?? [];

        return [
            'male' => count($k['male'] ?? []),
            'female' => count($k['female'] ?? []),
            'last' => count($k['last'] ?? []),
            'nicknames' => count($k['nicknames'] ?? []),
            'vehicles' => count($k['vehicles'] ?? []),
            'documents' => count($k['docTypes'] ?? []),
            'places' => count($k['placeA'] ?? []) * count($k['placeB'] ?? []),
            'streets' => count($k['streetA'] ?? []) * count($k['streetB'] ?? []),
        ];
    }

    /** Libellé d'un kit ou d'un thème dans la langue demandée. */
    public static function text(mixed $pair, string $lang): string
    {
        if (!is_array($pair)) {
            return '';
        }

        return (string) ($pair[$lang === 'en' ? 'en' : 'fr'] ?? $pair['fr'] ?? '');
    }

    /** Kits conseillés pour un thème ([] = tous). @return list<string> */
    public static function kitsForTheme(string $theme): array
    {
        $kits = self::theme($theme)['kits'] ?? [];

        return array_values(array_intersect(is_array($kits) ? $kits : [], self::codes()));
    }

    /**
     * Régions narratives du générateur du mod (comspec_sse_fnc_getNarrativePools) et kits qui les nourrissent. Les cinq
     * premières existaient avant les kits ; les autres sont nées avec eux.
     */
    public const REGIONS = [
        'IRAQ' => ['IRQ'], 'SYRIA' => ['SYR'], 'LEVANT' => ['LBN'], 'AFRICA_SAHEL' => ['MLI', 'NER', 'TCD'], 'RUSSIA' => ['RUS'],
        'AFGHANISTAN' => ['AFG'], 'UKRAINE' => ['UKR'], 'BALKANS' => ['SRB', 'ALB'], 'LATAM' => ['COL', 'MEX'], 'SE_ASIA' => ['PHL'],
        'HORN_AFRICA' => ['SOM'], 'LIBYA' => ['LBY'], 'YEMEN' => ['YEM'],
    ];

    /** Régions qui existaient avant les kits : leurs surnoms, nationalités, langues et indicatifs d'origine sont gardés. */
    public const LEGACY_REGIONS = ['IRAQ', 'SYRIA', 'LEVANT', 'AFRICA_SAHEL', 'RUSSIA'];

    /** Libellés des régions ajoutées par les kits (liste « Région / théâtre » d'Eden) : [français, anglais]. */
    public const REGION_LABELS = [
        'AFGHANISTAN' => ['Afghanistan', 'Afghanistan'], 'UKRAINE' => ['Ukraine', 'Ukraine'], 'BALKANS' => ['Balkans', 'Balkans'],
        'LATAM' => ['Amérique latine', 'Latin America'], 'SE_ASIA' => ['Asie du Sud-Est', 'South-East Asia'],
        'HORN_AFRICA' => ['Corne de l’Afrique', 'Horn of Africa'], 'LIBYA' => ['Libye', 'Libya'], 'YEMEN' => ['Yémen', 'Yemen'],
    ];

    /**
     * Vivier narratif d'une région : prénoms masculins, noms, surnoms, nationalités, langues, indicatifs et véhicules de
     * ses kits, dans l'ordre des kits (même contenu dans fn_kitPools.sqf du mod, écrit par tools/gen_kit_pools.php).
     *
     * @return array{first: list<string>, last: list<string>, aliases: list<string>, nationalities: list<string>, languages: list<string>,
     *     phonePrefixes: list<string>, vehicleTypes: list<string>}|null null : région sans kit
     */
    public static function regionPool(string $region): ?array
    {
        $codes = self::REGIONS[strtoupper(trim($region))] ?? null;
        if ($codes === null) {
            return null;
        }
        $out = ['first' => [], 'last' => [], 'aliases' => [], 'nationalities' => [], 'languages' => [], 'phonePrefixes' => [], 'vehicleTypes' => []];
        foreach ($codes as $code) {
            $kit = self::kit($code);
            if ($kit === null) {
                continue;
            }
            $out['first'] = array_merge($out['first'], $kit['male']);
            $out['last'] = array_merge($out['last'], $kit['last']);
            $out['aliases'] = array_merge($out['aliases'], $kit['nicknames']);
            $out['nationalities'][] = (string) $kit['nationality']['fr'];
            $out['languages'] = array_merge($out['languages'], $kit['languages']);
            foreach ($kit['phones'] as $pattern) {
                $out['phonePrefixes'][] = trim((string) preg_replace('/[#\s]*#.*$/', '', (string) $pattern));
            }
            foreach ($kit['vehicles'] as $v) {
                $out['vehicleTypes'][] = $v['kind'] . ' ' . $v['make'] . ' ' . $v['model'];
            }
        }
        $out['nationalities'][] = 'Inconnue';
        foreach ($out as $k => $list) {
            $out[$k] = array_values(array_unique(array_filter($list, static fn (string $v): bool => $v !== '')));
        }

        return $out;
    }

    /**
     * Contenu de addons/generator/functions/fn_kitPools.sqf du mod SSE : une entrée par région narrative. Les prénoms et
     * noms sont ceux que le site tire pour la même région ($names, SseIdentityGeneratorService::regionPool) ; une région
     * d'origine garde ses surnoms, nationalités, langues et indicatifs et reçoit ceux des kits en plus.
     *
     * @param callable(string): array{first: list<string>, last: list<string>} $names
     */
    public static function narrativeSqf(callable $names): string
    {
        $q = static fn (string $s): string => '"' . str_replace('"', '""', $s) . '"';
        $arr = static function (array $list) use ($q): string {
            $lines = [];
            $cur = '';
            foreach ($list as $item) {
                $piece = $q((string) $item) . ',';
                if ($cur !== '' && strlen($cur) + strlen($piece) > 150) {
                    $lines[] = $cur;
                    $cur = '';
                }
                $cur .= $piece;
            }
            $lines[] = rtrim($cur, ',');

            return '[' . implode("\n            ", $lines) . ']';
        };
        $rows = [];
        foreach (array_keys(self::REGIONS) as $region) {
            $pool = self::regionPool($region);
            $n = $names($region);
            $legacy = in_array($region, self::LEGACY_REGIONS, true);
            $pairs = [
                '["legacy", ' . ($legacy ? 'true' : 'false') . ']',
                '["firstNames", ' . $arr($n['first']) . ']',
                '["lastNames", ' . $arr($n['last']) . ']',
                '["aliases", ' . $arr($pool['aliases']) . ']',
                '["vehicleTypes", ' . $arr($pool['vehicleTypes']) . ']',
            ];
            if (!$legacy) {
                $pairs[] = '["nationalities", ' . $arr($pool['nationalities']) . ']';
                $pairs[] = '["languages", ' . $arr($pool['languages']) . ']';
                $pairs[] = '["phonePrefixes", ' . $arr($pool['phonePrefixes']) . ']';
            }
            $rows[] = '    [' . $q($region) . ", createHashMapFromArray [\n        " . implode(",\n        ", $pairs) . "\n    ]]";
        }

        return "/*\n"
            . "    GÉNÉRÉ par tools/gen_kit_pools.php depuis config/sse_kits/kits.json (site) : ne pas modifier à la main.\n"
            . "    Kits de contenu SSE : viviers de chaque région narrative (prénoms, noms, surnoms, véhicules ; nationalités,\n"
            . "    langues et indicatifs pour les régions nées avec les kits). Tout est fictif : personnes et surnoms inventés.\n"
            . "    Mêmes prénoms et noms sur Athena pour la même région (une graine donne la même identité des deux côtés).\n"
            . "    [] call comspec_sse_fnc_kitPools -> HashMap région -> HashMap (legacy : la région existait avant les kits)\n"
            . "*/\n"
            . "private _m = missionNamespace getVariable \"comspec_sse_kitPoolsCache\";\n"
            . "if (!isNil \"_m\") exitWith { _m };\n"
            . "_m = createHashMapFromArray [\n" . implode(",\n", $rows) . "\n];\n"
            . "missionNamespace setVariable [\"comspec_sse_kitPoolsCache\", _m];\n"
            . "_m\n";
    }

    /** @return array<string, mixed> */
    private static function read(string $file): array
    {
        $raw = (string) file_get_contents(self::directory() . '/' . $file);
        /** @var array<string, mixed> $data */
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        return $data;
    }
}
