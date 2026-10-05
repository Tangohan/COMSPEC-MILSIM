<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Database;
use PDO;
use Throwable;

/**
 * Abrégés automatiques des noms d'unité et de fonction, partagés par le back-office et l'ATAK.
 *
 * Règles (les mêmes que comspec_atak_native_fnc_abbrev côté jeu) :
 * - un abrégé saisi sur la fiche unité (units.short_label) gagne toujours ;
 * - dans un chemin d'organigramme, le nom du parent n'est pas répété :
 *   « 24th STS Gold Team SOF TACP » sous « 24th Special Tactics Squadron - Gold Team » → « SOF TACP » ;
 * - un nom trop long devient un sigle : les suites de mots deviennent leurs initiales
 *   (« Joint Special Operations Command » → « JSOC »), les numéros et les sigles déjà en
 *   majuscules restent tels quels, les mots de liaison (of, the, de, des…) sont ignorés
 *   et un complément court après un tiret est gardé (« 24th STS Gold Team »).
 * Le nom complet reste en infobulle (html()).
 */
final class UnitAbbreviation
{
    public const MAX_LENGTH = 24;

    /** Au-delà, un segment après un tiret est lui aussi réduit en sigle. */
    private const KEEP_SEGMENT_LENGTH = 14;

    private const STOP_WORDS = [
        'of', 'the', 'and', 'for', 'a', 'an', 'at', 'in', 'on', 'to',
        'de', 'du', 'des', 'la', 'le', 'les', 'et', 'd', 'l', 'en', 'au', 'aux', 'pour', 'sur',
    ];

    /** @var array<int, array<string, string>> tenant → nom normalisé → abrégé saisi */
    private static array $overrides = [];

    private static ?bool $hasColumn = null;

