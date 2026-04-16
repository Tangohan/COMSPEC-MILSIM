<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Repositories\UnitRepository;

/**
 * Sections organisationnelles minimales pour le pilotage RH / compétences (par tenant).
 */
final class TenantPedagogyStructureService
{
    public const UNIT_SLUG_PILOTAGE = 'pilotage-expertise';

    public const UNIT_SLUG_BUREAU = 'bureau-personnel-competences';

    public function __construct(
        private UnitRepository $unitRepository,
    ) {}

    /** Crée les sections réservées si absentes (idempotent). */
    public function ensureMandatorySectionsForTenant(int $tenantId): void
    {
        if ($tenantId < 1) {
            return;
        }
        $parentId = $this->unitRepository->findIdByTenantAndSlug($tenantId, self::UNIT_SLUG_PILOTAGE);
        if ($parentId === null) {
            $row = $this->unitRepository->create($tenantId, [
                'name' => 'Pilotage et expertise',
                'slug' => self::UNIT_SLUG_PILOTAGE,
                'type' => 'pedagogy_pilotage',
                'parent_id' => null,
                'display_order' => -100,
                'show_on_public_page' => 0,
            ]);
            $parentId = (int) ($row['id'] ?? 0);
        }
        if ($parentId < 1) {
            return;
        }
        if ($this->unitRepository->findIdByTenantAndSlug($tenantId, self::UNIT_SLUG_BUREAU) !== null) {
            return;
        }
        $this->unitRepository->create($tenantId, [
            'name' => 'Bureau du personnel et des compétences',
            'slug' => self::UNIT_SLUG_BUREAU,
            'type' => 'pedagogy_bureau_competences',
            'parent_id' => $parentId,
            'display_order' => 0,
            'show_on_public_page' => 0,
        ]);
    }
}
