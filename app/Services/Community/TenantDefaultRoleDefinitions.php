<?php

declare(strict_types=1);

namespace App\Services\Community;

use App\Support\SqlText;
use PDO;

/**
 * Rôles communauté / opérationnels par défaut : libellés et descriptions réalistes (milsim / unité).
 * Les slugs sont stables (références code, e-mails recrutement, etc.).
 */
final class TenantDefaultRoleDefinitions
{
    /**
     * @return list<array{slug: string, name: string, description: string, role_layer: string, is_system: int, is_locked: int}>
     */
    public static function governanceRoles(): array
    {
        return [
            [
                'slug' => 'community_owner',
                'name' => 'Gestionnaire',
                'description' => 'Autorité stratégique sur l’entité : gouvernance globale, hors périmètre technique de la plateforme.',
                'role_layer' => 'community',
                'is_system' => 1,
                'is_locked' => 1,
            ],
            [
                'slug' => 'tenant_admin',
                'name' => 'Gestionnaire adjoint',
                'description' => 'Administration opérationnelle quotidienne : membres, contenus et paramètres internes.',
                'role_layer' => 'community',
                'is_system' => 1,
                'is_locked' => 0,
            ],
            [
                'slug' => 'deputy_commander',
                'name' => 'Adjoint au commandement',
                'description' => 'Adjoint à la direction : coordination et relais de gouvernance.',
                'role_layer' => 'community',
                'is_system' => 1,
                'is_locked' => 0,
            ],
            [
                'slug' => 'technical_admin',
                'name' => 'Administrateur technique local',
                'description' => 'Paramètres techniques et outils au sein de la communauté.',
                'role_layer' => 'community',
                'is_system' => 1,
                'is_locked' => 0,
            ],
        ];
    }

    /**
     * États-majors / emplois organiques (hors catalogue militaire MOS) à droits métier ciblés.
     * Les badges de statut (élite, surveillance, fondateur…) restent sans permissions.
     *
     * @return list<array{slug: string, name: string, description: string, role_layer: string, is_system: int, is_locked: int}>
     */
    public static function organicStaffRoles(): array
    {
        return [
            [
                'slug' => 'operations_officer',
                'name' => 'Officier opérations',
                'description' => 'Planification et conduite des activités opérationnelles.',
                'role_layer' => 'intra',
                'is_system' => 1,
                'is_locked' => 0,
            ],
            [
                'slug' => 'training_officer',
                'name' => 'Officier formation',
                'description' => 'Pilotage des parcours, qualifications et exercices.',
                'role_layer' => 'intra',
                'is_system' => 1,
                'is_locked' => 0,
            ],
            [
                'slug' => 'intelligence_officer',
                'name' => 'Officier renseignement',
                'description' => 'Veille, synthèse et diffusion d’informations pertinentes.',
                'role_layer' => 'intra',
                'is_system' => 1,
                'is_locked' => 0,
            ],
            [
                'slug' => 'logistics_officer',
                'name' => 'Officier logistique',
                'description' => 'Soutien matériel, stocks et chaîne d’approvisionnement.',
                'role_layer' => 'intra',
                'is_system' => 1,
                'is_locked' => 0,
            ],
            [
                'slug' => 'discipline_officer',
                'name' => 'Officier discipline',
                'description' => 'Application du règlement intérieur et suivi des incidents.',
                'role_layer' => 'intra',
                'is_system' => 1,
                'is_locked' => 0,
            ],
            [
                'slug' => 'recruitment_officer',
                'name' => 'Officier recrutement',
                'description' => 'Pipeline des candidatures et intégration des nouveaux membres.',
                'role_layer' => 'intra',
                'is_system' => 1,
                'is_locked' => 0,
            ],
            [
                'slug' => 'security_officer',
                'name' => 'Officier sécurité',
                'description' => 'Sensibilisation, bonnes pratiques et coordination sécurité.',
                'role_layer' => 'intra',
                'is_system' => 1,
                'is_locked' => 0,
            ],
            [
                'slug' => 'auditor_internal',
                'name' => 'Contrôleur interne',
                'description' => 'Contrôles internes et recommandations d’amélioration.',
                'role_layer' => 'intra',
                'is_system' => 1,
                'is_locked' => 0,
            ],
        ];
    }

