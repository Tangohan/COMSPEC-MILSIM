<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Gate;
use App\Repositories\TrainingPublicationRepository;
use App\Repositories\TrainingPublicationRevisionRepository;

class AdminTrainingPublicationController
{
    public function __construct(
        private TrainingPublicationRepository $publicationRepository,
        private TrainingPublicationRevisionRepository $revisionRepository,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        if (!$this->canAccess()) {
            return Response::redirect(url('formation'));
        }

        $tenantId = $this->tenantId();
        $rows = $this->publicationRepository->listByTenant($tenantId, 200);

        return Response::view('layout.training_lms_staff_shell', [
            'content' => 'admin.training.publications',
            'title' => 'Back-office publications formation',
            'trainingAdminNav' => 'publications',
            'activeNav' => 'publications',
            'publicationRows' => $rows,
            'totalModules' => count($rows),
        ]);
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

        return Response::view('layout.training_lms_staff_shell', [
            'content' => 'admin.training.publications_changelog',
            'title' => 'Change log publication #' . $id,
            'trainingAdminNav' => 'publications',
            'activeNav' => 'publications',
            'publication' => $publication,
            'revisions' => $revisions,
            'totalModules' => count($revisions),
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
