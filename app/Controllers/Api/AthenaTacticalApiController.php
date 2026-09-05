<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AthenaTacticalRepository;
use App\Support\ComspecApiKeyAuth;

final class AthenaTacticalApiController
{
    public function __construct(private ?AthenaTacticalRepository $repository = null)
    {
        $this->repository ??= new AthenaTacticalRepository();
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenant = $this->tenant();
        if ($tenant instanceof Response) {
            return $tenant;
        }
        $world = $this->world($request);

        return Response::json([
            'data' => $this->repository->markers($tenant, $world, $this->isWeb()),
            'world_name' => $world,
        ]);
    }

    public function sync(Request $request, array $params = []): Response
    {
        $tenant = $this->tenant();
        if ($tenant instanceof Response) {
            return $tenant;
        }
        $world = $this->world($request);
        $cursor = max(0, (int) $request->query('cursor', 0));
        if ($cursor === 0) {
            $items = $this->repository->markers($tenant, $world, false);
            $next = 0;
            foreach ($items as $item) {
                $next = max($next, (int) $item['revision']);
            }

            return Response::json([
                'mode' => 'snapshot',
                'cursor' => $next,
                'items' => $items,
                'has_more' => false,
            ]);
        }
        $events = $this->repository->sync($tenant, $world, $cursor);
        $next = $cursor;
        foreach ($events as &$event) {
            $event['payload'] = json_decode((string) ($event['payload'] ?? ''), true);
            $next = max($next, (int) $event['revision']);
        }
        unset($event);

        return Response::json([
            'mode' => 'delta',
            'cursor' => $next,
            'events' => $events,
            'has_more' => count($events) === 500,
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        $tenant = $this->webTenant();
        if ($tenant instanceof Response) {
            return $tenant;
        }
        $body = ComspecApiKeyAuth::peekJsonObject();
        $error = $this->validate($body);
        if ($error) {
            return Response::json(['error' => 'validation', 'message' => $error], 422);
        }

        return Response::json(
            $this->repository->create($tenant, (int) Session::get('user_id', 0), $body),
            201
        );
    }

    public function update(Request $request, array $params = []): Response
    {
        $tenant = $this->webTenant();
        if ($tenant instanceof Response) {
            return $tenant;
        }
        $body = ComspecApiKeyAuth::peekJsonObject();
        $expected = (int) ($body['revision'] ?? 0);
        if ($expected < 1) {
            return Response::json(['error' => 'revision_required'], 428);
        }
        try {
            $row = $this->repository->update($tenant, (string) ($params['uuid'] ?? ''), $expected, $body);

            return $row ? Response::json($row) : Response::json(['error' => 'not_found'], 404);
        } catch (\DomainException) {
            return Response::json([
                'error' => 'revision_conflict',
                'current' => $this->repository->find($tenant, (string) ($params['uuid'] ?? '')),
            ], 409);
        }
    }

    public function delete(Request $request, array $params = []): Response
    {
        $tenant = $this->webTenant();
        if ($tenant instanceof Response) {
            return $tenant;
        }
        $body = ComspecApiKeyAuth::peekJsonObject();
        $expected = (int) ($body['revision'] ?? $request->query('revision', 0));
        if ($expected < 1) {
            return Response::json(['error' => 'revision_required'], 428);
        }
        try {
            return $this->repository->delete($tenant, (string) ($params['uuid'] ?? ''), $expected)
                ? Response::json(['ok' => true])
                : Response::json(['error' => 'not_found'], 404);
        } catch (\DomainException) {
            return Response::json([
                'error' => 'revision_conflict',
                'current' => $this->repository->find($tenant, (string) ($params['uuid'] ?? '')),
            ], 409);
        }
    }

    private function world(Request $r): string
    {
        $w = preg_replace('/[^A-Za-z0-9_.-]/', '', (string) $r->query('world_name', 'Altis'));

        return $w !== '' ? $w : 'Altis';
    }

    private function tenant(): int|Response
    {
        if ($this->isWeb()) {
            return (int) Session::get('tenant_id');
        }
        if (!ComspecApiKeyAuth::requestPresentsValidKey()) {
            return Response::json(['error' => 'unauthorized'], 401);
        }
        $id = ComspecApiKeyAuth::matchedTenantId() ?? (int) (getenv('ATAK_DEFAULT_TENANT_ID') ?: 0);

        return $id > 0 ? $id : Response::json(['error' => 'tenant_context_required'], 403);
    }

    private function webTenant(): int|Response
    {
        $id = (int) Session::get('tenant_id', 0);

        return $id > 0 ? $id : Response::json(['error' => 'authentication_required'], 401);
    }

    private function isWeb(): bool
    {
        return (int) Session::get('tenant_id', 0) > 0;
    }

    /** @param array<string, mixed> $b */
    private function validate(array $b): ?string
    {
        if (empty($b['world_name'])) {
            return 'world_name requis';
        }
        if (!isset($b['coordinates']) || !is_array($b['coordinates'])) {
            return 'coordinates doit être un tableau';
        }
        $allowed = ['POINT', 'LINESTRING', 'POLYGON', 'RECTANGLE', 'ELLIPSE', 'ROUTE', 'TEXT'];
        if (!in_array($b['geometry_type'] ?? 'POINT', $allowed, true)) {
            return 'geometry_type invalide';
        }

        return null;
    }
}
