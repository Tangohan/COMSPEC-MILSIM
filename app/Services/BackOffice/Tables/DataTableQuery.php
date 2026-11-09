<?php

declare(strict_types=1);

namespace App\Services\BackOffice\Tables;

/**
 * Définition d'un tableau de données (colonnes, filtres, tri, regroupement) et son calcul. Calculs purs : les lignes
 * viennent de DataTableSources::load(), déjà limitées à la communauté et aux droits de la personne.
 *
 * Définition : {source, columns: [clé…], filters: [{col, op, value}…], sort: {col, dir}, group: {col, agg: {col, fn}}}
 * Toute clé inconnue du catalogue de la source est ignorée : une définition enregistrée ne peut rien ouvrir de plus.
 */
final class DataTableQuery
{
    public const MAX_COLUMNS = 12;
    public const MAX_FILTERS = 6;
    public const COUNT_KEY = '_count';

    public const OPS = [
        'contains' => 'contient',
        'eq' => 'est égal à',
        'ne' => 'est différent de',
        'gt' => 'supérieur à',
        'lt' => 'inférieur à',
        'empty' => 'est vide',
        'notempty' => 'n’est pas vide',
    ];

    public const AGGS = ['sum' => 'Somme', 'avg' => 'Moyenne', 'min' => 'Minimum', 'max' => 'Maximum'];

    /**
     * @param array<string, mixed> $raw
     * @param array<string, array{label: string, type: string}> $columns colonnes de la source
     * @return array{source: string, columns: list<string>, filters: list<array{col: string, op: string, value: string}>, sort: array{col: string, dir: string}, group: array{col: string, agg_col: string, agg_fn: string}}
     */
    public static function normalize(array $raw, string $source, array $columns): array
    {
        $keys = array_keys($columns);
        $cols = [];
        foreach ((array) ($raw['columns'] ?? []) as $c) {
            if (is_string($c) && isset($columns[$c]) && !in_array($c, $cols, true)) {
                $cols[] = $c;
            }
        }
        $cols = array_slice($cols, 0, self::MAX_COLUMNS);
        if ($cols === []) {
            $cols = array_slice($keys, 0, 6);
        }
        $filters = [];
        foreach ((array) ($raw['filters'] ?? []) as $f) {
            if (!is_array($f)) {
                continue;
            }
            $col = (string) ($f['col'] ?? '');
            $op = (string) ($f['op'] ?? '');
            if (!isset($columns[$col]) || !isset(self::OPS[$op])) {
                continue;
            }
            $filters[] = ['col' => $col, 'op' => $op, 'value' => mb_substr(trim((string) ($f['value'] ?? '')), 0, 100, 'UTF-8')];
            if (count($filters) >= self::MAX_FILTERS) {
                break;
            }
        }
        $sort = is_array($raw['sort'] ?? null) ? $raw['sort'] : [];
        $sortCol = (string) ($sort['col'] ?? '');
        $group = is_array($raw['group'] ?? null) ? $raw['group'] : [];
        $groupCol = isset($columns[(string) ($group['col'] ?? '')]) ? (string) $group['col'] : '';
        $aggCol = (string) ($group['agg_col'] ?? '');
        $aggFn = (string) ($group['agg_fn'] ?? '');
        if ($groupCol === '' || !isset($columns[$aggCol]) || $columns[$aggCol]['type'] !== 'number' || !isset(self::AGGS[$aggFn])) {
            $aggCol = '';
            $aggFn = '';
        }

        return [
            'source' => $source,
            'columns' => $cols,
            'filters' => $filters,
            'sort' => [
                'col' => isset($columns[$sortCol]) || ($groupCol !== '' && $sortCol === self::COUNT_KEY) ? $sortCol : '',
                'dir' => ($sort['dir'] ?? '') === 'desc' ? 'desc' : 'asc',
            ],
            'group' => ['col' => $groupCol, 'agg_col' => $aggCol, 'agg_fn' => $aggFn],
        ];
    }

