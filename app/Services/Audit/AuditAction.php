<?php

declare(strict_types=1);

namespace App\Services\Audit;

/**
 * Préfixes d’actions pour le journal audit_logs (cohérence recherche / exports).
 */
final class AuditAction
{
    public const AUTH_LOGIN_SUCCESS = 'auth.login_success';
    public const AUTH_LOGIN_FAILURE = 'auth.login_failure';
    public const AUTH_LOGOUT = 'auth.logout';
    public const AUTH_PASSWORD_RESET_REQUESTED = 'auth.password_reset_requested';
    public const AUTH_PASSWORD_RESET_COMPLETED = 'auth.password_reset_completed';
    public const AUTH_REGISTER = 'auth.register';

    public const TENANT_CREATED = 'tenant.created';
    public const TENANT_SETUP_COMPLETED = 'tenant.setup_completed';

    public const INVITATION_SENT = 'invitation.sent';
    public const INVITATION_ACCEPTED = 'invitation.accepted';
    public const INVITATION_REVOKED = 'invitation.revoked';

    public const MODERATION_ACTION = 'moderation.action_applied';
    public const MODERATION_REVOKED = 'moderation.action_revoked';
    public const FORUM_MODERATION = 'forum.moderation_action';

    public const SECURITY_EVENT = 'security.event';

    public const ROLE_PERMISSIONS_UPDATED = 'role.permissions_updated';

    public const SITE_ROLE_ASSIGNED = 'site_role.assigned';
    public const SITE_ROLE_REVOKED = 'site_role.revoked';

    /** Changement de statut compte depuis l’annuaire plateforme. */
    public const USER_STATUS_UPDATED = 'user.status_updated';

    /** Suppression (douce/anonymisation) d’un compte depuis l’annuaire plateforme. */
    public const USER_DELETED = 'user.deleted';

    /** Journalisation convention slugs site.* / community.* / intra.* (migration progressive). */
    public const PERMISSION_SCOPE_MIGRATION = 'permission.scope_migration';

    public const PLATFORM_SETTINGS_UPDATED = 'platform.settings_updated';

    public const SUBSCRIPTION_PLAN_UPDATED = 'platform.subscription_plan_updated';

    /** Affectation manuelle d’une formule à une communauté (opérateur plateforme). */
    public const TENANT_PLAN_ASSIGNED = 'platform.tenant_plan_assigned';

    public const DEPLOYMENT_MODULE_CREATED = 'deployment.module_created';
    public const DEPLOYMENT_MODULE_UPDATED = 'deployment.module_updated';
    public const DEPLOYMENT_VERSION_CREATED = 'deployment.version_created';
    public const DEPLOYMENT_RELEASE_SET = 'deployment.release_set';
    public const DEPLOYMENT_ACCESS_RULE_ADDED = 'deployment.access_rule_added';
    public const DEPLOYMENT_ACCESS_RULE_REMOVED = 'deployment.access_rule_removed';
    public const DEPLOYMENT_TESTER_COMMUNITY_UPDATED = 'deployment.tester_community_updated';
    public const DEPLOYMENT_TESTER_MEMBER_ADDED = 'deployment.tester_member_added';
    public const DEPLOYMENT_TESTER_MEMBER_REMOVED = 'deployment.tester_member_removed';

    public const DEPLOYMENT_CAMPAIGN_CREATED = 'deployment.campaign_created';

    public const DEPLOYMENT_CAMPAIGN_FAILED = 'deployment.campaign_failed';

    public const APP_UPDATE_UPLOADED = 'app_update.uploaded';
    public const APP_UPDATE_DEPLOYED = 'app_update.deployed';
    public const APP_UPDATE_FAILED = 'app_update.failed';
    public const APP_UPDATE_ROLLED_BACK = 'app_update.rolled_back';

    /** Restauration d’état depuis le journal d’activité. */
    public const AUDIT_ROLLBACK = 'audit.rollback';

    /** Alerte staff envoyée depuis le détail d’un événement du journal. */
    public const AUDIT_ROLLBACK_ALERT = 'audit.rollback_alert';
}
