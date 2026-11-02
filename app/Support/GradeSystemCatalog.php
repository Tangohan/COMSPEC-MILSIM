<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\Organization\GradeLevel;
use App\Support\QualificationCatalog\CatalogCountry;

/**
 * Catalogue des systèmes de grades officiels pour la fenêtre « Parcourir les catalogues » (référentiel des grades,
 * étape Grades de la création de communauté) : pays (drapeau, nom natif), puis armées ou systèmes du pays, et grades
 * ordonnés du plus élevé au plus bas (abrégé, intitulé natif, translittération, équivalent anglais, code OTAN, niveau).
 *
 * Tous les systèmes du registre OfficialGradeSystems sont listés. Grades : catalogue en base quand il est installé
 * (France et États-Unis n’existent qu’en base), sinon OfficialGradeSystems::GRADES ; « installed » indique si la
 * communauté peut déjà l’adopter (étape de migration jouée).
 */
final class GradeSystemCatalog
{
    /** Libellés des armées (traduits avec la page). */
    public const BRANCHES = ['army' => 'Armée de terre', 'navy' => 'Marine', 'air' => 'Armée de l’air', 'joint' => 'Interarmées'];

    /**
     * @param array<string, list<array<string, mixed>>> $dbGradesBySystem grades en base par code de système
     * @return list<array{country: string, native: string, systems: list<array<string, mixed>>}>
     */
    public static function countries(array $dbGradesBySystem, string $preferredSystem = ''): array
    {
        $countries = [];
        foreach (OfficialGradeSystems::CODES as $code) {
            $country = OfficialGradeSystems::country($code);
            $countries[$country] ??= ['country' => $country, 'native' => CatalogCountry::native($country) ?: $country, 'systems' => []];
            $countries[$country]['systems'][] = self::system($code, $dbGradesBySystem[$code] ?? []);
        }
        $preferred = OfficialGradeSystems::has($preferredSystem) ? OfficialGradeSystems::country($preferredSystem) : '';
        if ($preferred !== '' && isset($countries[$preferred])) {
            $countries = [$preferred => $countries[$preferred]] + $countries;
        }

        return array_values($countries);
    }

    /**
     * @param list<array<string, mixed>> $dbRows
     * @return array<string, mixed>
     */
    public static function system(string $code, array $dbRows = []): array
    {
        $card = OfficialGradeSystems::card($code);
        $rows = [];
        if ($dbRows !== []) {
            foreach ($dbRows as $r) {
                $gCode = (string) ($r['code'] ?? '');
                $long = trim((string) ($r['catalog_label_long'] ?? $r['label_long'] ?? ''));
                $rows[] = [
                    'id' => (int) ($r['id'] ?? 0), 'code' => $gCode,
                    'label_short' => trim((string) ($r['catalog_label_short'] ?? $r['label_short'] ?? '')),
                    'label_long' => $long,
                    'label_otan' => (string) ($r['label_otan'] ?? ''),
                    'category_code' => (string) ($r['category_code'] ?? ''),
                    'sort_order' => (int) ($r['sort_order'] ?? 0),
                    'is_commissioned' => (int) ($r['is_commissioned'] ?? 0),
                ];
            }
        } else {
            $i = 0;
            foreach (OfficialGradeSystems::GRADES[$code] ?? [] as $g) {
                $rows[] = ['id' => ++$i, 'code' => $g[0], 'label_short' => $g[1], 'label_long' => $g[2], 'label_otan' => $g[4],
                    'category_code' => $g[5], 'sort_order' => $g[6], 'is_commissioned' => $g[7]];
            }
        }
        $grades = [];
        foreach (GradeSelectOptions::sorted($rows) as $r) {
            $grades[] = [
                'code' => $r['code'],
                'short' => $r['label_short'] !== '' ? $r['label_short'] : $r['code'],
                'long' => $r['label_long'],
                'roman' => OfficialGradeSystems::romanized($code, $r['code']),
                'english' => OfficialGradeSystems::english($code, $r['code']),
                'nato' => $r['label_otan'],
                'level' => GradeLevel::fromCode($r['label_otan']),
                'category' => $r['category_code'] !== '' ? $r['category_code'] : 'AUTRE',
            ];
        }
        $title = $card['title'];
        $sub = str_contains($title, ' · ') ? substr($title, strpos($title, ' · ') + strlen(' · ')) : '';

        return [
            'code' => $code,
            'country' => OfficialGradeSystems::country($code),
            'name' => OfficialGradeSystems::tabLabel($code),
            'title' => $title,
            'native' => $sub,
            'text' => $card['text'],
            'family' => OfficialGradeSystems::family($code),
            'branch' => OfficialGradeSystems::branch($code),
            'lang' => OfficialGradeSystems::lang($code),
            'tab' => OfficialGradeSystems::tab($code),
            'installed' => $dbRows !== [],
            'grades' => $grades,
        ];
    }
}