    /**
     * Rôles métiers livrés avec le forum / documents (couche intra ou communauté selon le slug).
     *
     * @return list<array{slug: string, name: string, description: string, role_layer: string, is_system: int, is_locked: int}>
     */
    public static function operationalRoles(): array
    {
        return [
            [
                'slug' => 'member',
                'name' => 'Opérateur',
                'description' => 'Membre titulaire de l’unité : accès forum, documents standards et formations selon affectation.',
                'role_layer' => 'intra',
                'is_system' => 1,
                'is_locked' => 0,
            ],
            [
                'slug' => 'officer',
                'name' => 'Cadre',
                'description' => 'Encadrement : coordination d’équipe, documents opérationnels et visibilité renforcée sur les ressources.',
                'role_layer' => 'intra',
                'is_system' => 1,
                'is_locked' => 0,
            ],
            [
                'slug' => 'forum_moderator',
                'name' => 'Officier responsable de la communication',
                'description' => 'Échanges, annonces, modération et structuration du discours collectif au sein de l’organisation.',
                'role_layer' => 'intra',
                'is_system' => 1,
                'is_locked' => 0,
            ],
            [
                'slug' => 'hr',
                'name' => 'Ressources humaines',
                'description' => 'Effectifs, recrutements, statuts, parcours et conformité interne.',
                'role_layer' => 'intra',
                'is_system' => 1,
                'is_locked' => 0,
            ],
            [
                'slug' => 'recruiter',
                'name' => 'Recrutement',
                'description' => 'Pipeline recrutement : candidatures, échanges avec les postulants et liaison avec le commandement.',
                'role_layer' => 'intra',
                'is_system' => 1,
                'is_locked' => 0,
            ],
            [
                'slug' => 'invite',
                'name' => 'Visiteur',
                'description' => 'Accès limité en attente d’intégration ou compte prospect (lecture ciblée).',
                'role_layer' => 'intra',
                'is_system' => 1,
                'is_locked' => 0,
            ],
            [
                'slug' => 'instructor',
                'name' => 'Instructeur',
                'description' => 'Pôle formation : parcours, assignations, correction des rendus et suivi des qualifications.',
                'role_layer' => 'intra',
                'is_system' => 1,
                'is_locked' => 0,
            ],
            [
                'slug' => 'trainer',
                'name' => 'Formateur',
                'description' => 'Conçoit et structure les parcours, objectifs, modules, prérequis et critères de validation.',
                'role_layer' => 'intra',
                'is_system' => 1,
                'is_locked' => 0,
            ],
            [
                'slug' => 'senior_instructor',
                'name' => 'Responsable des formateurs',
                'description' => 'Expertise pédagogique avancée et mentorat des instructeurs.',
                'role_layer' => 'intra',
                'is_system' => 1,
                'is_locked' => 0,
            ],
            [
                'slug' => 'instructor_trainer',
                'name' => 'Formateur d’instructeurs',
                'description' => 'Forme, valide et suit les instructeurs ; peut retirer ou suspendre une capacité d’animation.',
                'role_layer' => 'intra',
                'is_system' => 1,
                'is_locked' => 0,
            ],
            [
                'slug' => 'trainer_of_trainers',
                'name' => 'Formateur de formateurs',
                'description' => 'Gouvernance des concepteurs, référentiels, normes d’évaluation et qualité pédagogique.',
                'role_layer' => 'intra',
                'is_system' => 1,
                'is_locked' => 0,
            ],
            [
                'slug' => 'medic',
                'name' => 'OPSAN',
                'description' => 'Santé / secours : visibilité renforcée sur les informations médicales autorisées et coordination sanitaire.',
                'role_layer' => 'intra',
                'is_system' => 1,
                'is_locked' => 0,
            ],
            [
                'slug' => 'logistics',
                'name' => 'Logistique',
                'description' => 'Soutien matériel : dépôt, fiches équipement et documentation de soutien.',
                'role_layer' => 'intra',
                'is_system' => 1,
                'is_locked' => 0,
            ],
            [
                'slug' => 'rto',
                'name' => 'R2 (transmissions)',
                'description' => 'Radio-téléphoniste / transmissions : diffusion d’informations officielles et coordination des annonces.',
                'role_layer' => 'intra',
                'is_system' => 1,
                'is_locked' => 0,
            ],
            [
                'slug' => 'probation',
                'name' => 'Période d’essai',
                'description' => 'Intégration provisoire : participation encadrée au forum en attendant la titularisation.',
                'role_layer' => 'intra',
                'is_system' => 1,
                'is_locked' => 0,
            ],
            [
                'slug' => 'video_creator',
                'name' => 'Créateur de contenus vidéo',
                'description' => 'Produit et dépose les images et vidéos de la communauté : tournages, montages courts et liens vers les vidéos longues.',
                'role_layer' => 'intra',
                'is_system' => 1,
                'is_locked' => 0,
            ],
            [
                'slug' => 'media_manager',
                'name' => 'Responsable des médias',
                'description' => 'Pilote la bibliothèque médias : collections, floutage, publication sur la page publique et cohérence visuelle.',
                'role_layer' => 'intra',
                'is_system' => 1,
                'is_locked' => 0,
            ],
        ];
    }

