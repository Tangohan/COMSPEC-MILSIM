<?php

declare(strict_types=1);

namespace App\Services\Advancement;

use App\Repositories\AdvancementRepository;
use App\Repositories\GradeRepository;
use App\Repositories\TenantRepository;
use Throwable;

/**
 * Duplique une échelle type dans la communauté. Chaque tenant a sa copie :
 * rien n'est partagé entre communautés.
 */
final class GradeScaleTemplateService
{
    public function __construct(
        private ?AdvancementRepository $repository = null,
    ) {
        $this->repository ??= new AdvancementRepository();
    }

    public function templateForSystem(string $gradeSystemCode): string
    {
        $code = strtoupper(trim($gradeSystemCode));
        if (str_contains($code, 'US')) {
            return 'us_army_enlisted';
        }
        if (str_contains($code, 'FR') || str_contains($code, 'GD') || str_contains($code, 'GEND')) {
            return 'gendarmerie';
        }

        return 'generique';
    }

    /**
     * @return array<string, array{label:string, filieres:list<array{code:string, label:string}>, grades:list<array<string, mixed>>}>
     */
    public function templates(): array
    {
        return [
            'generique' => [
                'label' => 'Échelle générique',
                'filieres' => [
                    ['code' => 'general', 'label' => 'Cadre général'],
                ],
                'grades' => [
                    $this->grade('REC', 'Recrue', 'Recrue', 1, true, false, 0),
                    $this->grade('OPE', 'Opérateur', 'Opé.', 2, true, false, 6),
                    $this->grade('CPL', 'Chef d’équipe', 'Cpl', 3, true, true, 12),
                    $this->grade('SGT', 'Chef de groupe', 'Sgt', 4, true, true, 18),
                    $this->grade('ADJ', 'Adjudant', 'Adj', 5, false, true, 24),
                    $this->grade('LTN', 'Lieutenant', 'Ltn', 6, false, true, 24),
                ],
            ],
            'us_army_enlisted' => [
                'label' => 'US Army enlisted',
                'filieres' => [
                    ['code' => 'enlisted', 'label' => 'Enlisted'],
                ],
                'grades' => [
                    $this->grade('PV1', 'Private', 'PV1', 1, true, false, 0),
                    $this->grade('PV2', 'Private', 'PV2', 2, true, false, 6),
                    $this->grade('PFC', 'Private First Class', 'PFC', 3, true, false, 12),
                    $this->grade('SPC', 'Specialist', 'SPC', 4, true, false, 18),
                    $this->grade('SGT', 'Sergeant', 'SGT', 5, true, true, 24),
                    $this->grade('SSG', 'Staff Sergeant', 'SSG', 6, false, true, 36),
                    $this->grade('SFC', 'Sergeant First Class', 'SFC', 7, false, true, 36),
                    $this->grade('MSG', 'Master Sergeant', 'MSG', 8, false, true, 36),
                    $this->grade('SGM', 'Sergeant Major', 'SGM', 9, false, true, 36),
                ],
            ],
            'gendarmerie' => [
                'label' => 'Gendarmerie',
                'filieres' => [
                    ['code' => 'cadre', 'label' => 'Cadre général'],
                    ['code' => 'aero', 'label' => 'Aéronautique'],
                    ['code' => 'spe', 'label' => 'Spécialiste'],
                ],
                'grades' => [
                    $this->grade('GND', 'Gendarme', 'GND', 1, true, false, 0),
                    $this->grade('MDL', 'Maréchal des logis', 'MDL', 2, true, true, 12),
                    $this->grade('ADJ', 'Adjudant', 'ADJ', 3, true, true, 24),
                    $this->grade('ADC', 'Adjudant-chef', 'ADC', 4, false, true, 24),
                    $this->grade('MAJ', 'Major', 'MAJ', 5, false, true, 24, 'CEFEO'),
                ],
            ],
        ];
    }

    public function seedForNewTenant(int $tenantId, string $gradeSystemCode): bool
    {
        return $this->ensureForTenant($tenantId, $gradeSystemCode);
    }

    /**
     * Communautés déjà créées : la table d’avancement est vide tant qu’on n’a pas
     * dupliqué une échelle. Recopie le référentiel de la communauté, sinon un modèle.
     */
    public function ensureForTenant(int $tenantId, string $gradeSystemCode = ''): bool
    {
        if ($tenantId < 1 || !$this->repository->tablesReady()) {
            return false;
        }
        if ($this->repository->listGrades($tenantId, true) !== []) {
            return false;
        }
        if ($this->copyFromCommunityCatalog($tenantId)) {
            return true;
        }
        if (trim($gradeSystemCode) === '') {
            $gradeSystemCode = $this->tenantGradeSystemCode($tenantId);
        }

        return $this->duplicate($tenantId, $this->templateForSystem($gradeSystemCode));
    }

    public function ensureForAllTenants(): int
    {
        if (!$this->repository->tablesReady()) {
            return 0;
        }
        try {
            $ids = $this->repository->pdo()->query('SELECT id FROM tenants ORDER BY id ASC')->fetchAll(\PDO::FETCH_COLUMN) ?: [];
        } catch (Throwable) {
            return 0;
        }
        $n = 0;
        foreach ($ids as $id) {
            if ($this->ensureForTenant((int) $id)) {
                $n++;
            }
        }

        return $n;
    }

