<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Gate;
use App\Repositories\TrainingCourseRepository;
use App\Repositories\TrainingPublicationRepository;
use App\Repositories\TrainingPublicationRevisionRepository;
use App\Services\TrainingPublication\TrainingPublicationService;

class AdminTrainingPublicationController
{
    public function __construct(
        private TrainingPublicationRepository $publicationRepository,
        private TrainingPublicationRevisionRepository $revisionRepository,
        private TrainingCourseRepository $trainingCourseRepository,
        private TrainingPublicationService $publicationService,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        if (!$this->canAccess()) {
            return Response::redirect(url('formation'));
        }

        $tenantId = $this->tenantId();
        $rows = $this->publicationRepository->listByTenant($tenantId, 200);
        $courseList = $this->trainingCourseRepository->listForTenant($tenantId, null);
        $totalModules = count($courseList);

        return Response::view('layout.training_lms_staff_shell', [
            'content' => 'admin.training.publications',
            'title' => 'Publications documentaires',
            'trainingAdminNav' => 'publications',
            'activeNav' => 'publications',
            'publicationRows' => $rows,
            'publicationCourses' => $courseList,
            'totalModules' => $totalModules,
        ]);
    }

    public function storeDraft(Request $request, array $params = []): Response
    {
        if (!$this->canAccess()) {
            return Response::redirect(url('formation'));
        }
        $redirectList = Response::redirect(training_lms_admin_url('publications'));
        if (!$request->isPost()) {
            return $redirectList;
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return $redirectList;
        }
        $tenantId = $this->tenantId();
        $userId = (int) (Session::get('user_id') ?? 0);
        if ($userId < 1) {
            Session::flash('error', 'Session utilisateur invalide.');

            return $redirectList;
        }
        $courseId = (int) $request->input('course_id', 0);
        $ref = trim((string) $request->input('document_reference', ''));
        try {
            $pub = $this->publicationService->createDraft($tenantId, $userId, [
                'course_id' => $courseId,
                'document_reference' => $ref !== '' ? $ref : null,
            ]);
            $newId = (int) ($pub['id'] ?? 0);
            Session::flash('success', 'Brouillon de publication créé. Vous pouvez suivre les révisions dans le journal.');

            return Response::redirect($newId > 0
                ? training_lms_admin_url('publications/' . $newId . '/changelog')
                : training_lms_admin_url('publications'));
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());

            return $redirectList;
        }
    }

    public function changelog(Request $request, array $params = []): Response
    {
        if (!$this->canAccess()) {
            return Response::redirect(url('formation'));
        }

        $tenantId = $this->tenantId();
        $id = (int) ($params['id'] ?? 0);
        $publication = $this->publicationRepository->findById($id, $tenantId);
        if (!$publication) {
            return Response::redirect(url('formation/publications'));
        }

        $revisions = $this->revisionRepository->listForPublication($id, $tenantId);
        $totalModules = count($this->trainingCourseRepository->listForTenant($tenantId, null));

        return Response::view('layout.training_lms_staff_shell', [
            'content' => 'admin.training.publications_changelog',
            'title' => 'Historique des versions (publication n°' . $id . ')',
            'trainingAdminNav' => 'publications',
            'activeNav' => 'publications',
            'publication' => $publication,
            'revisions' => $revisions,
            'totalModules' => $totalModules,
        ]);
    }

    private function canAccess(): bool
    {
        $gate = Gate::getInstance();

        return $gate->allows('training.publications.manage')
            || $gate->allows('training.publish')
            || $gate->allows('training.manage')
            || $gate->allows('admin.access');
    }

    private function tenantId(): int
    {
        return (int) (Session::get('tenant_id') ?? 0);
    }
}
