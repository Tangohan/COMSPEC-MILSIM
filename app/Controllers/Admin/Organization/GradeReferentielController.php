<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\GradeCategoryRepository;
use App\Repositories\GradeRepository;
use App\Repositories\GradeSystemRepository;
use App\Services\GradeDisplayService;

class GradeReferentielController
{
    public function __construct(
        private GradeRepository $gradeRepository,
        private GradeCategoryRepository $gradeCategoryRepository,
        private GradeSystemRepository $gradeSystemRepository,
        private GradeDisplayService $gradeDisplayService
    ) {
    }

    public function index(Request $request, array $params = []): Response
    {
        if (!(int) Session::get('tenant_id')) {
            return Response::redirect(url('login'));
        }
        $tab = $request->input('tab') ?: 'fr';
        $categoryFilter = (int) $request->query('categorie', 0);
        $categoryFilter = $categoryFilter > 0 ? $categoryFilter : null;
        $systems = $this->gradeSystemRepository->listActive();
        $categories = $this->gradeCategoryRepository->listActive();
        $gradesFr = $this->gradeRepository->listBySystemCodeAndCategoryId('FR_CLASSIC', $categoryFilter);
        $gradesUs = $this->gradeRepository->listBySystemCodeAndCategoryId('US_CLASSIC', $categoryFilter);
        $allGrades = $this->gradeRepository->listActive();

        return Response::view('layout.main', [
            'content' => 'admin.organization.referentiels.grades.index',
            'title' => 'Référentiel des grades',
            'tab' => $tab,
            'systems' => $systems,
            'categories' => $categories,
            'gradesFr' => $gradesFr,
            'gradesUs' => $gradesUs,
            'allGrades' => $allGrades,
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
        return Response::view('layout.main', [
            'content' => 'admin.organization.referentiels.grades.form',
            'title' => 'Nouveau grade',
            'grade' => null,
            'systems' => $systems,
            'categories' => $categories,
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        if (!(int) Session::get('tenant_id')) {
            return Response::redirect(url('login'));
        }
        if (!$request->isPost() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Requête invalide.');
            return Response::redirect(url('back-office/referentiels/grades/create'));
        }
        $systemId = (int) $request->input('grade_system_id');
        $categoryId = (int) $request->input('grade_category_id');
        $code = trim((string) $request->input('code'));
        $labelShort = trim((string) $request->input('label_short'));
        $labelLong = trim((string) $request->input('label_long'));
        if ($code === '' || $labelShort === '' || $labelLong === '' || !$systemId || !$categoryId) {
            Session::flash('error', 'Code, libellé court, libellé long, système et catégorie sont requis.');
            return Response::redirect(url('back-office/referentiels/grades/create'));
        }
        $this->gradeRepository->create([
            'grade_system_id' => $systemId,
            'grade_category_id' => $categoryId,
            'code' => $code,
            'label_short' => $labelShort,
            'label_long' => $labelLong,
            'label_otan' => trim((string) $request->input('label_otan')) ?: null,
            'sort_order' => (int) $request->input('sort_order'),
            'is_commissioned' => $request->input('is_commissioned') ? 1 : 0,
            'is_active' => 1,
        ]);
        Session::flash('success', 'Grade créé.');
        return Response::redirect(url('back-office/referentiels/grades'));
    }

    public function edit(Request $request, array $params = []): Response
    {
        if (!(int) Session::get('tenant_id')) {
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
        return Response::view('layout.main', [
            'content' => 'admin.organization.referentiels.grades.form',
            'title' => 'Modifier le grade',
            'grade' => $grade,
            'systems' => $systems,
            'categories' => $categories,
        ]);
    }

    public function update(Request $request, array $params = []): Response
    {
        if (!(int) Session::get('tenant_id')) {
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
        $systemId = (int) $request->input('grade_system_id');
        $categoryId = (int) $request->input('grade_category_id');
        $code = trim((string) $request->input('code'));
        $labelShort = trim((string) $request->input('label_short'));
        $labelLong = trim((string) $request->input('label_long'));
        if ($code === '' || $labelShort === '' || $labelLong === '' || !$systemId || !$categoryId) {
            Session::flash('error', 'Code, libellé court, libellé long, système et catégorie sont requis.');
            return Response::redirect(url('back-office/referentiels/grades/' . $id . '/edit'));
        }
        $this->gradeRepository->update($id, [
            'grade_system_id' => $systemId,
            'grade_category_id' => $categoryId,
            'code' => $code,
            'label_short' => $labelShort,
            'label_long' => $labelLong,
            'label_otan' => trim((string) $request->input('label_otan')) ?: null,
            'sort_order' => (int) $request->input('sort_order'),
            'is_commissioned' => $request->input('is_commissioned') ? 1 : 0,
            'is_active' => $request->input('is_active') ? 1 : 0,
        ]);
        Session::flash('success', 'Grade mis à jour.');
        return Response::redirect(url('back-office/referentiels/grades'));
    }

    public function deactivate(Request $request, array $params = []): Response
    {
        if (!(int) Session::get('tenant_id')) {
            return Response::redirect(url('login'));
        }
        if (!$request->isPost() || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Requête invalide.');
            return Response::redirect(url('back-office/referentiels/grades'));
        }
        $id = (int) ($params['id'] ?? 0);
        if ($id && $this->gradeRepository->setActive($id, false)) {
            Session::flash('success', 'Grade désactivé.');
        } else {
            Session::flash('error', 'Impossible de désactiver le grade.');
        }
        return Response::redirect(url('back-office/referentiels/grades'));
    }
}
