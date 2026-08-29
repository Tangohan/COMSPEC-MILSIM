<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Repositories\SeniorityRepository;

/**
 * Initialise les indicateurs d’ancienneté standards pour une communauté (tenant).
 */
final class SeniorityTenantDefaultsService
{
    /**
     * Lot catalogue : les deux premiers sont visibles sur les fiches par défaut ;
     * les autres sont prêts à l’emploi (actifs mais masqués) pour limiter le bruit jusqu’à publication par l’encadrement.
     *
     * @var list<array{code: string, label: string, scope: string, calc_mode: string, source_type: string, sort_order: int, is_active?: bool, is_visible?: bool}>
     */
    private const STANDARD_PACK = [
        [
            'code' => 'tenure_community',
            'label' => 'Ancienneté dans la communauté',
            'scope' => 'user',
            'calc_mode' => 'from_start',
            'source_type' => 'manual',
            'sort_order' => 10,
            'is_visible' => true,
        ],
        [
            'code' => 'tenure_service',
            'label' => 'Ancienneté de service cumulée',
            'scope' => 'user',
            'calc_mode' => 'sum_periods',
            'source_type' => 'manual',
            'sort_order' => 20,
            'is_visible' => true,
        ],
        [
            'code' => 'tenure_unit_primary',
            'label' => 'Ancienneté dans l’unité d’emploi',
            'scope' => 'unit',
            'calc_mode' => 'from_start',
            'source_type' => 'manual',
            'sort_order' => 30,
            'is_visible' => false,
        ],
        [
            'code' => 'tenure_operational_commitment',
            'label' => 'Engagement opérationnel (périodes cumulées)',
            'scope' => 'mission',
            'calc_mode' => 'sum_periods',
            'source_type' => 'manual',
            'sort_order' => 40,
            'is_visible' => false,
        ],
        [
            'code' => 'tenure_field_deployment',
            'label' => 'Temps sur théâtre extérieur',
            'scope' => 'mission',
            'calc_mode' => 'sum_periods',
            'source_type' => 'manual',
            'sort_order' => 50,
            'is_visible' => false,
        ],
        [
            'code' => 'tenure_garrison',
            'label' => 'Temps en garnison / poste fixe',
            'scope' => 'unit',
            'calc_mode' => 'sum_periods',
            'source_type' => 'manual',
            'sort_order' => 60,
            'is_visible' => false,
        ],
        [
            'code' => 'tenure_training_track',
            'label' => 'Parcours formation & certification',
            'scope' => 'qualification',
            'calc_mode' => 'sum_periods',
            'source_type' => 'manual',
            'sort_order' => 70,
            'is_visible' => false,
        ],
        [
            'code' => 'tenure_instructor_capacity',
            'label' => 'Temps d’activité formateur',
            'scope' => 'qualification',
            'calc_mode' => 'sum_periods',
            'source_type' => 'manual',
            'sort_order' => 80,
            'is_visible' => false,
        ],
        [
            'code' => 'tenure_qualification_hold',
            'label' => 'Maintien des qualifications clés',
            'scope' => 'qualification',
            'calc_mode' => 'active_only',
            'source_type' => 'manual',
            'sort_order' => 90,
            'is_visible' => false,
        ],
        [
            'code' => 'tenure_rank_current',
            'label' => 'Ancienneté au grade actuel',
            'scope' => 'grade',
            'calc_mode' => 'from_start',
            'source_type' => 'manual',
            'sort_order' => 100,
            'is_visible' => false,
        ],
        [
            'code' => 'tenure_role_community',
            'label' => 'Ancienneté dans le rôle communauté',
            'scope' => 'role',
            'calc_mode' => 'from_start',
            'source_type' => 'manual',
            'sort_order' => 110,
            'is_visible' => false,
        ],
        [
            'code' => 'tenure_campaign_participation',
            'label' => 'Participation aux campagnes (cumul)',
            'scope' => 'campaign',
            'calc_mode' => 'sum_periods',
            'source_type' => 'manual',
            'sort_order' => 120,
            'is_visible' => false,
        ],
        [
            'code' => 'tenure_reserve_status',
            'label' => 'Périodes en réserve / disponibilité restreinte',
            'scope' => 'user',
            'calc_mode' => 'active_only',
            'source_type' => 'manual',
            'sort_order' => 130,
            'is_visible' => false,
        ],
        [
            'code' => 'tenure_staff_assignment',
            'label' => 'Temps en fonction d’encadrement',
            'scope' => 'role',
            'calc_mode' => 'sum_periods',
            'source_type' => 'manual',
            'sort_order' => 140,
            'is_visible' => false,
        ],
        [
            'code' => 'tenure_joint_interop',
            'label' => 'Engagements inter-unités ou intercommunautés',
            'scope' => 'custom',
            'calc_mode' => 'sum_periods',
            'source_type' => 'manual',
            'sort_order' => 150,
            'is_visible' => false,
        ],
        [
            'code' => 'tenure_custom_engagement',
            'label' => 'Engagement spécifique (règle personnalisée)',
            'scope' => 'custom',
            'calc_mode' => 'custom_rule',
            'source_type' => 'manual',
            'sort_order' => 160,
            'is_visible' => false,
        ],
        [
            'code' => 'tenure_tenant_wide_recognition',
            'label' => 'Reconnaissance interne communauté (cumul)',
            'scope' => 'tenant',
            'calc_mode' => 'sum_periods',
            'source_type' => 'manual',
            'sort_order' => 170,
            'is_visible' => false,
        ],
        [
            'code' => 'tenure_group_attachment',
            'label' => 'Ancienneté dans un groupe fonctionnel',
            'scope' => 'group',
            'calc_mode' => 'from_start',
            'source_type' => 'manual',
            'sort_order' => 180,
            'is_visible' => false,
        ],
    ];

    public function __construct(
        private SeniorityRepository $seniorityRepository,
    ) {}

    /**
     * Codes du lot catalogue standard (ordre métier).
     *
     * @return list<string>
     */
    public static function listStandardPackCodes(): array
    {
        $codes = [];
        foreach (self::STANDARD_PACK as $row) {
            $code = trim((string) ($row['code'] ?? ''));
            if ($code !== '') {
                $codes[] = $code;
            }
        }

        return $codes;
    }

    /**
     * Crée les indicateurs manquants du lot standard (sans supprimer l’existant).
     *
     * @return array{created: int, skipped: int}
     */
    public function ensureStandardPack(int $tenantId): array
    {
        if (!$this->seniorityRepository->schemaReady() || $tenantId < 1) {
            return ['created' => 0, 'skipped' => 0];
        }
        $created = 0;
        $skipped = 0;
        foreach (self::STANDARD_PACK as $row) {
            if ($this->seniorityRepository->findDefinitionIdByTenantAndCode($tenantId, $row['code']) !== null) {
                ++$skipped;
                continue;
            }
            $isActive = (bool) ($row['is_active'] ?? true);
            $isVisible = (bool) ($row['is_visible'] ?? false);
            $id = $this->seniorityRepository->insertDefinition(
                $tenantId,
                $row['code'],
                $row['label'],
                $row['scope'],
                $row['calc_mode'],
                $row['source_type'],
                $isActive,
                $isVisible,
                (int) $row['sort_order'],
            );
            if ($id !== null) {
                ++$created;
            }
        }

        return ['created' => $created, 'skipped' => $skipped];
    }
}
