<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Texte de tableau SQF (sortie de `str`, lu en jeu par `parseSimpleArray`) ⇄ PHP.
 *
 * Les campagnes SSE voyagent sous cette forme : ["COMSPEC_SSE_CAMPAIGN", 1, <paquet>],
 * où le paquet est produit par comspec_sse_fnc_bioPack (une HashMap devient ["#M", [[clé, valeur], …]]).
 * decode() lit le texte, unpack() rend les HashMaps en tableaux associatifs PHP,
 * pack() et encode() refont le texte que le jeu sait relire.
 */
final class SqfArrayText
{
    private const MAP_TAG = '#M';

    /**
     * @return mixed tableau PHP (listes), chaîne, nombre, booléen ou null
     */
    public static function decode(string $text): mixed
    {
        $i = 0;
        $len = strlen($text);
        $value = self::parseValue($text, $i, $len, 0);
        self::skipSpace($text, $i, $len);
        if ($i < $len) {
            throw new \InvalidArgumentException('Texte de campagne : contenu en trop après la fin du tableau.');
        }

        return $value;
    }

    public static function encode(mixed $value): string
    {
        if ($value === null) {
            return '""';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_float($value)) {
            if (floor($value) === $value && abs($value) < 1e15) {
                return (string) (int) $value;
            }

            return rtrim(rtrim(sprintf('%.6F', $value), '0'), '.');
        }
        if (is_array($value)) {
            return '[' . implode(',', array_map([self::class, 'encode'], array_values($value))) . ']';
        }

        return '"' . str_replace('"', '""', (string) $value) . '"';
    }

    /**
     * Paquet bioPack → PHP : chaque HashMap devient un tableau associatif marqué (clé MAP_MARK).
     */
    public static function unpack(mixed $v): mixed
    {
        if (is_array($v) && count($v) === 2 && ($v[0] ?? null) === self::MAP_TAG && is_array($v[1] ?? null)) {
            $map = [self::MAP_MARK => true];
            foreach ($v[1] as $pair) {
                if (is_array($pair) && count($pair) === 2 && (is_string($pair[0]) || is_int($pair[0]) || is_float($pair[0]))) {
                    $map[(string) $pair[0]] = self::unpack($pair[1]);
                }
            }

            return $map;
        }
        if (is_array($v)) {
            return array_map([self::class, 'unpack'], $v);
        }

        return $v;
    }

    /** Clé qui distingue une HashMap d'une liste après unpack(). */
    public const MAP_MARK = "\0map";

    public static function pack(mixed $v): mixed
    {
        if (is_array($v) && array_key_exists(self::MAP_MARK, $v)) {
            $pairs = [];
            foreach ($v as $k => $item) {
                if ($k === self::MAP_MARK) {
                    continue;
                }
                $pairs[] = [(string) $k, self::pack($item)];
            }

            return [self::MAP_TAG, $pairs];
        }
        if (is_array($v)) {
            return array_map([self::class, 'pack'], array_values($v));
        }

        return $v;
    }

    /** @return array<string, mixed> HashMap vide prête à remplir */
    public static function newMap(): array
    {
        return [self::MAP_MARK => true];
    }

    public static function isMap(mixed $v): bool
    {
        return is_array($v) && array_key_exists(self::MAP_MARK, $v);
    }

    /**
     * Entrées d'une HashMap dépaquetée, sans le marqueur.
     *
     * @return array<string, mixed>
     */
    public static function entries(mixed $map): array
    {
        if (!self::isMap($map)) {
            return [];
        }
        unset($map[self::MAP_MARK]);

        return $map;
    }

    private static function parseValue(string $s, int &$i, int $len, int $depth): mixed
    {
        if ($depth > 64) {
            throw new \InvalidArgumentException('Texte de campagne : imbrication trop profonde.');
        }
        self::skipSpace($s, $i, $len);
        if ($i >= $len) {
            throw new \InvalidArgumentException('Texte de campagne : fin inattendue.');
        }
        $c = $s[$i];
        if ($c === '[') {
            $i++;
            $out = [];
            self::skipSpace($s, $i, $len);
            if ($i < $len && $s[$i] === ']') {
                $i++;

                return $out;
            }
            while (true) {
                $out[] = self::parseValue($s, $i, $len, $depth + 1);
                self::skipSpace($s, $i, $len);
                if ($i >= $len) {
                    throw new \InvalidArgumentException('Texte de campagne : tableau non fermé.');
                }
                if ($s[$i] === ',') {
                    $i++;
                    continue;
                }
                if ($s[$i] === ']') {
                    $i++;

                    return $out;
                }
                throw new \InvalidArgumentException('Texte de campagne : caractère inattendu à la position ' . $i . '.');
            }
        }
        if ($c === '"' || $c === "'") {
            $q = $c;
            $i++;
            $buf = '';
            while ($i < $len) {
                $ch = $s[$i];
                if ($ch === $q) {
                    if ($i + 1 < $len && $s[$i + 1] === $q) {
                        $buf .= $q;
                        $i += 2;
                        continue;
                    }
                    $i++;

                    return $buf;
                }
                $buf .= $ch;
                $i++;
            }
            throw new \InvalidArgumentException('Texte de campagne : chaîne non fermée.');
        }
        // Nombre, booléen ou mot nu (any, <null>…) jusqu'au prochain séparateur.
        $start = $i;
        while ($i < $len && !in_array($s[$i], [',', ']', ' ', "\t", "\r", "\n"], true)) {
            $i++;
        }
        $word = substr($s, $start, $i - $start);
        $lw = strtolower($word);
        if ($lw === 'true') {
            return true;
        }
        if ($lw === 'false') {
            return false;
        }
        if (is_numeric($word)) {
            return (str_contains($word, '.') || str_contains($lw, 'e')) ? (float) $word : (int) $word;
        }

        return null;
    }

    private static function skipSpace(string $s, int &$i, int $len): void
    {
        while ($i < $len && ($s[$i] === ' ' || $s[$i] === "\t" || $s[$i] === "\r" || $s[$i] === "\n")) {
            $i++;
        }
    }
}
