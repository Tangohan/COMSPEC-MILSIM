<?php

declare(strict_types=1);

namespace App\Controllers\Courrier;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\Courrier\CourrierDocumentRepository;
use App\Services\Audit\AuditService;
use App\Services\Courrier\DocumentExportService;

class CourrierPdfController
{
    public function __construct(
        private CourrierDocumentRepository $documentRepository,
        private DocumentExportService $exportService,
        private AuditService $auditService = new AuditService()
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
        $html = $this->exportService->buildPrintHtml($document, $context, ['export_mode' => 'internal']);

        return (new Response())->setBody($html);
    }

    /**
     * GET courrier/documents/{id}/pdf — PDF diffusion interne (caviardage visuel conservé).
     */
    public function pdf(Request $request, array $params = []): Response
    {
        return $this->streamPdf($params, 'internal');
    }

    /**
     * GET courrier/documents/{id}/pdf-external — PDF avec caviardage irréversible ([[REDACT]]).
     */
    public function pdfExternal(Request $request, array $params = []): Response
    {
        return $this->streamPdf($params, 'external');
    }

    /**
     * @param 'internal'|'external' $mode
     */
    private function streamPdf(array $params, string $mode): Response
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

        $context = ['user_id' => $userId, 'tenant_id' => $tenantId, 'document' => $document];
        $binary = $this->exportService->renderPdfBinary($document, $context, ['export_mode' => $mode]);
        if ($binary === null) {
            Session::flash('error', 'Génération PDF indisponible. Installez les dépendances : composer install (dompdf/dompdf).');
            return Response::redirect(url('courrier/editor/' . $id));
        }

        $this->auditService->log(
            $mode === 'external' ? 'courrier.pdf_export_external' : 'courrier.pdf_export_internal',
            $tenantId,
            $userId,
            'courrier_document',
            $id
        );

        $suffix = $mode === 'external' ? '-externe' : '';
        $filename = 'courrier-' . $id . $suffix . '.pdf';

        return (new Response())
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($binary);
    }
}
