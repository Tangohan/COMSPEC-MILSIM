<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\MissionPlanning\MissionPlanningAtakService;
use App\Services\MissionPlanning\MissionPlanningPdfService;
use App\Support\ComspecApiKeyAuth;

/**
 * Plan de mission côté carte ATAK (calque, panneau, tableau de conduite, documents).
 */
final class AtakMissionApiController
{
    private const DEFAULT_MAP_ID = 1;

    /** @var array<string, mixed>|null */
    private ?array $jsonBodyCache = null;

    public function __construct(
        private ?MissionPlanningAtakService $atakPlan = null,
        private ?MissionPlanningPdfService $pdf = null,
    ) {
        $this->atakPlan ??= new MissionPlanningAtakService(
            new \App\Repositories\MissionPlanRepository(),
            new \App\Services\MissionPlanning\MissionPlanningService(
                new \App\Repositories\MissionPlanRepository()
            )
        );
        $this->pdf ??= new MissionPlanningPdfService(
            new \App\Services\MissionPlanning\MissionPlanningService(
                new \App\Repositories\MissionPlanRepository()
            )
        );
    }

    public function show(Request $request, array $params = []): Response
    {
        $tenantId = $this->resolveTenantId($request);
        if ($tenantId < 1) {
            return $this->tenantRequired();
        }
        try {
            return Response::json($this->atakPlan->snapshot($tenantId, $this->mapId($request)));
        } catch (\Throwable) {
            return Response::json([
                'ok' => true,
                'plan' => null,
                'overlay' => ['graphics' => [], 'routes' => []],
                'task_org' => [],
                'roster' => [],
                'timeline' => [],
                'next_events' => [],
                'unit_status' => [],
                'documents' => [],
                'slots' => [],
            ]);
        }
    }

    public function unit(Request $request, array $params = []): Response
    {
        $tenantId = $this->resolveTenantId($request);
        if ($tenantId < 1) {
            return $this->tenantRequired();
        }
        try {
            $snap = $this->atakPlan->snapshot($tenantId, $this->mapId($request));
        } catch (\Throwable) {
            $snap = ['plan' => null, 'slots' => []];
        }
        $cs = strtoupper(trim((string) ($request->query('callsign') ?? $request->query('call_sign') ?? '')));
        $slots = is_array($snap['slots'] ?? null) ? $snap['slots'] : [];
        $slot = $cs !== '' ? ($slots[$cs] ?? null) : null;

        return Response::json([
            'ok' => true,
            'plan' => $snap['plan'] ?? null,
            'slot' => $slot,
        ]);
    }

    public function placeGraphic(Request $request, array $params = []): Response
    {
        $guard = $this->guardWrite($request);
        if ($guard instanceof Response) {
            return $guard;
        }
        [$tenantId] = $guard;
        $id = (int) ($params['id'] ?? 0);
        if ($id < 1) {
            return Response::json(['ok' => false, 'error' => 'Repère introuvable.'], 404);
        }
        $body = $this->body($request);
        $snap = $this->atakPlan->placeGraphic(
            $tenantId,
            $this->mapId($request, true),
            $id,
            $body,
            $this->actorId()
        );
        if ($snap === null) {
            return Response::json(['ok' => false, 'error' => 'Impossible de placer ce repère.'], 404);
        }

        return Response::json($snap);
    }

    public function graphicState(Request $request, array $params = []): Response
    {
        $guard = $this->guardWrite($request);
        if ($guard instanceof Response) {
            return $guard;
        }
        [$tenantId] = $guard;
        $id = (int) ($params['id'] ?? 0);
        $body = $this->body($request);
        $state = strtolower(trim((string) ($body['state'] ?? $body['draw_state'] ?? '')));
        $snap = $this->atakPlan->setGraphicState($tenantId, $this->mapId($request, true), $id, $state, $this->actorId());
        if ($snap === null) {
            return Response::json(['ok' => false, 'error' => 'État de repère refusé.'], 422);
        }

        return Response::json($snap);
    }

