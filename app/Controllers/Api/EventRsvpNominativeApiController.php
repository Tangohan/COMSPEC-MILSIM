<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\CommunityEventRepository;
use App\Repositories\EventRsvpNominativeRepository;
use App\Services\Attendance\EventRsvpNominativeService;
use App\Services\Rbac\RolePermissionMatrixCatalog;
use App\Support\ModuleFeatureAccess;

final class EventRsvpNominativeApiController
{
    public function __construct(
        private ?CommunityEventRepository $events = null,
        private ?EventRsvpNominativeRepository $nominative = null,
        private ?EventRsvpNominativeService $service = null,
    ) {
        $this->events ??= new CommunityEventRepository();
        $this->nominative ??= new EventRsvpNominativeRepository();
        $this->service ??= new EventRsvpNominativeService($this->nominative);
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::json(['ok' => false, 'error' => 'Connexion requise.'], 401);
        }
        if (!ModuleFeatureAccess::allows(RolePermissionMatrixCatalog::MODULE_OPERATIONS, 'view')) {
            return Response::json(['ok' => false, 'error' => 'Accès refusé.'], 403);
        }

        $eventId = (int) ($params['id'] ?? 0);
        if ($eventId < 1 || !$this->events->belongsToTenant($eventId, $tenantId)) {
            return Response::json(['ok' => false, 'error' => 'Créneau introuvable.'], 404);
        }

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'response' => trim((string) $request->query('response', '')),
            'section' => trim((string) $request->query('section', '')),
            'atak' => trim((string) $request->query('atak', '')),
        ];
        $data = $this->service->listForEvent($tenantId, $eventId, $filters);

        return Response::json([
            'ok' => true,
            'rows' => $data['rows'],
            'stats' => $data['stats'],
            'sections' => $data['sections'],
            'response_labels' => EventRsvpNominativeService::responseFilterLabelsFr(),
            'atak_labels' => EventRsvpNominativeService::atakFilterLabelsFr(),
        ]);
    }

    public function export(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::json(['ok' => false, 'error' => 'Connexion requise.'], 401);
        }
        if (!ModuleFeatureAccess::allows(RolePermissionMatrixCatalog::MODULE_OPERATIONS, 'export')) {
            return Response::json(['ok' => false, 'error' => 'Accès refusé.'], 403);
        }

        $eventId = (int) ($params['id'] ?? 0);
        if ($eventId < 1 || !$this->events->belongsToTenant($eventId, $tenantId)) {
            return Response::json(['ok' => false, 'error' => 'Créneau introuvable.'], 404);
        }

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'response' => trim((string) $request->query('response', '')),
            'section' => trim((string) $request->query('section', '')),
            'atak' => trim((string) $request->query('atak', '')),
        ];
        $data = $this->service->listForEvent($tenantId, $eventId, $filters);

        return Response::json([
            'ok' => true,
            'generated_at' => gmdate('c'),
            'rows' => $data['rows'],
            'stats' => $data['stats'],
        ]);
    }

    public function updateMeta(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::json(['ok' => false, 'error' => 'Connexion requise.'], 401);
        }
        if (!ModuleFeatureAccess::allows(RolePermissionMatrixCatalog::MODULE_OPERATIONS, 'manage')) {
            return Response::json(['ok' => false, 'error' => 'Accès refusé.'], 403);
        }
        if (!$this->csrfOk($request)) {
            return Response::json(['ok' => false, 'error' => 'Session expirée.'], 419);
        }

        $eventId = (int) ($params['id'] ?? 0);
        $userId = (int) ($params['userId'] ?? $request->input('user_id', 0));
        if ($eventId < 1 || $userId < 1) {
            return Response::json(['ok' => false, 'error' => 'Paramètres invalides.'], 422);
        }

        $body = $this->body();
        $saved = $this->service->updateRowMeta($tenantId, $eventId, $userId, $body + [
            'availability_from' => $request->input('availability_from', $body['availability_from'] ?? null),
            'availability_to' => $request->input('availability_to', $body['availability_to'] ?? null),
            'admin_comment' => $request->input('admin_comment', $body['admin_comment'] ?? null),
        ]);

        return Response::json(['ok' => $saved]);
    }

    private function csrfOk(Request $request): bool
    {
        $body = $this->body();
        $token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $request->input('_csrf_token') ?? ($body['_csrf_token'] ?? ''));

        return Csrf::validate($token);
    }

    /**
     * @return array<string, mixed>
     */
    private function body(): array
    {
        $raw = file_get_contents('php://input');
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
