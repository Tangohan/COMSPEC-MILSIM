<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\GradeDefinitionRepository;
use App\Repositories\GradeFiliereDefinitionRepository;
use App\Repositories\QualificationDefinitionRepository;
use App\Services\Personnel\GradeScaleSeedService;

final class AdvancementGradeController
{
    public function __construct(
        private GradeDefinitionRepository $grades,
        private GradeFiliereDefinitionRepository $filieres,
        private QualificationDefinitionRepository $qualifications,
        private GradeScaleSeedService $seeds,
    ) {
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantIdOrRedirect();
        if ($tenantId instanceof Response) {
            return $tenantId;
        }

        return Response::view('layout.main', [
            'content' => 'admin.organization.advancement.grades_index',
            'title' => 'Échelle de grades',
            'isBackOfficeShell' => true,
            'boPageTitle' => 'Échelle de grades',
            'boPageKicker' => 'ORGANISATION · GRADES',
            'boPageSubtitle' => 'Référentiel de la communauté : ordre, voies d’avancement, temps mini et qualification requise.',
            'grades' => $this->grades->listForTenant($tenantId, true),
            'filieres' => $this->filieres->listForTenant($tenantId),
            'templates' => GradeScaleSeedService::templates(),
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error'),
        ]);
    }

    public function create(Request $request, array $params = []): Response
    {
        return $this->form($request, null);
    }

    public function edit(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantIdOrRedirect();
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $id = (int) ($params['id'] ?? 0);
        $grade = $this->grades->find($tenantId, $id);
        if ($grade === null) {
            Session::flash('error', 'Grade introuvable.');

            return Response::redirect(url('back-office/organisation/grades'));
        }

        return $this->form($request, $grade);
    }

    public function store(Request $request, array $params = []): Response
    {
        $tenantId = $this->requirePost($request, 'back-office/organisation/grades/create');
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $payload = $this->payload($request);
        if ($payload['code'] === '' || $payload['label'] === '') {
            Session::flash('error', 'Le code et le libellé sont obligatoires.');

            return Response::redirect(url('back-office/organisation/grades/create'));
        }
        if ($this->grades->findByCode($tenantId, $payload['code']) !== null) {
            Session::flash('error', 'Ce code est déjà utilisé dans votre communauté.');

            return Response::redirect(url('back-office/organisation/grades/create'));
        }
        $this->grades->create($tenantId, $payload);
        Session::flash('success', 'Grade créé.');

        return Response::redirect(url('back-office/organisation/grades'));
    }

    public function update(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $tenantId = $this->requirePost($request, 'back-office/organisation/grades/' . $id . '/edit');
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $this->grades->update($tenantId, $id, $this->payload($request));
        Session::flash('success', 'Grade enregistré.');

        return Response::redirect(url('back-office/organisation/grades'));
    }

    public function archive(Request $request, array $params = []): Response
    {
        $tenantId = $this->requirePost($request, 'back-office/organisation/grades');
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $this->grades->archive($tenantId, (int) ($params['id'] ?? 0));
        Session::flash('success', 'Grade archivé. L’historique des personnels est conservé.');

        return Response::redirect(url('back-office/organisation/grades'));
    }

    public function storeFiliere(Request $request, array $params = []): Response
    {
        $tenantId = $this->requirePost($request, 'back-office/organisation/grades');
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $code = strtoupper(trim((string) $request->input('code', '')));
        $label = trim((string) $request->input('label', ''));
        if ($code === '' || $label === '') {
            Session::flash('error', 'Code et libellé de filière obligatoires.');

            return Response::redirect(url('back-office/organisation/grades'));
        }
        $this->filieres->create($tenantId, $code, $label, (int) $request->input('sort_order', 0));
        Session::flash('success', 'Filière ajoutée.');

        return Response::redirect(url('back-office/organisation/grades'));
    }

    public function seed(Request $request, array $params = []): Response
    {
        $tenantId = $this->requirePost($request, 'back-office/organisation/grades');
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $template = (string) $request->input('template_key', GradeScaleSeedService::TEMPLATE_GENERIC);
        $onlyIfEmpty = $request->input('force') !== '1';
        $out = $this->seeds->seedForTenant($tenantId, $template, $onlyIfEmpty);
        if (!empty($out['skipped']) && $onlyIfEmpty) {
            Session::flash('error', 'Une échelle existe déjà. Cochez « remplacer les manquants » pour compléter.');
        } else {
            Session::flash('success', $out['grades'] . ' grade(s) et ' . $out['filieres'] . ' filière(s) importés.');
        }

        return Response::redirect(url('back-office/organisation/grades'));
    }

    public function reorder(Request $request, array $params = []): Response
    {
        $tenantId = $this->requirePost($request, 'back-office/organisation/grades');
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $ids = $request->input('order', '');
        $list = is_array($ids) ? $ids : preg_split('/[,\s]+/', (string) $ids) ?: [];
        $this->grades->reorder($tenantId, array_map('intval', $list));
        Session::flash('success', 'Ordre hiérarchique enregistré.');

        return Response::redirect(url('back-office/organisation/grades'));
    }

    /** @param array<string, mixed>|null $grade */
    private function form(Request $request, ?array $grade): Response
    {
        $tenantId = $this->tenantIdOrRedirect();
        if ($tenantId instanceof Response) {
            return $tenantId;
        }

        return Response::view('layout.main', [
            'content' => 'admin.organization.advancement.grade_form',
            'title' => $grade ? 'Modifier le grade' : 'Nouveau grade',
            'isBackOfficeShell' => true,
            'boPageTitle' => $grade ? 'Modifier le grade' : 'Nouveau grade',
            'boPageKicker' => 'ORGANISATION · GRADES',
            'grade' => $grade,
            'filieres' => $this->filieres->listForTenant($tenantId),
            'qualifications' => $this->qualifications->schemaReady()
                ? $this->qualifications->listForTenant($tenantId)
                : [],
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(Request $request): array
    {
        return [
            'code' => strtoupper(trim((string) $request->input('code', ''))),
            'label' => trim((string) $request->input('label', '')),
            'short_label' => trim((string) $request->input('short_label', '')),
            'filiere_id' => (int) $request->input('filiere_id', 0),
            'rank_order' => (int) $request->input('rank_order', 0),
            'advancement_seniority_enabled' => $request->input('advancement_seniority_enabled') ? 1 : 0,
            'advancement_choice_enabled' => $request->input('advancement_choice_enabled') ? 1 : 0,
            'min_time_in_previous_grade_months' => $request->input('min_time_in_previous_grade_months'),
            'required_qualification_id' => (int) $request->input('required_qualification_id', 0),
            'required_qualification_level_id' => (int) $request->input('required_qualification_level_id', 0),
        ];
    }

    private function tenantIdOrRedirect(): int|Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId <= 0) {
            return Response::redirect(url('login'));
        }

        return $tenantId;
    }

    private function requirePost(Request $request, string $fallback): int|Response
    {
        $tenantId = $this->tenantIdOrRedirect();
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        if (!Csrf::validate((string) $request->input('_csrf_token', ''))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url($fallback));
        }

        return $tenantId;
    }
}
