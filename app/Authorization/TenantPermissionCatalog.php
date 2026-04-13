<?php

declare(strict_types=1);

namespace App\Authorization;

/**
 * Catalogue canonique des permissions d’espace communauté (tenant).
 * Les slugs sont insérés par tenant ; les métadonnées servent au seed et à l’UI.
 */
final class TenantPermissionCatalog
{
    /** Verbes CRUD / RBAC alignés sur la taxonomie produit. */
    public const ACTIONS = [
        'view',
        'create',
        'update',
        'delete',
        'manage',
        'assign',
        'moderate',
        'export',
        'archive',
        'approve',
    ];

    /**
     * @return list<array{slug: string, module: string, action: string|null, name: string}>
     */
    public static function definitions(): array
    {
        return array_merge(
            self::adminDefinitions(),
            self::dashboardDefinitions(),
            self::forumDefinitions(),
            self::interteamDefinitions(),
            self::cooperationDefinitions(),
            self::documentsDefinitions(),
            self::trainingDefinitions(),
            self::personnelDefinitions(),
            self::organizationDefinitions(),
            self::commsDefinitions(),
            self::courrierDefinitions(),
            self::legacyDefinitions(),
        );
    }

    /** @return list<string> */
    public static function allSlugs(): array
    {
        return array_values(array_unique(array_map(static fn (array $r): string => $r['slug'], self::definitions())));
    }

    /**
     * Slugs couverts par l’ancienne permission forum.moderate (hors catégories globales).
     *
     * @return list<string>
     */
    public static function forumModerateGranularSlugs(): array
    {
        return [
            'forum.private.view',
            'forum.topic.pin',
            'forum.topic.lock',
            'forum.topic.move',
            'forum.post.edit_any',
            'forum.post.delete_any',
            'forum.reports.manage',
            'forum.tags.manage',
            'forum.announcements.publish',
        ];
    }

    /**
     * Slugs couverts par training.manage (hors assign).
     *
     * @return list<string>
     */
    public static function trainingManageGranularSlugs(): array
    {
        return [
            'training.create',
            'training.update',
            'training.delete',
            'training.publish',
            'training.submissions.grade',
            'training.certifications.manage',
            'training.prerequisites.manage',
        ];
    }

    /**
     * @return list<array{slug: string, module: string, action: string|null, name: string}>
     */
    private static function adminDefinitions(): array
    {
        return [
            ['slug' => 'admin.access', 'module' => 'admin', 'action' => 'manage', 'name' => 'Accès administration (tenant)'],
            ['slug' => 'admin.organization', 'module' => 'admin', 'action' => 'manage', 'name' => 'Administration organisationnelle'],
            ['slug' => 'admin.backoffice.view', 'module' => 'admin', 'action' => 'view', 'name' => 'Voir le back-office'],
            ['slug' => 'admin.members.view', 'module' => 'admin', 'action' => 'view', 'name' => 'Voir les membres'],
            ['slug' => 'admin.members.manage', 'module' => 'admin', 'action' => 'manage', 'name' => 'Gérer les membres'],
            ['slug' => 'admin.members.invite', 'module' => 'admin', 'action' => 'create', 'name' => 'Inviter des membres'],
            ['slug' => 'admin.members.moderate', 'module' => 'admin', 'action' => 'moderate', 'name' => 'Gérer les restrictions d’activité des membres (organisation)'],
            ['slug' => 'admin.roles.manage', 'module' => 'admin', 'action' => 'manage', 'name' => 'Gérer les rôles'],
            ['slug' => 'admin.permissions.manage', 'module' => 'admin', 'action' => 'manage', 'name' => 'Gérer les permissions'],
            ['slug' => 'admin.audit.view', 'module' => 'admin', 'action' => 'view', 'name' => 'Voir les journaux d’audit'],
            ['slug' => 'admin.compliance.export', 'module' => 'admin', 'action' => 'export', 'name' => 'Exporter les dossiers conformité (formations)'],
            ['slug' => 'admin.settings.manage', 'module' => 'admin', 'action' => 'manage', 'name' => 'Gérer les paramètres de la communauté'],
            ['slug' => 'admin.branding.manage', 'module' => 'admin', 'action' => 'manage', 'name' => 'Gérer l’identité visuelle / branding'],
            ['slug' => 'admin.integrations.manage', 'module' => 'admin', 'action' => 'manage', 'name' => 'Gérer les intégrations / API / webhooks'],
            ['slug' => 'invitations.send', 'module' => 'admin', 'action' => 'create', 'name' => 'Envoyer des invitations'],
        ];
    }

