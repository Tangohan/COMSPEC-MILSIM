<?php

declare(strict_types=1);

namespace App\Controllers\Courrier;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\Courrier\CourrierDocumentRepository;
use App\Services\Courrier\DocumentWorkflowService;

class CourrierWorkflowController
{
    public function __construct(
        private CourrierDocumentRepository $documentRepository,
        private DocumentWorkflowService $workflowService
    ) {
    }

    /**
     * POST courrier/documents/{id}/workflow — transition (submit_validation, validate, reject, sign, send, archive)
     */
    public function transition(Request $request, array $params = []): Response
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

        $action = trim((string) ($request->input('action') ?? ''));
        $toStatus = match ($action) {
            'submit_validation' => DocumentWorkflowService::STATUS_PENDING_VALIDATION,
            'validate' => DocumentWorkflowService::STATUS_VALIDATED,
            'reject' => DocumentWorkflowService::STATUS_REJECTED,
            'sign' => DocumentWorkflowService::STATUS_SIGNED,
            'send' => DocumentWorkflowService::STATUS_SENT,
            'archive' => DocumentWorkflowService::STATUS_ARCHIVED,
            default => null,
        };

        if ($toStatus === null) {
            Session::flash('error', 'Action inconnue.');
            return Response::redirect(url('courrier/editor/' . $id));
        }

        $comment = $request->input('comment') ? trim((string) $request->input('comment')) : null;
        $ok = $this->workflowService->transition($id, $toStatus, $userId, $comment, $tenantId);
        if (!$ok) {
            Session::flash('error', 'Transition impossible (statut ou validation).');
        } else {
            Session::flash('success', 'Statut mis à jour.');
        }
        return Response::redirect(url('courrier/editor/' . $id));
    }
}