    /**
     * Permissions par défaut (slugs) pour rôles opérationnels / organiques — appliquées si la permission existe pour le tenant.
     * Les badges de statut (élite, surveillance, fondateur…) n’ont volontairement aucune entrée ici.
     *
     * @return array<string, list<string>>
     */
    public static function defaultPermissionSlugsForOperationalRoles(): array
    {
        $officerCore = [
            'forum.view', 'forum.create_topic', 'forum.reply', 'forum.edit_own', 'forum.delete_own',
            'documents.view', 'documents.download.standard', 'training.view',
            'personnel.profile.view',
            'operational.board.view',
            'organization.orbat.view',
            'dashboard.pins.manage',
            'interteam.missions.respond',
            'cooperation.missions.respond',
            'cooperation.missions.view',
            'cooperation.exchange.read',
            'cooperation.exchange.write',
        ];

        return [
            'community_owner' => [
                'admin.organization',
                'dashboard.pins.manage',
            ],
            'tenant_admin' => [
                'admin.organization',
                'dashboard.pins.manage',
            ],
            'member' => [
                'forum.view', 'forum.create_topic', 'forum.reply', 'forum.edit_own', 'forum.delete_own',
                'documents.view', 'documents.download.standard', 'training.view',
                'operational.board.view',
                'organization.orbat.view',
                'operations.tactical.view',
                'operations.missions.view',
            ],
            'hr' => [
                'documents.view', 'forum.view', 'forum.create_topic', 'forum.reply', 'training.view',
                'invitations.send', 'admin.members.view', 'personnel.profile.view', 'personnel.profile.update',
                'dashboard.pins.manage',
                'organization.orbat.view',
                'organization.effectifs.hub.view',
                'organization.recruitment', 'organization.recruitment.manage', 'organization.recruitment.openings.manage',
                'member_integration.view', 'member_integration.manage', 'member_integration.assign',
                'member_integration.note', 'member_integration.template_manage',
            ],
            'recruiter' => [
                'forum.view', 'forum.create_topic', 'forum.reply',
                'invitations.send', 'admin.members.view', 'personnel.profile.view',
                'operational.board.view',
                'organization.orbat.view',
                'organization.recruitment', 'organization.recruitment.manage', 'organization.recruitment.openings.manage',
                'member_integration.view', 'member_integration.assign', 'member_integration.note',
            ],
            'instructor' => [
                'forum.view', 'forum.create_topic', 'forum.reply', 'forum.edit_own', 'forum.delete_own',
                'documents.view', 'documents.download.standard',
                'training.view', 'training.assign', 'training.submissions.grade', 'training.results.view',
                'training.create', 'training.update', 'training.manage',
                'personnel.profile.view',
                'dashboard.pins.manage',
                'operational.board.view',
                'organization.orbat.view',
                'member_integration.view',
            ],
            'trainer' => [
                'forum.view', 'forum.create_topic', 'forum.reply', 'forum.edit_own', 'forum.delete_own',
                'documents.view', 'documents.download.standard',
                'training.view', 'training.assign', 'training.submissions.grade', 'training.results.view',
                'training.create', 'training.update', 'training.manage',
                'personnel.profile.view',
                'dashboard.pins.manage',
                'operational.board.view',
                'organization.orbat.view',
            ],
            'senior_instructor' => [
                'forum.view', 'forum.create_topic', 'forum.reply', 'forum.edit_own', 'forum.delete_own',
                'documents.view', 'documents.download.standard',
                'training.view', 'training.assign', 'training.submissions.grade', 'training.results.view',
                'training.create', 'training.update', 'training.delete', 'training.publish',
                'training.manage', 'training.certifications.manage', 'training.publications.manage',
                'personnel.profile.view',
                'dashboard.pins.manage',
                'operational.board.view',
                'organization.orbat.view',
            ],
            'instructor_trainer' => [
                'forum.view', 'forum.create_topic', 'forum.reply', 'forum.edit_own', 'forum.delete_own',
                'documents.view', 'documents.download.standard',
                'training.view', 'training.assign', 'training.submissions.grade', 'training.results.view',
                'personnel.profile.view',
                'dashboard.pins.manage',
                'operational.board.view',
                'organization.orbat.view',
            ],
            'trainer_of_trainers' => [
                'forum.view', 'forum.create_topic', 'forum.reply', 'forum.edit_own', 'forum.delete_own',
                'documents.view', 'documents.download.standard',
                'training.view', 'training.assign', 'training.submissions.grade', 'training.results.view',
                'personnel.profile.view',
                'dashboard.pins.manage',
                'operational.board.view',
                'organization.orbat.view',
            ],
            'medic' => [
                'forum.view', 'forum.reply',
                'documents.view', 'documents.download.standard',
                'personnel.profile.view', 'personnel.sensitive.view',
                'organization.orbat.view',
            ],
            'logistics' => [
                'forum.view', 'forum.create_topic', 'forum.reply',
                'documents.view', 'documents.upload', 'documents.metadata.update',
                'organization.orbat.view',
            ],
            'rto' => [
                'forum.view', 'forum.create_topic', 'forum.reply', 'forum.edit_own',
                'comms.announcement.send',
                'comms.email.send.orbat',
                'comms.email.send.activity',
                'comms.email.send.custom',
                'organization.orbat.view',
            ],
            'probation' => [
                'forum.view', 'forum.reply',
            ],
            'officer' => $officerCore,
            'video_creator' => [
                'media.view',
                'media.upload',
                'forum.view',
                'forum.create_topic',
                'forum.reply',
                'documents.view',
                'documents.upload',
                'operational.board.view',
                'organization.orbat.view',
            ],
            'media_manager' => [
                'media.view',
                'media.upload',
                'media.collections.manage',
                'media.publish',
                'media.manage',
                'admin.branding.manage',
                'admin.backoffice.view',
                'forum.view',
                'forum.create_topic',
                'forum.reply',
                'forum.announcements.publish',
                'comms.announcement.send',
                'documents.view',
                'documents.upload',
                'operational.board.view',
                'organization.orbat.view',
            ],

            // —— Gouvernance communauté (sous-ensemble, hors owner) ——
            'technical_admin' => [
                'admin.access',
                'admin.backoffice.view',
                'admin.settings.manage',
                'admin.branding.manage',
                'admin.integrations.manage',
                'admin.audit.view',
                'documents.view',
                'documents.upload',
                'documents.categories.manage',
                'media.view',
                'media.upload',
                'media.collections.manage',
                'forum.view',
                'forum.categories.manage',
                'forum.manage_categories',
                'organization.orbat.view',
                'dashboard.pins.manage',
            ],
            'deputy_commander' => [
                'admin.access',
                'admin.organization',
                'admin.backoffice.view',
                'admin.members.view',
                'admin.members.manage',
                'admin.members.moderate',
                'admin.members.invite',
                'admin.audit.view',
                'invitations.send',
                'forum.view', 'forum.create_topic', 'forum.reply', 'forum.edit_own',
                'forum.moderate_organization',
                'forum.announcements.publish',
                'documents.view', 'documents.upload', 'documents.download.standard',
                'training.view',
                'personnel.profile.view', 'personnel.profile.update', 'personnel.assignments.manage',
                'organization.orbat.view', 'organization.orbat.manage',
                'organization.effectifs.hub.view',
                'operational.board.view', 'operational.board.edit',
                'dashboard.pins.manage',
                'comms.announcement.send',
                'comms.tenant_messages.receive',
                'interteam.missions.respond',
                'cooperation.missions.view', 'cooperation.missions.respond',
                'cooperation.exchange.read', 'cooperation.exchange.write',
                'member_integration.view', 'member_integration.manage', 'member_integration.assign',
                'member_integration.note', 'member_integration.template_manage',
            ],

            // —— États-majors / emplois organiques ——
            'operations_officer' => array_values(array_unique(array_merge($officerCore, [
                'operational.board.edit',
                'operations.missions.view', 'operations.missions.manage',
                'operations.tactical.view', 'operations.planning.edit',
                'operations.orders.edit', 'operations.overlay.publish', 'operations.phase.change',
                'operations.sitrep.view', 'operations.sitrep.create',
                'operations.aar.view',
                'operations.readiness.view', 'operations.readiness.manage',
                'operations.doctrine.view',
                'operations.comms.view',
                'documents.upload',
                'comms.email.send.mission',
                'comms.email.send.activity',
            ]))),
            'training_officer' => [
                'forum.view', 'forum.create_topic', 'forum.reply', 'forum.edit_own', 'forum.delete_own',
                'documents.view', 'documents.download.standard',
                'training.view', 'training.assign', 'training.submissions.grade', 'training.results.view',
                'training.certifications.manage', 'training.prerequisites.manage',
                'personnel.profile.view',
                'dashboard.pins.manage',
                'operational.board.view',
                'organization.orbat.view',
                'admin.backoffice.view',
                'organization.effectifs.hub.view',
                'member_integration.view',
            ],
            'intelligence_officer' => array_values(array_unique(array_merge($officerCore, [
                'operations.sitrep.view', 'operations.sitrep.create',
                'operations.doctrine.view',
                'operations.intel.product',
                'operations.tactical.view',
                'documents.upload',
                'documents.sensitive.view',
            ]))),
            'logistics_officer' => array_values(array_unique(array_merge($officerCore, [
                'operations.logistics.view', 'operations.logistics.manage',
                'operations.readiness.view',
                'documents.upload', 'documents.metadata.update',
            ]))),
            'discipline_officer' => [
                'admin.backoffice.view',
                'admin.members.view',
                'admin.members.moderate',
                'admin.audit.view',
                'personnel.profile.view',
                'personnel.status.manage',
                'forum.view', 'forum.reply',
                'forum.moderate_organization',
                'forum.reports.manage',
                'organization.effectifs.hub.view',
                'organization.orbat.view',
                'documents.view',
            ],
            'recruitment_officer' => [
                'forum.view', 'forum.create_topic', 'forum.reply',
                'invitations.send',
                'admin.backoffice.view',
                'admin.members.view',
                'admin.members.invite',
                'personnel.profile.view',
                'organization.recruitment.manage',
                'organization.recruitment.openings.manage',
                'organization.effectifs.hub.view',
                'organization.orbat.view',
                'operational.board.view',
                'documents.view',
                'member_integration.view', 'member_integration.assign', 'member_integration.note',
            ],
            'security_officer' => [
                'admin.backoffice.view',
                'admin.audit.view',
                'admin.members.view',
                'admin.members.moderate',
                'personnel.profile.view',
                'forum.view',
                'forum.moderate_organization',
                'forum.reports.manage',
                'documents.view',
                'documents.sensitive.view',
                'organization.orbat.view',
                'organization.effectifs.hub.view',
            ],
            'auditor_internal' => [
                'admin.backoffice.view',
                'admin.audit.view',
                'admin.compliance.export',
                'admin.members.view',
                'personnel.profile.view',
                'training.view',
                'training.results.view',
                'documents.view',
                'organization.orbat.view',
                'organization.effectifs.hub.view',
            ],
        ];
    }

