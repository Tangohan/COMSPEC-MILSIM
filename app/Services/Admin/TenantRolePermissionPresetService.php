<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Authorization\TenantPermissionCatalog;
use App\Repositories\PermissionRepository;

/**
 * Profils prédéfinis de permissions pour les rôles d’espace communauté.
 * Ne couvre jamais les droits « site » : {@see EXCLUDED_SLUGS}.
 */
final class TenantRolePermissionPresetService
{
    /** Droits plateforme exclus de tout profil automatique. */
    public const EXCLUDED_SLUGS = [
        'admin.system',
        'forum.moderate', // modération forum étendue (niveau site / global)
    ];

    public function __construct(
        private PermissionRepository $permissionRepository
    ) {}

    /**
     * @return list<array{id: string, label: string, description: string}>
     */
    public function listPresetMeta(): array
    {
        return [
            [
                'id' => 'member',
                'label' => 'Membre actif',
                'description' => 'Forum (lecture et participation), documents standards, formations en consultation, annuaire en lecture.',
            ],
            [
                'id' => 'instructor',
                'label' => 'Instructeur / formateur',
                'description' => 'Base membre + assignation de formations, correction des rendus, consultation des résultats.',
            ],
            [
                'id' => 'hr_recruitment',
                'label' => 'RH & recrutement',
                'description' => 'Invitations, vue et gestion des membres, fiches personnel, grades et affectations.',
            ],
            [
                'id' => 'forum_moderator_org',
                'label' => 'Modérateur forum (organisation)',
                'description' => 'Toute la modération forum sur votre communauté (épinglage, signalements, catégories), sans modération « site » étendue.',
            ],
            [
                'id' => 'doc_curator',
                'label' => 'Gestion documentaire',
                'description' => 'Téléversement, versions, métadonnées, catégories et droits d’accès documentaires.',
            ],
            [
                'id' => 'org_admin_full',
                'label' => 'Administration organisation (complète)',
                'description' => 'Toutes les permissions du catalogue communauté disponibles pour ce tenant, hors droits plateforme réservés.',
            ],
            [
                'id' => 'commandement_unite',
                'label' => 'Commandement unité',
                'description' => 'Pilotage ORBAT, membres, documents opérationnels, formations en lecture/correction ciblée, modération org.',
            ],
            [
                'id' => 'pole_formation',
                'label' => 'Pôle formation',
                'description' => 'Parcours LMS, assignations, notation, certifications et suivi pédagogique.',
            ],
            [
                'id' => 'cellule_recrutement',
                'label' => 'Cellule recrutement',
                'description' => 'Invitations, dossiers candidats, profils et intégration (sans admin système).',
            ],
        ];
    }

    /**
     * @return list<int> IDs de permissions existantes pour le tenant
     */
    public function getPermissionIdsForPreset(int $tenantId, string $presetId): array
    {
        $rows = $this->permissionRepository->allForTenant($tenantId);
        /** @var array<string, int> $slugToId */
        $slugToId = [];
        foreach ($rows as $p) {
            $slug = (string) ($p['slug'] ?? '');
            if ($slug !== '') {
                $slugToId[$slug] = (int) $p['id'];
            }
        }

        $slugs = $this->resolveSlugs($presetId);
        $ids = [];
        foreach ($slugs as $slug) {
            if (isset($slugToId[$slug])) {
                $ids[] = $slugToId[$slug];
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return list<string>
     */
    private function resolveSlugs(string $presetId): array
    {
        $excluded = self::EXCLUDED_SLUGS;

        if ($presetId === 'org_admin_full') {
            return array_values(array_diff(TenantPermissionCatalog::allSlugs(), $excluded));
        }

        if ($presetId === 'forum_moderator_org') {
            $forum = array_filter(
                TenantPermissionCatalog::definitions(),
                static fn (array $d): bool => ($d['module'] ?? '') === 'forum'
            );
            $slugs = array_map(static fn (array $d): string => (string) $d['slug'], $forum);

            return array_values(array_diff($slugs, $excluded));
        }

        if ($presetId === 'instructor') {
            $merged = array_merge($this->resolveSlugs('member'), [
                'training.assign', 'training.submissions.grade', 'training.results.view',
                'training.certifications.manage', 'training.prerequisites.manage',
                'dashboard.pins.manage',
            ]);

            return array_values(array_unique($merged));
        }

        if ($presetId === 'commandement_unite') {
            return array_values(array_unique(array_merge(
                $this->resolveSlugs('member'),
                [
                    'admin.organization', 'admin.backoffice.view', 'admin.members.view', 'admin.members.manage',
                    'invitations.send', 'documents.view', 'documents.upload', 'documents.metadata.update',
                    'forum.moderate_organization', 'dashboard.pins.manage',
                    'personnel.profile.view', 'personnel.profile.update', 'personnel.assignments.manage',
                ]
            )));
        }

        if ($presetId === 'pole_formation') {
            return $this->resolveSlugs('instructor');
        }

        if ($presetId === 'cellule_recrutement') {
            return array_values(array_unique(array_merge(
                $this->resolveSlugs('hr_recruitment'),
                ['invitations.send', 'admin.members.view', 'personnel.profile.view']
            )));
        }

        return match ($presetId) {
            'member' => [
                'forum.view', 'forum.create_topic', 'forum.reply', 'forum.edit_own', 'forum.delete_own',
                'documents.view', 'documents.download.standard',
                'training.view',
                'personnel.profile.view',
            ],
            'hr_recruitment' => [
                'admin.organization', 'admin.backoffice.view', 'admin.members.view', 'admin.members.manage', 'admin.members.invite',
                'invitations.send',
                'dashboard.pins.manage',
                'personnel.profile.view', 'personnel.profile.update', 'personnel.sensitive.view',
                'personnel.grades.manage', 'personnel.assignments.manage', 'personnel.status.manage',
                'personnel.badges.manage', 'personnel.directory.export',
            ],
            'doc_curator' => [
                'documents.view', 'documents.sensitive.view', 'documents.download.standard', 'documents.download_sensitive',
                'documents.upload', 'documents.version.replace', 'documents.metadata.update', 'documents.delete',
                'documents.update', 'documents.archive', 'documents.categories.manage', 'documents.access.manage',
                'documents.share.public', 'documents.publish',
            ],
            default => [],
        };
    }
}
