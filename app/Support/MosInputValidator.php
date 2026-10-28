<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Validation des entrées « code spécialité » alignées sur le format MOS / AOC U.S. Army (chiffres + lettres, tiret optionnel).
 */
final class MosInputValidator
{
    public static function normalizeCode(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $s = strtoupper(trim($raw));
        if ($s === '') {
            return null;
        }
        // MOS / AOC Army (11B, 68W, 153A) ou AFSC enlisted Air Force (1Z2X1), cf. UsArmyMosCatalog.
        if (!preg_match('/^[0-9]{1,4}[A-Z](-[0-9A-Z]{1,4})?$/', $s)
            && !preg_match('/^[A-Z]?[0-9][A-Z][0-9][0-9X][0-9][A-Z]?$/', $s)) {
            return null;
        }

        return strlen($s) <= 16 ? $s : null;
    }

    public static function normalizeSpecialtyTitle(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $s = trim($raw);
        if ($s === '') {
            return null;
        }

        return strlen($s) <= 255 ? $s : null;
    }
}