    /**
     * @param list<array<string, string|float>> $rows
     * @param array{source: string, columns: list<string>, filters: list<array{col: string, op: string, value: string}>, sort: array{col: string, dir: string}, group: array{col: string, agg_col: string, agg_fn: string}} $def
     * @param array<string, array{label: string, type: string}> $columns
     * @return array{cols: list<array{key: string, label: string, type: string}>, rows: list<list<string>>, total: int}
     */
    public static function run(array $rows, array $def, array $columns): array
    {
        $rows = array_values(array_filter($rows, static function (array $r) use ($def, $columns): bool {
            foreach ($def['filters'] as $f) {
                if (!self::matches($r[$f['col']] ?? '', $f['op'], $f['value'], $columns[$f['col']]['type'])) {
                    return false;
                }
            }

            return true;
        }));
        $total = count($rows);
        $group = $def['group'];

        if ($group['col'] !== '') {
            [$outCols, $outRows] = self::grouped($rows, $group, $columns);
            $sortKey = $def['sort']['col'];
            $idx = array_search($sortKey, array_column($outCols, 'key'), true);
            if ($idx !== false) {
                $type = $outCols[$idx]['type'];
                usort($outRows, static fn (array $a, array $b): int => self::cmp($a[$idx], $b[$idx], $type) * ($def['sort']['dir'] === 'desc' ? -1 : 1));
            } else {
                usort($outRows, static fn (array $a, array $b): int => self::cmp($a[0], $b[0], $outCols[0]['type']));
            }

            return ['cols' => $outCols, 'rows' => array_map(static fn (array $r): array => array_map([self::class, 'show'], $r), $outRows), 'total' => $total];
        }

        $sortCol = $def['sort']['col'];
        if ($sortCol !== '') {
            $type = $columns[$sortCol]['type'];
            $dir = $def['sort']['dir'] === 'desc' ? -1 : 1;
            usort($rows, static fn (array $a, array $b): int => self::cmp($a[$sortCol] ?? '', $b[$sortCol] ?? '', $type) * $dir);
        }
        $outCols = [];
        foreach ($def['columns'] as $k) {
            $outCols[] = ['key' => $k, 'label' => $columns[$k]['label'], 'type' => $columns[$k]['type']];
        }
        $outRows = [];
        foreach ($rows as $r) {
            $outRows[] = array_map(static fn (string $k): string => self::show($r[$k] ?? ''), $def['columns']);
        }

        return ['cols' => $outCols, 'rows' => $outRows, 'total' => $total];
    }

    /**
     * @param list<array<string, string|float>> $rows
     * @param array{col: string, agg_col: string, agg_fn: string} $group
     * @param array<string, array{label: string, type: string}> $columns
     * @return array{0: list<array{key: string, label: string, type: string}>, 1: list<list<string|float>>}
     */
    private static function grouped(array $rows, array $group, array $columns): array
    {
        $buckets = [];
        foreach ($rows as $r) {
            $v = self::show($r[$group['col']] ?? '');
            $label = $v === '' ? '(vide)' : $v;
            $buckets[$label]['n'] = ($buckets[$label]['n'] ?? 0) + 1;
            if ($group['agg_col'] !== '') {
                $x = $r[$group['agg_col']] ?? '';
                if ($x !== '' && is_numeric($x)) {
                    $buckets[$label]['v'][] = (float) $x;
                }
            }
        }
        $outCols = [
            ['key' => $group['col'], 'label' => $columns[$group['col']]['label'], 'type' => 'text'],
            ['key' => self::COUNT_KEY, 'label' => 'Effectif', 'type' => 'number'],
        ];
        if ($group['agg_col'] !== '') {
            $outCols[] = ['key' => $group['agg_col'], 'label' => self::AGGS[$group['agg_fn']] . ' · ' . $columns[$group['agg_col']]['label'], 'type' => 'number'];
        }
        $out = [];
        foreach ($buckets as $label => $b) {
            $line = [(string) $label, (float) $b['n']];
            if ($group['agg_col'] !== '') {
                $vals = $b['v'] ?? [];
                $line[] = $vals === [] ? '' : match ($group['agg_fn']) {
                    'sum' => array_sum($vals),
                    'avg' => array_sum($vals) / count($vals),
                    'min' => min($vals),
                    default => max($vals),
                };
            }
            $out[] = $line;
        }

        return [$outCols, $out];
    }

    private static function matches(string|float $cell, string $op, string $value, string $type): bool
    {
        $c = trim((string) $cell);
        if ($op === 'empty') {
            return $c === '';
        }
        if ($op === 'notempty') {
            return $c !== '';
        }
        if ($op === 'gt' || $op === 'lt') {
            if ($c === '' || $value === '') {
                return false;
            }
            $r = self::cmp($c, $value, $type);

            return $op === 'gt' ? $r > 0 : $r < 0;
        }
        $a = mb_strtolower($c, 'UTF-8');
        $b = mb_strtolower($value, 'UTF-8');

        return match ($op) {
            'eq' => $a === $b,
            'ne' => $a !== $b,
            default => $b === '' || str_contains($a, $b),
        };
    }

