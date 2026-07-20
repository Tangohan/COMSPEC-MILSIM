<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\CompetencyGradeRequirementRepository;
use App\Repositories\GradeRepository;

/**
 * Matrice de compétences × grades : catalogue de référence associant chaque compétence/module
 * d'un palier de formation au grade attendu et au niveau d'acquisition visé.
 */
class CompetencyMatrixController
{
    /** @var list<string> */
    public const ACQUISITION_LEVELS = ['Basique', 'Intermédiaire', 'Avancé', 'Spécifique'];

    public function __construct(
        private CompetencyGradeRequirementRepository $requirements,
        private GradeRepository $gradeRepository
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $rows = $this->requirements->listForTenant($tenantId);
        $grades = $this->gradeRepository->listForTenant($tenantId);
        $gradeLabels = [];
        foreach ($grades as $g) {
            $gradeLabels[(int) $g['id']] = (string) ($g['label_long'] ?? $g['label_short'] ?? '');
        }

        $byPalier = [];
        foreach ($rows as $r) {
            $byPalier[(string) $r['palier']][] = $r;
        }

        return Response::view('layout.main', [
            'content' => 'admin.organization.referentiels.competency_matrix',
            'title' => 'Matrice de compétences × grades',
            'competencyByPalier' => $byPalier,
            'competencyGrades' => $grades,
            'competencyGradeLabels' => $gradeLabels,
            'competencyAcquisitionLevels' => self::ACQUISITION_LEVELS,
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
        $this->requirements->create($tenantId, [
            'palier' => mb_substr($palier, 0, 120),
            'palier_order' => max(0, (int) $request->input('palier_order', 0)),
            'label' => mb_substr($label, 0, 255),
            'grade_id' => $gradeId > 0 ? $gradeId : null,
            'acquisition_level' => in_array($level, self::ACQUISITION_LEVELS, true) ? $level : null,
        ], $userId ?: null);
        Session::flash('success', 'Compétence ajoutée à la matrice.');

        return Response::redirect(url('back-office/referentiels/competences'));
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
        ]);
        Session::flash('success', 'Compétence modifiée.');

        return Response::redirect(url('back-office/referentiels/competences'));
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
        Session::flash($deleted ? 'success' : 'error', $deleted ? 'Compétence retirée de la matrice.' : 'Compétence introuvable.');

        return Response::redirect(url('back-office/referentiels/competences'));
    }
}