    /**
     * @return list<array{slug: string, module: string, action: string|null, name: string}>
     */
    private static function dashboardDefinitions(): array
    {
        return [
            ['slug' => 'dashboard.pins.manage', 'module' => 'dashboard', 'action' => 'manage', 'name' => 'Gérer les raccourcis du tableau de bord'],
        ];
    }

    /**
     * @return list<array{slug: string, module: string, action: string|null, name: string}>
     */
    private static function interteamDefinitions(): array
    {
        return [
            ['slug' => 'interteam.missions.manage', 'module' => 'interteam', 'action' => 'manage', 'name' => 'Piloter les missions inter-unités (invitations, partages)'],
            ['slug' => 'interteam.missions.respond', 'module' => 'interteam', 'action' => 'approve', 'name' => 'Accepter ou refuser une mission inter-unités'],
        ];
    }

    /**
     * @return list<array{slug: string, module: string, action: string|null, name: string}>
     */
    private static function cooperationDefinitions(): array
    {
        return [
            ['slug' => 'cooperation.missions.view', 'module' => 'cooperation', 'action' => 'view', 'name' => 'Voir les coopérations inter-unités'],
            ['slug' => 'cooperation.missions.create', 'module' => 'cooperation', 'action' => 'create', 'name' => 'Proposer une coopération inter-unités'],
            ['slug' => 'cooperation.missions.manage', 'module' => 'cooperation', 'action' => 'manage', 'name' => 'Piloter une coopération (invitations, autorisations, liaisons)'],
            ['slug' => 'cooperation.missions.respond', 'module' => 'cooperation', 'action' => 'approve', 'name' => 'Répondre à une proposition de coopération'],
            ['slug' => 'cooperation.missions.activate', 'module' => 'cooperation', 'action' => 'approve', 'name' => 'Lancer une coopération validée'],
            ['slug' => 'cooperation.missions.close', 'module' => 'cooperation', 'action' => 'archive', 'name' => 'Clôturer une coopération'],
            ['slug' => 'cooperation.missions.archive', 'module' => 'cooperation', 'action' => 'archive', 'name' => 'Archiver une coopération clôturée'],
            ['slug' => 'cooperation.exchange.read', 'module' => 'cooperation', 'action' => 'view', 'name' => 'Consulter l’espace commun de coopération'],
            ['slug' => 'cooperation.exchange.write', 'module' => 'cooperation', 'action' => 'create', 'name' => 'Publier dans l’espace commun de coopération'],
            ['slug' => 'cooperation.exchange.moderate', 'module' => 'cooperation', 'action' => 'moderate', 'name' => 'Modérer l’espace commun de coopération'],
            ['slug' => 'cooperation.meeting.launch', 'module' => 'cooperation', 'action' => 'manage', 'name' => 'Organiser ou ouvrir une réunion de coopération'],
            ['slug' => 'cooperation.data.request', 'module' => 'cooperation', 'action' => 'create', 'name' => 'Demander un partage de données dans une coopération'],
            ['slug' => 'cooperation.data.approve', 'module' => 'cooperation', 'action' => 'approve', 'name' => 'Approuver un partage de données (autorisation de partage)'],
            ['slug' => 'cooperation.data.revoke', 'module' => 'cooperation', 'action' => 'delete', 'name' => 'Révoquer un partage de données'],
            ['slug' => 'cooperation.orbat.view', 'module' => 'cooperation', 'action' => 'view', 'name' => 'Voir les structures et liaisons de coopération'],
            ['slug' => 'cooperation.readiness.view', 'module' => 'cooperation', 'action' => 'view', 'name' => 'Voir la préparation opérationnelle liée à une coopération'],
            ['slug' => 'cooperation.audit.view', 'module' => 'cooperation', 'action' => 'view', 'name' => 'Voir le journal d’audit d’une coopération'],
            ['slug' => 'cooperation.rex.submit', 'module' => 'cooperation', 'action' => 'create', 'name' => 'Rédiger un retour d’expérience de coopération'],
            ['slug' => 'cooperation.rex.read', 'module' => 'cooperation', 'action' => 'view', 'name' => 'Lire les retours d’expérience consolidés'],
            ['slug' => 'cooperation.catalog.manage', 'module' => 'cooperation', 'action' => 'manage', 'name' => 'Gérer le catalogue des types de coopération (communauté)'],
            ['slug' => 'cooperation.announcements.manage', 'module' => 'cooperation', 'action' => 'manage', 'name' => 'Gérer les messages types d’annonces coopération (communauté)'],
        ];
    }

