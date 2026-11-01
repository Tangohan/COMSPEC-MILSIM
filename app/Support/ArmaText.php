<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Texte remonté par Arma (rôle de slot, classe) : quand la mission ou le jeu n'a pas traduit une clé de
 * stringtable, le jeu envoie la clé brute (« STR_role_default_commander_role_name »). Elle n'est jamais affichée
 * telle quelle : traduite si on la connaît, sinon réduite à ses mots utiles.
 */
final class ArmaText
{
    /** Clés connues (en minuscules, sans « $ »). */
    private const KNOWN = [
        'str_role_default_commander_role_name' => 'Commandant',
    ];

    /** Morceaux techniques d'une clé, sans sens pour un joueur. */
    private const NOISE = ['str', 'role', 'roles', 'default', 'name', 'displayname', 'desc', 'description', 'a3', 'cfg', 'text', 'title'];

    public static function isKey(string $text): bool
    {
        return preg_match('/^\$?STR_[A-Za-z0-9_]+$/i', trim($text)) === 1;
    }

    public static function label(?string $text): string
    {
        $text = trim((string) $text);
        if (!self::isKey($text)) {
            return $text;
        }
        $key = strtolower(ltrim($text, '$'));
        if (isset(self::KNOWN[$key])) {
            return self::KNOWN[$key];
        }
        $words = array_filter(
            explode('_', $key),
            static fn (string $w): bool => $w !== '' && !in_array($w, self::NOISE, true) && !str_starts_with($w, 'cfg') && preg_match('/^[0-9a-f]{6,}$|^\d+$/', $w) !== 1
        );

        return $words === [] ? '' : ucfirst(implode(' ', $words));
    }

    /** Libellé de rôle tenu (« SLOT:STR_… » côté clé ATAK) : la partie Arma passe par label(). */
    public static function roleLabel(?string $text): string
    {
        $text = trim((string) $text);
        if (preg_match('/^SLOT:\s*(.*)$/i', $text, $m) === 1) {
            $inner = self::label($m[1]);

            return $inner === '' ? '' : 'SLOT:' . $inner;
        }

        return self::label($text);
    }
}
