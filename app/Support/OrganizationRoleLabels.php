<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Libellés d’affichage des rôles d’organisation (FR / anglais doctrine US) selon le réglage communauté.
 */
final class OrganizationRoleLabels
{
    public const MODE_FR = 'fr';

    public const MODE_EN = 'en';

    /**
     * @param array<string, mixed> $community Paramètre community issu de tenants.settings (décodé).
     * @param array<string, mixed> $tenant Ligne tenant (ex. default_locale).
     */
    public static function mode(array $community, array $tenant): string
    {
        $raw = trim((string) ($community['organization_role_labels'] ?? ''));
        if ($raw === self::MODE_EN) {
            return self::MODE_EN;
        }
        if ($raw === self::MODE_FR) {
            return self::MODE_FR;
        }
        $dl = strtolower(trim((string) ($community['default_locale'] ?? ($tenant['default_locale'] ?? ''))));
        if (str_starts_with($dl, 'en')) {
            return self::MODE_EN;
        }

        return self::MODE_FR;
    }

    /**
     * @param array<string, mixed> $roleRow Ligne roles (name, label_en, …).
     */
    public static function displayName(array $roleRow, string $mode): string
    {
        if ($mode === self::MODE_EN) {
            $en = trim((string) ($roleRow['label_en'] ?? ''));
            if ($en !== '') {
                return $en;
            }
        }

        return trim((string) ($roleRow['name'] ?? '')) ?: '—';
    }

    /**
     * Titres de sections (équivalent optgroup) pour le back-office.
     *
     * @param 'community'|'intra'|'other' $layer
     */
    public static function layerGroupLabel(string $layer, string $mode): string
    {
        if ($layer === 'other') {
            return $mode === self::MODE_EN
                ? 'Other organization roles'
                : 'Autres rôles organisationnels';
        }
        if ($layer === 'community') {
            return $mode === self::MODE_EN
                ? 'Governance and permissions'
                : 'Gouvernance et habilitations';
        }

        return $mode === self::MODE_EN
            ? 'Operational roles'
            : 'Rôles opérationnels et métier';
    }
}
