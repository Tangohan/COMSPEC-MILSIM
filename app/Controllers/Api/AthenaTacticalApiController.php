<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AthenaTacticalRepository;
use App\Support\ComspecApiKeyAuth;

/**
 * Marqueurs de la carte tactique Athena. Lecture : session du portail (marqueurs publiés) ou clé communauté du jeu.
 * Écriture (création, modification, retrait) et brouillons : seulement ceux qui tiennent l'éditeur de la carte
 * (admin.system, admin.organization ou admin.access, comme la page Opérations › Carte tactique), avec le jeton CSRF
 * en en-tête X-CSRF-Token. Avant, n'importe quel membre connecté pouvait écrire.
 */
final class AthenaTacticalApiController
{
    /** @var (callable(): bool)|null Droit d'éditer (tests) ; null : droits Gate de la session. */
    private $editGate;

    public function __construct(private ?AthenaTacticalRepository $repository = null)
    {
        $this->repository ??= new AthenaTacticalRepository();
    }

    /** Tests : impose la décision du droit d'éditer. */
    public function setEditGate(?callable $gate): void
    {
        $this->editGate = $gate;
    }

    private function canEdit(): bool
    {
        if ($this->editGate !== null) {
            return (bool) ($this->editGate)();
        }
        if ((int) Session::get('user_id', 0) < 1) {
            return false;
        }
        (new \App\Services\Sse\SseAccessCodeService())->ensureGateHydrated();
        $gate = Gate::getInstance();

        return $gate->allows('admin.system') || $gate->allows('admin.organization') || $gate->allows('admin.access');
    }

    /** Écriture : session de communauté, droit d'éditeur, jeton CSRF. */
    private function writeGuard(Request $request): int|Response
    {
        $tenant = $this->webTenant();
        if ($tenant instanceof Response) {
            return $tenant;
        }
        if (!$this->canEdit()) {
            return Response::json(['error' => 'forbidden', 'message' => 'Réservé aux éditeurs de la carte tactique.'], 403);
        }
        if (!Csrf::validate($request->header('X-CSRF-Token'))) {
            return Response::json(['error' => 'csrf', 'message' => 'Session expirée. Rechargez la page.'], 419);
        }

        return $tenant;
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenant = $this->tenant();
        if ($tenant instanceof Response) {
            return $tenant;
        }
        $world = $this->world($request);

        // Les brouillons ne sont montrés qu'aux éditeurs de la carte.
        return Response::json([
            'data' => $this->repository->markers($tenant, $world, $this->isWeb() && $this->canEdit()),
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
        $tenant = $this->writeGuard($request);
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
        $tenant = $this->writeGuard($request);
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
        $tenant = $this->writeGuard($request);
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
