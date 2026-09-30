<?php

declare(strict_types=1);

namespace App\Services\Advancement;

use App\Repositories\AdvancementRepository;
use App\Repositories\TenantRepository;
use Throwable;

/**
 * Projette le référentiel unique (FR_CLASSIC / US_CLASSIC) dans grade_definitions
 * pour les flags d’avancement. Une communauté n’a pas son propre catalogue de grades.
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
        if (str_contains($code, 'FR')) {
            return 'fr_classic';
        }

        return 'generique';
    }

    /**
     * Secours uniquement si le référentiel n’est pas encore installé (tests, bootstrap).
     *
     * @return array<string, array{label:string, filieres:list<array{code:string, label:string}>, grades:list<array<string, mixed>>}>
     */
    public function templates(): array
    {
        $filieresFrUs = [
            ['code' => 'mdr', 'label' => 'Militaire du rang'],
            ['code' => 'sous_officier', 'label' => 'Sous-officier'],
            ['code' => 'officier', 'label' => 'Officier'],
            ['code' => 'civil', 'label' => 'Civil'],
            ['code' => 'hors_grade', 'label' => 'Hors grade'],
        ];

        return [
            'fr_classic' => [
                'label' => 'Référentiel français (FR_CLASSIC)',
                'filieres' => $filieresFrUs,
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
                    $this->grade('SL', 'Sous-lieutenant', 'Slt', 1, false, true, 12, null, 'officier'),
                    $this->grade('LT', 'Lieutenant', 'Lt', 2, false, true, 18, null, 'officier'),
                    $this->grade('CNE', 'Capitaine', 'Cne', 3, false, true, 24, null, 'officier'),
                    $this->grade('CDT', 'Commandant', 'Cdt', 4, false, true, 24, null, 'officier'),
                    $this->grade('LCL', 'Lieutenant-colonel', 'Lcl', 5, false, true, 36, null, 'officier'),
                    $this->grade('COL', 'Colonel', 'Col', 6, false, true, 36, null, 'officier'),
                    $this->grade('GBR', 'Général de brigade', 'Gén. bde', 7, false, true, 36, null, 'officier'),
                    $this->grade('GDV', 'Général de division', 'Gén. div.', 8, false, true, 36, null, 'officier'),
                    $this->grade('GCA', 'Général de corps d’armée', 'Gén. c. a.', 9, false, true, 36, null, 'officier'),
                    $this->grade('GAR', 'Général d’armée', 'Gén. armée', 10, false, true, 36, null, 'officier'),
                    $this->grade('CIV', 'Personnel civil', 'Civil', 1, false, false, 0, null, 'civil'),
                    $this->grade('HG', 'Sans grade militaire', 'Hors grade', 1, false, false, 0, null, 'hors_grade'),
                ],
            ],
            'us_classic' => [
                'label' => 'Référentiel américain (US_CLASSIC)',
                'filieres' => $filieresFrUs,
                'grades' => [
                    $this->grade('PVT', 'Private', 'PVT', 1, true, false, 0, null, 'mdr'),
                    $this->grade('PV2', 'Private Second Class', 'PV2', 2, true, false, 6, null, 'mdr'),
                    $this->grade('PFC', 'Private First Class', 'PFC', 3, true, false, 12, null, 'mdr'),
                    $this->grade('CPL', 'Corporal', 'CPL', 4, true, true, 18, null, 'mdr'),
                    $this->grade('SGT', 'Sergeant', 'SGT', 1, true, true, 18, null, 'sous_officier'),
                    $this->grade('SSG', 'Staff Sergeant', 'SSG', 2, true, true, 24, null, 'sous_officier'),
                    $this->grade('SFC', 'Sergeant First Class', 'SFC', 3, false, true, 24, null, 'sous_officier'),
                    $this->grade('MSG', 'Master Sergeant', 'MSG', 4, false, true, 36, null, 'sous_officier'),
                    $this->grade('SGM', 'Sergeant Major', 'SGM', 5, false, true, 36, null, 'sous_officier'),
                    $this->grade('2LT', 'Second Lieutenant', '2LT', 1, false, true, 12, null, 'officier'),
                    $this->grade('1LT', 'First Lieutenant', '1LT', 2, false, true, 18, null, 'officier'),
                    $this->grade('CPT', 'Captain', 'CPT', 3, false, true, 24, null, 'officier'),
                    $this->grade('MAJ', 'Major', 'MAJ', 4, false, true, 24, null, 'officier'),
                    $this->grade('LTC', 'Lieutenant Colonel', 'LTC', 5, false, true, 36, null, 'officier'),
                    $this->grade('COL', 'Colonel', 'COL', 6, false, true, 36, null, 'officier'),
                    $this->grade('BG', 'Brigadier General', 'BG', 7, false, true, 36, null, 'officier'),
                    $this->grade('MG', 'Major General', 'MG', 8, false, true, 36, null, 'officier'),
                    $this->grade('LTG', 'Lieutenant General', 'LTG', 9, false, true, 36, null, 'officier'),
                    $this->grade('GEN', 'General', 'GEN', 10, false, true, 36, null, 'officier'),
                    $this->grade('CIV', 'Civilian (non-military)', 'Civilian', 1, false, false, 0, null, 'civil'),
                    $this->grade('HG', 'No military grade', 'No grade', 1, false, false, 0, null, 'hors_grade'),
                ],
            ],
            'generique' => [
                'label' => 'Secours (référentiel absent)',
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
     * Aligne l’échelle d’avancement sur le référentiel unique.
     * N’invente pas de grades hors catalogue. N’écrase pas les flags d’avancement.
     */
    public function completeForTenant(int $tenantId, string $gradeSystemCode = ''): int
    {
        if ($tenantId < 1 || !$this->repository->tablesReady()) {
            return 0;
        }
        if (trim($gradeSystemCode) === '') {
            $gradeSystemCode = $this->tenantGradeSystemCode($tenantId);
        }
        $before = count($this->repository->listGrades($tenantId, true));
        $catalogCodes = $this->copyFromCommunityCatalog($tenantId, $gradeSystemCode);
        if ($catalogCodes !== []) {
            $this->archiveUnusedGradesNotIn($tenantId, $catalogCodes);

            return max(0, count($this->repository->listGrades($tenantId, true)) - $before);
        }
        if ($this->repository->listGrades($tenantId, true) !== []) {
            return 0;
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

    /**
     * @param list<string> $catalogCodes
     */
    public function archiveUnusedGradesNotIn(int $tenantId, array $catalogCodes): int
    {
        $allowed = [];
        foreach ($catalogCodes as $code) {
            $normalized = strtoupper(trim((string) $code));
            if ($normalized !== '') {
                $allowed[$normalized] = true;
            }
        }
        if ($allowed === []) {
            return 0;
        }
        $archived = 0;
        foreach ($this->repository->listGrades($tenantId, false) as $grade) {
            $code = strtoupper(trim((string) ($grade['code'] ?? '')));
            if ($code !== '' && isset($allowed[$code])) {
                continue;
            }
            $id = (int) ($grade['id'] ?? 0);
            if ($id < 1 || $this->repository->gradeIsReferenced($id)) {
                continue;
            }
            $this->repository->archiveGrade($id, $tenantId);
            $archived++;
        }

        return $archived;
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

    /**
     * @return list<string> codes du référentiel (vide si tables absentes)
     */
    private function copyFromCommunityCatalog(int $tenantId, string $gradeSystemCode = ''): array
    {
        $rows = $this->catalogRowsForTenant($tenantId, $gradeSystemCode);
        if ($rows === []) {
            return [];
        }
        $codes = [];
        $grouped = [];
        foreach ($rows as $row) {
            $code = strtoupper(trim((string) ($row['code'] ?? '')));
            if ($code === '') {
                $code = 'G' . (int) ($row['id'] ?? 0);
            }
            $code = substr($code, 0, 64);
            $codes[] = $code;
            $row['_normalized_code'] = $code;
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
        foreach ($grouped as $catCode => $catRows) {
            usort($catRows, fn (array $a, array $b): int => $this->progressionKey($a) <=> $this->progressionKey($b));
            $idsInOrder = [];
            $inserted = false;
            foreach ($catRows as $index => $row) {
                $code = (string) $row['_normalized_code'];
                $existing = $this->repository->findGradeByCode($tenantId, $code);
                $filiereId = $filiereIds[$catCode] ?? null;
                $label = trim((string) ($row['label_long'] ?? $row['label'] ?? $code)) ?: $code;
                $short = trim((string) ($row['label_short'] ?? '')) ?: null;
                $rankOrder = $index + 1;
                if ($existing === null) {
                    $id = $this->repository->saveGrade($tenantId, [
                        'code' => $code,
                        'label' => $label,
                        'short_label' => $short,
                        'filiere_id' => $filiereId,
                        'rank_order' => $rankOrder,
                        'advancement_seniority_enabled' => empty($row['is_commissioned']),
                        'advancement_choice_enabled' => !empty($row['is_commissioned']) || $index >= 2,
                        'min_time_in_previous_grade_months' => $index === 0 ? 0 : min(36, ($index + 1) * 6),
                    ]);
                    $inserted = true;
                    $idsInOrder[] = $id;
                    continue;
                }
                $this->repository->saveGrade($tenantId, [
                    'code' => $code,
                    'label' => $label,
                    'short_label' => $short,
                    'filiere_id' => $filiereId,
                    'rank_order' => (int) ($existing['rank_order'] ?? $rankOrder),
                    'advancement_seniority_enabled' => $existing['advancement_seniority_enabled'] ?? 0,
                    'advancement_choice_enabled' => $existing['advancement_choice_enabled'] ?? 0,
                    'min_time_in_previous_grade_months' => $existing['min_time_in_previous_grade_months'] ?? null,
                    'required_qualification_id' => $existing['required_qualification_id'] ?? null,
                    'required_qualification_level_id' => $existing['required_qualification_level_id'] ?? null,
                ], (int) $existing['id']);
                if (!empty($existing['archived_at'])) {
                    $this->repository->restoreGrade((int) $existing['id'], $tenantId);
                }
                $idsInOrder[] = (int) $existing['id'];
            }
            if ($inserted && $idsInOrder !== []) {
                $this->repository->reorderGrades($tenantId, $idsInOrder);
            }
        }

        return $codes;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function catalogRowsForTenant(int $tenantId, string $gradeSystemCode = ''): array
    {
        $pdo = $this->repository->pdo();
        $systemCode = strtoupper(trim($gradeSystemCode));
        if ($systemCode === '') {
            $systemCode = strtoupper(trim($this->tenantGradeSystemCode($tenantId)));
        }
        if ($systemCode === '') {
            $systemCode = 'FR_CLASSIC';
        }
        $sql = 'SELECT g.id, g.code, g.label_short, g.label_long, g.label_otan, g.sort_order, g.is_commissioned,
                       gc.code AS category_code, gc.label AS category_label
                FROM %s g
                INNER JOIN grade_systems gs ON g.grade_system_id = gs.id
                INNER JOIN grade_categories gc ON g.grade_category_id = gc.id
                WHERE g.is_active = 1 AND gs.code = ?
                ORDER BY gc.sort_order ASC, g.sort_order ASC, g.id ASC';
        $rows = [];
        foreach (['grades', 'grades_referentiel'] as $table) {
            try {
                $st = $pdo->prepare(sprintf($sql, $table));
                $st->execute([$systemCode]);
                $rows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                break;
            } catch (Throwable) {
                $rows = [];
            }
        }
        if ($rows === []) {
            return [];
        }

        return $this->applyCatalogOverrides($pdo, $tenantId, $rows);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function applyCatalogOverrides(\PDO $pdo, int $tenantId, array $rows): array
    {
        try {
            $st = $pdo->prepare(
                'SELECT grade_id, label_short_override, label_long_override, sort_order_override, is_enabled
                 FROM tenant_grade_overrides WHERE tenant_id = ?'
            );
            $st->execute([$tenantId]);
            $over = [];
            foreach ($st->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
                $over[(int) $row['grade_id']] = $row;
            }
        } catch (Throwable) {
            return $rows;
        }
        if ($over === []) {
            return $rows;
        }
        $out = [];
        foreach ($rows as $grade) {
            $id = (int) ($grade['id'] ?? 0);
            if (isset($over[$id]) && (int) ($over[$id]['is_enabled'] ?? 1) === 0) {
                continue;
            }
            if (isset($over[$id])) {
                $o = $over[$id];
                if ($o['label_short_override'] !== null && $o['label_short_override'] !== '') {
                    $grade['label_short'] = (string) $o['label_short_override'];
                }
                if ($o['label_long_override'] !== null && $o['label_long_override'] !== '') {
                    $grade['label_long'] = (string) $o['label_long_override'];
                }
                if ($o['sort_order_override'] !== null && $o['sort_order_override'] !== '') {
                    $grade['sort_order'] = (int) $o['sort_order_override'];
                }
            }
            $out[] = $grade;
        }

        return $out;
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