    /** Comparaison selon le type : nombres en nombres, dates en texte ISO, texte sans accents ni casse. Vide en dernier. */
    private static function cmp(string|float $a, string|float $b, string $type): int
    {
        $a = is_float($a) ? (string) $a : $a;
        $b = is_float($b) ? (string) $b : $b;
        if ($a === '' || $b === '') {
            return $a === $b ? 0 : ($a === '' ? 1 : -1);
        }
        if ($type === 'number' && is_numeric($a) && is_numeric($b)) {
            return (float) $a <=> (float) $b;
        }
        if ($type === 'date') {
            return strcmp($a, $b);
        }

        return strcmp(self::fold($a), self::fold($b));
    }

    /** Minuscules sans accents, identique sur tous les systèmes (pas de dépendance à iconv). */
    private static function fold(string $s): string
    {
        return strtr(mb_strtolower($s, 'UTF-8'), [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ã' => 'a', 'å' => 'a', 'ç' => 'c',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ñ' => 'n', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o', 'ú' => 'u', 'ù' => 'u',
            'û' => 'u', 'ü' => 'u', 'ý' => 'y', 'ÿ' => 'y', 'œ' => 'oe', 'æ' => 'ae',
        ]);
    }

    /** Texte d'une cellule : nombres sans décimales inutiles. */
    public static function show(string|float $v): string
    {
        if (is_float($v)) {
            return floor($v) === $v && abs($v) < 1e15 ? (string) (int) $v : rtrim(rtrim(number_format($v, 2, ',', ''), '0'), ',');
        }

        return $v;
    }

    /**
     * CSV (« ; », UTF-8 avec BOM pour Excel). Toute cellule qui commencerait par = + - @ (ou tabulation / retour chariot)
     * et qui n'est pas un nombre est préfixée d'une apostrophe : un tableur qui ouvre le fichier n'exécute rien.
     *
     * @param list<array{key: string, label: string, type: string}> $cols
     * @param list<list<string>> $rows
     */
    public static function toCsv(array $cols, array $rows): string
    {
        $fh = fopen('php://temp', 'r+');
        if ($fh === false) {
            return '';
        }
        $safe = static function (string $v): string {
            $v = str_replace(["\r\n", "\r", "\n"], ' ', $v);
            if ($v !== '' && str_contains("=+-@\t", $v[0]) && preg_match('/^-?\d+(?:[.,]\d+)?$/', $v) !== 1) {
                return "'" . $v;
            }

            return $v;
        };
        fputcsv($fh, array_map(static fn (array $c): string => $safe($c['label']), $cols), ';', '"', '');
        foreach ($rows as $row) {
            fputcsv($fh, array_map($safe, $row), ';', '"', '');
        }
        rewind($fh);
        $csv = (string) stream_get_contents($fh);
        fclose($fh);

        return "\u{FEFF}" . $csv;
    }

    /**
     * Donnée de tableur (bo_sheets) depuis un résultat : au plus 12 colonnes et 200 lignes. Une cellule qui commence
     * par « = » est préfixée d'une apostrophe pour ne pas devenir une formule du tableur.
     *
     * @param list<array{key: string, label: string, type: string}> $cols
     * @param list<list<string>> $rows
     * @return array{data: array{cols: list<array{name: string}>, rows: list<list<string>>}, truncated: bool}
     */
    public static function toSheet(array $cols, array $rows): array
    {
        $maxCols = \App\Services\BackOffice\Home\SheetData::MAX_COLS;
        $maxRows = \App\Services\BackOffice\Home\SheetData::MAX_ROWS;
        $truncated = count($cols) > $maxCols || count($rows) > $maxRows;
        $cols = array_slice($cols, 0, $maxCols);
        $guard = static fn (string $v): string => str_starts_with($v, '=') ? "'" . $v : $v;
        $data = ['cols' => [], 'rows' => []];
        foreach ($cols as $c) {
            $data['cols'][] = ['name' => $guard($c['label'])];
        }
        foreach (array_slice($rows, 0, $maxRows) as $r) {
            $data['rows'][] = array_map($guard, array_slice($r, 0, $maxCols));
        }

        return ['data' => \App\Services\BackOffice\Home\SheetData::sanitize($data), 'truncated' => $truncated];
    }
}
