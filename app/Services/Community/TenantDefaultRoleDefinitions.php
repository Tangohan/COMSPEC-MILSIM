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
                'name' => 'Gestionnaire d’organisation',
                'description' => 'Autorité stratégique sur l’entité : gouvernance globale, hors périmètre technique de la plateforme.',
                'role_layer' => 'community',
                'is_system' => 1,
                'is_locked' => 1,
            ],
            [
                'slug' => 'tenant_admin',
                'name' => 'Gestionnaire administratif d’organisation',
                'description' => 'Administration opérationnelle quotidienne : membres, contenus et paramètres internes.',
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
                'name' => 'Officier responsable de la communication',
                'description' => 'Échanges, annonces, modération et structuration du discours collectif au sein de l’organisation.',
                'role_layer' => 'intra',
                'is_system' => 1,
                'is_locked' => 0,
            ],
            [
                'slug' => 'hr',
                'name' => 'Gestionnaire des ressources humaines de l’organisation',
                'description' => 'Effectifs, recrutements, statuts, parcours et conformité interne.',
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
                'interteam.missions.respond',
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
            'recruiter' => 30,
        ];
        /** @var array<string, int> */
        $intraSlug = [
            'officer' => 10,
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
