<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AtakIntelEventRepository;
use App\Support\ComspecApiKeyAuth;

final class AtakIntelEventApiController
{
    private const DEFAULT_MAP_ID = 1;

    public function __construct(private ?AtakIntelEventRepository $events = null)
    {
        $this->events ??= new AtakIntelEventRepository();
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = $this->resolveTenantId($request);
        if ($tenantId < 1) {
            return Response::json([
                'ok' => false,
                'error' => 'tenant_context_required',
                'message' => 'Communauté non identifiée.',
            ], 403);
        }
        $raw = $request->query('mapId');
        $mapId = ($raw !== null && $raw !== '') ? (int) $raw : self::DEFAULT_MAP_ID;
        if ($mapId < 1) {
            $mapId = self::DEFAULT_MAP_ID;
        }
        $limit = (int) ($request->query('limit') ?? 80);
        $unitRef = trim((string) ($request->query('unit') ?? $request->query('call_sign') ?? ''));
        $rows = $this->events->listRecent($tenantId, $mapId, $limit, $unitRef !== '' ? $unitRef : null);

        return Response::json(['ok' => true, 'events' => $rows]);
    }

    private function resolveTenantId(Request $request): int
    {
        $matched = ComspecApiKeyAuth::matchedTenantId();
        if ($matched !== null && $matched > 0) {
            return $matched;
        }
        $sid = Session::get('tenant_id');
        if ($sid !== null && $sid !== '') {
            $n = (int) $sid;

            return $n > 0 ? $n : 0;
        }

        return 0;
    }
}
