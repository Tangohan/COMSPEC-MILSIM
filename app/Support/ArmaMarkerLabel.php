<?php

declare(strict_types=1);

/**
 * Libellés lisibles pour marqueurs Arma / BCE / cTab (évite _USER_DEFINED #… dans l’UI).
 */
final class ArmaMarkerLabel
{
    public static function isTechnicalName(string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return true;
        }
        if (str_starts_with(strtolower($value), 'comspec_')) {
            return true;
        }
        if (preg_match('/_(?:user|ictab)_defined\s*#/i', $value) === 1) {
            return true;
        }
        if (preg_match('/^ctab_u_\d+$/i', $value) === 1) {
            return true;
        }

        return false;
    }

    /**
     * @param array<string, mixed> $decoded
     */
    public static function displayLabel(string $armaName, array $decoded = []): string
    {
        $text = trim((string) ($decoded['text'] ?? $decoded['label'] ?? ''));
        if ($text !== '' && !self::isTechnicalName($text)) {
            return $text;
        }

        $type = strtolower(trim((string) ($decoded['type'] ?? '')));
        $texture = strtolower(trim((string) ($decoded['texture'] ?? '')));
        $fromType = self::labelFromTypeOrTexture($type, $texture);
        if ($fromType !== '') {
            return $fromType;
        }

        if (!self::isTechnicalName($armaName)) {
            return $armaName;
        }

        return 'Repère tactique';
    }

    /**
     * @param array<string, mixed> $decoded
     */
    public static function actorFromMarker(array $decoded, string $fallback = ''): string
    {
        $cs = trim((string) ($decoded['callsign'] ?? ''));
        if ($cs !== '' && !self::isTechnicalName($cs)) {
            return $cs;
        }
        $fallback = trim($fallback);
        if ($fallback !== '' && !self::isTechnicalName($fallback)) {
            return $fallback;
        }

        return 'Opérateur';
    }

    private static function labelFromTypeOrTexture(string $type, string $texture): string
    {
        $hay = $type . ' ' . $texture;

        if (str_contains($hay, 'o_air') || str_contains($hay, 'helo') || str_contains($hay, 'rotary') || str_contains($hay, 'uav')) {
            return 'Hélicoptère';
        }
        if (str_contains($hay, 'medevac') || str_contains($hay, 'mplus_medevac')) {
            return 'Évacuation médicale';
        }
        if (str_contains($hay, 'checkpoint') || str_contains($hay, 'mplus_checkpoint')) {
            return 'Point de contrôle';
        }
        if (str_contains($hay, 'rally') || str_contains($hay, 'mplus_rallypoint') || str_contains($hay, 'mil_join')) {
            return 'Point de ralliement';
        }
        if (str_contains($hay, 'objective') || str_contains($hay, 'mplus_seize')) {
            return 'Objectif';
        }
        if (str_contains($hay, 'destroy') || str_contains($hay, 'mplus_destroy')) {
            return 'Cible à neutraliser';
        }
        if (str_contains($hay, 'warning') || str_contains($hay, 'mil_warning')) {
            return 'Alerte';
        }
        if (str_starts_with($type, 'o_') || str_contains($hay, '/o_')) {
            return 'Contact adverse';
        }
        if (str_starts_with($type, 'b_') || str_contains($hay, '/b_')) {
            return 'Repère ami';
        }
        if (str_contains($hay, 'waypoint') || str_contains($hay, 'mplus_waypoint')) {
            return 'Point de passage';
        }

        return '';
    }
}
