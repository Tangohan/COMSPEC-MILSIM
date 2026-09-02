<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Authorization\SystemReservedPermissions;

/**
 * Kits d’accès simples : lecture, modification, recrutement, paramètres…
 * Multi-sélectionnables et attribuables à des membres via un rôle communauté dédié.
 *
 * @phpstan-type KitDef array{
 *   id: string,
 *   label: string,
 *   summary: string,
 *   tone: string,
 *   role_slug: string,
 *   permission_slugs: list<string>
 * }
 */
final class PersonnelFunctionKitCatalog
{
    public const ROLE_SLUG_PREFIX = 'access_kit_';

    /**
     * @return list<KitDef>
     */
    public static function all(): array
    {
        return [
            [
                'id' => 'lecture',
                'label' => 'Lecture',
                'summary' => 'Consulter le forum, les documents standards, les formations et l’annuaire.',
                'tone' => 'lecture',
                'role_slug' => self::ROLE_SLUG_PREFIX . 'lecture',
                'permission_slugs' => [
                    'forum.view',
                    'documents.view',
                    'documents.download.standard',
                    'training.view',
                    'personnel.profile.view',
                ],
            ],
            [
                'id' => 'lecture_modification',
                'label' => 'Lecture et modification',
                'summary' => 'Participer au forum, déposer des documents et mettre à jour les fiches.',
                'tone' => 'modification',
                'role_slug' => self::ROLE_SLUG_PREFIX . 'lecture_modification',
                'permission_slugs' => [
                    'forum.view', 'forum.create_topic', 'forum.reply', 'forum.edit_own', 'forum.delete_own',
                    'documents.view', 'documents.download.standard', 'documents.upload', 'documents.metadata.update',
                    'training.view',
                    'personnel.profile.view', 'personnel.profile.update',
                ],
            ],
            [
                'id' => 'effectifs_lecture',
                'label' => 'Effectifs — lecture',
                'summary' => 'Voir les membres, le hub effectifs et les fiches, sans les modifier.',
                'tone' => 'lecture',
                'role_slug' => self::ROLE_SLUG_PREFIX . 'effectifs_lecture',
                'permission_slugs' => [
                    'admin.backoffice.view',
                    'admin.members.view',
                    'personnel.profile.view',
                    'organization.effectifs.hub.view',
                    'organization.orbat.view',
                ],
            ],
            [
                'id' => 'effectifs_modification',
                'label' => 'Effectifs — modification',
                'summary' => 'Gérer fiches, grades, affectations, statuts et badges.',
                'tone' => 'modification',
                'role_slug' => self::ROLE_SLUG_PREFIX . 'effectifs_modification',
                'permission_slugs' => [
                    'admin.backoffice.view',
                    'admin.members.view', 'admin.members.manage', 'admin.members.moderate',
                    'personnel.profile.view', 'personnel.profile.update', 'personnel.sensitive.view',
                    'personnel.grades.manage', 'personnel.assignments.manage', 'personnel.status.manage',
                    'personnel.badges.manage', 'personnel.directory.export', 'personnel.member_number.manage',
                    'organization.effectifs.hub.view', 'organization.orbat.view',
                ],
            ],
            [
                'id' => 'recrutement_lecture',
                'label' => 'Recrutement — lecture',
                'summary' => 'Consulter le hub et les profils liés à l’accueil, sans décider.',
                'tone' => 'lecture',
                'role_slug' => self::ROLE_SLUG_PREFIX . 'recrutement_lecture',
                'permission_slugs' => [
                    'admin.backoffice.view',
                    'admin.members.view',
                    'personnel.profile.view',
                    'organization.effectifs.hub.view',
                    'member_integration.view',
                ],
            ],
            [
                'id' => 'recrutement',
                'label' => 'Recruter',
                'summary' => 'Invitations, dossiers candidats, offres et décisions d’intégration.',
                'tone' => 'modification',
                'role_slug' => self::ROLE_SLUG_PREFIX . 'recrutement',
                'permission_slugs' => [
                    'admin.backoffice.view',
                    'admin.members.view', 'admin.members.manage', 'admin.members.invite',
                    'invitations.send',
                    'personnel.profile.view', 'personnel.profile.update',
                    'organization.effectifs.hub.view',
                    'organization.recruitment.manage',
                    'organization.recruitment.openings.manage',
                    'member_integration.view', 'member_integration.manage', 'member_integration.assign',
                    'member_integration.note',
                ],
            ],
            [
                'id' => 'documents_lecture',
                'label' => 'Documents — lecture',
                'summary' => 'Voir et télécharger les documents standards de la communauté.',
                'tone' => 'lecture',
                'role_slug' => self::ROLE_SLUG_PREFIX . 'documents_lecture',
                'permission_slugs' => [
                    'documents.view',
                    'documents.download.standard',
                ],
            ],
            [
                'id' => 'documents_modification',
                'label' => 'Documents — modification',
                'summary' => 'Téléverser, versions, métadonnées, catégories et droits d’accès.',
                'tone' => 'modification',
                'role_slug' => self::ROLE_SLUG_PREFIX . 'documents_modification',
                'permission_slugs' => [
                    'documents.view', 'documents.sensitive.view',
                    'documents.download.standard', 'documents.download_sensitive',
                    'documents.upload', 'documents.version.replace', 'documents.metadata.update',
                    'documents.delete', 'documents.update', 'documents.archive',
                    'documents.categories.manage', 'documents.access.manage',
                    'documents.share.public', 'documents.publish',
                ],
            ],
            [
                'id' => 'formations_lecture',
                'label' => 'Formations — lecture',
                'summary' => 'Consulter les parcours et les résultats.',
                'tone' => 'lecture',
                'role_slug' => self::ROLE_SLUG_PREFIX . 'formations_lecture',
                'permission_slugs' => [
                    'training.view',
                    'training.results.view',
                ],
            ],
            [
                'id' => 'formations_modification',
                'label' => 'Formations — modification',
                'summary' => 'Assigner, corriger, publier et suivre les certifications.',
                'tone' => 'modification',
                'role_slug' => self::ROLE_SLUG_PREFIX . 'formations_modification',
                'permission_slugs' => [
                    'training.view', 'training.create', 'training.update', 'training.publish',
                    'training.assign', 'training.submissions.grade', 'training.results.view',
                    'training.certifications.manage', 'training.prerequisites.manage',
                    'dashboard.pins.manage',
                ],
            ],
            [
                'id' => 'tenant_parametres',
                'label' => 'Paramètres de la communauté',
                'summary' => 'Administrer les réglages, rôles, accès et mise à niveau du tenant.',
                'tone' => 'admin',
                'role_slug' => self::ROLE_SLUG_PREFIX . 'tenant_parametres',
                'permission_slugs' => [
                    'admin.access',
                    'admin.organization',
                    'admin.backoffice.view',
                    'admin.settings.manage',
                    'admin.roles.manage',
                    'admin.permissions.manage',
                    'admin.branding.manage',
                    'tenant.configuration.manage',
                    'dashboard.pins.manage',
                    'organization.catalog.manage',
                    'organization.job_roles.referential.manage',
                ],
            ],
        ];
    }

