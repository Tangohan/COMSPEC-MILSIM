<?php

declare(strict_types=1);

namespace App\Services\Community;

/**
 * Lecture de l’état « archivée » d’une communauté (clé `platform_archive` des réglages).
 * Une communauté archivée garde toutes ses données, mais disparaît de l’annuaire,
 * de sa page publique et des sélecteurs de communauté de ses membres.
 */
final class TenantArchiveState
{
    public const SETTINGS_KEY = 'platform_archive';

    /**
     * @param array<string, mixed> $tenantRow ligne `tenants` (settings en JSON texte ou déjà décodés)
     */
    public static function isArchived(array $tenantRow): bool
    {
        return self::info($tenantRow) !== null;
    }

    /**
     * @param array<string, mixed> $tenantRow
     * @return array<string, mixed>|null
     */
    public static function info(array $tenantRow): ?array
    {
        $settings = $tenantRow['settings'] ?? null;

        return self::infoFromSettings($settings);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function infoFromSettings(mixed $settings): ?array
    {
        if (is_string($settings)) {
            if (trim($settings) === '' || !str_contains($settings, self::SETTINGS_KEY)) {
                return null;
            }
            $settings = json_decode($settings, true);
        }
        if (!is_array($settings)) {
            return null;
        }
        $info = $settings[self::SETTINGS_KEY] ?? null;
        if (!is_array($info) || trim((string) ($info['archived_at'] ?? '')) === '') {
            return null;
        }

        return $info;
    }
}