    /**
     * Sigle automatique d'un nom (sans tenir compte de la longueur).
     */
    public static function auto(string $name): string
    {
        $name = self::clean($name);
        if ($name === '') {
            return '';
        }
        $segments = preg_split('/\s+[-–—|·:\/]\s+|\s*,\s*/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [$name];
        $out = [];
        foreach ($segments as $i => $segment) {
            $segment = trim($segment);
            if ($segment === '') {
                continue;
            }
            $piece = ($i > 0 && mb_strlen($segment) <= self::KEEP_SEGMENT_LENGTH)
                ? $segment
                : self::acronymSegment($segment);
            // « SOAR - The Special Operations Action Regiments » : le développé d'un sigle déjà là ne le répète pas.
            if ($i > 0 && in_array(self::norm($piece), array_map(self::norm(...), $out), true)) {
                continue;
            }
            $out[] = $piece;
        }
        $result = trim(implode(' ', array_filter($out, static fn (string $s): bool => $s !== '')));

        return $result !== '' ? $result : $name;
    }

    /**
     * Abrégé d'un nom hors contexte : saisie manuelle, sinon sigle s'il dépasse $max.
     */
    public static function standalone(string $name, int $tenantId = 0, int $max = self::MAX_LENGTH): string
    {
        $name = self::clean($name);
        if ($name === '') {
            return '';
        }
        $override = self::override($name, $tenantId);
        if ($override !== null) {
            return $override;
        }

        return mb_strlen($name) > $max ? self::auto($name) : $name;
    }

    /**
     * Abrégé d'une unité affichée sous ses parents (fil d'Ariane, organigramme) :
     * saisie manuelle, sinon nom sans le préfixe répété d'un parent, sinon standalone().
     *
     * @param list<string> $ancestors du plus haut au parent direct
     */
    public static function forUnit(string $name, array $ancestors = [], int $tenantId = 0, int $max = self::MAX_LENGTH): string
    {
        $name = self::clean($name);
        if ($name === '') {
            return '';
        }
        $override = self::override($name, $tenantId);
        if ($override !== null) {
            return $override;
        }
        $rest = self::within($name, self::contexts($ancestors, $tenantId));
        if ($rest !== null && $rest !== '') {
            return mb_strlen($rest) > $max ? self::auto($rest) : $rest;
        }

        return self::standalone($name, $tenantId, $max);
    }

    /**
     * Abrégé d'une fonction tenue dans une unité : on retire le nom de l'unité ou d'un parent
     * quand la fonction le répète. Une fonction identique au nom de l'unité prend l'abrégé de l'unité.
     *
     * @param list<string> $ancestors du plus haut au parent direct
     */
    public static function role(string $role, string $unitName, array $ancestors = [], int $tenantId = 0, int $max = self::MAX_LENGTH): string
    {
        $role = self::clean($role);
        if ($role === '') {
            return '';
        }
        $unitName = self::clean($unitName);
        if ($unitName !== '' && self::norm($role) === self::norm($unitName)) {
            return self::forUnit($unitName, $ancestors, $tenantId, $max);
        }
        $contexts = self::contexts($unitName !== '' ? [...$ancestors, $unitName] : $ancestors, $tenantId);
        $rest = self::within($role, $contexts);
        if ($rest !== null && $rest !== '') {
            return mb_strlen($rest) > $max ? self::auto($rest) : $rest;
        }

        return mb_strlen($role) > $max ? self::auto($role) : $role;
    }

    /**
     * Fil d'Ariane « A / B / C » : chaque élément abrégé par rapport aux précédents.
     *
     * @param list<string>|string $path
     * @return list<array{full: string, short: string}>
     */
    public static function trail(array|string $path, int $tenantId = 0, int $max = self::MAX_LENGTH): array
    {
        $parts = is_string($path)
            ? array_values(array_filter(array_map('trim', preg_split('/\s*\/\s*/', $path) ?: []), static fn (string $p): bool => $p !== ''))
            : array_values(array_filter(array_map(static fn ($p): string => self::clean((string) $p), $path), static fn (string $p): bool => $p !== ''));
        $out = [];
        $seen = [];
        foreach ($parts as $part) {
            $out[] = ['full' => $part, 'short' => self::forUnit($part, $seen, $tenantId, $max)];
            $seen[] = $part;
        }

        return $out;
    }

    /**
     * Ce qui reste de $label après le plus long préfixe trouvé dans $contexts
     * (comparaison sans casse ni accents, séparateurs ignorés). '' si $label est un contexte,
     * null si aucun contexte n'en est le préfixe.
     *
     * @param list<string> $contexts
     */
    public static function within(string $label, array $contexts): ?string
    {
        $label = self::clean($label);
        $tokens = self::tokens($label);
        if ($tokens === []) {
            return null;
        }
        $best = 0;
        foreach ($contexts as $context) {
            $ctx = array_column(self::tokens(self::clean((string) $context)), 0);
            $n = count($ctx);
            if ($n === 0 || $n > count($tokens) || $n <= $best) {
                continue;
            }
            $match = true;
            for ($i = 0; $i < $n; $i++) {
                if ($tokens[$i][0] !== $ctx[$i]) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                $best = $n;
            }
        }
        if ($best === 0) {
            return null;
        }
        if ($best >= count($tokens)) {
            return '';
        }
        $rest = substr($label, $tokens[$best][1]);

        return trim(preg_replace('/^[\s\-–—|·:\/,]+/u', '', $rest) ?? $rest);
    }

    /**
     * <abbr> avec le nom complet en infobulle, ou le nom échappé s'il n'est pas abrégé.
     */
    public static function html(string $full, string $short): string
    {
        $full = self::clean($full);
        $short = self::clean($short);
        $e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        if ($short === '' || $short === $full) {
            return $e($full);
        }

        return '<abbr class="ath-abbr" title="' . $e($full) . '">' . $e($short) . '</abbr>';
    }

    /**
     * Abrégé saisi pour une unité de ce nom (tenant courant si $tenantId = 0).
     */
    public static function override(string $name, int $tenantId = 0): ?string
    {
        $map = self::overrides($tenantId);
        $short = $map[self::norm($name)] ?? '';

        return $short !== '' ? $short : null;
    }

    /**
     * @return array<string, string> nom normalisé → abrégé saisi
     */
    public static function overrides(int $tenantId = 0): array
    {
        if ($tenantId < 1) {
            $tenantId = class_exists(\App\Core\Session::class) ? (int) \App\Core\Session::get('tenant_id') : 0;
        }
        if ($tenantId < 1) {
            return [];
        }
        if (isset(self::$overrides[$tenantId])) {
            return self::$overrides[$tenantId];
        }
        $map = [];
        try {
            $pdo = Database::getPdo();
            if (self::$hasColumn === null) {
                $st = $pdo->prepare(
                    'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
                );
                $st->execute(['units', 'short_label']);
                self::$hasColumn = (bool) $st->fetchColumn();
            }
            if (self::$hasColumn) {
                $st = $pdo->prepare("SELECT name, short_label FROM units WHERE tenant_id = ? AND short_label IS NOT NULL AND short_label <> ''");
                $st->execute([$tenantId]);
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $map[self::norm((string) $row['name'])] = self::clean((string) $row['short_label']);
                }
            }
        } catch (Throwable) {
            $map = [];
        }

        return self::$overrides[$tenantId] = $map;
    }

