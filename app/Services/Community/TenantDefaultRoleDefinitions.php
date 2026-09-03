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
        ];
    }

    /** Aucun ancien rôle organique n'est conservé dans le référentiel simplifié. */
    public static function organicStaffRoles(): array
    {
        return [];
    }

    /**
     * Les six rôles fonctionnels explicitement proposés aux communautés.
     *
     * @return list<array{slug: string, name: string, description: string, role_layer: string, is_system: int, is_locked: int}>
     */
    public static function operationalRoles(): array
    {
        return [
            [
                'slug' => 'member',
                'name' => 'Opérateur',
                'description' => 'Socle obligatoire de tout membre humain de la communauté.',
                'role_layer' => 'intra',
                'is_system' => 1,
                'is_locked' => 1,
            ],
            [
                'slug' => 'recruiter',
                'name' => 'Recrutement',
                'description' => 'Candidatures, échanges avec les postulants et intégration des nouveaux membres.',
                'role_layer' => 'intra',
                'is_system' => 1,
                'is_locked' => 0,
            ],
            [
                'slug' => 'hr',
                'name' => 'Ressources humaines',
                'description' => 'Effectifs, statuts, parcours et conformité interne.',
                'role_layer' => 'intra',
                'is_system' => 1,
                'is_locked' => 0,
            ],
            [
                'slug' => 'trainer',
                'name' => 'Formateur',
                'description' => 'Conçoit et structure les parcours, modules et critères de validation.',
                'role_layer' => 'intra',
                'is_system' => 1,
                'is_locked' => 0,
            ],
            [
                'slug' => 'senior_instructor',
                'name' => 'Responsable des formateurs',
                'description' => 'Pilote l’équipe pédagogique, ses référentiels et la qualité des formations.',
                'role_layer' => 'intra',
                'is_system' => 1,
                'is_locked' => 0,
            ],
            [
                'slug' => 'instructor',
                'name' => 'Instructeur',
                'description' => 'Anime les parcours, corrige les rendus et suit les qualifications.',
                'role_layer' => 'intra',
                'is_system' => 1,
                'is_locked' => 0,
            ],
        ];
    }

    /** @return list<string> */
    public static function allowedRoleSlugs(): array
    {
        return ['community_owner', 'tenant_admin', 'member', 'recruiter', 'hr', 'trainer', 'senior_instructor', 'instructor'];
    }

    /**
     * Permissions par défaut (slugs) pour rôles opérationnels / organiques — appliquées si la permission existe pour le tenant.
     * Les badges de statut (élite, surveillance, fondateur…) n’ont volontairement aucune entrée ici.
     *
     * @return array<string, list<string>>
     */
    public static function defaultPermissionSlugsForOperationalRoles(): array
    {
        return [
            'community_owner' => ['admin.organization', 'dashboard.pins.manage'],
            'tenant_admin' => ['admin.organization', 'dashboard.pins.manage'],
            'member' => [
                'forum.view', 'forum.create_topic', 'forum.reply', 'forum.edit_own', 'forum.delete_own',
                'documents.view', 'documents.download.standard', 'training.view',
                'operational.board.view', 'organization.orbat.view',
                'operations.tactical.view', 'operations.missions.view',
            ],
            'recruiter' => [
                'forum.view', 'forum.create_topic', 'forum.reply', 'invitations.send',
                'admin.members.view', 'personnel.profile.view', 'operational.board.view',
                'organization.orbat.view', 'organization.recruitment', 'organization.recruitment.manage',
                'organization.recruitment.openings.manage', 'member_integration.view',
                'member_integration.assign', 'member_integration.note',
            ],
            'hr' => [
                'documents.view', 'forum.view', 'forum.create_topic', 'forum.reply', 'training.view',
                'invitations.send', 'admin.members.view', 'personnel.profile.view', 'personnel.profile.update',
                'dashboard.pins.manage', 'organization.orbat.view', 'organization.effectifs.hub.view',
                'organization.recruitment', 'organization.recruitment.manage',
                'organization.recruitment.openings.manage', 'member_integration.view',
                'member_integration.manage', 'member_integration.assign', 'member_integration.note',
                'member_integration.template_manage',
            ],
            'trainer' => self::trainingPermissions(),
            'senior_instructor' => array_values(array_unique(array_merge(self::trainingPermissions(), [
                'training.delete', 'training.publish', 'training.certifications.manage',
                'training.publications.manage',
            ]))),
            'instructor' => array_values(array_unique(array_merge(self::trainingPermissions(), [
                'member_integration.view',
            ]))),
        ];
    }

    /** @return list<string> */
    private static function trainingPermissions(): array
    {
        return [
            'forum.view', 'forum.create_topic', 'forum.reply', 'forum.edit_own', 'forum.delete_own',
            'documents.view', 'documents.download.standard', 'training.view', 'training.assign',
            'training.submissions.grade', 'training.results.view', 'training.create', 'training.update',
            'training.manage', 'personnel.profile.view', 'dashboard.pins.manage',
            'operational.board.view', 'organization.orbat.view',
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
        return [
            'community_owner' => 'Organization manager',
            'tenant_admin' => 'Deputy organization manager',
            'member' => 'Operator',
            'recruiter' => 'Recruitment',
            'hr' => 'Human resources',
            'trainer' => 'Trainer',
            'senior_instructor' => 'Head of trainers',
            'instructor' => 'Instructor',
        ];
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
