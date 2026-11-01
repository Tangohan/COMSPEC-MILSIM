<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\CompetencyGradeRequirementRepository;
use App\Repositories\CompetencyLinkRepository;
use App\Repositories\GradeRepository;
use App\Services\Training\CompetencyCatalogService;
use App\Services\Training\CompetencyPathwayGenerator;

/**
 * Référentiel des compétences : chaque compétence d'un palier de formation, le grade auquel elle est attendue,
 * le niveau visé, la façon dont elle est validée et les modules où elle est utilisée (Academy, modules de compétence,
 * qualifications, emplois et postes ORBAT, dotation).
 */
class CompetencyMatrixController
{
    /** @var list<string> */
    public const ACQUISITION_LEVELS = ['Basique', 'Intermédiaire', 'Avancé', 'Spécifique'];

    public function __construct(
        private CompetencyGradeRequirementRepository $requirements,
        private GradeRepository $gradeRepository,
        private ?CompetencyLinkRepository $links = null,
        private ?CompetencyCatalogService $catalog = null,
    ) {
        $this->links ??= \App\Core\Container::get(CompetencyLinkRepository::class);
        $this->catalog ??= \App\Core\Container::get(CompetencyCatalogService::class);
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $grades = $this->gradeRepository->listForTenant($tenantId);
        $gradeLabels = [];
        foreach ($grades as $g) {
            $gradeLabels[(int) $g['id']] = (string) ($g['label_long'] ?? $g['label_short'] ?? '');
        }
        $catalog = $this->catalog->build($tenantId, true, $gradeLabels);

        $byPalier = [];
        foreach ($catalog['competencies'] as $c) {
            $c['resolved_links'] = CompetencyPathwayGenerator::resolveLinks($c, $catalog);
            $c['suggested_links'] = CompetencyPathwayGenerator::suggestLinks($c, $catalog);
            $byPalier[$c['palier']][] = $c;
        }

        return Response::view('layout.main', [
            'content' => 'admin.organization.referentiels.competency_matrix',
            'title' => 'Référentiel des compétences',
            'competencyByPalier' => $byPalier,
            'competencyGrades' => $grades,
            'competencyAcquisitionLevels' => self::ACQUISITION_LEVELS,
            'competencyCatalog' => $catalog,
            'competencyUsage' => $this->catalog->usage($tenantId),
            'competencyLinksReady' => $this->links->schemaReady() && $this->requirements->explanationReady(),
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        if (!$request->isPost() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/referentiels/competences'));
        }
        $palier = trim((string) $request->input('palier', ''));
        $label = trim((string) $request->input('label', ''));
        if ($palier === '' || $label === '') {
            Session::flash('error', 'Le palier et le nom de la compétence sont requis.');

            return Response::redirect(url('back-office/referentiels/competences'));
        }
        $gradeId = (int) $request->input('grade_id', 0);
        $level = trim((string) $request->input('acquisition_level', ''));
        $userId = (int) (Session::get('user_id') ?? 0);
        $id = $this->requirements->create($tenantId, [
            'palier' => mb_substr($palier, 0, 120),
            'palier_order' => max(0, (int) $request->input('palier_order', 0)),
            'label' => mb_substr($label, 0, 255),
            'grade_id' => $gradeId > 0 ? $gradeId : null,
            'acquisition_level' => in_array($level, self::ACQUISITION_LEVELS, true) ? $level : null,
        ] + $this->explanationInput($request), $userId ?: null);
        $this->links->replaceForRequirement($tenantId, $id, $this->linkInput($request));
        Session::flash('success', 'Compétence ajoutée au référentiel.');

        return Response::redirect(url('back-office/referentiels/competences') . '#competence-' . $id);
    }

    public function update(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        if (!$request->isPost() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/referentiels/competences'));
        }
        $id = (int) ($params['id'] ?? 0);
        if (!$this->requirements->findByIdForTenant($id, $tenantId)) {
            Session::flash('error', 'Compétence introuvable.');

            return Response::redirect(url('back-office/referentiels/competences'));
        }
        $palier = trim((string) $request->input('palier', ''));
        $label = trim((string) $request->input('label', ''));
        if ($palier === '' || $label === '') {
            Session::flash('error', 'Le palier et le nom de la compétence sont requis.');

            return Response::redirect(url('back-office/referentiels/competences'));
        }
        $gradeId = (int) $request->input('grade_id', 0);
        $level = trim((string) $request->input('acquisition_level', ''));
        $this->requirements->update($id, $tenantId, [
            'palier' => mb_substr($palier, 0, 120),
            'palier_order' => max(0, (int) $request->input('palier_order', 0)),
            'label' => mb_substr($label, 0, 255),
            'grade_id' => $gradeId > 0 ? $gradeId : null,
            'acquisition_level' => in_array($level, self::ACQUISITION_LEVELS, true) ? $level : null,
        ] + $this->explanationInput($request));
        $this->links->replaceForRequirement($tenantId, $id, $this->linkInput($request));
        Session::flash('success', 'Compétence modifiée.');

        return Response::redirect(url('back-office/referentiels/competences') . '#competence-' . $id);
    }

    public function destroy(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        if (!$request->isPost() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/referentiels/competences'));
        }
        $id = (int) ($params['id'] ?? 0);
        $deleted = $this->requirements->delete($id, $tenantId);
        if ($deleted) {
            $this->links->deleteForRequirement($tenantId, $id);
        }
        Session::flash($deleted ? 'success' : 'error', $deleted ? 'Compétence retirée du référentiel.' : 'Compétence introuvable.');

        return Response::redirect(url('back-office/referentiels/competences'));
    }

    /** @return array{description: ?string, validation_mode: ?string} */
    private function explanationInput(Request $request): array
    {
        $description = trim((string) $request->input('description', ''));
        $mode = (string) $request->input('validation_mode', '');

        return [
            'description' => $description !== '' ? mb_substr($description, 0, 2000) : null,
            'validation_mode' => isset(CompetencyPathwayGenerator::VALIDATION_MODES[$mode]) ? $mode : null,
        ];
    }

    /** @return array<string, list<string>> références cochées par type de lien */
    private function linkInput(Request $request): array
    {
        $raw = $request->input('links', []);
        $out = [];
        foreach (array_keys(CompetencyPathwayGenerator::LINK_TYPES) as $type) {
            $refs = is_array($raw) && isset($raw[$type]) ? (array) $raw[$type] : [];
            $out[$type] = array_values(array_filter(array_map(static fn (mixed $v): string => trim((string) $v), $refs), static fn (string $v): bool => $v !== ''));
        }

        return $out;
    }
}