    public function setPhase(Request $request, array $params = []): Response
    {
        $guard = $this->guardWrite($request);
        if ($guard instanceof Response) {
            return $guard;
        }
        [$tenantId] = $guard;
        $body = $this->body($request);
        $phase = trim((string) ($body['phase'] ?? $body['phase_label'] ?? ''));
        $snap = $this->atakPlan->setPhase($tenantId, $this->mapId($request, true), $phase, $this->actorId());
        if ($snap === null) {
            return Response::json(['ok' => false, 'error' => 'Aucun plan en cours sur cette carte.'], 404);
        }

        return Response::json($snap);
    }

    public function addTimeline(Request $request, array $params = []): Response
    {
        $guard = $this->guardWrite($request);
        if ($guard instanceof Response) {
            return $guard;
        }
        [$tenantId] = $guard;
        $body = $this->body($request);
        $label = trim((string) ($body['label'] ?? $body['message'] ?? ''));
        $snap = $this->atakPlan->addTimelineEvent($tenantId, $this->mapId($request, true), $label, 'c2', $this->actorId());
        if ($snap === null) {
            return Response::json(['ok' => false, 'error' => 'Indiquez un événement.'], 422);
        }

        return Response::json($snap);
    }

    public function pdf(Request $request, array $params = []): Response
    {
        $tenantId = $this->resolveTenantId($request);
        if ($tenantId < 1) {
            return $this->tenantRequired();
        }
        $snap = $this->atakPlan->snapshot($tenantId, $this->mapId($request));
        $plan = is_array($snap['plan'] ?? null) ? $snap['plan'] : null;
        $planId = is_array($plan) ? (int) ($plan['id'] ?? 0) : 0;
        if ($planId < 1) {
            return (new Response())->setStatusCode(404)->setBody('<p>Aucun ordre de mission ouvert sur cette carte.</p>');
        }

        return $this->pdf->export($tenantId, $planId, true);
    }

    /**
     * @return array{0:int}|Response
     */
    private function guardWrite(Request $request): array|Response
    {
        $tenantId = $this->resolveTenantId($request);
        if ($tenantId < 1) {
            return $this->tenantRequired();
        }
        if (!$this->writeAllowed($request)) {
            return Response::json(['ok' => false, 'error' => 'Session expirée ou clé d’accès absente.'], 419);
        }

        return [$tenantId];
    }

    private function writeAllowed(Request $request): bool
    {
        if (ComspecApiKeyAuth::extractPresentedKey() !== '') {
            return true;
        }
        $body = $this->body($request);
        $token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $request->input('_csrf_token') ?? ($body['_csrf_token'] ?? ''));

        return Csrf::validate($token);
    }

    private function actorId(): ?int
    {
        $id = (int) (Session::get('user_id') ?? 0);

        return $id > 0 ? $id : null;
    }

    private function mapId(Request $request, bool $fromBody = false): int
    {
        if ($fromBody) {
            $body = $this->body($request);
            $map = $body['mapId'] ?? $body['map_id'] ?? $request->query('mapId') ?? $request->query('map_id');
        } else {
            $map = $request->query('mapId') ?? $request->query('map_id');
        }
        $mapId = ($map !== null && $map !== '') ? (int) $map : self::DEFAULT_MAP_ID;

        return $mapId < 1 ? self::DEFAULT_MAP_ID : $mapId;
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
        $q = $request->query('tenant_id');
        if ($q !== null && $q !== '') {
            $n = (int) $q;

            return $n > 0 ? $n : 0;
        }

        return 0;
    }

    private function tenantRequired(): Response
    {
        return Response::json([
            'ok' => false,
            'error' => 'tenant_context_required',
            'message' => 'Communauté non identifiée.',
        ], 403);
    }

    /** @return array<string, mixed> */
    private function body(Request $request): array
    {
        if ($this->jsonBodyCache !== null) {
            return $this->jsonBodyCache;
        }
        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            $this->jsonBodyCache = [];

            return $this->jsonBodyCache;
        }
        $decoded = json_decode($raw, true);
        $this->jsonBodyCache = is_array($decoded) ? $decoded : [];

        return $this->jsonBodyCache;
    }
}
