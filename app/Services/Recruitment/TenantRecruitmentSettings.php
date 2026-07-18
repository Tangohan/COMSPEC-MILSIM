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
            'include_ao_segment' => true,
            'ao_segment' => 'AO',
            'include_unit_code' => true,
            'include_unit_name_abbr' => true,
            'include_arm_domain_abbr' => true,
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

    public static function defaultWorkflowMode(): string
    {
        return 'simple';
    }

    public static function workflowModeFromSettings(array $tenantSettings): string
    {
        $b = self::getRecruitmentBlock($tenantSettings);
        $raw = strtolower(trim((string) ($b['workflow_mode'] ?? self::defaultWorkflowMode())));

        return in_array($raw, ['simple', 'milsim'], true) ? $raw : self::defaultWorkflowMode();
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
     * Libellé humain pour un délai en heures (affichage candidat / instructeur).
     */
    public static function formatSlaHoursLabel(int $hours): string
    {
        $hours = max(1, $hours);
        if ($hours % 24 === 0) {
            $days = intdiv($hours, 24);

            return $days === 1
                ? '24 h (environ 1 jour)'
                : $hours . ' h (environ ' . $days . ' jours)';
        }

        return $hours . ' h';
    }

    /**
     * Heures écoulées depuis une date (soumission, etc.). Null si date invalide.
     */
    public static function hoursElapsedSince(?string $datetime): ?int
    {
        $base = trim((string) $datetime);
        if ($base === '') {
            return null;
        }
        $ts = strtotime($base);
        if ($ts === false || $ts <= 0) {
            return null;
        }
        $delta = time() - $ts;
        if ($delta < 0) {
            return 0;
        }

        return (int) floor($delta / 3600);
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
