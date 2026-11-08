<?php

declare(strict_types=1);

namespace App\Services\BackOffice\Home;

/**
 * Petit tableur de l'accueil du back-office : données, limites, formules et CSV. Calculs purs.
 *
 * Format stocké : {"cols": [{"name": "…"}], "rows": [["…", …], …]}, cellules en texte brut.
 * Formules : une cellule qui commence par « = » : SOMME / SUM, MOYENNE / AVERAGE, NB / COUNT, avec des plages
 * (A1:A10) ou des cellules (A1;B2). La ligne 1 est la première ligne de données (les en-têtes sont à part).
 */
final class SheetData
{
    public const MAX_COLS = 12;
    public const MAX_ROWS = 200;
    public const MAX_CELL = 200;
    public const MAX_TITLE = 80;
    public const MAX_HEADER = 40;

    private const FUNCS = [
        'SOMME' => 'sum', 'SUM' => 'sum',
        'MOYENNE' => 'avg', 'AVERAGE' => 'avg',
        'NB' => 'count', 'COUNT' => 'count',
    ];

    /** @return array{cols: list<array{name: string}>, rows: list<list<string>>} */
    public static function blank(int $cols = 4, int $rows = 5): array
    {
        $cols = max(1, min(self::MAX_COLS, $cols));
        $rows = max(1, min(self::MAX_ROWS, $rows));
        $out = ['cols' => [], 'rows' => []];
        for ($c = 0; $c < $cols; $c++) {
            $out['cols'][] = ['name' => 'Colonne ' . self::letter($c)];
        }
        for ($r = 0; $r < $rows; $r++) {
            $out['rows'][] = array_fill(0, $cols, '');
        }

        return $out;
    }

    /**
     * Données lues en base ou reçues : bornées, rectangulaires, texte uniquement.
     *
     * @return array{cols: list<array{name: string}>, rows: list<list<string>>}
     */
    public static function sanitize(mixed $raw): array
    {
        $raw = is_array($raw) ? $raw : [];
        $cols = [];
        foreach (array_slice(is_array($raw['cols'] ?? null) ? $raw['cols'] : [], 0, self::MAX_COLS) as $i => $col) {
            $name = is_array($col) ? (string) ($col['name'] ?? '') : (is_scalar($col) ? (string) $col : '');
            $cols[] = ['name' => self::clean($name, self::MAX_HEADER)];
        }
        if ($cols === []) {
            $cols[] = ['name' => 'Colonne A'];
        }
        $n = count($cols);
        $rows = [];
        foreach (array_slice(is_array($raw['rows'] ?? null) ? $raw['rows'] : [], 0, self::MAX_ROWS) as $row) {
            $row = is_array($row) ? array_values($row) : [];
            $line = [];
            for ($c = 0; $c < $n; $c++) {
                $v = $row[$c] ?? '';
                $line[] = self::clean(is_scalar($v) ? (string) $v : '', self::MAX_CELL);
            }
            $rows[] = $line;
        }

        return ['cols' => $cols, 'rows' => $rows];
    }

    public static function clean(string $v, int $max): string
    {
        $v = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $v);
        $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '';