    /**
     * @return list<array{slug: string, module: string, action: string|null, name: string}>
     */
    private static function forumDefinitions(): array
    {
        return [
            ['slug' => 'forum.view', 'module' => 'forum', 'action' => 'view', 'name' => 'Voir le forum'],
            ['slug' => 'forum.create_topic', 'module' => 'forum', 'action' => 'create', 'name' => 'Créer un sujet'],
            ['slug' => 'forum.reply', 'module' => 'forum', 'action' => 'create', 'name' => 'Répondre'],
            ['slug' => 'forum.edit_own', 'module' => 'forum', 'action' => 'update', 'name' => 'Modifier ses messages'],
            ['slug' => 'forum.delete_own', 'module' => 'forum', 'action' => 'delete', 'name' => 'Supprimer ses messages'],
            ['slug' => 'forum.private.view', 'module' => 'forum', 'action' => 'view', 'name' => 'Voir les sections privées'],
            ['slug' => 'forum.topic.pin', 'module' => 'forum', 'action' => 'manage', 'name' => 'Épingler un sujet'],
            ['slug' => 'forum.topic.lock', 'module' => 'forum', 'action' => 'moderate', 'name' => 'Verrouiller / déverrouiller un sujet'],
            ['slug' => 'forum.topic.move', 'module' => 'forum', 'action' => 'manage', 'name' => 'Déplacer un sujet'],
            ['slug' => 'forum.post.edit_any', 'module' => 'forum', 'action' => 'update', 'name' => 'Éditer n’importe quel message'],
            ['slug' => 'forum.post.delete_any', 'module' => 'forum', 'action' => 'delete', 'name' => 'Supprimer n’importe quel message'],
            ['slug' => 'forum.reports.manage', 'module' => 'forum', 'action' => 'moderate', 'name' => 'Gérer les signalements'],
            ['slug' => 'forum.tags.manage', 'module' => 'forum', 'action' => 'manage', 'name' => 'Gérer les tags / labels'],
            ['slug' => 'forum.categories.manage', 'module' => 'forum', 'action' => 'manage', 'name' => 'Gérer les catégories forum'],
            ['slug' => 'forum.announcements.publish', 'module' => 'forum', 'action' => 'approve', 'name' => 'Publier des annonces globales'],
            ['slug' => 'forum.moderate', 'module' => 'forum', 'action' => 'moderate', 'name' => 'Modérer le forum (périmètre étendu)'],
            ['slug' => 'forum.moderate_organization', 'module' => 'forum', 'action' => 'moderate', 'name' => 'Modérer la section forum organisation'],
        ];
    }

