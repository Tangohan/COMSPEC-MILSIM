<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Espaces de la page Équipement (un seul visible à la fois, chacun avec son adresse `?espace=…`), recherche, filtre par
 * type, tri et pagination côté serveur. Pur : ne touche ni la base ni la session, testable seul.
 */
final class EquipmentSpaces
{
    /** Espace => libellé. Le premier est celui où l'on atterrit sans paramètre. */
    public const SPACES = [
        'mes-tenues' => 'Mes tenues',
        'favoris' => 'Favoris',
        'collections' => 'Collections',
        'communaute' => 'Tenues de la communauté',
        'kits' => 'Kits de départ',
        'materiel' => 'Matériel',
    ];

    /** Cartes par page : deux rangées d'une grille dense tiennent dans un écran d'ordinateur. */
    public const PER_PAGE = 12;

    /** Kits par page : une carte de kit est plus haute qu'une carte de tenue. */
    public const PER_PAGE_KITS = 6;

    public const SORTS = ['name', 'date', 'collection'];

    public static function normalize(mixed $space): string
    {
        $space = is_string($space) ? trim($space) : '';

        return isset(self::SPACES[$space]) ? $space : 'mes-tenues';
    }

    /**
     * Anciennes adresses : ?collection=ID (et edit_collection), ?tab=fiches|dotation, ?fiche=ID, ?dotation=ID mènent à
     * l'espace qui les porte désormais ; ?tenue=ID reste sur l'espace demandé (la fenêtre de la tenue s'ouvre dessus).
     *
     * @param array<string, mixed> $query
     * @return array{espace: string, collection: int, tab: string}
     */
    public static function resolve(array $query): array
    {
        $space = self::normalize($query['espace'] ?? '');
        $collection = (int) ($query['collection'] ?? 0);
        $tab = self::materielTab($query);
        if (!isset($query['espace'])) {
            if ($collection > 0) {
                $space = 'collections';
            } elseif (in_array($query['tab'] ?? '', ['fiches', 'dotation'], true) || (int) ($query['fiche'] ?? 0) > 0 || (int) ($query['dotation'] ?? 0) > 0) {
                $space = 'materiel';
            }
        }

        return ['espace' => $space, 'collection' => $collection > 0 ? $collection : 0, 'tab' => $tab];
    }

    /**
     * Onglet de l'espace Matériel : catalogue des objets du jeu (par défaut), fiches de la communauté ou dotation.
     * ?fiche=ID et ?dotation=ID ouvrent l'onglet qui porte la fenêtre ; ?objet=CLASSE reste sur le catalogue.
     *
     * @param array<string, mixed> $query
     */
    public static function materielTab(array $query): string
    {
        $tab = is_string($query['tab'] ?? null) ? $query['tab'] : '';
        if ($tab === 'dotation' || (int) ($query['dotation'] ?? 0) > 0) {
            return 'dotation';
        }
        if ($tab === 'fiches' || (int) ($query['fiche'] ?? 0) > 0) {
            return 'fiches';
        }

        return 'catalogue';
    }

    /** Minuscules sans accents : « Médecin » se trouve en tapant « medecin ». */
    public static function fold(string $text): string
    {
        $text = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);

        return strtr($text, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i',
            'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'œ' => 'oe',
        ]);
    }

    /**
     * Tenues dont le nom, le propriétaire, la collection ou les pièces principales contiennent la recherche, et qui
     * ont le type demandé (famille de ArsenalLoadoutItems::kindLabels, vide = tous).
     *
     * @param list<array<string, mixed>> $wardrobes
     * @return list<array<string, mixed>>
     */
    public static function filterWardrobes(array $wardrobes, string $query, string $kind): array
    {
        $needle = self::fold(trim($query));
        $kind = trim($kind);
        $out = [];
        foreach ($wardrobes as $w) {
            if ($kind !== '' && !in_array($kind, is_array($w['kinds'] ?? null) ? $w['kinds'] : [], true)) {
                continue;
            }
            if ($needle !== '') {
                $items = array_map(static fn (array $p): string => (string) ($p['name'] ?? ''), is_array($w['preview'] ?? null) ? $w['preview'] : []);
                $hay = self::fold(implode(' ', [
                    (string) ($w['name'] ?? ''), (string) ($w['display_name'] ?? ''), (string) ($w['owner_label'] ?? ''),
                    (string) ($w['collection_name'] ?? ''), implode(' ', $items),
                ]));
                if (!str_contains($hay, $needle)) {
                    continue;
                }
            }
            $out[] = $w;
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $wardrobes
     * @return list<array<string, mixed>>
     */
    public static function sortWardrobes(array $wardrobes, string $sort): array
    {
        $sort = in_array($sort, self::SORTS, true) ? $sort : 'name';
        usort($wardrobes, static function (array $a, array $b) use ($sort): int {
            if ($sort === 'date') {
                return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
            }
            if ($sort === 'collection') {
                $c = strcmp(self::fold((string) ($a['collection_name'] ?? '')), self::fold((string) ($b['collection_name'] ?? '')));
                if ($c !== 0) {
                    return $c;
                }
            }

            return strcmp(self::fold((string) ($a['display_name'] ?? $a['name'] ?? '')), self::fold((string) ($b['display_name'] ?? $b['name'] ?? '')));
        });

        return $wardrobes;
    }

    /**
     * @template T
     * @param list<T> $items
     * @return array{items: list<T>, page: int, pages: int, total: int, from: int, to: int}
     */
    public static function paginate(array $items, int $page, int $perPage = self::PER_PAGE): array
    {
        $perPage = max(1, $perPage);
        $total = count($items);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($pages, $page));
        $slice = array_slice($items, ($page - 1) * $perPage, $perPage);

        return [
            'items' => array_values($slice),
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'from' => $total === 0 ? 0 : ($page - 1) * $perPage + 1,
            'to' => ($page - 1) * $perPage + count($slice),
        ];
    }

    /**
     * Adresse d'un espace (paramètres vides ou par défaut omis, ordre stable).
     *
     * @param array<string, mixed> $params espace, collection, q, type, tri, page, tab
     */
    public static function url(array $params, string $base = '/equipment'): string
    {
        $keep = [];
        foreach (['espace', 'collection', 'q', 'type', 'tri', 'tab', 'page'] as $key) {
            $v = $params[$key] ?? '';
            if ($v === '' || $v === null || $v === 0 || $v === '0') {
                continue;
            }
            if ($key === 'espace' && $v === 'mes-tenues') {
                continue;
            }
            if ($key === 'tab' && $v === 'catalogue') {
                continue;
            }
            if ($key === 'tri' && $v === 'name') {
                continue;
            }
            if ($key === 'page' && (int) $v < 2) {
                continue;
            }
            $keep[$key] = (string) $v;
        }

        return $base . ($keep === [] ? '' : '?' . http_build_query($keep, '', '&', PHP_QUERY_RFC3986));
    }
}
