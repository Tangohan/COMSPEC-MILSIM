<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Gate;

/**
 * Accès au bureau LMS de pilotage des effectifs (outil RH organisationnel).
 */
final class EffectifsLmsAccess
{
    public static function allows(Gate $gate): bool
    {
        return $gate->allows('admin.organization')
            || $gate->allows('admin.access')
            || $gate->allows('site.support')
            || $gate->allows('organization.effectifs.hub.view')
            || $gate->allows('personnel.profile.view')
            || $gate->allows('personnel.profile.update')
            || $gate->allows('personnel.assignments.manage')
            || $gate->allows('personnel.grades.manage')
            || $gate->allows('personnel.status.manage');
    }

    public static function canEditProfiles(Gate $gate): bool
    {
        return $gate->allows('admin.organization')
            || $gate->allows('admin.access')
            || $gate->allows('personnel.profile.update');
    }

    public static function canManageStatus(Gate $gate): bool
    {
        return $gate->allows('admin.organization')
            || $gate->allows('admin.access')
            || $gate->allows('personnel.status.manage');
    }

    public static function canManageAssignments(Gate $gate): bool
    {
        return $gate->allows('admin.organization')
            || $gate->allows('admin.access')
            || $gate->allows('personnel.assignments.manage');
    }

    public static function canManageRoles(Gate $gate): bool
    {
        return $gate->allows('admin.organization')
            || $gate->allows('admin.access')
            || $gate->allows('admin.roles.manage');
    }

    public static function canManageGrades(Gate $gate): bool
    {
        return $gate->allows('admin.organization')
            || $gate->allows('admin.access')
            || $gate->allows('personnel.grades.manage');
    }

    /** Peut solliciter une élévation RH auprès des personnes habilitées. */
    public static function canRequestElevation(Gate $gate): bool
    {
        return self::allows($gate);
    }
}
