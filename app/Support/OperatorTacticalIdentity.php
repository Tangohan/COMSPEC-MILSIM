<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Indicatif et affectation d’un opérateur — jamais le nom de la communauté.
 * Aligné sur la colonne Indicatif / Affectation du tableau Effectifs.
 */
final class OperatorTacticalIdentity
{
    public const CALLSIGN_MAX_LEN = 40;

    /**
     * @param list<string|null> $candidates personnel, users.callsign, héritage Arma…
     */
    public static function callsign(array $candidates, string $tenantName = '', string $communityLabel = ''): string
    {
        foreach ($candidates as $raw) {
            $cs = self::sanitizeCallsign((string) $raw, $tenantName, $communityLabel);
            if ($cs !== '') {
                return $cs;
            }
        }

        return '';
    }

    public static function sanitizeCallsign(string $callsign, string $tenantName = '', string $communityLabel = ''): string
    {
        $cs = trim($callsign);
        if ($cs === '' || $cs === '-') {
            return '';
        }
        $low = self::fold($cs);
        if (in_array($low, ['unknown', 'inconnu', 'operateur', 'operator', 'none', 'n/a'], true)) {
            return '';
        }
        if (str_contains($low, '://') || str_starts_with($low, 'http')) {
            return '';
        }
        if (mb_strlen($cs) > self::CALLSIGN_MAX_LEN) {
            return '';
        }
        if (self::looksLikeCommunityTitle($cs, $tenantName, $communityLabel)) {
            return '';
        }

        return $cs;
    }

    public static function unitAssignment(string $unitName, string $tenantName = '', string $communityLabel = ''): string
    {
        $unit = trim($unitName);
        if ($unit === '' || $unit === '-') {
            return '';
        }
        $low = self::fold($unit);
        if (str_contains($low, '://') || str_starts_with($low, 'http')) {
            return '';
        }
        if (self::looksLikeCommunityTitle($unit, $tenantName, $communityLabel)) {
            return '';
        }

        return $unit;
    }

    public static function looksLikeCommunityTitle(string $value, string $tenantName = '', string $communityLabel = ''): bool
    {
        $raw = trim($value);
        if ($raw === '') {
            return false;
        }
        $low = self::fold($raw);
        foreach ([$tenantName, $communityLabel] as $label) {
            $ref = self::fold(trim((string) $label));
            if ($ref === '') {
                continue;
            }
            if ($low === $ref) {
                return true;
            }
            // Titre de communauté tronqué à 50 caractères (limite historique du téléphone).
            if (mb_strlen($ref) >= 16 && mb_strlen($low) >= 16 && (str_starts_with($ref, $low) || str_starts_with($low, $ref))) {
                return true;
            }
        }

        return false;
    }

    private static function fold(string $s): string
    {
        $s = mb_strtolower($s);

        return preg_replace('/\s+/u', ' ', $s) ?? $s;
    }
}
