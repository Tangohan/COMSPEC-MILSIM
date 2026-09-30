<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Repositories\GradeDefinitionRepository;
use App\Repositories\GradeFiliereDefinitionRepository;
use App\Repositories\GradeRepository;
use App\Repositories\PersonnelGradeHistoryRepository;
use App\Support\AdvancementCodes;

final class GradeScaleSeedService
{
    public const TEMPLATE_GENERIC = 'generic';
    public const TEMPLATE_FR = 'fr_gendarmerie';
    public const TEMPLATE_US = 'us_enlisted';

    public function __construct(
        private GradeFiliereDefinitionRepository $filieres,
        private GradeDefinitionRepository $grades,
        private ?GradeRepository $catalog = null,
        private ?PersonnelGradeHistoryRepository $history = null,
    ) {
        $this->catalog ??= new GradeRepository();
        $this->history ??= new PersonnelGradeHistoryRepository();
    }

    /**
     * @return list<array{key: string, label: string, detail: string}>
     */
    public static function templates(): array
    {
        return [
            [
                'key' => self::TEMPLATE_GENERIC,
                'label' => 'Échelle générique',
                'detail' => 'Soldat → Capitaine, voies ancienneté et choix mixtes.',
            ],
            [
                'key' => self::TEMPLATE_FR,
                'label' => 'Modèle gendarmerie / FR',
                'detail' => 'De Gendarme à Commandant, filière Cadre général.',
            ],
            [
                'key' => self::TEMPLATE_US,
                'label' => 'US Army enlisted',
                'detail' => 'PV1 → SGM, filière Enlisted.',
            ],
        ];
    }

    public static function templateForGradeSystem(string $gradeSystemCode): string
    {
        $code = strtoupper(trim($gradeSystemCode));

        return match ($code) {
            'US_CLASSIC' => self::TEMPLATE_US,
            'FR_CLASSIC' => self::TEMPLATE_FR,
            default => self::TEMPLATE_GENERIC,
        };
    }

    /**
     * @return array{filieres: int, grades: int, skipped: bool}
     */
    public function seedForTenant(int $tenantId, string $templateKey, bool $onlyIfEmpty = true): array
    {
        if (!$this->grades->schemaReady() || !$this->filieres->schemaReady()) {
            return ['filieres' => 0, 'grades' => 0, 'skipped' => true];
        }
        if ($onlyIfEmpty && $this->grades->countForTenant($tenantId) > 0) {
            return ['filieres' => 0, 'grades' => 0, 'skipped' => true];
        }

        $key = strtolower(trim($templateKey));
        if ($key === '') {
            $key = self::TEMPLATE_GENERIC;
        }

        $filiereCount = 0;
        $gradeCount = 0;
        foreach ($this->blueprint($key) as $filiere) {
            $existing = $this->filieres->findByCode($tenantId, (string) $filiere['code']);
            $filiereId = $existing !== null
                ? (int) $existing['id']
                : $this->filieres->create(
                    $tenantId,
                    (string) $filiere['code'],
                    (string) $filiere['label'],
                    (int) ($filiere['sort_order'] ?? 0)
                );
            $filiereCount++;
            $order = 10;
            foreach ($filiere['grades'] as $row) {
                $code = strtoupper((string) $row['code']);
                if ($this->grades->findByCode($tenantId, $code) !== null) {
                    $order += 10;
                    continue;
                }
                $catalogId = $this->resolveCatalogId($code, (string) ($row['catalog_hint'] ?? ''));
                $this->grades->create($tenantId, [
                    'code' => $code,
                    'label' => (string) $row['label'],
                    'short_label' => (string) ($row['short'] ?? $code),
                    'filiere_id' => $filiereId,
                    'rank_order' => $order,
                    'advancement_seniority_enabled' => !empty($row['seniority']),
                    'advancement_choice_enabled' => !empty($row['choice']),
                    'min_time_in_previous_grade_months' => $row['months'] ?? null,
                    'template_key' => $key,
                    'source_catalog_grade_id' => $catalogId,
                ]);
                $gradeCount++;
                $order += 10;
            }
        }

        return ['filieres' => $filiereCount, 'grades' => $gradeCount, 'skipped' => false];
    }

    public function seedInitialHistory(int $tenantId, int $personnelId, ?int $legacyGradeId, ?int $actorId = null): void
    {
        if (!$this->history->schemaReady()) {
            return;
        }
        if ($this->history->currentForPersonnel($tenantId, $personnelId) !== null) {
            return;
        }
        $grades = $this->grades->listForTenant($tenantId);
        if ($grades === []) {
            return;
        }
        $match = null;
        if ($legacyGradeId !== null && $legacyGradeId > 0) {
            foreach ($grades as $g) {
                if ((int) ($g['source_catalog_grade_id'] ?? 0) === $legacyGradeId) {
                    $match = $g;
                    break;
                }
            }
        }
        $match ??= $grades[0];
        $this->history->append($tenantId, $personnelId, (int) $match['id'], [
            'obtained_at' => date('Y-m-d'),
            'obtained_via' => AdvancementCodes::VIA_INITIAL,
            'created_by' => $actorId,
        ]);
    }

