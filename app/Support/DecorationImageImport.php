<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Import d’insignes de décoration (PNG, WebP, JPEG) : un fichier = une décoration.
 * Logique pure, sans base ni système de fichiers (testée unitairement).
 * La même dérivation nom / code est reproduite côté navigateur (awards_index.php).
 */
final class DecorationImageImport
{
    public const MAX_FILES = 20;
    public const CODE_MAX = 32;

    /**
     * Met à plat une entrée $_FILES multiple (name[], tmp_name[]…) en liste de fichiers.
     *
     * @param mixed $entry
     * @return list<array{name: string, type: string, tmp_name: string, error: int, size: int}>
     */
    public static function normalizeUploads(mixed $entry): array
    {
        if (!is_array($entry) || !isset($entry['name'])) {
            return [];
        }
        if (!is_array($entry['name'])) {
            return [[
                'name' => (string) $entry['name'],
                'type' => (string) ($entry['type'] ?? ''),
                'tmp_name' => (string) ($entry['tmp_name'] ?? ''),
                'error' => (int) ($entry['error'] ?? UPLOAD_ERR_NO_FILE),
                'size' => (int) ($entry['size'] ?? 0),
            ]];
        }
        $out = [];
        foreach (array_keys($entry['name']) as $i) {
            $out[] = [
                'name' => (string) ($entry['name'][$i] ?? ''),
                'type' => (string) ($entry['type'][$i] ?? ''),
                'tmp_name' => (string) ($entry['tmp_name'][$i] ?? ''),
                'error' => (int) ($entry['error'][$i] ?? UPLOAD_ERR_NO_FILE),
                'size' => (int) ($entry['size'][$i] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * « ranger_tab.png » → « Ranger Tab » ; « Air-Assault-Badge (1).png » → « Air Assault Badge ».
     */
    public static function nameFromFilename(string $filename): string
    {
        $base = basename(str_replace('\\', '/', $filename));
        $base = (string) preg_replace('/\.[A-Za-z0-9]{2,5}$/', '', $base);
        $base = (string) preg_replace('/\s*\(\d+\)\s*$/', '', $base);
        $base = (string) preg_replace('/[_\-.]+/', ' ', $base);
        $base = trim((string) preg_replace('/\s+/u', ' ', $base));
        if ($base === '') {
            return '';
        }
        if (mb_strtolower($base, 'UTF-8') === $base) {
            $base = mb_convert_case($base, MB_CASE_TITLE, 'UTF-8');
        }

        return mb_substr($base, 0, 180, 'UTF-8');
    }

    /**
     * « Brevet de chef d’équipe » → « BREVET_DE_CHEF_D_EQUIPE » (A-Z, 0-9, _ ; 32 caractères max).
     */
    public static function codeFromName(string $name): string
    {
        $map = [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ã' => 'a', 'å' => 'a',
            'ç' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'í' => 'i', 'ì' => 'i', 'ñ' => 'n',
            'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'ò' => 'o', 'õ' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u', 'ÿ' => 'y', 'œ' => 'oe', 'æ' => 'ae',
        ];
        $s = strtr(mb_strtolower($name, 'UTF-8'), $map);
        $s = strtoupper((string) preg_replace('/[^a-z0-9]+/', '_', $s));
        $s = trim($s, '_');
        if (strlen($s) > self::CODE_MAX) {
            $s = rtrim(substr($s, 0, self::CODE_MAX), '_');
        }

        return $s;
    }

    /**
     * Code saisi par l’administrateur, nettoyé (même alphabet que codeFromName).
     */
    public static function sanitizeCode(string $code): string
    {
        $s = strtoupper(trim($code));
        $s = (string) preg_replace('/[^A-Z0-9_\-]+/', '_', $s);
        $s = trim($s, '_');

        return strlen($s) > self::CODE_MAX ? rtrim(substr($s, 0, self::CODE_MAX), '_') : $s;
    }

    /**
     * Construit les lignes d’import : fichier + nom / code / branche / motif saisis (ou dérivés).
     * Les fichiers absents (UPLOAD_ERR_NO_FILE) sont ignorés ; les codes en double gardent la première ligne.
     *
     * @param list<array{name: string, type: string, tmp_name: string, error: int, size: int}> $files
     * @param array<int|string, mixed> $names
     * @param array<int|string, mixed> $codes
     * @param array<int|string, mixed> $branches
     * @param array<int|string, mixed> $criteria
     * @return array{rows: list<array{file: array{name: string, type: string, tmp_name: string, error: int, size: int}, name: string, code: string, branch: string, criterion: string}>, skipped: list<string>}
     */
    public static function buildRows(array $files, array $names, array $codes, array $branches, array $criteria): array
    {
        $rows = [];
        $skipped = [];
        $seen = [];
        foreach (array_values($files) as $i => $file) {
            if ($file['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if (count($rows) >= self::MAX_FILES) {
                $skipped[] = $file['name'] . ' (limite de ' . self::MAX_FILES . ' fichiers)';
                continue;
            }
            $name = trim((string) ($names[$i] ?? ''));
            if ($name === '') {
                $name = self::nameFromFilename($file['name']);
            }
            $code = self::sanitizeCode((string) ($codes[$i] ?? ''));
            if ($code === '') {
                $code = self::codeFromName($name);
            }
            if ($name === '' || $code === '') {
                $skipped[] = $file['name'] . ' (nom illisible)';
                continue;
            }
            if (isset($seen[$code])) {
                $skipped[] = $file['name'] . ' (code ' . $code . ' en double)';
                continue;
            }
            $seen[$code] = true;
            $rows[] = [
                'file' => $file,
                'name' => mb_substr($name, 0, 180, 'UTF-8'),
                'code' => $code,
                'branch' => mb_substr(trim((string) ($branches[$i] ?? '')), 0, 80, 'UTF-8'),
                'criterion' => trim((string) ($criteria[$i] ?? '')),
            ];
        }

        return ['rows' => $rows, 'skipped' => $skipped];
    }
}
