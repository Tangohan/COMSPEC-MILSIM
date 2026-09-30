<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AdvancementRepository;
use App\Repositories\GradeCategoryRepository;
use App\Repositories\GradeRepository;
use App\Repositories\GradeSystemRepository;
use App\Repositories\TenantGradeOverrideRepository;
use App\Repositories\TenantRepository;
use App\Services\Advancement\GradeScaleTemplateService;
use App\Services\GradeDisplayService;

class GradeReferentielController
{
    public function __construct(
        private GradeRepository $gradeRepository,
        private GradeCategoryRepository $gradeCategoryRepository,
        private GradeSystemRepository $gradeSystemRepository,
        private GradeDisplayService $gradeDisplayService,
        private TenantRepository $tenantRepository,
        private TenantGradeOverrideRepository $overrides,
        private AdvancementRepository $advancement,
        private GradeScaleTemplateService $templates,
    ) {
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }
        $settings = $this->tenantRepository->getSettings($tenantId);
        $systemCode = strtoupper(trim((string) ($settings['grade_system_code'] ?? ''))) ?: 'FR_CLASSIC';
        $tab = $this->normalizeTab((string) $request->query('tab', 'fr'));
        $categoryFilter = (int) $request->query('categorie', 0);
        $categoryFilter = $categoryFilter > 0 ? $categoryFilter : null;
        $systems = $this->gradeSystemRepository->listActive();
        $categories = $this->gradeCategoryRepository->listActive();
        $gradesFr = $this->gradeRepository->listBySystemCodeForTenantAdmin('FR_CLASSIC', $tenantId, $categoryFilter);
        $gradesUs = $this->gradeRepository->listBySystemCodeForTenantAdmin('US_CLASSIC', $tenantId, $categoryFilter);
        $allGrades = $this->gradeRepository->listActive();
        $this->templates->completeForTenant($tenantId, $systemCode);
        $catalogForExtras = array_merge(
            $this->gradeRepository->listBySystemCode('FR_CLASSIC'),
            $this->gradeRepository->listBySystemCode('US_CLASSIC')
        );

