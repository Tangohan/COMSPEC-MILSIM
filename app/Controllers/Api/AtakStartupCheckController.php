<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Services\Atak\AtakStartupCheckService;
use App\Support\HttpJsonBody;

/**
 * Check de démarrage du mod ATAK natif (POST /api/atak/startup-check, commande DLL StartupCheck) :
 * relayé dans le salon Discord de debug (AtakStartupCheckService). Public comme /api/atak/version,
 * sans session ni clé, rate-limité par adresse (RateLimitMiddleware) ; rien n'est enregistré.
 */
final class AtakStartupCheckController
{
    public function __construct(
        private AtakStartupCheckService $service = new AtakStartupCheckService()
    ) {
    }

    public function store(Request $request, array $params = []): Response
    {
        $raw = HttpJsonBody::rawJson(64 * 1024);
        $body = $raw !== '' ? json_decode($raw, true) : null;
        if (!is_array($body)) {
            return Response::json(['ok' => false, 'status' => 'invalid'], 400);
        }
        $result = $this->service->handle($body);

        return Response::json(['ok' => $result['ok'], 'status' => $result['status']], $result['http']);
    }
}
