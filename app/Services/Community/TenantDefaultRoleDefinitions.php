<?php

declare(strict_types=1);

namespace App\Services\Community;

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
                'name' => 'Fondateur',
                'description' => 'Propriétaire de la communauté : vision, gouvernance et validation stratégique. Ne confère pas l’administration technique de la plateforme.',
                'role_layer' => 'community',
                'is_system' => 1,
                'is_locked' => 1,
            ],
            [
                'slug' => 'tenant_admin',
                'name' => 'État-major',
                'description' => 'Direction de l’unité au quotidien : effectifs, ORBAT, rôles, invitations, modération organisationnelle et paramètres.',
                'role_layer' => 'community',
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
                'name' => 'Modérateur forum',
                'description' => 'Modération des espaces de discussion de l’unité (épinglage, signalements, catégories). Périmètre organisation, pas administration plateforme.',
                'role_layer' => 'intra',
                'is_system' => 1,
                'is_locked' => 0,
            ],
            [
                'slug' => 'hr',
                'name' => 'RH (S1)',
                'description' => 'Ressources humaines : dossiers personnel, grades et suivi administratif des effectifs.',
                'role_layer' => 'intra',
                'is_system' => 1,
                'is_locked' => 0,
            ],
            [
                'slug' => 'recruiter',
                'name' => 'Recruteur',
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
        ];
    }

    /**
     * Permissions par défaut (slugs) pour rôles opérationnels — appliquées si la permission existe pour le tenant.
     *
     * @return array<string, list<string>>
     */
    public static function defaultPermissionSlugsForOperationalRoles(): array
    {
        return [
            'hr' => [
                'documents.view', 'forum.view', 'forum.create_topic', 'forum.reply', 'training.view',
                'invitations.send', 'admin.members.view', 'personnel.profile.view', 'personnel.profile.update',
                'dashboard.pins.manage',
            ],
            'recruiter' => [
                'forum.view', 'forum.create_topic', 'forum.reply',
                'invitations.send', 'admin.members.view', 'personnel.profile.view',
            ],
            'instructor' => [
                'forum.view', 'forum.create_topic', 'forum.reply', 'forum.edit_own', 'forum.delete_own',
                'documents.view', 'documents.download.standard',
                'training.view', 'training.assign', 'training.submissions.grade', 'training.results.view',
                'personnel.profile.view',
                'dashboard.pins.manage',
            ],
            'medic' => [
                'forum.view', 'forum.reply',
                'documents.view', 'documents.download.standard',
                'personnel.profile.view', 'personnel.sensitive.view',
            ],
            'logistics' => [
                'forum.view', 'forum.create_topic', 'forum.reply',
                'documents.view', 'documents.upload', 'documents.metadata.update',
            ],
            'rto' => [
                'forum.view', 'forum.create_topic', 'forum.reply', 'forum.edit_own',
                'comms.announcement.send',
            ],
            'probation' => [
                'forum.view', 'forum.reply',
            ],
            'officer' => [
                'forum.view', 'forum.create_topic', 'forum.reply', 'forum.edit_own', 'forum.delete_own',
                'documents.view', 'documents.download.standard', 'training.view',
                'personnel.profile.view',
                'dashboard.pins.manage',
            ],
        ];
    }

    /**
     * Met à jour nom + description pour les slugs connus (idempotent, sans toucher aux rôles personnalisés).
     */
    public static function applyCanonicalLabels(\PDO $pdo, int $tenantId): void
    {
        if ($tenantId <= 0) {
            return;
        }
        $upd = $pdo->prepare('UPDATE roles SET name = ?, description = ? WHERE tenant_id = ? AND slug = ? AND is_system = 1');
        foreach (array_merge(self::governanceRoles(), self::operationalRoles()) as $row) {
            $upd->execute([
                $row['name'],
                $row['description'],
                $tenantId,
                $row['slug'],
            ]);
        }
    }
}
