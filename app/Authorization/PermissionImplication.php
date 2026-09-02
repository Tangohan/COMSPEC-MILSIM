<?php

declare(strict_types=1);

namespace App\Authorization;

/**
 * Résout les permissions implicites (agrégats historiques et rôles larges).
 */
final class PermissionImplication
{
    /** @var list<string>|null */
    private static ?array $tenantCatalogSlugs = null;

    /** @return list<string> */
    private static function tenantCatalogSlugs(): array
    {
        if (self::$tenantCatalogSlugs === null) {
            self::$tenantCatalogSlugs = TenantPermissionCatalog::allSlugs();
        }

        return self::$tenantCatalogSlugs;
    }

    /**
     * @param list<string> $granted Slugs issus du rôle + rôles site
     */
    public static function isGranted(array $granted, string $permission): bool
    {
        if ($permission === '') {
            return false;
        }
        if (in_array($permission, $granted, true) || in_array('*', $granted, true)) {
            return true;
        }

        if ($permission === 'organization.orbat.view' && in_array('organization.orbat.manage', $granted, true)) {
            return true;
        }
        if ($permission === 'organization.catalog.manage' && (
            in_array('organization.orbat.manage', $granted, true)
            || in_array('admin.organization', $granted, true)
        )) {
            return true;
        }

        if (in_array('admin.system', $granted, true)) {
            return true;
        }

        if (in_array('site.support', $granted, true) && self::impliedBySiteSupport($permission)) {
            return true;
        }

        if (in_array('admin.access', $granted, true) && in_array($permission, self::tenantCatalogSlugs(), true)) {
            return true;
        }

        if (in_array('admin.organization', $granted, true) && self::impliedByAdminOrganization($permission)) {
            return true;
        }

        if (in_array('forum.moderate', $granted, true) && self::impliedByForumModerate($permission)) {
            return true;
        }

        if (in_array('forum.moderate_organization', $granted, true) && self::impliedByForumModerate($permission)) {
            return true;
        }

        if (in_array('training.manage', $granted, true) && self::impliedByTrainingManage($permission)) {
            return true;
        }

        if (in_array('media.manage', $granted, true) && self::impliedByMediaManage($permission)) {
            return true;
        }

        if (self::aliasMatch($granted, $permission)) {
            return true;
        }

        if (self::impliedByCommsEmail($granted, $permission)) {
            return true;
        }

        if (self::impliedByInterteamLegacy($granted, $permission)) {
            return true;
        }

        return false;
    }

    /**
     * Ancien droit large « broadcast » et annonces → envois par famille.
     *
     * @param list<string> $granted
     */
    private static function impliedByCommsEmail(array $granted, string $permission): bool
    {
        $sendSlugs = [
            'comms.email.send.orbat',
            'comms.email.send.mission',
            'comms.email.send.activity',
            'comms.email.send.custom',
        ];
        if (in_array('comms.email.broadcast', $granted, true) && in_array($permission, $sendSlugs, true)) {
            return true;
        }
        if (in_array('comms.announcement.send', $granted, true) && $permission === 'comms.email.send.custom') {
            return true;
        }

        return false;
    }

