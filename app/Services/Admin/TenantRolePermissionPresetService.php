<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Authorization\SystemReservedPermissions;
use App\Authorization\TenantPermissionCatalog;
use App\Repositories\PermissionRepository;

/**
 * Profils prédéfinis de permissions pour les rôles d’espace communauté.
 * Ne couvre jamais les droits « site » : exclusions métier dans {@see EXCLUDED_SLUGS},
 * barrière plateforme dans {@see SystemReservedPermissions}.
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

        return $this->permissionIdsForPresetFromTenantRows($presetId, $rows);
    }

    /**
     * Libellés de domaine pour l’affichage (évite le jargon technique des modules).
     *
     * @return array<string, string>
     */
    public static function permissionModuleLabelsFr(): array
    {
        return [
            'admin' => 'Administration',
            'dashboard' => 'Tableau de bord',
            'forum' => 'Forum',
            'documents' => 'Documents',
            'training' => 'Formations',
            'personnel' => 'Personnel',
            'organization' => 'Organisation',
            'comms' => 'Communications',
            'courrier' => 'Courrier',
            'interteam' => 'Inter-unités',
            'autre' => 'Autres',
        ];
    }

    /**
     * @param list<array<string, mixed>> $tenantPermissionRows Résultat de {@see PermissionRepository::allForTenant()}
     *
     * @return list<int>
     */
    public function permissionIdsForPresetFromTenantRows(string $presetId, array $tenantPermissionRows): array
    {
        /** @var array<string, int> $slugToId */
        $slugToId = [];
        foreach ($tenantPermissionRows as $p) {
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
     * Diff entre les habilitations actuelles d’un rôle et celles du profil (IDs résolus pour ce tenant).
     *
     * @param list<int> $currentPermissionIds
     * @param list<array<string, mixed>> $tenantPermissionRows
     *
     * @return array{ok: true, added: list<array{id: int, name: string, module: string, slug: string}>, removed: list<array{id: int, name: string, module: string, slug: string}>, added_by_module: array<string, list<array{id: int, name: string, module: string, slug: string}>>, removed_by_module: array<string, list<array{id: int, name: string, module: string, slug: string}>>, unchanged_count: int, current_total: int, preset_total: int, added_count: int, removed_count: int}|array{ok: false, error: string}
     */
    public function buildApplyDiff(string $presetId, array $currentPermissionIds, array $tenantPermissionRows): array
    {
        $allowed = array_column($this->listPresetMeta(), 'id');
        if (!in_array($presetId, $allowed, true)) {
            return ['ok' => false, 'error' => 'Profil inconnu ou non disponible.'];
        }
        $presetIds = $this->permissionIdsForPresetFromTenantRows($presetId, $tenantPermissionRows);
        if ($presetIds === []) {
            return [
                'ok' => false,
                'error' => 'Ce profil ne correspond à aucune habilitation enregistrée pour votre communauté. Vérifiez que les mises à jour du schéma et du catalogue ont bien été exécutées.',
            ];
        }

        $byId = [];
        foreach ($tenantPermissionRows as $p) {
            $byId[(int) ($p['id'] ?? 0)] = $p;
        }

        $cur = array_values(array_unique(array_map('intval', $currentPermissionIds)));
        $pre = $presetIds;
        $curSet = array_flip($cur);
        $preSet = array_flip($pre);

        $addedIds = [];
        foreach ($pre as $id) {
            if (!isset($curSet[$id])) {
                $addedIds[] = $id;
            }
        }
        $removedIds = [];
        foreach ($cur as $id) {
            if (!isset($preSet[$id])) {
                $removedIds[] = $id;
            }
        }
        $unchanged = 0;
        foreach ($cur as $id) {
            if (isset($preSet[$id])) {
                $unchanged++;
            }
        }

        $toEntry = static function (int $id) use ($byId): array {
            $p = $byId[$id] ?? null;
            $name = $p ? trim((string) ($p['name'] ?? '')) : '';
            if ($name === '') {
                $name = 'Habilitation #' . $id;
            }

            return [
                'id' => $id,
                'name' => $name,
                'module' => (string) ($p['module'] ?? 'autre'),
                'slug' => (string) ($p['slug'] ?? ''),
            ];
        };

        $added = array_map($toEntry, $addedIds);
        $removed = array_map($toEntry, $removedIds);
        usort($added, static function (array $a, array $b): int {
            return [$a['module'], $a['name']] <=> [$b['module'], $b['name']];
        });
        usort($removed, static function (array $a, array $b): int {
            return [$a['module'], $a['name']] <=> [$b['module'], $b['name']];
        });

        $groupByModule = static function (array $items): array {
            $g = [];
            foreach ($items as $it) {
                $m = (string) ($it['module'] ?? 'autre');
                $g[$m][] = $it;
            }
            ksort($g);

            return $g;
        };

        return [
            'ok' => true,
            'added' => $added,
            'removed' => $removed,
            'added_by_module' => $groupByModule($added),
            'removed_by_module' => $groupByModule($removed),
            'unchanged_count' => $unchanged,
            'current_total' => count($cur),
            'preset_total' => count($pre),
            'added_count' => count($added),
            'removed_count' => count($removed),
        ];
    }

    /**
     * Habilitations d’un profil, après retrait inconditionnel de tout ce qui est réservé
     * à l’administration de la plateforme ({@see SystemReservedPermissions}).
     *
     * Le filtre est appliqué ici, en sortie unique, et non branche par branche : un futur
     * profil ne peut pas ouvrir de droits plateforme par oubli d’exclusion.
     *
     * @return list<string>
     */
    private function resolveSlugs(string $presetId): array
    {
        return SystemReservedPermissions::filter($this->resolveSlugsRaw($presetId));
    }

    /**
     * @return list<string>
     */
    private function resolveSlugsRaw(string $presetId): array
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
                    'admin.members.moderate',
                    'invitations.send', 'documents.view', 'documents.upload', 'documents.metadata.update',
                    'forum.moderate_organization', 'dashboard.pins.manage',
                    'personnel.profile.view', 'personnel.profile.update', 'personnel.assignments.manage',
                    'organization.orbat.manage', 'organization.orbat.view', 'organization.effectifs.hub.view',
                ]
            )));
        }

        if ($presetId === 'pole_formation') {
            return $this->resolveSlugs('instructor');
        }

        if ($presetId === 'cellule_recrutement') {
            return array_values(array_unique(array_merge(
                $this->resolveSlugs('hr_recruitment'),
                [
                    'invitations.send', 'admin.members.view', 'personnel.profile.view',
                    'organization.recruitment.manage', 'organization.recruitment.openings.manage',
                    'organization.effectifs.hub.view',
                ]
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
                'admin.members.moderate',
                'invitations.send',
                'dashboard.pins.manage',
                'personnel.profile.view', 'personnel.profile.update', 'personnel.sensitive.view',
                'personnel.grades.manage', 'personnel.assignments.manage', 'personnel.status.manage',
                'personnel.badges.manage', 'personnel.directory.export',
                'organization.recruitment.manage', 'organization.recruitment.openings.manage',
                'organization.effectifs.hub.view', 'organization.job_roles.referential.manage',
                'organization.orbat.view',
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