    private function resolveCatalogId(string $code, string $hint): ?int
    {
        try {
            foreach (['FR_CLASSIC', 'US_CLASSIC'] as $system) {
                foreach ($this->catalog->listBySystemCode($system) as $row) {
                    $c = strtoupper((string) ($row['code'] ?? ''));
                    $short = strtoupper((string) ($row['label_short'] ?? ''));
                    if ($c === $code || $short === $code || ($hint !== '' && strcasecmp((string) ($row['label_long'] ?? ''), $hint) === 0)) {
                        return (int) $row['id'];
                    }
                }
            }
        } catch (\Throwable) {
        }

        return null;
    }

    /**
     * @return list<array{code: string, label: string, sort_order: int, grades: list<array<string, mixed>>}>
     */
    private function blueprint(string $key): array
    {
        if ($key === self::TEMPLATE_US) {
            return [[
                'code' => 'ENLISTED',
                'label' => 'Enlisted',
                'sort_order' => 10,
                'grades' => [
                    ['code' => 'PV1', 'label' => 'Private', 'short' => 'PV1', 'seniority' => 1, 'choice' => 0, 'months' => 0],
                    ['code' => 'PV2', 'label' => 'Private Second Class', 'short' => 'PV2', 'seniority' => 1, 'choice' => 0, 'months' => 6],
                    ['code' => 'PFC', 'label' => 'Private First Class', 'short' => 'PFC', 'seniority' => 1, 'choice' => 1, 'months' => 12],
                    ['code' => 'SPC', 'label' => 'Specialist', 'short' => 'SPC', 'seniority' => 1, 'choice' => 1, 'months' => 12],
                    ['code' => 'CPL', 'label' => 'Corporal', 'short' => 'CPL', 'seniority' => 0, 'choice' => 1, 'months' => 18],
                    ['code' => 'SGT', 'label' => 'Sergeant', 'short' => 'SGT', 'seniority' => 0, 'choice' => 1, 'months' => 18],
                    ['code' => 'SSG', 'label' => 'Staff Sergeant', 'short' => 'SSG', 'seniority' => 0, 'choice' => 1, 'months' => 24],
                    ['code' => 'SFC', 'label' => 'Sergeant First Class', 'short' => 'SFC', 'seniority' => 0, 'choice' => 1, 'months' => 24],
                    ['code' => 'MSG', 'label' => 'Master Sergeant', 'short' => 'MSG', 'seniority' => 0, 'choice' => 1, 'months' => 36],
                    ['code' => 'SGM', 'label' => 'Sergeant Major', 'short' => 'SGM', 'seniority' => 0, 'choice' => 1, 'months' => 36],
                ],
            ]];
        }
        if ($key === self::TEMPLATE_FR) {
            return [[
                'code' => 'CADRE_GENERAL',
                'label' => 'Cadre général',
                'sort_order' => 10,
                'grades' => [
                    ['code' => 'GEND', 'label' => 'Gendarme', 'short' => 'Gend', 'seniority' => 1, 'choice' => 0, 'months' => 0, 'catalog_hint' => 'Gendarme'],
                    ['code' => 'MDL', 'label' => 'Maréchal des logis', 'short' => 'Mdl', 'seniority' => 1, 'choice' => 1, 'months' => 12],
                    ['code' => 'ADJ', 'label' => 'Adjudant', 'short' => 'Adj', 'seniority' => 1, 'choice' => 1, 'months' => 24, 'catalog_hint' => 'Adjudant'],
                    ['code' => 'ADC', 'label' => 'Adjudant-chef', 'short' => 'Adc', 'seniority' => 0, 'choice' => 1, 'months' => 24, 'catalog_hint' => 'Adjudant-chef'],
                    ['code' => 'MAJ', 'label' => 'Major', 'short' => 'Maj', 'seniority' => 0, 'choice' => 1, 'months' => 36, 'catalog_hint' => 'Major'],
                    ['code' => 'LT', 'label' => 'Lieutenant', 'short' => 'Lt', 'seniority' => 0, 'choice' => 1, 'months' => 36],
                    ['code' => 'CNE', 'label' => 'Capitaine', 'short' => 'Cne', 'seniority' => 0, 'choice' => 1, 'months' => 48],
                    ['code' => 'CDT', 'label' => 'Commandant', 'short' => 'Cdt', 'seniority' => 0, 'choice' => 1, 'months' => 48],
                ],
            ]];
        }

        return [[
            'code' => 'GENERALE',
            'label' => 'Filière générale',
            'sort_order' => 10,
            'grades' => [
                ['code' => 'SDT', 'label' => 'Soldat', 'short' => 'Sdt', 'seniority' => 1, 'choice' => 0, 'months' => 0],
                ['code' => 'CPL', 'label' => 'Caporal', 'short' => 'Cpl', 'seniority' => 1, 'choice' => 1, 'months' => 6],
                ['code' => 'SGT', 'label' => 'Sergent', 'short' => 'Sgt', 'seniority' => 1, 'choice' => 1, 'months' => 12],
                ['code' => 'ADJ', 'label' => 'Adjudant', 'short' => 'Adj', 'seniority' => 0, 'choice' => 1, 'months' => 18],
                ['code' => 'LT', 'label' => 'Lieutenant', 'short' => 'Lt', 'seniority' => 0, 'choice' => 1, 'months' => 24],
                ['code' => 'CNE', 'label' => 'Capitaine', 'short' => 'Cne', 'seniority' => 0, 'choice' => 1, 'months' => 36],
            ],
        ]];
    }
}
