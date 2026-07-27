<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AtakWaypointRepository;
use App\Support\ComspecApiKeyAuth;

/**
 * Itinéraires de patrouille et waypoints partagés.
 *
 * Deux familles d’appelants : le mod en jeu, authentifié par clé d’accès Overwatch, et la
 * carte web, authentifiée par session + jeton CSRF. Les lectures sont ouvertes aux deux dès
 * que la communauté est identifiée ; les écritures exigent l’un ou l’autre.
 */
final class AtakWaypointApiController
{
    private const DEFAULT_CONTEXT_ID = 1;

    /** @var array<string, mixed>|null Cache php://input (une seule lecture par requête). */
    private ?array $jsonBodyCache = null;

    private AtakWaypointRepository $waypoints;

    public function __construct(?AtakWaypointRepository $waypoints = null)
    {
        $this->waypoints = $waypoints ?? new AtakWaypointRepository();
    }

    /**
     * GET /api/atak/waypoint-routes
     */
    public function routesIndex(Request $request, array $params = []): Response
    {
        $tenantId = $this->resolveTenantId($request);
        if ($tenantId < 1) {
            return $this->tenantRequired();
        }

        $routes = $this->waypoints->listRoutes($tenantId, $this->contextId($request), [
            'status' => $request->query('status'),
            'route_type' => $request->query('route_type'),
            'assigned_callsign' => $request->query('assigned_callsign'),
            'is_visible' => $this->optionalBool($request->query('is_visible')),
            'limit' => $request->query('limit'),
            'offset' => $request->query('offset'),
        ]);

        return Response::json([
            'ok' => true,
            'routes' => $routes,
            'count' => count($routes),
            'route_types' => AtakWaypointRepository::routeTypes(),
            'waypoint_types' => AtakWaypointRepository::waypointTypes(),
        ]);
    }

    /**
     * POST /api/atak/waypoint-routes
     *
     * Accepte un itinéraire complet : les points fournis dans `waypoints` sont créés dans
     * l’ordre du tableau, ce qui évite au mod un aller-retour par point.
     */
    public function routesStore(Request $request, array $params = []): Response
    {
        $tenantId = $this->resolveTenantId($request);
        if ($tenantId < 1) {
            return $this->tenantRequired();
        }
        if (!$this->writeAllowed($request)) {
            return $this->writeRefused();
        }

        $body = $this->body($request);
        $name = trim((string) ($body['route_name'] ?? $request->input('route_name', '')));
        if ($name === '') {
            return Response::json(['ok' => false, 'error' => 'Nom d’itinéraire requis.'], 422);
        }

        $contextId = $this->contextId($request);
        $body['route_name'] = $name;
        $body['created_by_user_id'] = $body['created_by_user_id'] ?? (Session::get('user_id') ?: null);

        $routeId = $this->waypoints->createRoute($tenantId, $contextId, $body);
        if ($routeId < 1) {
            return Response::json(['ok' => false, 'error' => 'Création de l’itinéraire impossible.'], 500);
        }

        $created = 0;
        $rawPoints = $body['waypoints'] ?? null;
        if (is_array($rawPoints)) {
            foreach ($rawPoints as $point) {
                if (!is_array($point) || !$this->hasPosition($point)) {
                    continue;
                }
                $point['route_id'] = $routeId;
                $point['created_by_user_id'] = $point['created_by_user_id'] ?? $body['created_by_user_id'];
                $this->waypoints->addWaypoint($tenantId, $contextId, $point);
                $created++;
            }
        }

        return Response::json([
            'ok' => true,
            'route' => $this->waypoints->findRoute($tenantId, $routeId),
            'waypoints_created' => $created,
        ], 201);
    }

