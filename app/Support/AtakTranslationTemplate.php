<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Modèle de traduction communautaire de l'ATAK natif (page publique /traduire-atak).
 *
 * Source unique : mod/COMSPEC_ATAK_Native/localisation/template/xx.txt, régénéré à chaque version par
 * « python3 tools/i18n.py community » (le VPS déploie le dépôt entier, la page suit donc chaque version sans copie).
 * Une ligne = ["français", "traduction", "anglais"] ; le français est la clé lue par fn_langApply, jamais modifiée.
 * Mêmes règles de lecture que load_community() de tools/i18n.py (guillemet doublé "" dans un texte).
 */
final class AtakTranslationTemplate
{
    public const TEMPLATE = 'mod/COMSPEC_ATAK_Native/localisation/template/xx.txt';

    private const LINE = '/\[\s*"((?:[^"]|"")*)"\s*,\s*"((?:[^"]|"")*)"\s*(?:,\s*"((?:[^"]|"")*)"\s*)?\]/u';

    /**
     * @return list<array{0: string, 1: string}> [français, anglais] dans l'ordre du modèle
     */
    public static function entries(?string $root = null): array
    {
        $path = self::path($root);
        $raw = is_file($path) ? (string) file_get_contents($path) : '';

        return self::parse($raw);
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    public static function parse(string $raw): array
    {
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }
        if (preg_match_all(self::LINE, $raw, $m, PREG_SET_ORDER) === false) {
            return [];
        }
        $out = [];
        $seen = [];
        foreach ($m as $row) {
            $fr = str_replace('""', '"', $row[1]);
            if ($fr === '' || isset($seen[$fr])) {
                continue;
            }
            $seen[$fr] = true;
            $out[] = [$fr, str_replace('""', '"', (string) ($row[3] ?? ''))];
        }

        return $out;
    }

    /** Empreinte du modèle (ETag de GET /traduire-atak/modele.json) : change à chaque régénération. */
    public static function fingerprint(?string $root = null): string
    {
        $path = self::path($root);
        if (!is_file($path)) {
            return 'none';
        }

        return substr((string) md5_file($path), 0, 16);
    }

    private static function path(?string $root): string
    {
        return rtrim($root ?? base_path(), '/') . '/' . self::TEMPLATE;
    }
}
