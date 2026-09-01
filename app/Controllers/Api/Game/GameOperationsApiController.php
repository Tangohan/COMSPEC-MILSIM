<?php

declare(strict_types=1);

namespace App\Controllers\Api\Game;

use App\Core\Request;
use App\Core\Response;
use App\Services\Game\GameAuthService;
use App\Services\Operations\OperationWorkspaceService;

final class GameOperationsApiController
{
    public function __construct(
        private GameAuthService $auth,
        private OperationWorkspaceService $workspace,
    ) {}

    public function list(Request $request, array $params = []): Response
    {
        $session = $this->requireSession();
        if ($session instanceof Response) {
            return $session;
        }
        $tenantId = (int) ($session['tenant_id'] ?? 0);
        $repo = \App\Core\Container::get(\App\Repositories\OperationWorkspaceRepository::class);
        $rows = array_map(
            [$this->workspace, 'presentOperation'],
            $repo->listForTenant($tenantId)
        );
        $out = [];
        foreach ($rows as $row) {
            if (!in_array((string) ($row['status'] ?? ''), ['planned', 'active', 'paused'], true)) {
                continue;
            }
            $out[] = [
                'uuid' => $row['uuid'],
                'code' => $row['code'],
                'name' => $row['name'],
                'status' => $row['status_label'],
                'phase' => $row['phase_label'],
                'classification' => $row['classification_label'],
            ];
        }

        return Response::json(['ok' => true, 'operations' => $out]);
    }

    public function tactical(Request $request, array $params = []): Response
    {
        $session = $this->requireSession();
        if ($session instanceof Response) {
            return $session;
        }
        $tenantId = (int) ($session['tenant_id'] ?? 0);
        $uuid = trim((string) ($params['uuid'] ?? ''));
        $payload = $this->workspace->tacticalPayload($tenantId, $uuid);
        if ($payload === null) {
            return Response::json(['ok' => false, 'error' => 'NOT_FOUND'], 404);
        }
        $markers = [];
        foreach ($payload['objects'] as $obj) {
            $geo = is_array($obj['geometry'] ?? null) ? $obj['geometry'] : [];
            $markers[] = [
                'uuid' => $obj['uuid'],
                'name' => $obj['name'],
                'type' => $obj['graphic_type'],
                'affiliation' => $obj['affiliation'],
                'status' => $obj['status'],
                'geometry' => $geo,
            ];
        }

        return Response::json([
            'ok' => true,
            'operation' => [
                'code' => $payload['operation']['code'],
                'name' => $payload['operation']['name'],
                'status' => $payload['operation']['status_label'],
                'phase' => $payload['operation']['phase_label'],
            ],
            'markers' => $markers,
        ]);
    }

    /**
     * @return array<string, mixed>|Response
     */
    private function requireSession(): array|Response
    {
        $token = $this->bearer();
        $session = $this->auth->sessionFromBearer($token);
        if ($session === null) {
            return Response::json(['authenticated' => false, 'error' => 'SESSION_EXPIRED'], 401);
        }

        return $session;
    }

    private function bearer(): string
    {
        $auth = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
        if (str_starts_with($auth, 'Bearer ')) {
            return trim(substr($auth, 7));
        }

        return trim((string) ($_SERVER['HTTP_X_COMSPEC_SESSION'] ?? ''));
    }
}
