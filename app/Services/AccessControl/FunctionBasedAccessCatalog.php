<?php

declare(strict_types=1);

namespace App\Services\AccessControl;

use App\Authorization\SystemReservedPermissions;

/**
 * Catalogue fermé des sept rôles d'accès ATHENA.
 *
 * Les fonctions et emplois militaires restent dans leurs référentiels métier : ils sont
 * uniquement visuels et ne doivent jamais être utilisés comme source d'autorisation.
 */
final class FunctionBasedAccessCatalog
{
    public const MANAGER = 'manager';
    public const DEPUTY_MANAGER = 'deputy_manager';
    public const TRAINER = 'trainer';
    public const RECRUITER = 'recruiter';
    public const HR_MANAGER = 'hr_manager';
    public const DEPUTY_HR_MANAGER = 'deputy_hr_manager';
    public const MEMBER = 'member';

    /** @return array<string, array{label:string, role_slug:string, permission_slugs:list<string>}> */
    public static function roles(): array
    {
        $member = [
            'forum.view', 'documents.view', 'documents.download.standard', 'training.view',
            'training.results.view', 'personnel.profile.view', 'organization.effectifs.hub.view',
            'organization.orbat.view', 'operational.board.view', 'operations.missions.view',
            'operations.sitrep.view', 'operations.aar.view', 'operations.readiness.view',
            'operations.medical.view', 'operations.logistics.view', 'operations.comms.view',
            'operations.doctrine.view', 'media.view',
        ];
        $management = array_merge($member, [
            'forum.topic.pin', 'forum.topic.lock', 'forum.topic.move', 'forum.post.edit_any',
            'forum.post.delete_any', 'forum.reports.manage', 'forum.announcements.publish',
            'documents.upload', 'documents.version.replace', 'documents.metadata.update',
            'documents.delete', 'documents.archive', 'documents.categories.manage', 'documents.access.manage',
            'admin.members.view', 'admin.members.manage', 'admin.members.moderate',
            'personnel.profile.update', 'personnel.grades.manage', 'personnel.assignments.manage',
            'personnel.status.manage', 'personnel.badges.manage', 'organization.orbat.manage',
            'operational.board.edit', 'operations.missions.manage', 'operations.planning.edit',
            'operations.orders.edit', 'operations.overlay.publish', 'operations.phase.change',
            'operations.readiness.manage', 'operations.medical.manage', 'operations.logistics.manage',
            'operations.comms.manage', 'operations.doctrine.manage', 'media.manage',
        ]);
        $training = array_merge($member, [
            'training.create', 'training.update', 'training.delete', 'training.publish',
            'training.assign', 'training.submissions.grade', 'training.certifications.manage',
            'training.prerequisites.manage',
        ]);
        $recruitment = array_merge($member, [
            'admin.members.view', 'admin.members.invite', 'invitations.send',
            'organization.recruitment.manage', 'organization.recruitment.openings.manage',
            'member_integration.view', 'member_integration.manage', 'member_integration.assign',
            'member_integration.note',
        ]);
        $humanResources = array_merge($member, [
            'admin.members.view', 'admin.members.manage', 'personnel.profile.update',
            'personnel.sensitive.view', 'personnel.grades.manage', 'personnel.assignments.manage',
            'personnel.status.manage', 'personnel.badges.manage', 'personnel.directory.export',
            'personnel.member_number.manage', 'organization.orbat.manage',
        ]);

        return [
            self::MANAGER => self::definition('Gestionnaire', 'community_owner', $management),
            self::DEPUTY_MANAGER => self::definition('Gestionnaire adjoint', 'tenant_admin', $management),
            self::TRAINER => self::definition('Formateur', 'trainer', $training),
            self::RECRUITER => self::definition('Recruteur', 'recruiter', $recruitment),
            self::HR_MANAGER => self::definition('Responsable ressources humaines', 'hr', $humanResources),
            self::DEPUTY_HR_MANAGER => self::definition('Adjoint responsable ressources humaines', 'deputy_hr', $humanResources),
            self::MEMBER => self::definition('Membre', 'member', $member),
        ];
    }

    /** @return list<string> */
    public static function accessRoleSlugs(): array
    {
        return array_values(array_column(self::roles(), 'role_slug'));
    }

    public static function assertInvariants(): void
    {
        $roles = self::roles();
        if (count($roles) !== 7 || $roles[self::MANAGER]['permission_slugs'] !== $roles[self::DEPUTY_MANAGER]['permission_slugs']) {
            throw new \LogicException('The access catalogue must contain seven roles and equal manager grants.');
        }
        if ($roles[self::HR_MANAGER]['permission_slugs'] !== $roles[self::DEPUTY_HR_MANAGER]['permission_slugs']) {
            throw new \LogicException('HR manager and deputy grants must be identical.');
        }
        foreach ($roles as $role) {
            foreach ($role['permission_slugs'] as $slug) {
                if (SystemReservedPermissions::isReserved($slug) || in_array($slug, ['admin.access', 'admin.organization'], true)) {
                    throw new \LogicException('Reserved or aggregate permission in access role: ' . $slug);
                }
            }
        }
    }

    /** @param list<string> $permissions @return array{label:string,role_slug:string,permission_slugs:list<string>} */
    private static function definition(string $label, string $roleSlug, array $permissions): array
    {
        $permissions = SystemReservedPermissions::filter(array_values(array_unique($permissions)));
        sort($permissions);
        return ['label' => $label, 'role_slug' => $roleSlug, 'permission_slugs' => $permissions];
    }
}
