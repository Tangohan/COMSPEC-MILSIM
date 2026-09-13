<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Doctrine\DocumentInboxService;

final class DocumentInboxController
{
    public function __construct(
        private DocumentInboxService $inboxService,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId < 1 || $userId < 1) {
            return Response::redirect(url('login'));
        }
        if (Gate::getInstance()->deny('documents.view') && Gate::getInstance()->deny('doctrine.view')) {
            return (new Response())->setStatusCode(403)->setBody('Accès refusé.');
        }

        $filter = trim((string) $request->input('filtre', 'a_lire'));
        $box = $this->inboxService->forUser($tenantId, $userId, $filter);

        return Response::view('layout.main', [
            'content' => 'documents/inbox',
            'title' => 'Mes documents',
            'filter' => $filter,
            'counts' => $box['counts'],
            'items' => $box['items'],
        ]);
    }
}
