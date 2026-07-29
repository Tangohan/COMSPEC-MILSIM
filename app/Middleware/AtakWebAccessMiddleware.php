<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TacticalPhonePairingRepository;
use App\Services\Sse\SseAccessCodeService;
use App\Support\LoginIntendedDestination;

/**
 * Accès web à la carte ATAK : compte membre connecté, ou session téléphone
 * établie après appariement (/connect → Carte ATAK).
 */
final class AtakWebAccessMiddleware
{
    public function __invoke(Request $request, callable $next): Response
    {
        // Invité renseignement : périmètre limité au portail SSE (pas de carte tactique).
        $sse = new SseAccessCodeService();
        if ($sse->hasActiveClearance() && $sse->isGuest()) {
            return Response::redirect(url('atak/sse/dossiers'));
        }

        if (Session::get('user_id')) {
            return (new AuthMiddleware())($request, $next);
        }

        if ($this->phonePairingSessionIsValid()) {
            return $next($request);
        }

        if (!LoginIntendedDestination::rememberFromRequest($request)) {
            Session::flash('error', 'Connectez-vous ou appariez votre téléphone pour ouvrir la carte.');
        }

        return Response::redirect(url('login'));
    }

    private function phonePairingSessionIsValid(): bool
    {
        $token = trim((string) Session::get('atak_phone_pairing_token', ''));
        $tenantId = (int) Session::get('tenant_id');
        if ($token === '' || $tenantId < 1) {
            return false;
        }

        $pairing = (new TacticalPhonePairingRepository())->findValidByToken($token);
        if ($pairing === null) {
            Session::forgetMany(['atak_phone_pairing_token', 'atak_phone_operator_label']);

            return false;
        }

        if ((int) ($pairing['tenant_id'] ?? 0) !== $tenantId) {
            Session::forgetMany(['atak_phone_pairing_token', 'atak_phone_operator_label', 'tenant_id']);

            return false;
        }

        return true;
    }
}
