<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Cadre délégué : un membre sans droit d'administration globale (admin.organization, admin.access, site.support) qui tient
 * pourtant un droit de gestion précis (RH, recrutement, accès, opérations, renseignement, communication…). Le middleware du
 * back-office lui ouvre déjà ses pages ; le menu lui montre maintenant les mêmes, filtrées par ses droits, au-dessus de
 * « Mon espace » (audit du back-office, §9). Garder cette liste en phase avec OrganizationAdminMiddleware.
 */
final class BackOfficeDelegatedStaff
{
    /** @var list<string> */
    public const PERMISSIONS = [
        'organization.effectifs.hub.view', 'admin.roles.manage', 'admin.audit.view', 'admin.compliance.export', 'admin.members.manage',
        'admin.members.moderate', 'admin.permissions.manage',
        'member_integration.view', 'member_integration.manage', 'member_integration.assign', 'member_integration.note',
        'member_integration.template_manage', 'personnel.advancement.manage', 'personnel.awards.manage', 'personnel.badges.manage',
        'personnel.profile.update', 'personnel.assignments.manage', 'personnel.grades.manage', 'personnel.status.manage',
        'personnel.member_number.manage', 'personnel.callsign.manage', 'personnel.equipment.manage',
        'organization.recruitment.manage', 'organization.recruitment.openings.manage', 'organization.catalog.manage',
        'organization.orbat.manage', 'organization.job_roles.referential.manage',
        'comms.email.send.orbat', 'comms.email.send.mission', 'comms.email.send.activity', 'comms.email.send.custom',
        'comms.email.broadcast', 'comms.email_templates.manage', 'comms.notifications.history.view',
        'operations.missions.manage', 'operational.board.edit', 'atak.command.alert', 'atak.sse.case.manage', 'atak.sse.grant',
    ];

    /**
     * Les droits de simple lecture (organization.orbat.view, personnel.progression.view : page « Disponibilité ») n'en font
     * pas un cadre : le menu de gestion n'apparaît que pour un droit de gestion.
     *
     * @param callable(string): bool $can droit détenu (Gate::allows)
     */
    public static function is(callable $can): bool
    {
        if ($can('admin.organization') || $can('admin.access') || $can('site.support')) {
            return false;
        }
        foreach (self::PERMISSIONS as $permission) {
            if ($can($permission)) {
                return true;
            }
        }

        return false;
    }
}
