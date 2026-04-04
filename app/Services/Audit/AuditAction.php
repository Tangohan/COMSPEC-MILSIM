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

    /** Journalisation convention slugs site.* / community.* / intra.* (migration progressive). */
    public const PERMISSION_SCOPE_MIGRATION = 'permission.scope_migration';
}
