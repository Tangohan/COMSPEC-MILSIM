<?php

declare(strict_types=1);

namespace App\Services\Personnel;

/**
 * Paramètres tenant pour l’écran d’attribution des rôles métier (JSON tenants.settings.personnel_job_roles_assignments).
 */
final class PersonnelJobRoleAssignmentsSettings
{
    private const SETTINGS_KEY = 'personnel_job_roles_assignments';

    /**
     * @param array<string, mixed> $tenantSettings résultat de TenantRepository::getSettings
     *
     * @return array{
     *   max_roles_per_member: int,
     *   require_primary_when_multiple: bool,
     *   assignments_page_size: int,
     *   show_english_labels: bool,
     *   append_secondaries_to_primary_display: bool,
     *   show_category_in_role_picklist: bool,
     *   default_expand_role_rows: int
     * }
     */
    public static function resolve(array $tenantSettings): array
    {
        $raw = $tenantSettings[self::SETTINGS_KEY] ?? [];
        if (!is_array($raw)) {
            $raw = [];
        }

        $max = (int) ($raw['max_roles_per_member'] ?? 5);
        $max = max(1, min(12, $max));

        $page = (int) ($raw['assignments_page_size'] ?? 30);
        $page = max(10, min(100, $page));

        $expand = (int) ($raw['default_expand_role_rows'] ?? 3);
        $expand = max(1, min(12, $expand));

        return [
            'max_roles_per_member' => $max,
            'require_primary_when_multiple' => array_key_exists('require_primary_when_multiple', $raw)
                ? (bool) $raw['require_primary_when_multiple']
                : true,
            'assignments_page_size' => $page,
            'show_english_labels' => !empty($raw['show_english_labels']),
            'append_secondaries_to_primary_display' => array_key_exists('append_secondaries_to_primary_display', $raw)
                ? (bool) $raw['append_secondaries_to_primary_display']
                : true,
            'show_category_in_role_picklist' => array_key_exists('show_category_in_role_picklist', $raw)
                ? (bool) $raw['show_category_in_role_picklist']
                : true,
            'default_expand_role_rows' => $expand,
        ];
    }

    /**
     * @param array<string, mixed> $patch champs autorisés uniquement
     */
    public static function mergePatch(array $tenantSettings, array $patch): array
    {
        $current = $tenantSettings[self::SETTINGS_KEY] ?? [];
        if (!is_array($current)) {
            $current = [];
        }
        $allowed = [
            'max_roles_per_member',
            'require_primary_when_multiple',
            'assignments_page_size',
            'show_english_labels',
            'append_secondaries_to_primary_display',
            'show_category_in_role_picklist',
            'default_expand_role_rows',
        ];
        foreach ($patch as $k => $v) {
            if (!in_array($k, $allowed, true)) {
                continue;
            }
            $current[$k] = $v;
        }
        $tenantSettings[self::SETTINGS_KEY] = $current;

        return $tenantSettings;
    }
}