    /**
     * Libellés anglais (doctrine US / usage international) pour les slugs système connus.
     * Complète les entrées hors catalogue militaire (`MilitaryOperationalRoleCatalog`).
     *
     * @return array<string, string> slug => label_en
     */
    public static function canonicalEnglishLabelsBySlug(): array
    {
        $fromDefs = [
            'community_owner' => 'Organization manager',
            'tenant_admin' => 'Organization administrator',
            'member' => 'Operator',
            'officer' => 'Supervisory member',
            'forum_moderator' => 'Communications officer',
            'hr' => 'Human resources manager',
            'recruiter' => 'Recruiter',
            'invite' => 'Visitor',
            'instructor' => 'Instructor',
            'trainer' => 'Trainer',
            'senior_instructor' => 'Senior Instructor',
            'instructor_trainer' => 'Instructor Trainer',
            'trainer_of_trainers' => 'Trainer of Trainers',
            'medic' => 'Medic',
            'logistics' => 'Logistics',
            'rto' => 'RTO (communications)',
            'probation' => 'Probation',
            'video_creator' => 'Video content creator',
            'media_manager' => 'Media manager',
        ];

        $organic = [
            'deputy_commander' => 'Deputy organization lead',
            'operations_officer' => 'Operations officer (S3)',
            'training_officer' => 'Training officer',
            'intelligence_officer' => 'Intelligence officer (S2)',
            'logistics_officer' => 'Logistics officer (S4)',
            'discipline_officer' => 'Discipline officer',
            'recruitment_officer' => 'Recruiting officer',
            'security_officer' => 'Security officer',
            'technical_admin' => 'Local technical administrator',
            'auditor_internal' => 'Internal auditor',
            'founder' => 'Founder',
            'veteran' => 'Veteran member',
            'certified_instructor' => 'Certified instructor',
            'elite_member' => 'Elite member',
            'disciplinary_watch' => 'Disciplinary watch',
            'probation_member' => 'Probationary member',
            'suspended_status' => 'Suspended',
            'honorary_member' => 'Honorary member',
            'guest' => 'Guest',
        ];

        return array_merge($fromDefs, $organic);
    }

