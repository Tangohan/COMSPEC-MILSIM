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
        if ($code === '') {
            return 'generique';
        }
        if (str_contains($code, 'US')) {
            return 'us_classic';
        }
        if (str_contains($code, 'GD') || str_contains($code, 'GEND')) {
            return 'gendarmerie';
        }
        if (str_contains($code, 'FR')) {
            return 'fr_classic';
        }

        return 'generique';
    }

    /**
     * @return array<string, array{label:string, filieres:list<array{code:string, label:string}>, grades:list<array<string, mixed>>}>
     */
    public function templates(): array
    {
        return [
            'fr_classic' => [
                'label' => 'Échelle française complète (MdR → généraux)',
                'filieres' => [
                    ['code' => 'mdr', 'label' => 'Militaire du rang'],
                    ['code' => 'sous_officier', 'label' => 'Sous-officier'],
                    ['code' => 'officier', 'label' => 'Officier'],
                    ['code' => 'civil', 'label' => 'Civil'],
                    ['code' => 'hors_grade', 'label' => 'Hors grade'],
                ],
                'grades' => [
                    $this->grade('SD2', 'Soldat de 2e classe', 'Sdt 2', 1, true, false, 0, null, 'mdr'),
                    $this->grade('SD1', 'Soldat de 1re classe', 'Sdt 1', 2, true, false, 6, null, 'mdr'),
                    $this->grade('CPL', 'Caporal', 'Cpl', 3, true, false, 6, null, 'mdr'),
                    $this->grade('CCH', 'Caporal-chef', 'Cch', 4, true, true, 12, null, 'mdr'),
                    $this->grade('SGT', 'Sergent', 'Sgt', 1, true, true, 12, null, 'sous_officier'),
                    $this->grade('SCH', 'Sergent-chef', 'Sch', 2, true, true, 18, null, 'sous_officier'),
                    $this->grade('ADJ', 'Adjudant', 'Adj', 3, true, true, 24, null, 'sous_officier'),
                    $this->grade('ADC', 'Adjudant-chef', 'Adc', 4, false, true, 24, null, 'sous_officier'),
                    $this->grade('MAJ', 'Major', 'Major', 5, false, true, 24, null, 'sous_officier'),
                    $this->grade('ASP', 'Aspirant', 'Asp', 1, false, true, 12, null, 'officier'),
                    $this->grade('SL', 'Sous-lieutenant', 'Slt', 2, false, true, 12, null, 'officier'),
                    $this->grade('LT', 'Lieutenant', 'Lt', 3, false, true, 18, null, 'officier'),
                    $this->grade('CNE', 'Capitaine', 'Cne', 4, false, true, 24, null, 'officier'),
                    $this->grade('CDT', 'Commandant', 'Cdt', 5, false, true, 24, null, 'officier'),
                    $this->grade('LCL', 'Lieutenant-colonel', 'Lcl', 6, false, true, 36, null, 'officier'),
                    $this->grade('COL', 'Colonel', 'Col', 7, false, true, 36, null, 'officier'),
                    $this->grade('GBR', 'Général de brigade', 'Gén. bde', 8, false, true, 36, null, 'officier'),
                    $this->grade('GDV', 'Général de division', 'Gén. div.', 9, false, true, 36, null, 'officier'),
                    $this->grade('GCA', 'Général de corps d’armée', 'Gén. c. a.', 10, false, true, 36, null, 'officier'),
                    $this->grade('GAR', 'Général d’armée', 'Gén. armée', 11, false, true, 36, null, 'officier'),
                    $this->grade('CIV', 'Personnel civil', 'Civil', 1, false, false, 0, null, 'civil'),
                    $this->grade('HG', 'Sans grade militaire', 'Hors grade', 1, false, false, 0, null, 'hors_grade'),
                ],
            ],
            'us_classic' => [
                'label' => 'Échelle US complète (enlisted → généraux)',
                'filieres' => [
                    ['code' => 'enlisted', 'label' => 'Enlisted'],
                    ['code' => 'nco', 'label' => 'NCO'],
                    ['code' => 'warrant', 'label' => 'Warrant Officer'],
                    ['code' => 'officer', 'label' => 'Officer'],
                    ['code' => 'civil', 'label' => 'Civilian'],
                    ['code' => 'hors_grade', 'label' => 'No grade'],
                ],
                'grades' => [
                    $this->grade('PVT', 'Private', 'PVT', 1, true, false, 0, null, 'enlisted'),
                    $this->grade('PV2', 'Private Second Class', 'PV2', 2, true, false, 6, null, 'enlisted'),
                    $this->grade('PFC', 'Private First Class', 'PFC', 3, true, false, 12, null, 'enlisted'),
                    $this->grade('SPC', 'Specialist', 'SPC', 4, true, false, 18, null, 'enlisted'),
                    $this->grade('CPL', 'Corporal', 'CPL', 5, true, true, 18, null, 'enlisted'),
                    $this->grade('SGT', 'Sergeant', 'SGT', 1, true, true, 18, null, 'nco'),
                    $this->grade('SSG', 'Staff Sergeant', 'SSG', 2, true, true, 24, null, 'nco'),
                    $this->grade('SFC', 'Sergeant First Class', 'SFC', 3, false, true, 24, null, 'nco'),
                    $this->grade('MSG', 'Master Sergeant', 'MSG', 4, false, true, 36, null, 'nco'),
                    $this->grade('1SG', 'First Sergeant', '1SG', 5, false, true, 36, null, 'nco'),
                    $this->grade('SGM', 'Sergeant Major', 'SGM', 6, false, true, 36, null, 'nco'),
                    $this->grade('CSM', 'Command Sergeant Major', 'CSM', 7, false, true, 36, null, 'nco'),
                    $this->grade('WO1', 'Warrant Officer 1', 'WO1', 1, false, true, 24, null, 'warrant'),
                    $this->grade('CW2', 'Chief Warrant Officer 2', 'CW2', 2, false, true, 24, null, 'warrant'),
                    $this->grade('CW3', 'Chief Warrant Officer 3', 'CW3', 3, false, true, 36, null, 'warrant'),
                    $this->grade('CW4', 'Chief Warrant Officer 4', 'CW4', 4, false, true, 36, null, 'warrant'),
                    $this->grade('CW5', 'Chief Warrant Officer 5', 'CW5', 5, false, true, 36, null, 'warrant'),
                    $this->grade('2LT', 'Second Lieutenant', '2LT', 1, false, true, 12, null, 'officer'),
                    $this->grade('1LT', 'First Lieutenant', '1LT', 2, false, true, 18, null, 'officer'),
                    $this->grade('CPT', 'Captain', 'CPT', 3, false, true, 24, null, 'officer'),
                    $this->grade('MAJ', 'Major', 'MAJ', 4, false, true, 24, null, 'officer'),
                    $this->grade('LTC', 'Lieutenant Colonel', 'LTC', 5, false, true, 36, null, 'officer'),
                    $this->grade('COL', 'Colonel', 'COL', 6, false, true, 36, null, 'officer'),
                    $this->grade('BG', 'Brigadier General', 'BG', 7, false, true, 36, null, 'officer'),
                    $this->grade('MG', 'Major General', 'MG', 8, false, true, 36, null, 'officer'),
                    $this->grade('LTG', 'Lieutenant General', 'LTG', 9, false, true, 36, null, 'officer'),
                    $this->grade('GEN', 'General', 'GEN', 10, false, true, 36, null, 'officer'),
                    $this->grade('CIV', 'Civilian (non-military)', 'Civilian', 1, false, false, 0, null, 'civil'),
                    $this->grade('HG', 'No military grade', 'No grade', 1, false, false, 0, null, 'hors_grade'),
                ],
            ],
            'gendarmerie' => [
                'label' => 'Gendarmerie (hommes du rang → généraux)',
                'filieres' => [
                    ['code' => 'rangs', 'label' => 'Hommes du rang'],
                    ['code' => 'sous_officiers', 'label' => 'Sous-officiers'],
                    ['code' => 'officiers', 'label' => 'Officiers'],
                ],
                'grades' => [
                    $this->grade('GAV', 'Gendarme adjoint volontaire', 'GAV', 1, true, false, 0, null, 'rangs'),
                    $this->grade('GND', 'Gendarme', 'GND', 2, true, false, 12, null, 'rangs'),
                    $this->grade('MDL', 'Maréchal des logis', 'MDL', 3, true, true, 18, null, 'rangs'),
                    $this->grade('MDC', 'Maréchal des logis-chef', 'MDC', 4, true, true, 24, null, 'rangs'),
                    $this->grade('ADJ', 'Adjudant', 'ADJ', 1, true, true, 24, null, 'sous_officiers'),
                    $this->grade('ADC', 'Adjudant-chef', 'ADC', 2, false, true, 24, null, 'sous_officiers'),
                    $this->grade('MAJ', 'Major', 'MAJ', 3, false, true, 24, null, 'sous_officiers'),
                    $this->grade('ASP', 'Aspirant', 'Asp', 1, false, true, 12, null, 'officiers'),
                    $this->grade('SLT', 'Sous-lieutenant', 'Slt', 2, false, true, 12, null, 'officiers'),
                    $this->grade('LTN', 'Lieutenant', 'Ltn', 3, false, true, 18, null, 'officiers'),
                    $this->grade('CNE', 'Capitaine', 'Cne', 4, false, true, 24, null, 'officiers'),
                    $this->grade('CEN', 'Chef d’escadron', 'Cen', 5, false, true, 24, null, 'officiers'),
                    $this->grade('LCL', 'Lieutenant-colonel', 'Lcl', 6, false, true, 36, null, 'officiers'),
                    $this->grade('COL', 'Colonel', 'Col', 7, false, true, 36, null, 'officiers'),
                    $this->grade('GBR', 'Général de brigade', 'Gén. bde', 8, false, true, 36, null, 'officiers'),
                    $this->grade('GDV', 'Général de division', 'Gén. div.', 9, false, true, 36, null, 'officiers'),
                    $this->grade('GCA', 'Général de corps d’armée', 'Gén. c. a.', 10, false, true, 36, null, 'officiers'),
                    $this->grade('GAR', 'Général d’armée', 'Gén. armée', 11, false, true, 36, null, 'officiers'),
                ],
            ],
            'us_army_enlisted' => [
                'label' => 'US Army enlisted seulement',
                'filieres' => [
                    ['code' => 'enlisted', 'label' => 'Enlisted'],
                ],
                'grades' => [
                    $this->grade('PV1', 'Private', 'PV1', 1, true, false, 0, null, 'enlisted'),
                    $this->grade('PV2', 'Private', 'PV2', 2, true, false, 6, null, 'enlisted'),
                    $this->grade('PFC', 'Private First Class', 'PFC', 3, true, false, 12, null, 'enlisted'),
                    $this->grade('SPC', 'Specialist', 'SPC', 4, true, false, 18, null, 'enlisted'),
                    $this->grade('SGT', 'Sergeant', 'SGT', 5, true, true, 24, null, 'enlisted'),
                    $this->grade('SSG', 'Staff Sergeant', 'SSG', 6, false, true, 36, null, 'enlisted'),
                    $this->grade('SFC', 'Sergeant First Class', 'SFC', 7, false, true, 36, null, 'enlisted'),
                    $this->grade('MSG', 'Master Sergeant', 'MSG', 8, false, true, 36, null, 'enlisted'),
                    $this->grade('SGM', 'Sergeant Major', 'SGM', 9, false, true, 36, null, 'enlisted'),
                ],
            ],
            'generique' => [
                'label' => 'Échelle générique milsim',
                'filieres' => [
                    ['code' => 'general', 'label' => 'Cadre général'],
                ],
                'grades' => [
                    $this->grade('REC', 'Recrue', 'Recrue', 1, true, false, 0, null, 'general'),
                    $this->grade('OPE', 'Opérateur', 'Opé.', 2, true, false, 6, null, 'general'),
                    $this->grade('CPL', 'Chef d’équipe', 'Cpl', 3, true, true, 12, null, 'general'),
                    $this->grade('SGT', 'Chef de groupe', 'Sgt', 4, true, true, 18, null, 'general'),
                    $this->grade('ADJ', 'Adjudant', 'Adj', 5, false, true, 24, null, 'general'),
                    $this->grade('LTN', 'Lieutenant', 'Ltn', 6, false, true, 24, null, 'general'),
                    $this->grade('CNE', 'Capitaine', 'Cne', 7, false, true, 24, null, 'general'),
                    $this->grade('CDT', 'Commandant', 'Cdt', 8, false, true, 36, null, 'general'),
                    $this->grade('LCL', 'Lieutenant-colonel', 'Lcl', 9, false, true, 36, null, 'general'),
                    $this->grade('COL', 'Colonel', 'Col', 10, false, true, 36, null, 'general'),
                ],
            ],
        ];
    }

    public function seedForNewTenant(int $tenantId, string $gradeSystemCode): bool
    {
        return $this->ensureForTenant($tenantId, $gradeSystemCode);
    }

    /**
     * Ajoute les grades manquants (référentiel communauté, sinon modèle complet).
     * N’écrase jamais un grade déjà présent.
     */
    public function completeForTenant(int $tenantId, string $gradeSystemCode = ''): int
    {
        if ($tenantId < 1 || !$this->repository->tablesReady()) {
            return 0;
        }
        $before = count($this->repository->listGrades($tenantId, true));
        $this->copyFromCommunityCatalog($tenantId);
        if (trim($gradeSystemCode) === '') {
            $gradeSystemCode = $this->tenantGradeSystemCode($tenantId);
        }
        $this->duplicate($tenantId, $this->templateForSystem($gradeSystemCode));

        return max(0, count($this->repository->listGrades($tenantId, true)) - $before);
    }

    public function ensureForTenant(int $tenantId, string $gradeSystemCode = ''): bool
    {
        if ($tenantId < 1 || !$this->repository->tablesReady()) {
            return false;
        }
        if ($this->repository->listGrades($tenantId, true) !== []) {
            return false;
        }

        return $this->completeForTenant($tenantId, $gradeSystemCode) > 0;
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
            if ($this->completeForTenant((int) $id) > 0) {
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
        $filiereIds = $this->ensureFilieres($tenantId, $template['filieres']);
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
            $filiereCode = (string) ($grade['filiere'] ?? '');
            $filiereId = $filiereIds[$filiereCode] ?? $primaryFiliere;
            $this->repository->saveGrade($tenantId, [
                'code' => $grade['code'],
                'label' => $grade['label'],
                'short_label' => $grade['short_label'],
                'filiere_id' => $filiereId > 0 ? $filiereId : null,
                'rank_order' => $grade['rank_order'],
                'advancement_seniority_enabled' => $grade['advancement_seniority_enabled'],
                'advancement_choice_enabled' => $grade['advancement_choice_enabled'],
                'min_time_in_previous_grade_months' => $grade['min_time_in_previous_grade_months'],
                'required_qualification_id' => $qualId,
            ]);
        }

        return true;
    }

    /**
     * @param list<array{code:string, label:string}> $filieres
     * @return array<string, int>
     */
    private function ensureFilieres(int $tenantId, array $filieres): array
    {
        $existing = [];
        foreach ($this->repository->listFilieres($tenantId) as $row) {
            $existing[(string) $row['code']] = (int) $row['id'];
        }
        $ids = [];
        $sort = count($existing) + 1;
        foreach ($filieres as $filiere) {
            $code = (string) $filiere['code'];
            if (isset($existing[$code])) {
                $ids[$code] = $existing[$code];
                continue;
            }
            $ids[$code] = $this->repository->saveFiliere($tenantId, [
                'code' => $code,
                'label' => $filiere['label'],
                'sort_order' => $sort,
            ]);
            $sort++;
        }

        return $ids;
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
        $grouped = [];
        foreach ($rows as $row) {
            if (isset($row['is_enabled']) && (int) $row['is_enabled'] === 0) {
                continue;
            }
            $catCode = strtolower(trim((string) ($row['category_code'] ?? 'general')));
            if ($catCode === '') {
                $catCode = 'general';
            }
            $grouped[$catCode][] = $row;
        }
        $filiereDefs = [];
        foreach ($grouped as $catCode => $catRows) {
            $label = trim((string) ($catRows[0]['category_label'] ?? 'Cadre général')) ?: 'Cadre général';
            $filiereDefs[] = ['code' => substr($catCode, 0, 40), 'label' => $label];
        }
        $filiereIds = $this->ensureFilieres($tenantId, $filiereDefs);
        $copied = 0;
        foreach ($grouped as $catCode => $catRows) {
            usort($catRows, fn (array $a, array $b): int => $this->progressionKey($a) <=> $this->progressionKey($b));
            $idsInOrder = [];
            foreach ($catRows as $index => $row) {
                $code = strtoupper(trim((string) ($row['code'] ?? '')));
                if ($code === '') {
                    $code = 'G' . (int) ($row['id'] ?? 0);
                }
                $code = substr($code, 0, 64);
                $existing = $this->repository->findGradeByCode($tenantId, $code);
                $filiereId = $filiereIds[$catCode] ?? null;
                if ($existing === null) {
                    $id = $this->repository->saveGrade($tenantId, [
                        'code' => $code,
                        'label' => trim((string) ($row['label_long'] ?? $row['label'] ?? $code)) ?: $code,
                        'short_label' => trim((string) ($row['label_short'] ?? '')) ?: null,
                        'filiere_id' => $filiereId,
                        'rank_order' => $index + 1,
                        'advancement_seniority_enabled' => empty($row['is_commissioned']),
                        'advancement_choice_enabled' => !empty($row['is_commissioned']) || $index >= 2,
                        'min_time_in_previous_grade_months' => $index === 0 ? 0 : min(36, ($index + 1) * 6),
                    ]);
                    $copied++;
                    $idsInOrder[] = $id;
                } elseif ($filiereId !== null && (int) ($existing['filiere_id'] ?? 0) === (int) $filiereId) {
                    $idsInOrder[] = (int) $existing['id'];
                }
            }
            if ($idsInOrder !== []) {
                $this->repository->reorderGrades($tenantId, $idsInOrder);
            }
        }

        return $copied > 0;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function progressionKey(array $row): int
    {
        $otan = strtoupper(trim((string) ($row['label_otan'] ?? '')));
        if (preg_match('/^(?:OR|OF|E|O)-(\d+)/', $otan, $m)) {
            $prefix = str_starts_with($otan, 'OF') || str_starts_with($otan, 'O-') ? 100 : 0;

            return $prefix + (int) $m[1];
        }
        $order = (int) ($row['sort_order'] ?? 0);
        $cat = strtoupper((string) ($row['category_code'] ?? ''));
        if (in_array($cat, ['SOUS_OFFICIER', 'MDR'], true)) {
            return 1000 - $order;
        }

        return $order;
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
        string $filiere = 'general',
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
            'filiere' => $filiere,
        ];
    }
}