    /**
     * GET /api/atak/waypoint-routes/{id}
     */
    public function routesShow(Request $request, array $params = []): Response
    {
        $tenantId = $this->resolveTenantId($request);
        if ($tenantId < 1) {
            return $this->tenantRequired();
        }

        $routeId = (int) ($params['id'] ?? 0);
        $route = $routeId > 0 ? $this->waypoints->findRoute($tenantId, $routeId) : null;
        if ($route === null) {
            return Response::json(['ok' => false, 'error' => 'Itinéraire introuvable.'], 404);
        }

        return Response::json([
            'ok' => true,
            'route' => $route,
            'next_waypoint' => $this->waypoints->nextPending($tenantId, $routeId),
        ]);
    }

    /**
     * PATCH|PUT /api/atak/waypoint-routes/{id}
     */
    public function routesUpdate(Request $request, array $params = []): Response
    {
        $tenantId = $this->resolveTenantId($request);
        if ($tenantId < 1) {
            return $this->tenantRequired();
        }
        if (!$this->writeAllowed($request)) {
            return $this->writeRefused();
        }

        $routeId = (int) ($params['id'] ?? 0);
        if ($routeId < 1 || $this->waypoints->findRoute($tenantId, $routeId) === null) {
            return Response::json(['ok' => false, 'error' => 'Itinéraire introuvable.'], 404);
        }

        $body = $this->body($request);
        if ($body === []) {
            $body = array_filter([
                'route_name' => $request->input('route_name'),
                'status' => $request->input('status'),
                'route_type' => $request->input('route_type'),
                'description' => $request->input('description'),
                'assigned_unit' => $request->input('assigned_unit'),
                'assigned_callsign' => $request->input('assigned_callsign'),
            ], static fn ($v): bool => $v !== null);
        }
        unset($body['waypoints'], $body['tenant_id'], $body['_csrf_token']);

        if (!$this->waypoints->updateRoute($tenantId, $routeId, $body)) {
            return Response::json(['ok' => false, 'error' => 'Aucune modification applicable.'], 422);
        }

        return Response::json([
            'ok' => true,
            'route' => $this->waypoints->findRoute($tenantId, $routeId),
        ]);
    }

    /**
     * DELETE|POST /api/atak/waypoint-routes/{id}/delete
     */
    public function routesDestroy(Request $request, array $params = []): Response
    {
        $tenantId = $this->resolveTenantId($request);
        if ($tenantId < 1) {
            return $this->tenantRequired();
        }
        if (!$this->writeAllowed($request)) {
            return $this->writeRefused();
        }

        $routeId = (int) ($params['id'] ?? 0);
        if ($routeId < 1 || !$this->waypoints->softDeleteRoute($tenantId, $routeId)) {
            return Response::json(['ok' => false, 'error' => 'Itinéraire introuvable.'], 404);
        }

        return Response::json(['ok' => true, 'deleted' => $routeId]);
    }

    /**
     * GET /api/atak/waypoints
     */
    public function waypointsIndex(Request $request, array $params = []): Response
    {
        $tenantId = $this->resolveTenantId($request);
        if ($tenantId < 1) {
            return $this->tenantRequired();
        }

        $waypoints = $this->waypoints->listWaypoints($tenantId, $this->contextId($request), [
            'route_id' => $request->query('route_id'),
            'orphans_only' => $this->optionalBool($request->query('orphans_only')),
            'waypoint_type' => $request->query('waypoint_type'),
            'reached' => $this->optionalBool($request->query('reached')),
            'limit' => $request->query('limit'),
            'offset' => $request->query('offset'),
        ]);

        return Response::json([
            'ok' => true,
            'waypoints' => $waypoints,
            'count' => count($waypoints),
        ]);
    }

