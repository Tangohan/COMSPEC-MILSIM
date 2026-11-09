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
    public const AUTH_PASSWORD_CHANGED = 'auth.password_changed';
    public const AUTH_SESSIONS_REVOKED = 'auth.sessions_revoked';
    public const AUTH_REGISTER = 'auth.register';
    public const AUTH_TOTP_ENABLED = 'auth.totp_enabled';
    public const AUTH_TOTP_DISABLED = 'auth.totp_disabled';
    public const AUTH_EMAIL_LOGIN_OTP_TOGGLED = 'auth.email_login_otp_toggled';
    public const AUTH_RECOVERY_CODE_USED = 'auth.recovery_code_used';
    public const AUTH_RECOVERY_CODES_GENERATED = 'auth.recovery_codes_generated';
    public const AUTH_TRUSTED_DEVICES_REVOKED = 'auth.trusted_devices_revoked';
    public const AUTH_PORTAL_SELECTED = 'auth.portal_selected';
    public const USER_STEAM_LINKED = 'user.steam_linked';
    /** Steam en double : le joueur a prouvé posséder Steam et le garde sur ce compte. */
    public const USER_STEAM_CONFLICT_KEPT = 'user.steam_conflict_kept';
    /** Steam en double : retiré d'une fiche d'une autre personne après preuve Steam du titulaire. */
    public const USER_STEAM_REMOVED_BY_OWNER = 'user.steam_removed_by_owner';
    /** Steam en double : le joueur garde son autre compte et retire Steam de celui-ci. */
    public const USER_STEAM_CONFLICT_RELEASED = 'user.steam_conflict_released';
    /** Steam en double : le joueur ne reconnaît pas l'autre compte (Steam mis de côté, signalement). */
    public const USER_STEAM_CONFLICT_REPORTED = 'user.steam_conflict_reported';
    public const ADMIN_STEAM_CONFLICT_RESTORED = 'admin.steam_conflict_restored';
    public const ADMIN_STEAM_CONFLICT_CLOSED = 'admin.steam_conflict_closed';

    /** Fusion de fiches users partageant le même e-mail (un compte, plusieurs communautés). */
    public const USER_IDENTITY_MERGED = 'user.identity_merged';

    /** Critères d’éligibilité à une élévation réglés pour la communauté (Effectifs). */
    public const ELEVATION_CRITERIA_UPDATED = 'effectifs.elevation_criteria_updated';

    public const TENANT_CREATED = 'tenant.created';
    public const TENANT_SETUP_COMPLETED = 'tenant.setup_completed';

    public const ORGANIZATION_CATALOG_APPLIED = 'organization.catalog.applied';
    public const ORGANIZATION_CATALOG_SNAPSHOT = 'organization.catalog.snapshot';
    public const ORGANIZATION_CATALOG_RENAMED = 'organization.catalog.renamed';
    public const ORGANIZATION_CATALOG_REFRESHED = 'organization.catalog.refreshed';
    public const ORGANIZATION_CATALOG_ARCHIVED = 'organization.catalog.archived';
    public const ORGANIZATION_CATALOG_RESTORED = 'organization.catalog.restored';

    public const CONFIGURATION_UPDATE_SEEN = 'configuration_update.seen';
    public const CONFIGURATION_UPDATE_STARTED = 'configuration_update.started';
    public const CONFIGURATION_UPDATE_COMPLETED = 'configuration_update.completed';
    public const CONFIGURATION_UPDATE_DISMISSED = 'configuration_update.dismissed';
    public const CONFIGURATION_UPDATE_REOPENED = 'configuration_update.reopened';

    public const INVITATION_SENT = 'invitation.sent';
    public const INVITATION_ACCEPTED = 'invitation.accepted';
    public const INVITATION_REVOKED = 'invitation.revoked';

    /** Liens d'invitation partageables (/i/<code>). */
    public const INVITE_LINK_CREATED = 'invite_link.created';
    public const INVITE_LINK_UPDATED = 'invite_link.updated';
    public const INVITE_LINK_REVOKED = 'invite_link.revoked';
    public const INVITE_LINK_DELETED = 'invite_link.deleted';
    public const INVITE_LINK_REACTIVATED = 'invite_link.reactivated';
    public const INVITE_LINK_JOINED = 'invite_link.joined';
    public const INVITE_LINK_JOIN_REQUESTED = 'invite_link.join_requested';
    public const INVITE_LINK_APPLIED = 'invite_link.applied';

    /** Liens « compléter son profil » (Effectifs). */
    public const PROFILE_INVITE_CREATED = 'profile_invite.created';
    public const PROFILE_INVITE_OPENED = 'profile_invite.opened';
    public const PROFILE_INVITE_EXTENDED = 'profile_invite.extended';
    public const PROFILE_INVITE_REGENERATED = 'profile_invite.regenerated';
    public const PROFILE_INVITE_REVOKED = 'profile_invite.revoked';
    public const PROFILE_INVITE_SENT = 'profile_invite.sent';
    public const PROFILE_INVITE_ACCOUNT_READY = 'profile_invite.account_ready';
    public const PROFILE_INVITE_COMPLETED = 'profile_invite.completed';

    /** Salons Discord de la communauté (Intégrations). */
    public const DISCORD_WEBHOOK_SAVED = 'discord_webhook.saved';
    public const DISCORD_WEBHOOK_REMOVED = 'discord_webhook.removed';
    public const DISCORD_WEBHOOK_TESTED = 'discord_webhook.tested';

    public const MODERATION_ACTION = 'moderation.action_applied';
    public const MODERATION_REVOKED = 'moderation.action_revoked';
    public const REPORT_MODERATION = 'report.moderation_action';

    public const SECURITY_EVENT = 'security.event';

    public const ROLE_PERMISSIONS_UPDATED = 'role.permissions_updated';

    public const SITE_ROLE_ASSIGNED = 'site_role.assigned';
    public const SITE_ROLE_REVOKED = 'site_role.revoked';

    /** Changement de statut compte depuis l’annuaire plateforme. */
    public const USER_STATUS_UPDATED = 'user.status_updated';

    /** Édition complète de fiche depuis l’annuaire plateforme. */
    public const USER_PROFILE_UPDATED = 'user.profile_updated';

    /** Attribution ou retrait de l’administration du site (bit users). */
    public const PLATFORM_ADMIN_UPDATED = 'user.platform_admin_updated';

    /** Suppression (douce/anonymisation) d’un compte depuis l’annuaire plateforme. */
    public const USER_DELETED = 'user.deleted';

    /**
     * Suppression définitive : la ligne users et tout ce qui décrit la personne sont
     * effacés de la base. Journalisée avant exécution — après, il ne reste rien à relier.
     */
    public const USER_PURGED = 'user.purged';

    /** Bannissement d’une personne sur tout le site (fiches inactives + liste de restriction). */
    public const USER_BANNED = 'user.banned';

    /** Levée d’un bannissement site : fiches remises dans leur état d’avant. */
    public const USER_UNBANNED = 'user.unbanned';

    /** Fin d’une sanction temporaire : date d’échéance atteinte, fiches remises dans leur état d’avant. */
    public const USER_BAN_EXPIRED = 'user.ban_expired';

    /** Export CSV de l’annuaire des comptes (données personnelles) par l’administration du site. */
    public const PLATFORM_USERS_EXPORTED = 'platform.users_exported';

    /** Retrait d’une personne d’une communauté (réversible, fiche conservée). */
    public const USER_MEMBERSHIP_REMOVED = 'user.membership_removed';

    /** Réintégration d’une personne dans une communauté qu’elle avait quittée. */
    public const USER_MEMBERSHIP_RESTORED = 'user.membership_restored';

    /** Suppression définitive d’une communauté et de ses données (administration du site). */
    public const TENANT_DELETED = 'platform.tenant_deleted';

    /** Archivage réversible d’une communauté (administration du site). */
    public const TENANT_ARCHIVED = 'platform.tenant_archived';

    /** Restauration d’une communauté archivée (administration du site). */
    public const TENANT_RESTORED = 'platform.tenant_restored';

    public const MEMBER_INTEGRATION_CREATED = 'member_integration.created';
    public const MEMBER_INTEGRATION_STEP_FORCED = 'member_integration.step_forced';
    public const MEMBER_INTEGRATION_APPOINTMENT = 'member_integration.appointment';
    public const MEMBER_INTEGRATION_CANCELLED = 'member_integration.cancelled';

    /** Journalisation convention slugs site.* / community.* / intra.* (migration progressive). */
    public const PERMISSION_SCOPE_MIGRATION = 'permission.scope_migration';

    public const PLATFORM_SETTINGS_UPDATED = 'platform.settings_updated';

    /** Plancher de version des DLL du jeu (réglage game_min_link_version : administration du site ou tools/game-client-gate.php). */
    public const PLATFORM_GAME_CLIENT_FLOOR_UPDATED = 'platform.game_client_floor_updated';

    /** Réglages de surveillance : seuils d'alerte de charge, ralentissement sous charge, seuils des détecteurs du mod. */
    public const PLATFORM_LOAD_SETTINGS_UPDATED = 'platform.load_settings_updated';

    /** Décision humaine sur une alerte d'activité anormale du mod ou de charge (prise en compte, ignorée, rouverte). */
    public const PLATFORM_ABUSE_ALERT_HANDLED = 'platform.abuse_alert_handled';

    /** Vidage d’un lot d’historiques volumineux (administration du site). */
    public const PLATFORM_STORAGE_PURGED = 'platform.storage_purged';

    /** Console d'exploitation (/admin/system/exploitation). */
    public const PLATFORM_OPS_UNLOCKED = 'platform.ops_console_unlocked';
    public const PLATFORM_OPS_MIGRATIONS_LAUNCHED = 'platform.ops_migrations_launched';
    public const PLATFORM_OPS_BACKUP_CREATED = 'platform.ops_backup_created';
    public const PLATFORM_OPS_BACKUP_DOWNLOADED = 'platform.ops_backup_downloaded';
    public const PLATFORM_OPS_BACKUP_RESTORED = 'platform.ops_backup_restored';
    public const PLATFORM_OPS_BACKUP_DELETED = 'platform.ops_backup_deleted';
    public const PLATFORM_OPS_DELETION_SCHEDULED = 'platform.ops_account_deletion_scheduled';
    public const PLATFORM_OPS_DELETION_CANCELLED = 'platform.ops_account_deletion_cancelled';
    public const PLATFORM_OPS_DELETION_EXECUTED = 'platform.ops_account_deletion_executed';
    public const PLATFORM_DATA_TABLE_EMPTIED = 'platform.data_table_emptied';
    public const PLATFORM_DATA_ROW_UPDATED = 'platform.data_row_updated';
    public const PLATFORM_DATA_ROW_DELETED = 'platform.data_row_deleted';

    /** Actions sensibles des outils du site jusque-là absentes du journal. */
    public const PLATFORM_CRON_RUN = 'platform.cron_run';

    /** Aperçu de la formule d'une communauté par un administrateur de la plateforme (lecture seule, session). */
    public const PLATFORM_PLAN_PREVIEW_STARTED = 'platform.plan_preview_started';
    public const PLATFORM_PLAN_PREVIEW_STOPPED = 'platform.plan_preview_stopped';
    public const PLATFORM_VIEW_PREVIEW_STARTED = 'platform.view_preview_started';
    public const PLATFORM_VIEW_PREVIEW_STOPPED = 'platform.view_preview_stopped';
    public const PLATFORM_CRON_INSTALLED = 'platform.cron_installed';
    public const PLATFORM_CRON_UNINSTALLED = 'platform.cron_uninstalled';
    public const PLATFORM_UPDATE_UPLOADED = 'platform.update_uploaded';
    public const PLATFORM_UPDATE_DEPLOYED = 'platform.update_deployed';
    public const PLATFORM_UPDATE_ROLLED_BACK = 'platform.update_rolled_back';
    public const PLATFORM_INTEGRATIONS_UPDATED = 'platform.integrations_updated';
    public const PLATFORM_DEMO_ACCESS_UPDATED = 'platform.demo_access_updated';
    public const PLATFORM_CONTRIBUTOR_UPDATED = 'platform.contributor_updated';

    public const SUBSCRIPTION_PLAN_UPDATED = 'platform.subscription_plan_updated';
    public const SUBSCRIPTION_PLAN_DUPLICATED = 'platform.subscription_plan_duplicated';

    /** Affectation manuelle d’une formule à une communauté (opérateur plateforme). */
    public const TENANT_PLAN_ASSIGNED = 'platform.tenant_plan_assigned';

    /** Formule offerte à une communauté (durée ou sans limite, e-mail + fenêtre aux gestionnaires). */
    public const TENANT_PLAN_GIFTED = 'platform.tenant_plan_gifted';

    /** Message de l’administration du site aux back-offices des communautés : publié, modifié, activé, supprimé. */
    public const PLATFORM_BO_MESSAGE_CREATED = 'platform.bo_message_created';
    public const PLATFORM_BO_MESSAGE_UPDATED = 'platform.bo_message_updated';
    public const PLATFORM_BO_MESSAGE_TOGGLED = 'platform.bo_message_toggled';
    public const PLATFORM_BO_MESSAGE_DELETED = 'platform.bo_message_deleted';
    /** Message urgent acquitté par un gestionnaire de la communauté. */
    public const PLATFORM_BO_MESSAGE_ACKED = 'platform.bo_message_acked';

    /** Formule offerte retirée avant son terme. */
    public const TENANT_PLAN_GIFT_REVOKED = 'platform.tenant_plan_gift_revoked';

    /** Identité d’une communauté (nom, adresse publique) depuis l’administration du site. */
    public const TENANT_IDENTITY_UPDATED = 'platform.tenant_identity_updated';

    /** Profil d’outils d’une communauté (Complet / Effectifs / ATAK) depuis l’administration du site. */
    public const TENANT_TYPE_ASSIGNED = 'platform.tenant_type_assigned';

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

    public const PLATFORM_TRANSLATION_ACCEPTED = 'platform.translation_accepted';
    public const PLATFORM_TRANSLATION_DECLINED = 'platform.translation_declined';
    public const PLATFORM_NAME_FILTER_UPDATED = 'platform.name_filter_updated';
    public const PLATFORM_NAME_ALERT_RESOLVED = 'platform.name_alert_resolved';
}