        return Response::view('layout.main', [
            'content' => 'admin.organization.referentiels.grades.index',
            'title' => 'Référentiel des grades',
            'tab' => $tab,
            'systems' => $systems,
            'categories' => $categories,
            'gradesFr' => $gradesFr,
            'gradesUs' => $gradesUs,
            'allGrades' => $allGrades,
            'tenantExtras' => $this->tenantExtras($tenantId, $catalogForExtras),
            'tenantSystemCode' => $systemCode,
            'gradeCategoryFilterId' => $categoryFilter,
            'gradeDisplayService' => $this->gradeDisplayService,
        ]);
    }

    public function create(Request $request, array $params = []): Response
    {
        if (!(int) Session::get('tenant_id')) {
            return Response::redirect(url('login'));
        }
        $systems = $this->gradeSystemRepository->listActive();
        $categories = $this->gradeCategoryRepository->listActive();
        $returnTab = $this->normalizeGradeTab((string) $request->query('tab', 'fr'));
        return Response::view('layout.main', [
            'content' => 'admin.organization.referentiels.grades.form',
            'title' => 'Nouveau grade',
            'grade' => null,
            'systems' => $systems,
            'categories' => $categories,
            'returnTab' => $returnTab,
            'tenantOwned' => true,
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }
        if (!$request->isPost() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Requête invalide.');
            return Response::redirect(url('back-office/referentiels/grades/create'));
        }
        $systemId = (int) $request->input('grade_system_id');
        $returnTab = $this->tabForSystemId($systemId);
        $categoryId = (int) $request->input('grade_category_id');
        $code = strtoupper(trim((string) $request->input('code')));
        $labelShort = trim((string) $request->input('label_short'));
        $labelLong = trim((string) $request->input('label_long'));
        if ($code === '' || $labelShort === '' || $labelLong === '' || !$systemId || !$categoryId) {
            Session::flash('error', 'Code, libellé court, libellé long, système et catégorie sont requis.');
            return Response::redirect(url('back-office/referentiels/grades/create'));
        }
        if ($this->advancement->findGradeByCode($tenantId, $code) !== null) {
            Session::flash('error', 'Ce code existe déjà dans le référentiel de la communauté.');
            return Response::redirect($this->indexUrl($returnTab));
        }
        $filiereId = $this->filiereFromCategory($tenantId, $categoryId);
        $this->advancement->saveGrade($tenantId, [
            'code' => $code,
            'label' => $labelLong,
            'short_label' => $labelShort,
            'filiere_id' => $filiereId,
            'rank_order' => (int) $request->input('sort_order'),
            'advancement_seniority_enabled' => $request->input('is_commissioned') ? 0 : 1,
            'advancement_choice_enabled' => 1,
        ]);
        Session::flash('success', 'Grade ajouté au référentiel de la communauté. Le catalogue partagé n’est pas modifié.');
        return Response::redirect($this->indexUrl($returnTab));
    }

    public function edit(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }
        $id = (int) ($params['id'] ?? 0);
        $grade = $id ? $this->gradeRepository->findById($id) : null;
        if (!$grade) {
            Session::flash('error', 'Grade introuvable.');
            return Response::redirect(url('back-office/referentiels/grades'));
        }
        $systems = $this->gradeSystemRepository->listActive();
        $categories = $this->gradeCategoryRepository->listActive();
        $returnTab = $this->tabForGrade($grade);
        $override = $this->overrides->find($tenantId, $id);
        if ($override !== null) {
            if (($override['label_short_override'] ?? '') !== '' && $override['label_short_override'] !== null) {
                $grade['label_short'] = (string) $override['label_short_override'];
            }
            if (($override['label_long_override'] ?? '') !== '' && $override['label_long_override'] !== null) {
                $grade['label_long'] = (string) $override['label_long_override'];
            }
            if ($override['sort_order_override'] !== null && $override['sort_order_override'] !== '') {
                $grade['sort_order'] = (int) $override['sort_order_override'];
            }
            $grade['is_active'] = (int) ($override['is_enabled'] ?? 1);
        } else {
            $grade['is_active'] = 1;
        }
        return Response::view('layout.main', [
            'content' => 'admin.organization.referentiels.grades.form',
            'title' => 'Modifier le grade',
            'grade' => $grade,
            'systems' => $systems,
            'categories' => $categories,
            'returnTab' => $returnTab,
            'tenantOwned' => true,
        ]);
    }

    public function update(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }
        if (!$request->isPost() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Requête invalide.');
            return Response::redirect(url('back-office/referentiels/grades'));
        }
        $id = (int) ($params['id'] ?? 0);
        $grade = $id ? $this->gradeRepository->findById($id) : null;
        if (!$grade) {
            Session::flash('error', 'Grade introuvable.');
            return Response::redirect(url('back-office/referentiels/grades'));
        }
        $systemId = (int) ($grade['grade_system_id'] ?? $request->input('grade_system_id'));
        $labelShort = trim((string) $request->input('label_short'));
        $labelLong = trim((string) $request->input('label_long'));
        if ($labelShort === '' || $labelLong === '') {
            Session::flash('error', 'Libellé court et libellé long sont requis.');
            return Response::redirect(url('back-office/referentiels/grades/' . $id . '/edit'));
        }
        $catalogShort = trim((string) ($grade['label_short'] ?? ''));
        $catalogLong = trim((string) ($grade['label_long'] ?? ''));
        $this->overrides->upsert($tenantId, $id, [
            'label_short' => $labelShort === $catalogShort ? null : $labelShort,
            'label_long' => $labelLong === $catalogLong ? null : $labelLong,
            'sort_order' => (int) $request->input('sort_order'),
            'is_enabled' => $request->input('is_active') ? true : false,
        ]);
        $this->syncAdvancementLabel($tenantId, (string) ($grade['code'] ?? ''), $labelLong, $labelShort);
        Session::flash('success', 'Référentiel de la communauté mis à jour. Les autres communautés gardent le catalogue.');
        return Response::redirect($this->indexUrl($this->tabForSystemId($systemId)));
    }

    public function deactivate(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('login'));
        }
        if (!$request->isPost() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Requête invalide.');
            return Response::redirect(url('back-office/referentiels/grades'));
        }
        $id = (int) ($params['id'] ?? 0);
        $grade = $id ? $this->gradeRepository->findById($id) : null;
        if ($id < 1 || $grade === null) {
            Session::flash('error', 'Impossible de supprimer le grade.');
            return Response::redirect($this->indexUrl($this->tabForGrade($grade)));
        }
        $this->overrides->upsert($tenantId, $id, ['is_enabled' => false]);
        Session::flash('success', 'Grade masqué pour cette communauté. Le catalogue partagé est inchangé.');
        return Response::redirect($this->indexUrl($this->tabForGrade($grade)));
    }

    private function normalizeTab(string $tab): string
    {
        return in_array($tab, ['fr', 'us', 'otan', 'categories'], true) ? $tab : 'fr';
    }

    private function normalizeGradeTab(string $tab): string
    {
        return $tab === 'us' ? 'us' : 'fr';
    }

    /** @param array<string, mixed>|null $grade */
    private function tabForGrade(?array $grade): string
    {
        return strtoupper((string) ($grade['country_code'] ?? 'FR')) === 'US' ? 'us' : 'fr';
    }

    private function tabForSystemId(int $systemId): string
    {
        foreach ($this->gradeSystemRepository->listActive() as $system) {
            if ((int) ($system['id'] ?? 0) === $systemId) {
                return strtoupper((string) ($system['country_code'] ?? 'FR')) === 'US' ? 'us' : 'fr';
            }
        }

        return 'fr';
    }

    private function indexUrl(string $tab): string
    {
        return url('back-office/referentiels/grades') . '?tab=' . rawurlencode($this->normalizeGradeTab($tab));
    }

    /**
     * @param list<array<string, mixed>> $catalogRows
     * @return list<array<string, mixed>>
     */
    private function tenantExtras(int $tenantId, array $catalogRows): array
    {
        if (!$this->advancement->tablesReady()) {
            return [];
        }
        $catalog = [];
        foreach ($catalogRows as $row) {
            $code = strtoupper(trim((string) ($row['code'] ?? '')));
            if ($code !== '') {
                $catalog[$code] = true;
            }
        }
        $extras = [];
        foreach ($this->advancement->listGrades($tenantId, true) as $grade) {
            $code = strtoupper(trim((string) ($grade['code'] ?? '')));
            if ($code !== '' && isset($catalog[$code])) {
                continue;
            }
            $extras[] = $grade;
        }

        return $extras;
    }

    private function filiereFromCategory(int $tenantId, int $categoryId): ?int
    {
        $category = null;
        foreach ($this->gradeCategoryRepository->listActive() as $row) {
            if ((int) ($row['id'] ?? 0) === $categoryId) {
                $category = $row;
                break;
            }
        }
        if ($category === null) {
            return null;
        }
        $code = strtolower(trim((string) ($category['code'] ?? 'general'))) ?: 'general';
        foreach ($this->advancement->listFilieres($tenantId) as $filiere) {
            if (strtolower((string) ($filiere['code'] ?? '')) === $code) {
                return (int) $filiere['id'];
            }
        }

        return $this->advancement->saveFiliere($tenantId, [
            'code' => substr($code, 0, 40),
            'label' => (string) ($category['label'] ?? $code),
            'sort_order' => (int) ($category['sort_order'] ?? 0),
        ]);
    }

    private function syncAdvancementLabel(int $tenantId, string $code, string $label, string $short): void
    {
        if ($code === '' || !$this->advancement->tablesReady()) {
            return;
        }
        $existing = $this->advancement->findGradeByCode($tenantId, $code);
        if ($existing === null) {
            return;
        }
        $this->advancement->saveGrade($tenantId, array_merge($existing, [
            'label' => $label,
            'short_label' => $short,
        ]), (int) $existing['id']);
    }
}