    /**
     * POST /api/atak/waypoints
     */
    public function waypointsStore(Request $request, array $params = []): Response
    {
        $tenantId = $this->resolveTenantId($request);
        if ($tenantId < 1) {
            return $this->tenantRequired();
        }
        if (!$this->writeAllowed($request)) {
            return $this->writeRefused();
        }

        $body = $this->body($request);
        if ($body === []) {
            $body = [
                'route_id' => $request->input('route_id'),
                'label' => $request->input('label'),
                'waypoint_type' => $request->input('waypoint_type'),
                'pos_x' => $request->input('pos_x'),
                'pos_y' => $request->input('pos_y'),
                'description' => $request->input('description'),
            ];
        }
        if (!$this->hasPosition($body)) {
            return Response::json(['ok' => false, 'error' => 'Position (pos_x, pos_y) requise.'], 422);
        }

        $routeId = (int) ($body['route_id'] ?? 0);
        if ($routeId > 0 && $this->waypoints->findRoute($tenantId, $routeId) === null) {
            return Response::json(['ok' => false, 'error' => 'Itinéraire introuvable.'], 404);
        }

        $body['created_by_user_id'] = $body['created_by_user_id'] ?? (Session::get('user_id') ?: null);
        $waypointId = $this->waypoints->addWaypoint($tenantId, $this->contextId($request), $body);
        if ($waypointId < 1) {
            return Response::json(['ok' => false, 'error' => 'Création du waypoint impossible.'], 500);
        }

        return Response::json([
            'ok' => true,
            'waypoint' => $this->waypoints->findWaypoint($tenantId, $waypointId),
        ], 201);
    }

    /**
     * PATCH|PUT /api/atak/waypoints/{id}
     */
    public function waypointsUpdate(Request $request, array $params = []): Response
    {
        $tenantId = $this->resolveTenantId($request);
        if ($tenantId < 1) {
            return $this->tenantRequired();
        }
        if (!$this->writeAllowed($request)) {
            return $this->writeRefused();
        }

        $waypointId = (int) ($params['id'] ?? 0);
        if ($waypointId < 1 || $this->waypoints->findWaypoint($tenantId, $waypointId) === null) {
            return Response::json(['ok' => false, 'error' => 'Waypoint introuvable.'], 404);
        }

        $body = $this->body($request);
        unset($body['tenant_id'], $body['_csrf_token'], $body['reached']);
        if (!$this->waypoints->updateWaypoint($tenantId, $waypointId, $body)) {
            return Response::json(['ok' => false, 'error' => 'Aucune modification applicable.'], 422);
        }

        return Response::json([
            'ok' => true,
            'waypoint' => $this->waypoints->findWaypoint($tenantId, $waypointId),
        ]);
    }

    /**
     * POST /api/atak/waypoints/{id}/reached
     *
     * C’est l’appel que fait le mod quand une patrouille franchit un point. `reached=false`
     * annule le marquage, pour rattraper une détection trop hâtive.
     */
    public function waypointsReached(Request $request, array $params = []): Response
    {
        $tenantId = $this->resolveTenantId($request);
        if ($tenantId < 1) {
            return $this->tenantRequired();
        }
        if (!$this->writeAllowed($request)) {
            return $this->writeRefused();
        }

        $waypointId = (int) ($params['id'] ?? 0);
        if ($waypointId < 1 || $this->waypoints->findWaypoint($tenantId, $waypointId) === null) {
            return Response::json(['ok' => false, 'error' => 'Waypoint introuvable.'], 404);
        }

        $body = $this->body($request);
        $rawReached = $body['reached'] ?? $request->input('reached', true);
        $reached = $this->optionalBool($rawReached) ?? true;

        $context = [
            'reached_by_user_id' => $body['reached_by_user_id'] ?? (Session::get('user_id') ?: null),
            'reached_by_callsign' => $body['reached_by_callsign'] ?? $request->input('reached_by_callsign'),
        ];

        if (!$this->waypoints->markReached($tenantId, $waypointId, $reached, $context)) {
            return Response::json(['ok' => false, 'error' => 'Marquage impossible.'], 422);
        }

        $waypoint = $this->waypoints->findWaypoint($tenantId, $waypointId);
        $routeId = (int) ($waypoint['route_id'] ?? 0);

        return Response::json([
            'ok' => true,
            'waypoint' => $waypoint,
            'route' => $routeId > 0 ? $this->waypoints->findRoute($tenantId, $routeId) : null,
            'next_waypoint' => $routeId > 0 ? $this->waypoints->nextPending($tenantId, $routeId) : null,
        ]);
    }