    public function duplicate(int $tenantId, string $templateCode): bool
    {
        $templates = $this->templates();
        if (!isset($templates[$templateCode])) {
            return false;
        }
        if (!$this->repository->tablesReady()) {
            return false;
        }
        $template = $templates[$templateCode];
        $filiereIds = [];
        $sort = 1;
        foreach ($template['filieres'] as $filiere) {
            $existing = null;
            foreach ($this->repository->listFilieres($tenantId) as $row) {
                if ((string) $row['code'] === (string) $filiere['code']) {
                    $existing = (int) $row['id'];
                    break;
                }
            }
            $filiereIds[(string) $filiere['code']] = $existing ?? $this->repository->saveFiliere($tenantId, [
                'code' => $filiere['code'],
                'label' => $filiere['label'],
                'sort_order' => $sort,
            ]);
            $sort++;
        }
        $primaryFiliere = (int) (reset($filiereIds) ?: 0);
        foreach ($template['grades'] as $grade) {
            if ($this->repository->findGradeByCode($tenantId, (string) $grade['code']) !== null) {
                continue;
            }
            $qualId = null;
            $qualCode = (string) ($grade['required_qualification_code'] ?? '');
            if ($qualCode !== '') {
                $qual = $this->repository->findQualificationByCode($tenantId, $qualCode);
                if ($qual !== null) {
                    $qualId = (int) $qual['id'];
                }
            }
            $this->repository->saveGrade($tenantId, [
                'code' => $grade['code'],
                'label' => $grade['label'],
                'short_label' => $grade['short_label'],
                'filiere_id' => $primaryFiliere > 0 ? $primaryFiliere : null,
                'rank_order' => $grade['rank_order'],
                'advancement_seniority_enabled' => $grade['advancement_seniority_enabled'],
                'advancement_choice_enabled' => $grade['advancement_choice_enabled'],
                'min_time_in_previous_grade_months' => $grade['min_time_in_previous_grade_months'],
                'required_qualification_id' => $qualId,
            ]);
        }

        return true;
    }

    private function copyFromCommunityCatalog(int $tenantId): bool
    {
        try {
            $rows = (new GradeRepository())->listForTenant($tenantId);
        } catch (Throwable) {
            return false;
        }
        if ($rows === []) {
            return false;
        }
        $filiereIds = [];
        $sortFiliere = 1;
        $order = 0;
        $copied = 0;
        $total = count($rows);
        foreach ($rows as $row) {
            if (isset($row['is_enabled']) && (int) $row['is_enabled'] === 0) {
                continue;
            }
            $code = strtoupper(trim((string) ($row['code'] ?? '')));
            if ($code === '') {
                $code = 'G' . (int) ($row['id'] ?? 0);
            }
            if ($this->repository->findGradeByCode($tenantId, $code) !== null) {
                continue;
            }
            $catCode = strtolower(trim((string) ($row['category_code'] ?? 'general')));
            if ($catCode === '') {
                $catCode = 'general';
            }
            if (!isset($filiereIds[$catCode])) {
                $filiereIds[$catCode] = $this->repository->saveFiliere($tenantId, [
                    'code' => substr($catCode, 0, 40),
                    'label' => trim((string) ($row['category_label'] ?? 'Cadre général')) ?: 'Cadre général',
                    'sort_order' => $sortFiliere,
                ]);
                $sortFiliere++;
            }
            $order++;
            $isCommissioned = !empty($row['is_commissioned']);
            $this->repository->saveGrade($tenantId, [
                'code' => substr($code, 0, 64),
                'label' => trim((string) ($row['label_long'] ?? $row['label'] ?? $code)) ?: $code,
                'short_label' => trim((string) ($row['label_short'] ?? '')) ?: null,
                'filiere_id' => $filiereIds[$catCode],
                'rank_order' => (int) ($row['sort_order'] ?? $order),
                'advancement_seniority_enabled' => !$isCommissioned,
                'advancement_choice_enabled' => $isCommissioned || $order > (int) ceil($total / 2),
                'min_time_in_previous_grade_months' => $order <= 1 ? 0 : min(36, $order * 6),
            ]);
            $copied++;
        }

        return $copied > 0;
    }

    private function tenantGradeSystemCode(int $tenantId): string
    {
        try {
            $settings = (new TenantRepository())->getSettings($tenantId);

            return trim((string) ($settings['grade_system_code'] ?? ''));
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function grade(
        string $code,
        string $label,
        string $short,
        int $order,
        bool $seniority,
        bool $choice,
        int $months,
        ?string $qualificationCode = null,
    ): array {
        return [
            'code' => $code,
            'label' => $label,
            'short_label' => $short,
            'rank_order' => $order,
            'advancement_seniority_enabled' => $seniority,
            'advancement_choice_enabled' => $choice,
            'min_time_in_previous_grade_months' => $months,
            'required_qualification_code' => $qualificationCode,
        ];
    }
}
