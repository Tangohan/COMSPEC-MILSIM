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
    /** Catégorie et fichier source de chaque phrase (tools/i18n_categories.py) ; facultatif. */
    public const CATEGORIES = 'mod/COMSPEC_ATAK_Native/localisation/template/categories.json';

    /** @var array<string, array<string, true>> clés des phrases par empreinte du modèle */
    private static array $keySets = [];

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

    /** Clé d'une phrase (packs partagés, categories.json) : sha1 du texte français, 16 caractères hexadécimaux. */
    public static function keyHash(string $fr): string
    {
        return substr(sha1($fr), 0, 16);
    }

    /**
     * Modèle complet pour l'atelier : [clé, français, anglais, catégorie, fichier source], plus les libellés des
     * catégories ({id: {fr, en}}). Sans categories.json, tout est dans « other ».
     *
     * @return array{entries: list<array{0: string, 1: string, 2: string, 3: string, 4: string}>, categories: array<string, array{fr: string, en: string}>}
     */
    public static function catalog(?string $root = null): array
    {
        $cats = self::categoryData($root);
        $out = [];
        foreach (self::entries($root) as [$fr, $en]) {
            $h = self::keyHash($fr);
            $c = $cats['strings'][$h] ?? null;
            $cat = is_array($c) && is_string($c[0] ?? null) ? $c[0] : 'other';
            $file = is_array($c) && is_string($c[1] ?? null) ? $c[1] : '';
            $out[] = [$h, $fr, $en, $cat, $file];
        }
        $labels = [];
        foreach ($cats['categories'] as $id => $l) {
            if (is_string($id) && is_array($l)) {
                $labels[$id] = ['fr' => (string) ($l['fr'] ?? $id), 'en' => (string) ($l['en'] ?? $id)];
            }
        }
        if (!isset($labels['other'])) {
            $labels['other'] = ['fr' => 'Autres', 'en' => 'Other'];
        }

        return ['entries' => $out, 'categories' => $labels];
    }

    /** La phrase de clé $h existe-t-elle dans le modèle publié ? (écritures des packs partagés) */
    public static function hasKey(string $h, ?string $root = null): bool
    {
        $fp = self::fingerprint($root) . '|' . ($root ?? '');
        if (!isset(self::$keySets[$fp])) {
            $set = [];
            foreach (self::entries($root) as [$fr]) {
                $set[self::keyHash($fr)] = true;
            }
            self::$keySets = [$fp => $set];
        }

        return isset(self::$keySets[$fp][$h]);
    }

    /**
     * @return array{categories: array<mixed>, strings: array<mixed>}
     */
    private static function categoryData(?string $root): array
    {
        $path = rtrim($root ?? base_path(), '/') . '/' . self::CATEGORIES;
        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        return [
            'categories' => is_array($data['categories'] ?? null) ? $data['categories'] : [],
            'strings' => is_array($data['strings'] ?? null) ? $data['strings'] : [],
        ];
    }

    /** Empreinte du modèle (ETag de GET /traduire-atak/modele.json) : change à chaque régénération. */
    public static function fingerprint(?string $root = null): string
    {
        $path = self::path($root);
        if (!is_file($path)) {
            return 'none';
        }

        $cat = rtrim($root ?? base_path(), '/') . '/' . self::CATEGORIES;

        return substr(md5((string) md5_file($path) . (is_file($cat) ? (string) md5_file($cat) : '')), 0, 16);
    }

    private static function path(?string $root): string
    {
        return rtrim($root ?? base_path(), '/') . '/' . self::TEMPLATE;
    }
}
