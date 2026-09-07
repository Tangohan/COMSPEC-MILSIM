<?php

declare(strict_types=1);

namespace App\Services\Effectifs;

use App\Repositories\TenantAdminSettingsRepository;

/**
 * Réglages du bureau effectifs (coffre, alertes, avancements, intégration).
 */
final class PersonnelHrWorkspaceSettings
{
    public const VISIBILITY_STAFF = 'STAFF';

    public const VISIBILITY_MEMBER = 'MEMBER';

    public const MODE_PROPOSE = 'propose';

    public const MODE_APPLY = 'apply';

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'reviewed' => false,
            'default_visibility' => self::VISIBILITY_STAFF,
            'auto_pdf_mobility' => false,
            'auto_pdf_elevation' => false,
            'auto_pdf_integration' => false,
            'advancement_enabled' => false,
            'advancement_months' => 12,
            'advancement_mode' => self::MODE_PROPOSE,
            'inactivity_days' => 45,
            'absence_days' => 14,
            'auto_start_integration' => true,
            'auto_start_on_assignment' => true,
        ];
    }

    /**
     * @param mixed $input
     * @return array<string, mixed>
     */
    public static function sanitize(mixed $input): array
    {
        $input = is_array($input) ? $input : [];
        $defaults = self::defaults();
        $visibility = strtoupper(trim((string) ($input['default_visibility'] ?? $defaults['default_visibility'])));
        $mode = strtolower(trim((string) ($input['advancement_mode'] ?? $defaults['advancement_mode'])));

        return [
            'reviewed' => self::bool($input['reviewed'] ?? false),
            'default_visibility' => $visibility === self::VISIBILITY_MEMBER
                ? self::VISIBILITY_MEMBER
                : self::VISIBILITY_STAFF,
            'auto_pdf_mobility' => self::bool($input['auto_pdf_mobility'] ?? false),
            'auto_pdf_elevation' => self::bool($input['auto_pdf_elevation'] ?? false),
            'auto_pdf_integration' => self::bool($input['auto_pdf_integration'] ?? false),
            'advancement_enabled' => self::bool($input['advancement_enabled'] ?? false),
            'advancement_months' => max(3, min(60, (int) ($input['advancement_months'] ?? $defaults['advancement_months']))),
            'advancement_mode' => $mode === self::MODE_APPLY ? self::MODE_APPLY : self::MODE_PROPOSE,
            'inactivity_days' => max(14, min(180, (int) ($input['inactivity_days'] ?? $defaults['inactivity_days']))),
            'absence_days' => max(7, min(90, (int) ($input['absence_days'] ?? $defaults['absence_days']))),
            'auto_start_integration' => array_key_exists('auto_start_integration', $input)
                ? self::bool($input['auto_start_integration'])
                : true,
            'auto_start_on_assignment' => array_key_exists('auto_start_on_assignment', $input)
                ? self::bool($input['auto_start_on_assignment'])
                : true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function forTenant(int $tenantId, ?TenantAdminSettingsRepository $repo = null): array
    {
        if ($tenantId < 1) {
            return self::defaults();
        }
        $repo ??= new TenantAdminSettingsRepository();
        try {
            $all = $repo->getForTenant($tenantId);
        } catch (\Throwable) {
            return self::defaults();
        }
        $hr = is_array($all['personnel_hr'] ?? null) ? $all['personnel_hr'] : [];

        return self::sanitize($hr);
    }

    public static function isReviewed(int $tenantId): bool
    {
        return !empty(self::forTenant($tenantId)['reviewed']);
    }

    private static function bool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }
}