    /**
     * @return array<string, KitDef>
     */
    public static function byId(): array
    {
        $out = [];
        foreach (self::all() as $kit) {
            $out[$kit['id']] = $kit;
        }

        return $out;
    }

    public static function find(string $kitId): ?array
    {
        $kitId = trim($kitId);

        return $kitId !== '' ? (self::byId()[$kitId] ?? null) : null;
    }

    /**
     * @param list<string> $kitIds
     * @return list<string>
     */
    public static function permissionSlugsForKitIds(array $kitIds): array
    {
        $wanted = array_fill_keys(self::normalizeIds($kitIds), true);
        if ($wanted === []) {
            return [];
        }
        $out = [];
        foreach (self::all() as $kit) {
            if (!isset($wanted[$kit['id']])) {
                continue;
            }
            foreach ($kit['permission_slugs'] as $slug) {
                $out[$slug] = true;
            }
        }

        return SystemReservedPermissions::filter(array_keys($out));
    }

    /**
     * @param list<string> $ids
     * @return list<string>
     */
    public static function normalizeIds(array $ids): array
    {
        $known = self::byId();
        $out = [];
        foreach ($ids as $id) {
            $id = trim((string) $id);
            if ($id !== '' && isset($known[$id]) && !isset($out[$id])) {
                $out[$id] = true;
            }
        }

        return array_keys($out);
    }

    public static function isAccessKitRoleSlug(string $slug): bool
    {
        return str_starts_with(trim($slug), self::ROLE_SLUG_PREFIX);
    }

    /**
     * Compat : anciennes API basées sur les slugs d’emplois métier.
     *
     * @param list<string> $kitIds
     * @return list<string>
     * @deprecated
     */
    public static function slugsForKitIds(array $kitIds): array
    {
        return self::permissionSlugsForKitIds($kitIds);
    }

    /**
     * @param list<string> $kitIds
     * @return list<array{slug: string, name: string, kit_id: string, kit_label: string}>
     * @deprecated Prefer iterating enabled kits directly.
     */
    public static function keyFunctionsForKitIds(array $kitIds): array
    {
        $out = [];
        foreach (self::normalizeIds($kitIds) as $id) {
            $kit = self::byId()[$id] ?? null;
            if ($kit === null) {
                continue;
            }
            $out[] = [
                'slug' => $kit['role_slug'],
                'name' => $kit['label'],
                'kit_id' => $kit['id'],
                'kit_label' => $kit['label'],
            ];
        }

        return $out;
    }

    /** @return list<string> */
    public static function visualOnlySlugs(): array
    {
        return [];
    }
}
