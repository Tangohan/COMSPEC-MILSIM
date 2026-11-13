<?php

declare(strict_types=1);

namespace App\Services\GameEquipment\Configurator;

/**
 * Texte d'un tableau venu du jeu → tableau PHP : JSON d'abord, sinon texte SQF (`str` d'un tableau : chaînes entre
 * guillemets, guillemet doublé = guillemet, nombres, true/false ; tout autre mot vaut null).
 *
 * Même lecture que ArsenalLoadoutItems::decode, mais pour de gros textes (export du catalogue : plusieurs Mo) : taille
 * bornée par l'appelant, profondeur bornée (16), aucune récursion sur les chaînes.
 */
final class SqfArrayText
{
    public const MAX_DEPTH = 16;

    /**
     * @return array<int|string, mixed>|null null si le texte n'est pas un tableau lisible ou dépasse $maxBytes
     */
    public static function decode(string $text, int $maxBytes): ?array
    {
        $text = trim($text);
        // Marque d'ordre des octets d'un fichier enregistré par un éditeur Windows.
        if (str_starts_with($text, "\xEF\xBB\xBF")) {
            $text = substr($text, 3);
        }
        if ($text === '' || strlen($text) > $maxBytes || $text[0] !== '[') {
            return null;
        }
        $json = json_decode($text, true, self::MAX_DEPTH + 2);
        if (is_array($json)) {
            return $json;
        }
        $pos = 0;
        try {
            $value = self::value($text, $pos, 0);
        } catch (\UnexpectedValueException) {
            return null;
        }
        // Rien d'autre qu'un blanc après le tableau.
        if (trim(substr($text, $pos)) !== '') {
            return null;
        }

        return is_array($value) ? $value : null;
    }

    private static function value(string $s, int &$pos, int $depth): mixed
    {
        $len = strlen($s);
        $pos += strspn($s, " \t\r\n", $pos);
        if ($pos >= $len || $depth > self::MAX_DEPTH) {
            throw new \UnexpectedValueException('sqf_eof');
        }
        $c = $s[$pos];
        if ($c === '[') {
            $pos++;
            $out = [];
            $pos += strspn($s, " \t\r\n", $pos);
            if ($pos < $len && $s[$pos] === ']') {
                $pos++;

                return $out;
            }
            while (true) {
                $out[] = self::value($s, $pos, $depth + 1);
                $pos += strspn($s, " \t\r\n", $pos);
                if ($pos < $len && $s[$pos] === ',') {
                    $pos++;
                    continue;
                }
                if ($pos < $len && $s[$pos] === ']') {
                    $pos++;

                    return $out;
                }
                throw new \UnexpectedValueException('sqf_array');
            }
        }
        if ($c === '"' || $c === "'") {
            $buf = '';
            $pos++;
            while (true) {
                $end = strpos($s, $c, $pos);
                if ($end === false) {
                    throw new \UnexpectedValueException('sqf_string');
                }
                $buf .= substr($s, $pos, $end - $pos);
                if ($end + 1 < $len && $s[$end + 1] === $c) {
                    $buf .= $c;
                    $pos = $end + 2;
                    continue;
                }
                $pos = $end + 1;

                return $buf;
            }
        }
        if (preg_match('/\G-?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?/', $s, $m, 0, $pos) === 1) {
            $pos += strlen($m[0]);

            return $m[0] + 0;
        }
        if (preg_match('/\G[A-Za-z_][A-Za-z0-9_]*/', $s, $m, 0, $pos) === 1) {
            $pos += strlen($m[0]);
            $w = strtolower($m[0]);

            return $w === 'true' ? true : ($w === 'false' ? false : null);
        }
        if ($c === '<') {
            $end = strpos($s, '>', $pos);
            if ($end !== false) {
                $pos = $end + 1;

                return null;
            }
        }
        throw new \UnexpectedValueException('sqf_token');
    }
}
