<?php

declare(strict_types=1);

namespace App\Controllers\Courrier;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\Courrier\CourrierDocumentNotificationRepository;
use App\Repositories\Courrier\CourrierDocumentRepository;
use App\Repositories\UserRepository;
use App\Services\Courrier\DocumentBuilderService;
use App\Services\Courrier\DocumentValidationService;
use App\Services\Courrier\DocumentWorkflowService;

/**
 * Page dédiée à la lecture d'un courrier (sans édition).
 */
class CourrierReadController
{
    public function __construct(
        private CourrierDocumentRepository $documentRepository,
        private DocumentBuilderService $builderService,
        private UserRepository $userRepository,
        private CourrierDocumentNotificationRepository $notificationRepository,
        private DocumentWorkflowService $workflowService,
        private DocumentValidationService $validationService
    ) {
    }

    public function show(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        $userId = (int) (Session::get('user_id') ?? 0);
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }

        $document = $this->documentRepository->findById($id, $tenantId);
        if (!$document) {
            Session::flash('error', 'Document introuvable.');
            return Response::redirect(url('courrier'));
        }

        $this->notificationRepository->markReadForDocument($tenantId, $userId, $id);

        $context = ['user_id' => $userId, 'tenant_id' => $tenantId, 'document' => $document];
        $previewHtml = $this->builderService->buildPreviewHtml($document, $context);
        $tenantUsers = $this->userRepository->listForTenant($tenantId, null, null, null, 300, 0);
        $versions = array_slice($this->documentRepository->getVersions($id), 0, 10);
        $workflowHistory = array_slice($this->workflowService->getHistory($id), 0, 10);
        $alerts = $this->validationService->validate($document, $context, []);

        return Response::view('layout.main', [
            'title' => ($document['title'] ?: 'Sans titre') . ' — Lecture — Bureau Courrier',
            'content' => 'courrier/read',
            'courrier' => [
                'document' => $document,
                'preview_html' => $previewHtml,
                'tenant_users' => $tenantUsers,
                'versions' => $versions,
                'workflow_history' => $workflowHistory,
                'alerts' => $alerts,
            ],
        ]);
    }
}