    /**
     * @return list<array{slug: string, module: string, action: string|null, name: string}>
     */
    private static function documentsDefinitions(): array
    {
        return [
            ['slug' => 'documents.view', 'module' => 'documents', 'action' => 'view', 'name' => 'Voir les documents'],
            ['slug' => 'documents.sensitive.view', 'module' => 'documents', 'action' => 'view', 'name' => 'Voir les documents sensibles'],
            ['slug' => 'documents.download.standard', 'module' => 'documents', 'action' => 'view', 'name' => 'Télécharger les documents standards'],
            ['slug' => 'documents.download_sensitive', 'module' => 'documents', 'action' => 'view', 'name' => 'Télécharger les documents sensibles'],
            ['slug' => 'documents.upload', 'module' => 'documents', 'action' => 'create', 'name' => 'Téléverser des documents'],
            ['slug' => 'documents.version.replace', 'module' => 'documents', 'action' => 'update', 'name' => 'Remplacer une version'],
            ['slug' => 'documents.metadata.update', 'module' => 'documents', 'action' => 'update', 'name' => 'Modifier les métadonnées'],
            ['slug' => 'documents.delete', 'module' => 'documents', 'action' => 'delete', 'name' => 'Supprimer un document'],
            ['slug' => 'documents.update', 'module' => 'documents', 'action' => 'update', 'name' => 'Modifier les documents (héritage)'],
            ['slug' => 'documents.archive', 'module' => 'documents', 'action' => 'archive', 'name' => 'Archiver / désarchiver'],
            ['slug' => 'documents.categories.manage', 'module' => 'documents', 'action' => 'manage', 'name' => 'Gérer les catégories documentaires'],
            ['slug' => 'documents.access.manage', 'module' => 'documents', 'action' => 'manage', 'name' => 'Gérer les droits d’accès documentaires'],
            ['slug' => 'documents.share.public', 'module' => 'documents', 'action' => 'manage', 'name' => 'Partager en lien public'],
            ['slug' => 'documents.publish', 'module' => 'documents', 'action' => 'approve', 'name' => 'Valider / publier un document'],
        ];
    }

    /**
     * @return list<array{slug: string, module: string, action: string|null, name: string}>
     */
    private static function trainingDefinitions(): array
    {
        return [
            ['slug' => 'training.view', 'module' => 'training', 'action' => 'view', 'name' => 'Voir les formations'],
            ['slug' => 'training.create', 'module' => 'training', 'action' => 'create', 'name' => 'Créer une formation'],
            ['slug' => 'training.update', 'module' => 'training', 'action' => 'update', 'name' => 'Modifier une formation'],
            ['slug' => 'training.delete', 'module' => 'training', 'action' => 'delete', 'name' => 'Supprimer une formation'],
            ['slug' => 'training.publish', 'module' => 'training', 'action' => 'approve', 'name' => 'Publier / dépublier une formation'],
            ['slug' => 'training.assign', 'module' => 'training', 'action' => 'assign', 'name' => 'Assigner les formations'],
            ['slug' => 'training.submissions.grade', 'module' => 'training', 'action' => 'approve', 'name' => 'Corriger / valider les rendus'],
            ['slug' => 'training.results.view', 'module' => 'training', 'action' => 'view', 'name' => 'Voir les résultats'],
            ['slug' => 'training.results.export', 'module' => 'training', 'action' => 'export', 'name' => 'Exporter les résultats'],
            ['slug' => 'training.certifications.manage', 'module' => 'training', 'action' => 'manage', 'name' => 'Gérer les certifications'],
            ['slug' => 'training.prerequisites.manage', 'module' => 'training', 'action' => 'manage', 'name' => 'Gérer les prérequis'],
            ['slug' => 'training.manage', 'module' => 'training', 'action' => 'manage', 'name' => 'Gérer les formations (périmètre étendu)'],
        ];
    }

    /**
     * @return list<array{slug: string, module: string, action: string|null, name: string}>
     */
    private static function personnelDefinitions(): array
    {
        return [
            ['slug' => 'personnel.profile.view', 'module' => 'personnel', 'action' => 'view', 'name' => 'Voir les fiches membres'],
            ['slug' => 'personnel.profile.update', 'module' => 'personnel', 'action' => 'update', 'name' => 'Modifier les fiches membres'],
            ['slug' => 'personnel.sensitive.view', 'module' => 'personnel', 'action' => 'view', 'name' => 'Voir les informations sensibles'],
            ['slug' => 'personnel.grades.manage', 'module' => 'personnel', 'action' => 'manage', 'name' => 'Gérer les grades'],
            ['slug' => 'personnel.assignments.manage', 'module' => 'personnel', 'action' => 'assign', 'name' => 'Gérer affectations / unités'],
            ['slug' => 'personnel.status.manage', 'module' => 'personnel', 'action' => 'manage', 'name' => 'Gérer les statuts'],
            ['slug' => 'personnel.badges.manage', 'module' => 'personnel', 'action' => 'manage', 'name' => 'Gérer badges / qualifications'],
            ['slug' => 'personnel.directory.export', 'module' => 'personnel', 'action' => 'export', 'name' => 'Exporter l’annuaire'],
        ];
    }

