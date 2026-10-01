<?php

declare(strict_types=1);

namespace App\Services\Organization;

use App\Repositories\OrbatBilletRepository;
use App\Repositories\UnitRepository;

/**
 * Applique les modèles d’organisation MILSIM (État-major + groupe de combat).
 */
final class MilsimOrgTemplateService
{
    public function __construct(
        private UnitRepository $units,
        private OrbatBilletRepository $billets,
        private OrbatBilletService $billetService,
    ) {}

    /**
     * @return array{ok: bool, hq_unit_id?: int, created_billets: list<int>, message?: string}
     */
    public function ensureHeadquarters(int $tenantId, ?int $actorUserId = null): array
    {
        if (!$this->billets->schemaReady()) {
            return ['ok' => false, 'created_billets' => [], 'message' => 'Schéma postes ORBAT indisponible.'];
        }

        $hq = $this->units->findBySlugForTenant($tenantId, 'etat-major');
        if (!$hq) {
            $created = $this->units->create($tenantId, [
                'name' => 'État-major / HQ',
                'slug' => 'etat-major',
                'type' => 'group',
                'code' => 'HQ',
                'display_order' => 10,
                'parent_id' => null,
            ]);
            $hqId = (int) ($created['id'] ?? 0);
            if ($hqId < 1) {
                return ['ok' => false, 'created_billets' => [], 'message' => 'Création État-major impossible.'];
            }
        } else {
            $hqId = (int) ($hq['id'] ?? 0);
        }

        $createdBillets = $this->ensureBilletsForUnit($tenantId, $hqId, PositionCapabilityCatalog::headquartersBillets(), $actorUserId);

        return ['ok' => true, 'hq_unit_id' => $hqId, 'created_billets' => $createdBillets];
    }

    /**
     * @return array{ok: bool, unit_id?: int, created_billets: list<int>, message?: string}
     */
    public function ensureCombatGroup(
        int $tenantId,
        string $groupCode = 'N-10',
        ?int $parentUnitId = null,
        ?int $actorUserId = null,
    ): array {
        if (!$this->billets->schemaReady()) {
            return ['ok' => false, 'created_billets' => [], 'message' => 'Schéma postes ORBAT indisponible.'];
        }
        $code = strtoupper(trim($groupCode)) !== '' ? strtoupper(trim($groupCode)) : 'N-10';
        $slug = 'groupe-' . strtolower(preg_replace('/[^a-z0-9]+/i', '-', $code) ?: 'n-10');
        $unit = $this->units->findBySlugForTenant($tenantId, $slug);
        if (!$unit) {
            $created = $this->units->create($tenantId, [
                'name' => 'Groupe ' . $code,
                'slug' => $slug,
                'type' => 'group',
                'code' => $code,
                'display_order' => 100,
                'parent_id' => $parentUnitId,
            ]);
            $unitId = (int) ($created['id'] ?? 0);
            if ($unitId < 1) {
                return ['ok' => false, 'created_billets' => [], 'message' => 'Création du groupe impossible.'];
            }
        } else {
            $unitId = (int) ($unit['id'] ?? 0);
        }

        $createdBillets = $this->ensureBilletsForUnit(
            $tenantId,
            $unitId,
            PositionCapabilityCatalog::combatGroupBillets($code),
            $actorUserId
        );

        return ['ok' => true, 'unit_id' => $unitId, 'created_billets' => $createdBillets];
    }

    /**
     * @param list<array<string, mixed>> $defs
     * @return list<int>
     */
    private function ensureBilletsForUnit(int $tenantId, int $unitId, array $defs, ?int $actorUserId): array
    {
        $existing = $this->billets->listForUnit($tenantId, $unitId, true);
        $haveCodes = [];
        foreach ($existing as $b) {
            if ((int) ($b['is_active'] ?? 1) !== 1) {
                continue;
            }
            $code = strtoupper(trim((string) ($b['code'] ?? '')));
            if ($code !== '') {
                $haveCodes[$code] = (int) ($b['id'] ?? 0);
            }
        }

        $created = [];
        foreach ($defs as $def) {
            $code = strtoupper(trim((string) ($def['code'] ?? '')));
            if ($code === '' || isset($haveCodes[$code])) {
                continue;
            }
            $payload = [
                'unit_id' => $unitId,
                'code' => $code,
                'title' => (string) ($def['title'] ?? $code),
                'capability_template' => (string) ($def['capability_template'] ?? ''),
                'authorized_slots' => max(1, (int) ($def['authorized_slots'] ?? 1)),
                'sort_order' => (int) ($def['sort_order'] ?? 0),
                'is_key_post' => !empty($def['is_key_post']) ? 1 : 0,
                'key_post_kind' => (string) ($def['key_post_kind'] ?? ''),
                'function_label' => PositionCapabilityCatalog::label((string) ($def['capability_template'] ?? '')),
            ];
            $res = $this->billetService->createBillet($tenantId, $payload, $actorUserId);
            if (!empty($res['ok']) && !empty($res['id'])) {
                $created[] = (int) $res['id'];
            }
        }

        return $created;
    }
}