        return mb_substr(trim($v), 0, $max, 'UTF-8');
    }

    public static function cleanTitle(string $title): string
    {
        $t = self::clean($title, self::MAX_TITLE);

        return $t !== '' ? $t : 'Tableau sans titre';
    }

    /** Lettre de colonne : 0 → A, 11 → L. */
    public static function letter(int $c): string
    {
        return chr(65 + max(0, min(25, $c)));
    }

    /**
     * Change une cellule si sa valeur actuelle est toujours celle que la personne a vue (conflit sinon).
     *
     * @param array{cols: list<array{name: string}>, rows: list<list<string>>} $data
     * @return array{0: array{cols: list<array{name: string}>, rows: list<list<string>>}, 1: 'ok'|'conflict'|'bounds', 2: string}
     *         données, résultat, valeur actuelle (en cas de conflit)
     */
    public static function setCell(array $data, int $row, int $col, string $value, ?string $seen): array
    {
        if ($row < 0 || $col < 0 || $row >= count($data['rows']) || $col >= count($data['cols'])) {
            return [$data, 'bounds', ''];
        }
        $current = $data['rows'][$row][$col];
        if ($seen !== null && $current !== self::clean($seen, self::MAX_CELL)) {
            return [$data, 'conflict', $current];
        }
        $data['rows'][$row][$col] = self::clean($value, self::MAX_CELL);

        return [$data, 'ok', $data['rows'][$row][$col]];
    }

    /**
     * Opérations de structure : add_row, del_row, add_col, del_col, rename_col.
     *
     * @param array{cols: list<array{name: string}>, rows: list<list<string>>} $data
     * @return array{0: array{cols: list<array{name: string}>, rows: list<list<string>>}, 1: string} données, erreur ('' si ok)
     */
    public static function structure(array $data, string $op, int $index, string $name = ''): array
    {
        $nCols = count($data['cols']);
        $nRows = count($data['rows']);
        switch ($op) {
            case 'add_row':
                if ($nRows >= self::MAX_ROWS) {
                    return [$data, sprintf('%d lignes au plus.', self::MAX_ROWS)];
                }
                $at = $index < 0 || $index > $nRows ? $nRows : $index;
                array_splice($data['rows'], $at, 0, [array_fill(0, $nCols, '')]);

                return [$data, ''];
            case 'del_row':
                if ($index < 0 || $index >= $nRows) {
                    return [$data, 'Ligne introuvable.'];
                }
                array_splice($data['rows'], $index, 1);

                return [$data, ''];
            case 'add_col':
                if ($nCols >= self::MAX_COLS) {
                    return [$data, sprintf('%d colonnes au plus.', self::MAX_COLS)];
                }
                $data['cols'][] = ['name' => self::clean($name, self::MAX_HEADER) ?: 'Colonne ' . self::letter($nCols)];
                foreach ($data['rows'] as $i => $row) {
                    $data['rows'][$i][] = '';
                }

                return [$data, ''];
            case 'del_col':
                if ($nCols <= 1 || $index < 0 || $index >= $nCols) {
                    return [$data, $nCols <= 1 ? 'Un tableau garde au moins une colonne.' : 'Colonne introuvable.'];
                }
                array_splice($data['cols'], $index, 1);
                foreach ($data['rows'] as $i => $row) {
                    array_splice($data['rows'][$i], $index, 1);
                }

                return [$data, ''];
            case 'rename_col':
                if ($index < 0 || $index >= $nCols) {
                    return [$data, 'Colonne introuvable.'];
                }
                $data['cols'][$index]['name'] = self::clean($name, self::MAX_HEADER);

                return [$data, ''];
        }

        return [$data, 'Action inconnue.'];
    }

    /**
     * Valeurs affichées : texte tel quel, formules calculées (« #ERR » si invalide, « #CYCLE » si circulaire).
     *
     * @param array{cols: list<array{name: string}>, rows: list<list<string>>} $data
     * @return list<list<string>>
     */
    public static function computed(array $data): array
    {
        $cache = [];
        $out = [];
        foreach ($data['rows'] as $r => $row) {
            $line = [];
            foreach ($row as $c => $v) {
                $line[] = str_starts_with($v, '=') ? self::display(self::cellValue($data, $r, $c, $cache, [])) : $v;
            }
            $out[] = $line;
        }

        return $out;
    }

    /** Nombre écrit à la française ou à l'anglaise (1 234,5 ; 1234.5), null si ce n'est pas un nombre. */
    public static function number(string $v): ?float
    {
        $v = str_replace(["\u{00A0}", "\u{202F}", ' '], '', trim($v));
        if ($v === '') {
            return null;
        }
        if (preg_match('/^-?\d+(?:[.,]\d+)?$/', $v) !== 1) {
            return null;
        }

        return (float) str_replace(',', '.', $v);
    }

    /**
     * @param array{cols: list<array{name: string}>, rows: list<list<string>>} $data
     * @param array<string, float|string|null> $cache
     * @param array<string, true> $stack
     */
    private static function cellValue(array $data, int $r, int $c, array &$cache, array $stack): float|string|null
    {
        $key = $r . ':' . $c;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        $raw = $data['rows'][$r][$c] ?? '';
        if (!str_starts_with($raw, '=')) {
            return $cache[$key] = self::number($raw) ?? ($raw === '' ? null : $raw);
        }
        if (isset($stack[$key])) {
            return '#CYCLE';
        }
        $stack[$key] = true;
        if (preg_match('/^=\s*([A-Za-zÉé]+)\s*\(\s*([A-Za-z0-9:;,\s]*)\)\s*$/u', $raw, $m) !== 1) {
            return $cache[$key] = '#ERR';
        }
        $fn = self::FUNCS[mb_strtoupper($m[1], 'UTF-8')] ?? null;
        $cells = self::refs($m[2], count($data['rows']), count($data['cols']));
        if ($fn === null || $cells === null) {
            return $cache[$key] = '#ERR';
        }
        $nums = [];
        foreach ($cells as [$rr, $cc]) {
            $v = self::cellValue($data, $rr, $cc, $cache, $stack);
            if ($v === '#CYCLE' || $v === '#ERR') {
                return $cache[$key] = $v;
            }
            if (is_float($v)) {
                $nums[] = $v;
            }
        }
        $result = match ($fn) {
            'sum' => array_sum($nums),
            'avg' => $nums === [] ? '#DIV/0' : array_sum($nums) / count($nums),
            default => (float) count($nums),
        };

        return $cache[$key] = $result;
    }

    /**
     * « A1:B3;C5 » → cellules (ligne, colonne) dans les bornes, null si la référence est invalide.
     *
     * @return list<array{0: int, 1: int}>|null
     */
    public static function refs(string $spec, int $rows, int $cols): ?array
    {
        $out = [];
        foreach (preg_split('/[;,]/', $spec) ?: [] as $part) {
            $part = strtoupper(str_replace(' ', '', $part));
            if ($part === '') {
                continue;
            }
            if (preg_match('/^([A-L])(\d{1,3})(?::([A-L])(\d{1,3}))?$/', $part, $m) !== 1) {
                return null;
            }
            $c1 = ord($m[1]) - 65;
            $r1 = (int) $m[2] - 1;
            $c2 = isset($m[3]) && $m[3] !== '' ? ord($m[3]) - 65 : $c1;
            $r2 = isset($m[4]) && $m[4] !== '' ? (int) $m[4] - 1 : $r1;
            if ($r1 < 0 || $r2 < 0) {
                return null;
            }
            for ($r = min($r1, $r2); $r <= min(max($r1, $r2), $rows - 1); $r++) {
                for ($c = min($c1, $c2); $c <= min(max($c1, $c2), $cols - 1); $c++) {
                    $out[] = [$r, $c];
                }
            }
        }

        return $out === [] ? null : $out;
    }

    private static function display(float|string|null $v): string
    {
        if ($v === null) {
            return '';
        }
        if (is_string($v)) {
            return $v;
        }
        if (floor($v) === $v && abs($v) < 1e15) {
            return (string) (int) $v;
        }

        return rtrim(rtrim(number_format($v, 2, ',', ''), '0'), ',');
    }

    /**
     * Export CSV (séparateur « ; », UTF-8 avec BOM pour Excel) : en-têtes puis valeurs calculées. Une valeur qui
     * commencerait par = + - @ est préfixée d'une apostrophe (pas de formule exécutée par le tableur qui l'ouvre).
     *
     * @param array{cols: list<array{name: string}>, rows: list<list<string>>} $data
     */
    public static function toCsv(array $data): string
    {
        $fh = fopen('php://temp', 'r+');
        if ($fh === false) {
            return '';
        }
        $safe = static function (string $v): string {
            return $v !== '' && str_contains('=+-@', $v[0]) && self::number($v) === null ? "'" . $v : $v;
        };
        fputcsv($fh, array_map(static fn (array $c): string => $safe($c['name']), $data['cols']), ';', '"', '');
        foreach (self::computed($data) as $row) {
            fputcsv($fh, array_map($safe, $row), ';', '"', '');
        }
        rewind($fh);
        $csv = (string) stream_get_contents($fh);
        fclose($fh);

        return "\u{FEFF}" . $csv;
    }

    /**
     * Import CSV (« ; » ou « , » détecté sur la première ligne) : la première ligne donne les en-têtes. Coupé aux
     * limites ; renvoie aussi un avertissement si des lignes ou colonnes ont été laissées de côté.
     *
     * @return array{0: array{cols: list<array{name: string}>, rows: list<list<string>>}, 1: string}
     */
    public static function fromCsv(string $csv): array
    {
        $csv = preg_replace('/^\x{FEFF}/u', '', $csv) ?? $csv;
        if (!mb_check_encoding($csv, 'UTF-8')) {
            $csv = mb_convert_encoding($csv, 'UTF-8', 'Windows-1252');
        }
        $first = strtok($csv, "\n") ?: '';
        $sep = substr_count($first, ';') >= substr_count($first, ',') ? ';' : ',';
        $fh = fopen('php://temp', 'r+');
        if ($fh === false) {
            return [self::blank(), 'Fichier illisible.'];
        }
        fwrite($fh, $csv);
        rewind($fh);
        $lines = [];
        $cut = false;
        while (($line = fgetcsv($fh, 0, $sep, '"', '')) !== false) {
            if ($line === [null]) {
                continue;
            }
            if (count($lines) > self::MAX_ROWS) {
                $cut = true;
                break;
            }
            if (count($line) > self::MAX_COLS) {
                $cut = true;
            }
            $lines[] = array_map(static fn ($v): string => (string) $v, $line);
        }
        fclose($fh);
        if ($lines === []) {
            return [self::blank(), 'Le fichier est vide.'];
        }
        $header = array_shift($lines);
        $width = min(self::MAX_COLS, max(count($header), ...array_map('count', $lines ?: [[]])));
        $data = self::sanitize([
            'cols' => array_map(static fn (int $i): array => ['name' => $header[$i] ?? ''], range(0, $width - 1)),
            'rows' => $lines,
        ]);
        foreach ($data['rows'] as $r => $row) {
            foreach ($row as $c => $v) {
                // Une valeur importée n'est une formule que si elle suit la syntaxe connue ; sinon c'est du texte.
                if (str_starts_with($v, '=') && preg_match('/^=\s*(SOMME|SUM|MOYENNE|AVERAGE|NB|COUNT)\s*\(/iu', $v) !== 1) {
                    $data['rows'][$r][$c] = "'" . mb_substr($v, 0, self::MAX_CELL - 1, 'UTF-8');
                }
            }
        }

        return [$data, $cut ? sprintf('Import limité à %d colonnes et %d lignes.', self::MAX_COLS, self::MAX_ROWS) : ''];
    }
}