    public static function rolesTableHasLabelEnColumn(PDO $pdo): bool
    {
        try {
            $st = $pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'roles' AND COLUMN_NAME = 'label_en' LIMIT 1");

            return (bool) $st?->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Met à jour label_en pour les rôles système dont le slug est dans le référentiel anglais.
     *
     * @param ?int $tenantId null = tous les tenants (backfill / migration)
     */
    public static function applyCanonicalEnglishLabels(PDO $pdo, ?int $tenantId = null): void
    {
        if (!self::rolesTableHasLabelEnColumn($pdo)) {
            return;
        }
        $map = self::canonicalEnglishLabelsBySlug();
        if ($map === []) {
            return;
        }
        $upd = $pdo->prepare('UPDATE roles SET label_en = ? WHERE tenant_id = ? AND ' . SqlText::equals($pdo, 'slug') . ' AND is_system = 1');
        $run = static function (int $tid) use ($map, $upd): void {
            if ($tid <= 0) {
                return;
            }
            foreach ($map as $slug => $en) {
                $upd->execute([$en, $tid, $slug]);
            }
        };
        if ($tenantId !== null && $tenantId > 0) {
            $run($tenantId);

            return;
        }
        $st = $pdo->query('SELECT id FROM tenants');
        if (!$st) {
            return;
        }
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $run((int) ($row['id'] ?? 0));
        }
    }

    /**
     * Met à jour nom + description pour les slugs connus (idempotent, sans toucher aux rôles personnalisés).
     */
    public static function applyCanonicalLabels(PDO $pdo, int $tenantId): void
    {
        if ($tenantId <= 0) {
            return;
        }
        $upd = $pdo->prepare('UPDATE roles SET name = ?, description = ? WHERE tenant_id = ? AND ' . SqlText::equals($pdo, 'slug') . ' AND is_system = 1');
        foreach (array_merge(self::governanceRoles(), self::operationalRoles(), self::organicStaffRoles()) as $row) {
            $upd->execute([
                $row['name'],
                $row['description'],
                $tenantId,
                $row['slug'],
            ]);
        }
        self::applyCanonicalEnglishLabels($pdo, $tenantId);
    }

    /**
     * Clé de tri pour listes admin : d’abord la couche communauté, puis intra ;
     * à l’intérieur de chaque couche, ordre type état-major → encadrement → soutiens → ligne → accès réduit.
     *
     * @param array<string, mixed> $roleRow
     */
    public static function organizationRoleDisplaySortKey(array $roleRow): int
    {
        $slug = strtolower(trim((string) ($roleRow['slug'] ?? '')));
        $layer = (string) ($roleRow['role_layer'] ?? 'community');
        $base = $layer === 'community' ? 0 : 1000;

        /** @var array<string, int> */
        $communitySlug = [
            'community_owner' => 10,
            'tenant_admin' => 20,
            'deputy_commander' => 25,
            'technical_admin' => 28,
            'recruiter' => 30,
        ];
        /** @var array<string, int> */
        $intraSlug = [
            'officer' => 10,
            'operations_officer' => 12,
            'training_officer' => 14,
            'intelligence_officer' => 16,
            'logistics_officer' => 18,
            'discipline_officer' => 19,
            'recruitment_officer' => 21,
            'security_officer' => 22,
            'auditor_internal' => 23,
            'instructor' => 20,
            'forum_moderator' => 30,
            'hr' => 40,
            'logistics' => 50,
            'medic' => 60,
            'rto' => 70,
            'member' => 80,
            'probation' => 90,
            'invite' => 100,
            'guest' => 105,
        ];

        if ($layer === 'community') {
            if (isset($communitySlug[$slug])) {
                return $base + $communitySlug[$slug];
            }
            if (isset($intraSlug[$slug])) {
                return $base + 200 + $intraSlug[$slug];
            }

            return $base + 40;
        }

        if (isset($intraSlug[$slug])) {
            return $base + $intraSlug[$slug];
        }
        if (isset($communitySlug[$slug])) {
            return $base + $communitySlug[$slug];
        }

        return $base + 250;
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    public static function sortOrganizationRoleRows(array $rows): array
    {
        usort(
            $rows,
            static function (array $a, array $b): int {
                $la = (string) ($a['role_layer'] ?? 'community');
                $lb = (string) ($b['role_layer'] ?? 'community');
                if ($la !== $lb) {
                    return $la === 'community' ? -1 : 1;
                }
                $ga = (int) ($a['display_group'] ?? 0);
                $gb = (int) ($b['display_group'] ?? 0);
                if ($ga !== $gb && ($ga > 0 || $gb > 0)) {
                    return $ga <=> $gb;
                }
                $wa = (int) ($a['display_weight'] ?? 0);
                $wb = (int) ($b['display_weight'] ?? 0);
                if ($wa !== $wb && ($wa > 0 || $wb > 0)) {
                    return $wa <=> $wb;
                }
                $ka = self::organizationRoleDisplaySortKey($a);
                $kb = self::organizationRoleDisplaySortKey($b);
                if ($ka !== $kb) {
                    return $ka <=> $kb;
                }

                return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
            }
        );

        return $rows;
    }
}
