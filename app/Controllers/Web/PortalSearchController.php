<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Portal\PortalSearchService;

final class PortalSearchController
{
    public function __construct(
        private PortalSearchService $portalSearchService,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) > PortalSearchService::MAX_QUERY_LEN) {
            $q = mb_substr($q, 0, PortalSearchService::MAX_QUERY_LEN);
        }

        $gate = Gate::getInstance();

        return Response::view('layout.main', [
            'title' => 'Recherche portail',
            'content' => 'portal.search',
            'query' => $q,
            'canSearchDocuments' => !$gate->deny('documents.view'),
            'canSearchPersonnel' => $gate->allows('personnel.profile.view'),
        ]);
    }

    public function apiSearch(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId < 1 || $userId < 1) {
            return Response::json(['success' => false, 'error' => 'Non authentifié'], 401);
        }

        $raw = trim((string) $request->query('q', ''));
        $scopes = [
            'documents' => $this->queryFlag($request, 'documents', true),
            'forum' => $this->queryFlag($request, 'forum', true),
            'personnel' => $this->queryFlag($request, 'personnel', true),
            'events' => $this->queryFlag($request, 'events', true),
            'training' => $this->queryFlag($request, 'training', true),
            'commands' => $this->queryFlag($request, 'commands', true),
        ];

        $payload = $this->portalSearchService->search(
            $tenantId,
            $userId,
            $raw,
            Gate::getInstance(),
            $scopes
        );

        return Response::json(array_merge(['success' => true], $payload));
    }

    private function queryFlag(Request $request, string $key, bool $default): bool
    {
        $v = $request->query($key);
        if ($v === null || $v === '') {
            return $default;
        }

        return in_array((string) $v, ['1', 'true', 'yes', 'on'], true);
    }
}
