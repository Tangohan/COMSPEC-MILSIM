<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Code de session de jeu (« K7M-4QX ») : 6 caractères tirés de 32 symboles sans ambiguïté à l'oral ni à l'écran
 * (A-Z sans I ni O, chiffres 2 à 9 : jamais 0/O ni 1/I). 32^6 ≈ 1,07 milliard de codes, soit 30 bits d'entropie :
 * avec la limitation de débit de l'API (10 essais par joueur et 30 par adresse en 10 minutes), deviner le code d'une
 * session de quelques heures est hors de portée. Le code n'est pas un mot de passe : il prouve que le joueur a reçu
 * l'annonce de l'animateur (vocal, écran) et le rattache à la bonne session.
 *
 * Stockage : 6 caractères normalisés, sans tiret. Saisie : casse, espaces et tirets ignorés.
 */
final class SessionJoinCode
{
    public const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public const LENGTH = 6;

    /** @param (callable(int, int): int)|null $random tirage entier [min, max] (random_int par défaut) */
    public static function generate(?callable $random = null): string
    {
        $random ??= static fn (int $min, int $max): int => random_int($min, $max);
        $max = strlen(self::ALPHABET) - 1;
        $out = '';
        for ($i = 0; $i < self::LENGTH; $i++) {
            $out .= self::ALPHABET[$random(0, $max)];
        }

        return $out;
    }

    /** Saisie du joueur → code stocké (6 caractères), ou null si la saisie ne peut pas être un code. */
    public static function normalize(mixed $raw): ?string
    {
        if (!is_string($raw) || strlen($raw) > 32) {
            return null;
        }
        $code = strtoupper(preg_replace('/[\s\-_.]+/', '', $raw) ?? '');
        if (strlen($code) !== self::LENGTH || strspn($code, self::ALPHABET) !== self::LENGTH) {
            return null;
        }

        return $code;
    }

    /** « K7M4QX » → « K7M-4QX » (lecture à voix haute en deux groupes). */
    public static function format(string $code): string
    {
        return strlen($code) === self::LENGTH ? substr($code, 0, 3) . '-' . substr($code, 3) : $code;
    }
}
