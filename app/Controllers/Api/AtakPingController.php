<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;

/**
 * Santé ATAK sans ouvrir PDO ni le contrôleur principal.
 * Un incident base ne doit pas faire passer le ping en 503 (sinon toute la carte
 * croit que le poste de commandement est mort).
 */
final class AtakPingController
{
    public function ping(Request $request, array $params = []): Response
    {
        return Response::json([
            'ok' => true,
            'service' => 'atak',
            'server_ms' => (int) round(microtime(true) * 1000),
        ]);
    }
}
