<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

/**
 * Paramètres recrutement dans tenants.settings (clé "recruitment").
 */
final class TenantRecruitmentSettings
{
    /**
     * @param array<string, mixed> $tenantSettings déjà décodés depuis JSON
     * @return array<string, mixed>
     */
    public static function getRecruitmentBlock(array $tenantSettings): array
    {
        $r = $tenantSettings['recruitment'] ?? null;

        return is_array($r) ? $r : [];
    }

    /**
     * @param array<string, mixed> $recruitmentBlock
     * @return array<string, mixed>
     */
    public static function mergeReferenceFormat(array $recruitmentBlock, array $overrides): array
    {
        $fmt = is_array($recruitmentBlock['reference_format'] ?? null) ? $recruitmentBlock['reference_format'] : [];
        $defaults = self::defaultReferenceFormat();

        return array_merge($defaults, $fmt, $overrides);
    }

    /** @return array<string, mixed> */
    public static function defaultReferenceFormat(): array
    {
        return [
            'separator' => '/',
            'include_organization_tag' => true,
            'organization_tag' => '',
            'include_unit_code' => true,
            'include_rec_segment' => true,
            'rec_segment' => 'REC',
        ];
    }

    public static function prospectionDocumentRef(array $tenantSettings): string
    {
        $b = self::getRecruitmentBlock($tenantSettings);

        return trim((string) ($b['prospection_document_ref'] ?? ''));
    }

    public static function defaultEnlistmentSlaHours(): int
    {
        return 72;
    }

    /**
     * SLA interne recrutement (heures max sans action sur un dossier soumis).
     */
    public static function enlistmentSlaHoursFromSettings(array $tenantSettings): int
    {
        $b = self::getRecruitmentBlock($tenantSettings);
        $raw = (int) ($b['enlistment_sla_hours'] ?? self::defaultEnlistmentSlaHours());

        return max(1, min(720, $raw));
    }

    /**
     * @param array<string, mixed> $tenantSettings
     * @return array<string, mixed>
     */
    public static function referenceFormatFromSettings(array $tenantSettings): array
    {
        $b = self::getRecruitmentBlock($tenantSettings);

        return self::mergeReferenceFormat($b, []);
    }
}
