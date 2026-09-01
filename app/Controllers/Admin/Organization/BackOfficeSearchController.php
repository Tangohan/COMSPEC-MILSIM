<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Portal\BackOfficeSearchService;

final class BackOfficeSearchController
{
    public function __construct(
        private BackOfficeSearchService $search,
    ) {}

    public function api(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId < 1 || $userId < 1) {
            return Response::json(['success' => false, 'error' => 'Non authentifié'], 401);
        }

        $raw = trim((string) $request->query('q', ''));
        $payload = $this->search->search($tenantId, $raw, Gate::getInstance());

        return Response::json(array_merge(['success' => true], $payload));
    }
}
