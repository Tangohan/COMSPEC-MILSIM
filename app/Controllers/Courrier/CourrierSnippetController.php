<?php

declare(strict_types=1);

namespace App\Controllers\Courrier;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\Courrier\CourrierDocumentRepository;
use App\Services\Courrier\CourrierSnippetService;
use App\Services\Courrier\TemplateRenderService;

/**
 * API JSON — snippets pour l’éditeur Courrier.
 */
class CourrierSnippetController
{
    public function __construct(
        private CourrierSnippetService $snippetService,
        private TemplateRenderService $renderService,
        private CourrierDocumentRepository $documentRepository
    ) {
    }

    public function list(Request $request, array $params = []): Response
    {
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        if (!$tenantId) {
            return Response::json(['error' => 'Non autorisé'], 403);
        }

        $phase = $request->query('phase');
        $phase = is_string($phase) ? $phase : null;
        $userId = (int) (Session::get('user_id') ?? 0);
        $documentId = $request->query('document_id');
        $documentId = $documentId !== null && $documentId !== '' ? (int) $documentId : 0;

        $context = [
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'document' => [],
        ];
        if ($documentId > 0) {
            $doc = $this->documentRepository->findById($documentId, $tenantId);
            if ($doc) {
                $context['document'] = $doc;
            }
        }

        $snippets = $this->snippetService->listForEditor($phase);
        $rendered = [];
        foreach ($snippets as $s) {
            $rendered[] = [
                'code' => $s['code'],
                'label' => $s['label'],
                'phase' => $s['phase'],
                'html' => $this->snippetService->renderSnippetBody($s['code'], $context, $this->renderService),
            ];
        }

        return Response::json(['snippets' => $rendered]);
    }
}