    /**
     * Anciennes permissions interteam.* → équivalents cooperation.* (sans casser les rôles existants).
     *
     * @param list<string> $granted
     */
    private static function impliedByInterteamLegacy(array $granted, string $permission): bool
    {
        $manageExtras = [
            'cooperation.missions.view',
            'cooperation.exchange.read',
            'cooperation.exchange.write',
            'cooperation.orbat.view',
            'cooperation.readiness.view',
            'cooperation.meeting.launch',
            'cooperation.missions.activate',
            'cooperation.missions.close',
            'cooperation.missions.archive',
            'cooperation.data.request',
            'cooperation.data.approve',
            'cooperation.data.revoke',
            'cooperation.audit.view',
            'cooperation.rex.submit',
            'cooperation.rex.read',
            'cooperation.catalog.manage',
            'cooperation.announcements.manage',
        ];
        if (in_array('cooperation.missions.manage', $granted, true)
            && in_array($permission, ['cooperation.catalog.manage', 'cooperation.announcements.manage'], true)) {
            return true;
        }

        if (in_array('interteam.missions.manage', $granted, true)) {
            if ($permission === 'cooperation.missions.manage' || in_array($permission, $manageExtras, true)) {
                return true;
            }
        }
        if (in_array('interteam.missions.respond', $granted, true)) {
            if (in_array($permission, ['cooperation.missions.respond', 'cooperation.missions.view', 'cooperation.exchange.read'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Habilitations dérivées de « administration organisationnelle ».
     * Exclusions : droits sensibles à attribuer explicitement sur les rôles (ex. restrictions membres niveau org).
     * Les droits plateforme (ex. admin.system) ne sont jamais dérivés : ils ne figurent pas au catalogue tenant.
     *
     * @var list<string>
     */
    private static function adminOrganizationExcludedSlugs(): array
    {
        return [
            'admin.members.moderate',
            'admin.system',
        ];
    }

    private static function impliedByAdminOrganization(string $permission): bool
    {
        if (str_starts_with($permission, 'admin.')) {
            if (in_array($permission, self::adminOrganizationExcludedSlugs(), true)) {
                return false;
            }
            // Uniquement les droits admin du catalogue communauté — jamais le pilotage site.
            return in_array($permission, self::tenantCatalogSlugs(), true);
        }
        // Pilotage LMS de la communauté (gabarit attestations, catalogue, etc.).
        if (str_starts_with($permission, 'training.')) {
            return true;
        }
        if (str_starts_with($permission, 'personnel.')) {
            return true;
        }
        if (str_starts_with($permission, 'operational.board.')) {
            return true;
        }
        if (str_starts_with($permission, 'intel.')) {
            return true;
        }
        if (str_starts_with($permission, 'organization.orbat.')) {
            return true;
        }
        if (str_starts_with($permission, 'media.')) {
            return true;
        }
        // Vitrine et raccourcis du tableau de bord : administration de la communauté, pas de la plateforme.
        if ($permission === 'dashboard.pins.manage') {
            return true;
        }

        return $permission === 'invitations.send';
    }

    /**
     * Habilitations dérivées du rôle site « assistance » (lecture / accompagnement, hors système).
     */
    private static function impliedBySiteSupport(string $permission): bool
    {
        static $slugs = null;
        if ($slugs === null) {
            $slugs = [
                'forum.view', 'forum.reply', 'forum.create_topic', 'forum.edit_own', 'forum.delete_own',
                'admin.backoffice.view', 'admin.members.view', 'personnel.profile.view',
                'admin.audit.view', 'documents.view', 'documents.download.standard',
            ];
        }

        return in_array($permission, $slugs, true);
    }

    private static function impliedByForumModerate(string $permission): bool
    {
        $set = array_merge(
            TenantPermissionCatalog::forumModerateGranularSlugs(),
            ['forum.categories.manage', 'forum.manage_categories']
        );

        return in_array($permission, $set, true);
    }

    private static function impliedByTrainingManage(string $permission): bool
    {
        $set = array_merge(
            TenantPermissionCatalog::trainingManageGranularSlugs(),
            ['training.view', 'training.results.view', 'training.results.export']
        );

        return in_array($permission, $set, true);
    }

    private static function impliedByMediaManage(string $permission): bool
    {
        return in_array($permission, [
            'media.view',
            'media.upload',
            'media.collections.manage',
            'media.publish',
            'media.manage',
        ], true);
    }

    /**
     * @param list<string> $granted
     */
    private static function aliasMatch(array $granted, string $permission): bool
    {
        $pairs = [
            ['forum.categories.manage', 'forum.manage_categories'],
        ];
        foreach ($pairs as [$a, $b]) {
            if ($permission === $a && in_array($b, $granted, true)) {
                return true;
            }
            if ($permission === $b && in_array($a, $granted, true)) {
                return true;
            }
        }

        return false;
    }
}
