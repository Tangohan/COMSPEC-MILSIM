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
        // Invitations
        'invitation.sent' => 'Invitation envoyée',
        'invitation.accepted' => 'Invitation acceptée',
        'invitation.revoked' => 'Invitation révoquée',
        // Modération
        'moderation.action_applied' => 'Action de modération appliquée',
        'moderation.action_revoked' => 'Action de modération révoquée',
        'forum.moderation_action' => 'Modération forum',
        // Sécurité
        'security.event' => 'Événement de sécurité',
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
        'platform.subscription_plan_updated' => 'Formule d’accès (palier) modifiée',
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
        'user.status_updated' => 'Statut du compte modifié',
        'user.profile_updated' => 'Fiche complète mise à jour',
        'user.deleted' => 'Compte supprimé (anonymisé)',
        'user.purged' => 'Compte supprimé définitivement',
        'user_purge_requested' => 'Demande de suppression définitive (orga)',
        'platform.tenant_plan_assigned' => 'Formule d’accès affectée à une communauté',
        'platform.tenant_identity_updated' => 'Identité d’une communauté mise à jour',
        'platform.tenant_type_assigned' => 'Profil d’outils d’une communauté modifié',
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

    /** Fallback : forum.post_hidden → « Forum · post hidden » */
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