    /**
     * DELETE|POST /api/atak/waypoints/{id}/delete
     */
    public function waypointsDestroy(Request $request, array $params = []): Response
    {
        $tenantId = $this->resolveTenantId($request);
        if ($tenantId < 1) {
            return $this->tenantRequired();
        }
        if (!$this->writeAllowed($request)) {
            return $this->writeRefused();
        }

        $waypointId = (int) ($params['id'] ?? 0);
        if ($waypointId < 1 || !$this->waypoints->softDeleteWaypoint($tenantId, $waypointId)) {
            return Response::json(['ok' => false, 'error' => 'Waypoint introuvable.'], 404);
        }

        return Response::json(['ok' => true, 'deleted' => $waypointId]);
    }

    private function tenantRequired(): Response
    {
        return Response::json([
            'ok' => false,
            'error' => 'tenant_context_required',
            'message' => 'Communauté non identifiée. Reliez le compte Athena en jeu, ou utilisez la clé d’accès fournie par votre administrateur.',
        ], 403);
    }

    private function writeRefused(): Response
    {
        return Response::json(['ok' => false, 'error' => 'Session expirée ou clé d’accès absente.'], 419);
    }

    /**
     * @param array<string,mixed> $data
     */
    private function hasPosition(array $data): bool
    {
        return isset($data['pos_x'], $data['pos_y'])
            && $data['pos_x'] !== ''
            && $data['pos_y'] !== ''
            && is_numeric($data['pos_x'])
            && is_numeric($data['pos_y']);
    }

    private function contextId(Request $request): int
    {
        $body = $this->body($request);
        $raw = $body['context_id'] ?? $body['mapId'] ?? $body['map_id']
            ?? $request->query('context_id') ?? $request->query('mapId');
        $contextId = ($raw !== null && $raw !== '') ? (int) $raw : self::DEFAULT_CONTEXT_ID;

        return $contextId < 1 ? self::DEFAULT_CONTEXT_ID : $contextId;
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
        $body = $this->body($request);
        if (!empty($body['tenant_id'])) {
            $n = (int) $body['tenant_id'];

            return $n > 0 ? $n : 0;
        }
        $q = $request->query('tenant_id');
        if ($q !== null && $q !== '') {
            $n = (int) $q;

            return $n > 0 ? $n : 0;
        }

        return 0;
    }

    private function writeAllowed(Request $request): bool
    {
        if ($this->isGameClient()) {
            return true;
        }

        return $this->csrfOk($request);
    }

    private function isGameClient(): bool
    {
        return ComspecApiKeyAuth::extractPresentedKey() !== '';
    }

    private function csrfOk(Request $request): bool
    {
        $body = $this->body($request);
        $token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $request->input('_csrf_token') ?? ($body['_csrf_token'] ?? ''));

        return Csrf::validate($token);
    }

    private function optionalBool(mixed $raw): ?bool
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_bool($raw)) {
            return $raw;
        }
        $value = strtolower(trim((string) $raw));
        if (in_array($value, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }
        if (in_array($value, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function body(Request $request): array
    {
        if ($this->jsonBodyCache !== null) {
            return $this->jsonBodyCache;
        }
        $raw = file_get_contents('php://input');
        if (!is_string($raw) || trim($raw) === '') {
            $this->jsonBodyCache = [];

            return $this->jsonBodyCache;
        }
        $decoded = json_decode($raw, true);
        $this->jsonBodyCache = is_array($decoded) ? $decoded : [];

        return $this->jsonBodyCache;
    }
}
