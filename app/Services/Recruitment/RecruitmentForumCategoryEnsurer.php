<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

use App\Repositories\ForumCategoryRepository;
use App\Services\Community\TenantSeedHelper;
use PDO;

/**
 * Garantit les sous-dossiers forum pour les annonces de recrutement (externe / interne).
 */
final class RecruitmentForumCategoryEnsurer
{
    private const SLUG_EXTERNE = 'recrutement-externe';

    private const SLUG_INTERNE = 'recrutement-interne';

    public function __construct(
        private ForumCategoryRepository $categories = new ForumCategoryRepository()
    ) {}

    /**
     * @return array{ok: bool, category_id?: int, error?: string}
     */
    public function ensureExterneSubcategory(PDO $pdo, int $tenantId): array
    {
        $root = $this->categories->findRootBySlug($tenantId, 'general')
            ?? $this->categories->findPreferredGeneralRoot($tenantId);
        if (!$root) {
            return ['ok' => false, 'error' => 'Aucune catégorie forum « général » n’a été trouvée. Créez une catégorie racine ou restaurez « Général » avant d’annoncer sur le forum public.'];
        }
        $parentId = (int) ($root['id'] ?? 0);
        if ($parentId < 1) {
            return ['ok' => false, 'error' => 'Catégorie forum invalide.'];
        }
        $existing = $this->categories->findChildByParentAndSlug($tenantId, $parentId, self::SLUG_EXTERNE);
        if ($existing) {
            return ['ok' => true, 'category_id' => (int) $existing['id']];
        }
        try {
            $id = $this->categories->create($tenantId, [
                'parent_id' => $parentId,
                'name' => 'Recrutement externe',
                'slug' => self::SLUG_EXTERNE,
                'description' => 'Annonces de postes ouverts visibles par toute la communauté.',
                'color_theme' => 'emerald',
                'display_order' => 5,
            ]);

            return ['ok' => true, 'category_id' => $id];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Impossible de créer le dossier forum pour le recrutement public.'];
        }
    }

    /**
     * @return array{ok: bool, category_id?: int, error?: string}
     */
    public function ensureInterneSubcategory(PDO $pdo, int $tenantId): array
    {
        try {
            TenantSeedHelper::ensureOrganizationForumSection($pdo, $tenantId);
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'La section forum de l’organisation n’a pas pu être préparée.'];
        }
        $root = $this->categories->findOrganizationRoot($tenantId);
        if (!$root) {
            return ['ok' => false, 'error' => 'La section forum réservée à l’organisation est absente. Vérifiez que le forum est à jour (migration).'];
        }
        $parentId = (int) ($root['id'] ?? 0);
        if ($parentId < 1) {
            return ['ok' => false, 'error' => 'Catégorie organisation invalide.'];
        }
        $existing = $this->categories->findChildByParentAndSlug($tenantId, $parentId, self::SLUG_INTERNE);
        if ($existing) {
            return ['ok' => true, 'category_id' => (int) $existing['id']];
        }
        try {
            $id = $this->categories->create($tenantId, [
                'parent_id' => $parentId,
                'name' => 'Recrutement interne',
                'slug' => self::SLUG_INTERNE,
                'description' => 'Annonces de postes à pourvoir pour les membres et l’encadrement.',
                'color_theme' => 'slate',
                'display_order' => 5,
            ]);

            return ['ok' => true, 'category_id' => $id];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Impossible de créer le dossier forum pour le recrutement interne.'];
        }
    }
}