    /**
     * Tests et enregistrement d'une fiche unité : remplace le cache des saisies manuelles.
     *
     * @param array<string, string>|null $map nom → abrégé ; null vide le cache
     */
    public static function primeOverrides(int $tenantId, ?array $map): void
    {
        if ($map === null) {
            unset(self::$overrides[$tenantId]);

            return;
        }
        $norm = [];
        foreach ($map as $name => $short) {
            $norm[self::norm((string) $name)] = self::clean((string) $short);
        }
        self::$overrides[$tenantId] = $norm;
    }

    /**
     * @param list<string> $names
     * @return list<string> noms complets, sigles automatiques et abrégés saisis
     */
    private static function contexts(array $names, int $tenantId): array
    {
        $out = [];
        foreach ($names as $name) {
            $name = self::clean((string) $name);
            if ($name === '') {
                continue;
            }
            $out[] = $name;
            $out[] = self::auto($name);
            $override = self::override($name, $tenantId);
            if ($override !== null) {
                $out[] = $override;
            }
        }

        return array_values(array_unique($out));
    }

    private static function acronymSegment(string $segment): string
    {
        $words = preg_split('/\s+/u', $segment, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = [];
        $run = [];
        $flush = static function () use (&$run, &$out): void {
            if (count($run) === 1) {
                $out[] = $run[0][1];
            } elseif ($run !== []) {
                $out[] = implode('', array_column($run, 0));
            }
            $run = [];
        };
        foreach ($words as $word) {
            // « d'Infanterie », « l’Air » : l'élision ne compte pas.
            $word = preg_replace('/^[dlDL][\'’]/u', '', $word) ?? $word;
            $bare = preg_replace('/[^\p{L}\p{N}]/u', '', $word) ?? '';
            if ($bare === '') {
                continue;
            }
            if (preg_match('/^\p{N}/u', $bare) || (mb_strlen($bare) >= 2 && mb_strtoupper($bare) === $bare)) {
                // Numéro (24th, 1er, 3e) ou sigle déjà en majuscules (SOF, TACP).
                $flush();
                $out[] = $word;

                continue;
            }
            if (in_array(self::fold($bare), self::STOP_WORDS, true)) {
                continue;
            }
            $run[] = [strtoupper(self::fold(mb_substr($bare, 0, 1))), $word];
        }
        $flush();

        return implode(' ', $out);
    }

    /**
     * Mots normalisés avec leur position (octets) dans la chaîne d'origine.
     *
     * @return list<array{0: string, 1: int}>
     */
    private static function tokens(string $s): array
    {
        if ($s === '' || !preg_match_all('/[\p{L}\p{N}]+/u', $s, $m, PREG_OFFSET_CAPTURE)) {
            return [];
        }
        $out = [];
        foreach ($m[0] as [$word, $offset]) {
            $out[] = [self::fold($word), (int) $offset];
        }

        return $out;
    }

    private static function norm(string $s): string
    {
        return implode(' ', array_column(self::tokens(self::clean($s)), 0));
    }

    private static function fold(string $s): string
    {
        $s = mb_strtolower($s, 'UTF-8');
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if ($t === false || $t === '') {
            return $s;
        }

        $t = preg_replace('/[^a-z0-9]/', '', strtolower($t)) ?? '';

        return $t !== '' ? $t : $s;
    }

    private static function clean(string $s): string
    {
        return trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
    }
}
