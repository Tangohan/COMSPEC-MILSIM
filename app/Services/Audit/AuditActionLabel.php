<?php

declare(strict_types=1);

namespace App\Services\Audit;

/**
 * Libellés français pour les slugs d’audit_logs (journal opérationnel, exports).
 */
final class AuditActionLabel
{
    /** @var array<string, string> */
    private const MAP = [
        // Auth
        'auth.login_success' => 'Connexion réussie',
        'auth.login_failure' => 'Échec de connexion',
        'auth.logout' => 'Déconnexion',
        'auth.password_reset_requested' => 'Demande de réinitialisation du mot de passe',
        'auth.password_reset_completed' => 'Mot de passe réinitialisé',
        'auth.register' => 'Inscription',
        // Tenant / communauté
        'tenant.created' => 'Organisation créée',
        'tenant.setup_completed' => 'Configuration de l’organisation terminée',
        'configuration_update.seen' => 'Nouveauté de configuration présentée',
        'configuration_update.started' => 'Configuration post-mise à jour commencée',
        'configuration_update.completed' => 'Configuration post-mise à jour terminée',
        'configuration_update.dismissed' => 'Configuration post-mise à jour ignorée',
        'configuration_update.reopened' => 'Configuration post-mise à jour rouverte',
        // ATAK : Contrôle de mission
        'atak.server_control.updated' => 'Contrôle de mission : réglages modifiés',
        'atak.server_control.network_adopted' => 'Contrôle de mission : conditions réseau reprises du jeu',
        // Invitations
        'invitation.sent' => 'Invitation envoyée',
        'invitation.accepted' => 'Invitation acceptée',
        'invitation.revoked' => 'Invitation révoquée',
        'invite_link.created' => 'Lien d’invitation créé',
        'invite_link.updated' => 'Lien d’invitation modifié',
        'invite_link.revoked' => 'Lien d’invitation révoqué',
        'invite_link.reactivated' => 'Lien d’invitation réactivé',
        'invite_link.joined' => 'Adhésion par lien d’invitation',
        'invite_link.join_requested' => 'Demande d’adhésion par lien d’invitation',
        'invite_link.applied' => 'Candidature par lien d’invitation',
        'profile_invite.created' => 'Lien pour compléter le profil créé',
        'profile_invite.opened' => 'Lien pour compléter le profil ouvert',
        'profile_invite.extended' => 'Lien pour compléter le profil prolongé',
        'profile_invite.regenerated' => 'Lien pour compléter le profil régénéré',
        'profile_invite.revoked' => 'Lien pour compléter le profil révoqué',
        'profile_invite.sent' => 'Lien pour compléter le profil envoyé',
        'profile_invite.account_ready' => 'Compte activé depuis le lien d’invitation',
        'profile_invite.completed' => 'Profil complété depuis le lien d’invitation',
        'discord_webhook.saved' => 'Salon Discord enregistré',
        'discord_webhook.removed' => 'Salon Discord retiré',
        'discord_webhook.tested' => 'Salon Discord testé',
        // Modération
        'moderation.action_applied' => 'Action de modération appliquée',
        'moderation.action_revoked' => 'Action de modération révoquée',
        'report.moderation_action' => 'Modération des signalements',
        // Sécurité
        'security.event' => 'Événement de sécurité',
        // Emplois et catégories du dossier (sans droits d’accès).
        'personnel.job_role.created' => 'Emploi créé',
        'personnel.job_role.updated' => 'Emploi modifié',
        'personnel.job_role.archived' => 'Emploi archivé',
        'personnel.job_role.restored' => 'Emploi restauré',
        'personnel.job_category.created' => 'Catégorie d’emplois créée',
        'personnel.job_category.updated' => 'Catégorie d’emplois modifiée',
        'personnel.job_category.deleted' => 'Catégorie d’emplois vide supprimée',
        // Rôles & permissions
        'role.permissions_updated' => 'Permissions du rôle mises à jour',
        'permission.scope_migration' => 'Migration des périmètres de permissions',
        'site_role.assigned' => 'Rôle site attribué',
        'site_role.revoked' => 'Rôle site révoqué',
        // Utilisateurs (back-office org)
        'user_created' => 'Utilisateur créé',
        'user_updated' => 'Utilisateur modifié',
        'user_deactivated' => 'Utilisateur désactivé',
        'user_left_community' => 'Départ volontaire d’une communauté',
        'role_assigned' => 'Rôle attribué',
        'effectifs.elevation_criteria_updated' => 'Critères d’élévation modifiés',
        'group_member_added' => 'Membre ajouté au groupe',
        'group_member_removed' => 'Membre retiré du groupe',
        // Documents (audit global)
        'document_uploaded' => 'Document téléversé',
        'document_downloaded' => 'Document téléchargé',
        'document_updated' => 'Document modifié',
        'document_archived' => 'Document archivé',
        // Formations LMS
        'course_created' => 'Formation créée',
        'course_updated' => 'Formation modifiée',
        'course_published' => 'Formation publiée',
        'enrollment_assigned' => 'Inscription attribuée',
        'lesson_completed' => 'Leçon terminée',
        'quiz_attempt_submitted' => 'Quiz soumis',
        'certificate_issued' => 'Certificat délivré',
        'certificate_revoked' => 'Certificat révoqué',
        // Documentations HTML LMS
        'formation_doc_created' => 'Documentation HTML créée',
        'formation_doc_updated' => 'Documentation HTML modifiée',
        'formation_doc_published' => 'Documentation HTML publiée',
        'formation_doc_archived' => 'Documentation HTML archivée',
        'formation_doc_deleted' => 'Documentation HTML supprimée',
        'formation_doc_restored' => 'Documentation HTML — version restaurée',
        'formation_doc_duplicated' => 'Documentation HTML dupliquée',
        // Plateforme — réglages et déploiement
        'platform.settings_updated' => 'Réglage plateforme (brief) mis à jour',
        'platform.storage_purged' => 'Historique volumineux vidé (administration du site)',
        'platform.ops_console_unlocked' => 'Console d’exploitation ouverte (double vérification)',
        'platform.ops_migrations_launched' => 'Migrations lancées depuis la console d’exploitation',
        'platform.ops_backup_created' => 'Sauvegarde créée',
        'platform.ops_backup_downloaded' => 'Sauvegarde téléchargée',
        'platform.ops_backup_restored' => 'Sauvegarde restaurée',
        'platform.ops_backup_deleted' => 'Sauvegarde supprimée',
        'platform.ops_account_deletion_scheduled' => 'Suppression de compte programmée',
        'platform.ops_account_deletion_cancelled' => 'Suppression de compte annulée',
        'platform.ops_account_deletion_executed' => 'Compte supprimé depuis la console d’exploitation',
        'platform.game_client_floor_updated' => 'Version minimale des DLL du jeu modifiée (administration du site)',
        'platform.load_settings_updated' => 'Réglages de charge et de détection du mod modifiés (administration du site)',
        'platform.abuse_alert_handled' => 'Alerte d’activité anormale traitée (administration du site)',
        'platform.donations_updated' => 'Réglage des dons PayPal modifié (administration du site)',
        'platform.data_table_emptied' => 'Table vidée depuis l’explorateur de données (administration du site)',
        'platform.data_row_updated' => 'Ligne modifiée depuis l’explorateur de données (administration du site)',
        'platform.data_row_deleted' => 'Ligne supprimée depuis l’explorateur de données (administration du site)',
        'platform.cron_run' => 'Tâches automatiques lancées à la main (administration du site)',
        'platform.cron_installed' => 'Planificateur des tâches installé sur le serveur',
        'platform.cron_uninstalled' => 'Planificateur des tâches retiré du serveur',
        'platform.update_uploaded' => 'Paquet de mise à jour du site envoyé',
        'platform.update_deployed' => 'Mise à jour du site déployée',
        'platform.update_rolled_back' => 'Retour à la version précédente du site',
        'platform.integrations_updated' => 'Intégrations du site modifiées (Discord, e-mails)',
        'platform.demo_access_updated' => 'Accès démo du site modifié',
        'platform.contributor_updated' => 'Contributeurs ou langues du site modifiés',
        'platform.subscription_plan_updated' => 'Formule d’accès (palier) modifiée',
        'platform.subscription_plan_duplicated' => 'Formule d’accès (palier) dupliquée',
        'deployment.module_created' => 'Déploiement — fonctionnalité créée',
        'deployment.module_updated' => 'Déploiement — fonctionnalité modifiée',
        'deployment.version_created' => 'Déploiement — version créée',
        'deployment.release_set' => 'Déploiement — publication sur un canal',
        'deployment.access_rule_added' => 'Déploiement — règle d’accès ajoutée',
        'deployment.access_rule_removed' => 'Déploiement — règle d’accès supprimée',
        'deployment.tester_community_updated' => 'Déploiement — communauté de test modifiée',
        'deployment.tester_member_added' => 'Déploiement — membre ajouté à une communauté de test',
        'deployment.tester_member_removed' => 'Déploiement — membre retiré d’une communauté de test',
        'deployment.campaign_created' => 'Déploiement — campagne de publication créée',
        'deployment.campaign_failed' => 'Déploiement — campagne de publication en échec',
        'user.identity_merged' => 'Comptes du même e-mail réunis en une seule identité',
        'user.steam_conflict_kept' => 'Steam en double : gardé sur ce compte après preuve Steam',
        'user.steam_removed_by_owner' => 'Steam retiré : le titulaire du compte Steam l’a prouvé depuis un autre compte',
        'user.steam_conflict_released' => 'Steam en double : retiré de ce compte, le joueur garde son autre compte',
        'user.steam_conflict_reported' => 'Steam en double : autre compte non reconnu, signalé à l’administration',
        'admin.steam_conflict_restored' => 'Steam en double : Steam rendu au déclarant par l’administration',
        'admin.steam_conflict_closed' => 'Steam en double : signalement classé par l’administration',
        'user.status_updated' => 'Statut du compte modifié',
        'user.profile_updated' => 'Fiche complète mise à jour',
        'user.deleted' => 'Compte supprimé (anonymisé)',
        'user.purged' => 'Compte supprimé définitivement',
        'user.banned' => 'Personne bannie du site',
        'user.unbanned' => 'Bannissement levé',
        'user.ban_expired' => 'Sanction temporaire terminée (date de fin atteinte)',
        'platform.users_exported' => 'Export CSV des comptes (administration du site)',
        'user.membership_removed' => 'Retrait d’une communauté',
        'user.membership_restored' => 'Réintégration dans une communauté',
        'platform.tenant_deleted' => 'Communauté supprimée',
        'platform.tenant_archived' => 'Communauté archivée',
        'platform.tenant_restored' => 'Communauté restaurée',
        'user_purge_requested' => 'Demande de suppression définitive (orga)',
        'platform.tenant_plan_assigned' => 'Formule d’accès affectée à une communauté',
        'platform.tenant_identity_updated' => 'Identité d’une communauté mise à jour',
        'platform.tenant_type_assigned' => 'Profil d’outils d’une communauté modifié',
        'platform.tenant_plan_gifted' => 'Formule offerte à une communauté',
        'platform.tenant_plan_gift_revoked' => 'Formule offerte retirée',
        'audit.rollback' => 'Restauration d’état depuis le journal',
        'audit.rollback_alert' => 'Alerte envoyée depuis le journal',
    ];

    /**
     * @return array<string, string> slug => libellé (tri par libellé)
     */
    public static function filterOptions(): array
    {
        $opts = self::MAP;
        asort($opts, SORT_NATURAL | SORT_FLAG_CASE);

        return $opts;
    }

    public static function toFrench(string $action): string
    {
        $action = trim($action);
        if ($action === '') {
            return '—';
        }
        if (isset(self::MAP[$action])) {
            return self::MAP[$action];
        }

        return self::humanizeSlug($action);
    }

    /** Fallback : moderation.user_muted → « Moderation · user muted » */
    private static function humanizeSlug(string $action): string
    {
        $parts = explode('.', $action, 2);
        if (count($parts) === 2) {
            $domain = str_replace(['_', '-'], ' ', $parts[0]);
            $verb = str_replace(['_', '-'], ' ', $parts[1]);
            $title = static function (string $s): string {
                if (function_exists('mb_convert_case')) {
                    return mb_convert_case($s, MB_CASE_TITLE, 'UTF-8');
                }

                return ucwords($s);
            };

            return $title($domain) . ' · ' . $title($verb);
        }

        return str_replace(['_', '-'], ' ', $action);
    }
}
