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
        if (!preg_match('/^[0-9]{1,4}[A-Z](-[0-9A-Z]{1,4})?$/', $s)) {
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
