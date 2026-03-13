<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\DocumentRepository;

class DocumentsController
{
    public function __construct(
        private DocumentRepository $documentRepository
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $docs = $this->documentRepository->listForTenant((int) $tenantId);
        return Response::view('layout.main', [
            'content' => 'documents.index',
            'title' => 'Documents',
            'documents' => $docs,
        ]);
    }

    public function download(Request $request, array $params = []): Response
    {
        $tenantId = Session::get('tenant_id');
        if (!$tenantId) {
            return (new Response())->setStatusCode(403)->setBody('Non autorisé');
        }
        $id = (int) ($params['id'] ?? 0);
        $doc = $this->documentRepository->findById($id, (int) $tenantId);
        if (!$doc || empty($doc['file_path'])) {
            return (new Response())->setStatusCode(404)->setBody('Document non trouvé');
        }
        $fullPath = base_path('storage/uploads/' . $doc['file_path']);
        if (!is_file($fullPath)) {
            return (new Response())->setStatusCode(404)->setBody('Fichier absent');
        }
        $response = new Response();
        $response->header('Content-Type', $doc['mime_type'] ?: 'application/octet-stream');
        $response->header('Content-Disposition', 'attachment; filename="' . basename($doc['file_path']) . '"');
        $response->setBody((string) file_get_contents($fullPath));
        return $response;
    }
}
