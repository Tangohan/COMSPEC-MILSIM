<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Relève les CREATE TABLE des étapes (database/steps/*.php), des fichiers SQL (migrations/*.sql) et des migrations
 * bootstrap (bootstrap/*.php) qui ne précisent pas COLLATE utf8mb4_unicode_ci.
 *
 * Sans COLLATE, MySQL / MariaDB prend la collation par défaut de la base (utf8mb4_uca1400_ai_ci sous MariaDB 11.4+,
 * utf8mb4_0900_ai_ci sous MySQL 8) : les jointures texte avec les tables en utf8mb4_unicode_ci échouent (1267).
 * Les commentaires sont ignorés. Une définition portable qui ajoute ses options par une variable ($engine, $opts…) est
 * acceptée si le fichier pose COLLATE=utf8mb4_unicode_ci. CREATE TEMPORARY TABLE n'est pas relevé.
 */
final class CreateTableCollationScanner
{
    /** @return list<string> chemins relatifs à la racine, séparateur « / » */
    public static function files(string $root): array
    {
        $out = [];
        foreach (['database/steps/*.php', 'migrations/*.sql', 'bootstrap/*.php'] as $pattern) {
            foreach (glob($root . '/' . $pattern) ?: [] as $path) {
                $out[] = substr(str_replace('\\', '/', $path), strlen(str_replace('\\', '/', $root)) + 1);
            }
        }
        sort($out);

        return $out;
    }

    /** @return list<string> tables créées sans COLLATE utf8mb4_unicode_ci, dans l'ordre du fichier */
    public static function offenders(string $source): array
    {
        $src = str_replace("\r\n", "\n", $source);
        $src = (string) preg_replace('~/\*.*?\*/~s', '', $src);
        $src = (string) preg_replace('~^\s*(--|//|#|\*).*$~m', '', $src);
        $fileHasCollate = preg_match('/COLLATE\s*=?\s*utf8mb4_unicode_ci/i', $src) === 1;
        if (preg_match_all('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?[`"]?([\w{}$]+)[`"]?\s*\(/i', $src, $m, PREG_OFFSET_CAPTURE) === 0) {
            return [];
        }
        $out = [];
        foreach ($m[1] as $i => [$table]) {
            $open = $m[0][$i][1] + strlen($m[0][$i][0]) - 1;
            $depth = 0;
            $len = strlen($src);
            for ($k = $open; $k < $len; $k++) {
                if ($src[$k] === '(') {
                    $depth++;
                } elseif ($src[$k] === ')' && --$depth === 0) {
                    break;
                }
            }
            $tail = substr($src, $k + 1, 220);
            $options = substr($tail, 0, strcspn($tail, ';'));
            if (preg_match('/COLLATE\s*=?\s*utf8mb4_unicode_ci/i', $options) === 1) {
                continue;
            }
            if ($fileHasCollate && preg_match('/\$[A-Za-z_]\w*/', $options) === 1) {
                continue;
            }
            $out[] = $table;
        }

        return $out;
    }

    /** @return array<string, list<string>> fichier => tables sans COLLATE */
    public static function scan(string $root): array
    {
        $out = [];
        foreach (self::files($root) as $file) {
            $tables = self::offenders((string) file_get_contents($root . '/' . $file));
            if ($tables !== []) {
                $out[$file] = $tables;
            }
        }

        return $out;
    }
}
