<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Services\Security\FileRateLimiter;
use App\Services\Sse\SessionSseMapLayerGate;
use App\Services\Sse\SseMapLayerGate;
use App\Services\Sse\SseMapLayersService;

/**
 * GET /api/sse/v1/calques : calques SSE des cartes du site (contrôles d'identité, lieux, pistes de téléphones) en GeoJSON.
 *
 * Lecture seule (aucune écriture, donc pas de CSRF). Session de navigateur uniquement : la communauté vient de la session,
 * jamais de la requête (pas d'identifiant de communauté en paramètre), et la porte d'entrée SSE doit être franchie (voir
 * SessionSseMapLayerGate). Documenté dans docs/sse-calques-api.md.
 */
final class SseMapLayersApiController
{
    /** Requêtes par minute et par compte. */
    private const RATE_MAX = 90;

    private const RATE_WINDOW = 60;

    public function __construct(
        private ?SseMapLayersService $service = null,
        private ?SseMapLayerGate $gate = null,
        private ?FileRateLimiter $limiter = null,
    ) {
        $this->service ??= new SseMapLayersService();
        $this->gate ??= new SessionSseMapLayerGate();
        $this->limiter ??= new FileRateLimiter();
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = $this->gate->tenantId();
        if ($tenantId < 1) {
            return $this->json(['error' => 'sse_access_required', 'message' => 'Accès au Bureau SSE requis.'], 403);
        }
        $user = (int) $this->gate->userId();
        if ($this->limiter->tooManyAttempts('sse_calques|' . $tenantId . '|' . $user, self::RATE_MAX, self::RATE_WINDOW)) {
            return $this->json(['error' => 'too_many_requests', 'message' => 'Trop de requêtes : réessayez dans un instant.', 'retry_after' => self::RATE_WINDOW], 429)
                ->header('Retry-After', (string) self::RATE_WINDOW);
        }

        $parsed = SseMapLayersService::parseQuery($request->queryParams());
        if (isset($parsed['error'])) {
            return $this->json(['error' => $parsed['error'], 'message' => 'Paramètres invalides.'], 422);
        }
        $query = $parsed['query'];

        try {
            $payload = $this->service->build($this->gate, $query);
            $payload = $this->service->auditTrackAccess($this->gate, $query, $payload);
        } catch (\Throwable $e) {
            error_log('Calques SSE : lecture impossible : ' . $e->getMessage());

            return $this->json(['error' => 'unavailable', 'message' => 'Calques indisponibles pour le moment.'], 503);
        }

        return $this->json($payload, 200);
    }

    /** @param array<string, mixed> $data */
    private function json(array $data, int $status): Response
    {
        return Response::json($data, $status)
            ->header('Cache-Control', 'private, no-store')
            ->header('Vary', 'Cookie');
    }
}
