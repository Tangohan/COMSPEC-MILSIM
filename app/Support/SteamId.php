<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Normalisation hors-ligne des identifiants Steam courants vers SteamID64 (chiffres).
 * Pas d’appel réseau — pour vanity / liens /id/… utiliser SteamWebApiService.
 */
final class SteamId
{
    /** Constante Steam : SteamID64 = account_id + cette base (univers public, type individuel). */
    private const STEAM64_BASE = '76561197960265728';

    /** Longueur min/max d’un SteamID64 stocké / comparé. */
    public const STEAM64_MIN_LEN = 15;
    public const STEAM64_MAX_LEN = 20;

    /**
     * Accepte SteamID64, SteamID2 (STEAM_X:Y:Z), SteamID3 ([U:1:N] / U:1:N),
     * URL …/profiles/…, espaces / séparateurs autour de chiffres, guillemets.
     * Rejette les chaînes vides et les placeholders solo Arma.
     */
    public static function normalize(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        if (
            (strlen($raw) >= 2 && $raw[0] === '"' && str_ends_with($raw, '"'))
            || (strlen($raw) >= 2 && $raw[0] === "'" && str_ends_with($raw, "'"))
        ) {
            $raw = trim(substr($raw, 1, -1));
        }
        if ($raw === '') {
            return null;
        }

        $upper = strtoupper($raw);
        if (str_starts_with($upper, '_SP_') || $upper === 'LOCAL' || $upper === 'AI') {
            return null;
        }

        if (preg_match('#steamcommunity\.com/profiles/(\d{15,20})\b#i', $raw, $m)) {
            return self::steam64OrNull($m[1]);
        }
        if (preg_match('#(?:https?://)?s\.team/p/(\d{15,20})\b#i', $raw, $m)) {
            return self::steam64OrNull($m[1]);
        }

        if (preg_match('/^STEAM_([0-5]):([01]):(\d{1,10})$/i', $raw, $m)) {
            return self::accountIdToSteam64((string) ((int) $m[3] * 2 + (int) $m[2]));
        }

        if (preg_match('/^\[?U:1:(\d{1,10})\]?$/i', $raw, $m)) {
            return self::accountIdToSteam64($m[1]);
        }

        // Chiffres seuls, éventuellement séparés (espaces, tirets) — pas une URL vanity.
        if (!preg_match('#steamcommunity\.com/id/#i', $raw) && !str_contains($raw, '/')) {
            $digits = preg_replace('/\D/', '', $raw) ?? '';
            if ($digits !== '' && strlen($digits) >= self::STEAM64_MIN_LEN && strlen($digits) <= self::STEAM64_MAX_LEN) {
                return self::steam64OrNull($digits);
            }
        }

        return null;
    }

    public static function isSteam64(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        return preg_match('/^\d{' . self::STEAM64_MIN_LEN . ',' . self::STEAM64_MAX_LEN . '}$/', $value) === 1;
    }

    private static function steam64OrNull(string $digits): ?string
    {
        $digits = preg_replace('/\D/', '', $digits) ?? '';
        if ($digits === '' || strlen($digits) < self::STEAM64_MIN_LEN || strlen($digits) > self::STEAM64_MAX_LEN) {
            return null;
        }
        // Évite les zéros / valeurs absurdes (ex. 000…0).
        if (ltrim($digits, '0') === '') {
            return null;
        }

        return $digits;
    }

    private static function accountIdToSteam64(string $accountId): ?string
    {
        $accountId = preg_replace('/\D/', '', $accountId) ?? '';
        if ($accountId === '' || strlen($accountId) > 10) {
            return null;
        }
        if (ltrim($accountId, '0') === '') {
            return null;
        }
        if (!function_exists('bcadd')) {
            // Repli si BCMath absent : account_id + base (entiers PHP 64 bits OK jusqu’à ~9e18).
            $sum = (string) ((int) $accountId + 76561197960265728);

            return self::steam64OrNull($sum);
        }

        return self::steam64OrNull(bcadd($accountId, self::STEAM64_BASE, 0));
    }
}
