<?php

declare(strict_types=1);

namespace App\Controllers\Courrier;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\Courrier\CourrierDocumentRepository;
use App\Services\Courrier\DocumentExportService;

class CourrierPdfController
{
    public function __construct(
        private CourrierDocumentRepository $documentRepository,
        private DocumentExportService $exportService
    ) {
    }

    /**
     * GET courrier/documents/{id}/print — page HTML pour impression
     */
    public function print(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $tenantId = (int) (Session::get('tenant_id') ?? 0);
        $userId = (int) (Session::get('user_id') ?? 0);
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }

        $document = $this->documentRepository->findById($id, $tenantId);
        if (!$document) {
            return (new Response())->setStatusCode(404)->setBody('Document introuvable.');
        }

        $context = ['user_id' => $userId, 'tenant_id' => $tenantId, 'document' => $document];
        $html = $this->exportService->buildPrintHtml($document, $context);
        return (new Response())->setBody($html);
    }
}