    /**
     * Habilitations fines pour l’organisation (tenant), sans droits « site ».
     *
     * @return list<array{slug: string, module: string, action: string|null, name: string}>
     */
    private static function organizationDefinitions(): array
    {
        return [
            ['slug' => 'organization.orbat.view', 'module' => 'organization', 'action' => 'view', 'name' => 'Consulter l’ORBAT'],
            ['slug' => 'organization.orbat.manage', 'module' => 'organization', 'action' => 'manage', 'name' => 'Gérer la structure ORBAT (unités, rattachements)'],
            ['slug' => 'organization.effectifs.hub.view', 'module' => 'organization', 'action' => 'view', 'name' => 'Accéder au hub effectifs'],
            ['slug' => 'organization.recruitment.manage', 'module' => 'organization', 'action' => 'manage', 'name' => 'Gérer le recrutement (dossiers, décisions)'],
            ['slug' => 'organization.recruitment.openings.manage', 'module' => 'organization', 'action' => 'manage', 'name' => 'Gérer les offres publiées et le format des références'],
            ['slug' => 'organization.job_roles.referential.manage', 'module' => 'organization', 'action' => 'manage', 'name' => 'Gérer le référentiel des emplois métier'],
        ];
    }

    /**
     * @return list<array{slug: string, module: string, action: string|null, name: string}>
     */
    private static function commsDefinitions(): array
    {
        return [
            ['slug' => 'comms.tenant_messages.receive', 'module' => 'comms', 'action' => 'view', 'name' => 'Recevoir les messages internes adressés à l’encadrement'],
            ['slug' => 'comms.announcement.send', 'module' => 'comms', 'action' => 'create', 'name' => 'Envoyer une annonce'],
            ['slug' => 'comms.email.broadcast', 'module' => 'comms', 'action' => 'manage', 'name' => 'Diffusion e-mail large (tous types de messages aux membres)'],
            ['slug' => 'comms.email.send.orbat', 'module' => 'comms', 'action' => 'create', 'name' => 'Envoyer un e-mail lié à la structure (ORBAT)'],
            ['slug' => 'comms.email.send.mission', 'module' => 'comms', 'action' => 'create', 'name' => 'Envoyer un e-mail lié au pilotage opérationnel'],
            ['slug' => 'comms.email.send.activity', 'module' => 'comms', 'action' => 'create', 'name' => 'Envoyer un e-mail lié aux activités'],
            ['slug' => 'comms.email.send.custom', 'module' => 'comms', 'action' => 'create', 'name' => 'Envoyer un e-mail libre aux membres'],
            ['slug' => 'comms.email_templates.manage', 'module' => 'comms', 'action' => 'manage', 'name' => 'Gérer les modèles d’email'],
            ['slug' => 'comms.notifications.history.view', 'module' => 'comms', 'action' => 'view', 'name' => 'Voir l’historique des notifications'],
            ['slug' => 'comms.alerts.manage', 'module' => 'comms', 'action' => 'manage', 'name' => 'Gérer les alertes automatiques'],
            ['slug' => 'comms.settings.advanced', 'module' => 'comms', 'action' => 'manage', 'name' => 'Paramétrage fin des communications'],
        ];
    }

    /**
     * @return list<array{slug: string, module: string, action: string|null, name: string}>
     */
    private static function courrierDefinitions(): array
    {
        return [
            ['slug' => 'courrier.view', 'module' => 'courrier', 'action' => 'view', 'name' => 'Voir le Bureau Courrier'],
            ['slug' => 'courrier.create', 'module' => 'courrier', 'action' => 'create', 'name' => 'Créer des documents courrier'],
            ['slug' => 'courrier.validate', 'module' => 'courrier', 'action' => 'approve', 'name' => 'Valider des documents courrier'],
            ['slug' => 'courrier.archive', 'module' => 'courrier', 'action' => 'archive', 'name' => 'Archiver des documents courrier'],
        ];
    }

    /**
     * Anciens identifiants conservés pour compatibilité (doublons sémantiques).
     *
     * @return list<array{slug: string, module: string, action: string|null, name: string}>
     */
    private static function legacyDefinitions(): array
    {
        return [
            ['slug' => 'forum.manage_categories', 'module' => 'forum', 'action' => 'manage', 'name' => 'Gérer les catégories (identifiant historique)'],
        ];
    }
}
