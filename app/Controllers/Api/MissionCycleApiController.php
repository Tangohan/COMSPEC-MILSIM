<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TheatreMissionCycleRepository;

/**
 * API légère cycle de mission (badge Tacmap / ATAK + actions TOC).
 */
final class MissionCycleApiController
{
    public function __construct(
        private ?TheatreMissionCycleRepository $cycles = null,
    ) {
        $this->cycles ??= new TheatreMissionCycleRepository();
    }

    public function current(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        if ($tenantId < 1) {
            return Response::json(['ok' => false, 'error' => 'Connexion requise.'], 401);
        }

        $mapId = max(1, (int) ($request->query('mapId') ?? $request->query('map_id') ?? 1));
        $row = $this->cycles->findCurrentForMap($tenantId, $mapId);

        return Response::json([
            'ok' => true,
            'mapId' => $mapId,
            'mission' => $row ? $this->cycles->present($row) : null,
        ]);
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        if ($tenantId < 1) {
            return Response::json(['ok' => false, 'error' => 'Connexion requise.'], 401);
        }

        $rows = $this->cycles->listForTenant($tenantId);
        $missions = array_map(fn (array $row) => $this->cycles->present($row), $rows);

        return Response::json([
            'ok' => true,
            'missions' => $missions,
        ]);
    }

    public function store(Request $request, array $params = []): Response
    {
        $tenantId = $this->tenantId();
        $userId = (int) Session::get('user_id');
        if ($tenantId < 1) {
            return Response::json(['ok' => false, 'error' => 'Connexion requise.'], 401);
        }
        if (!$this->csrfOk($request)) {
            return Response::json(['ok' => false, 'error' => 'Session expirée. Rechargez la page.'], 419);
        }

        $body = $this->jsonBody($request);
        $title = trim((string) ($body['title'] ?? $request->input('title', '')));
        $mapId = (int) ($body['mapId'] ?? $body['map_id'] ?? $request->input('map_id', 1));
        $result = $this->cycles->create($tenantId, $mapId, $title, $userId > 0 ? $userId : null);
        if (!$result['ok']) {
            return Response::json(['ok' => false, 'error' => $result['error'] ?? 'Création impossible.'], 422);
        }

        return Response::json([
            'ok' => true,
            'mission' => $this->cycles->present($result['mission']),
        ], 201);
    }

    public function open(Request $request, array $params = []): Response
    {
        return $this->mutate($request, $params, 'open');
    }

    public function close(Request $request, array $params = []): Response
    {
        return $this->mutate($request, $params, 'close');
    }

    private function mutate(Request $request, array $params, string $action): Response
    {
        $tenantId = $this->tenantId();
        $userId = (int) Session::get('user_id');
        if ($tenantId < 1) {
            return Response::json(['ok' => false, 'error' => 'Connexion requise.'], 401);
        }
        if (!$this->csrfOk($request)) {
            return Response::json(['ok' => false, 'error' => 'Session expirée. Rechargez la page.'], 419);
        }

        $id = (int) ($params['id'] ?? 0);
        if ($id < 1) {
            return Response::json(['ok' => false, 'error' => 'Mission introuvable.'], 404);
        }

        if ($action === 'open') {
            $result = $this->cycles->open($tenantId, $id);
        } else {
            $body = $this->jsonBody($request);
            $summary = trim((string) ($body['aar_summary'] ?? $request->input('aar_summary', '')));
            $result = $this->cycles->close(
                $tenantId,
                $id,
                $userId > 0 ? $userId : null,
                $summary !== '' ? $summary : null
            );
        }

        if (!$result['ok']) {
            return Response::json(['ok' => false, 'error' => $result['error'] ?? 'Action impossible.'], 422);
        }

        return Response::json([
            'ok' => true,
            'mission' => $this->cycles->present($result['mission']),
        ]);
    }

    private function tenantId(): int
    {
        return (int) Session::get('tenant_id');
    }

    private function csrfOk(Request $request): bool
    {
        $token = (string) (
            $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? $request->input('_csrf_token')
            ?? ''
        );
        if ($token === '') {
            $body = $this->jsonBody($request);
            $token = (string) ($body['_csrf_token'] ?? '');
        }

        return Csrf::validate($token);
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonBody(Request $request): array
    {
        $raw = file_get_contents('php://input');
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
