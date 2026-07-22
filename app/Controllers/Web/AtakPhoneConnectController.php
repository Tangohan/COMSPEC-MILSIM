<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TacticalBriefingSlideRepository;
use App\Repositories\TacticalPhonePairingRepository;
use App\Repositories\TenantRepository;

/**
 * Connexion téléphone du module ATAK (inspiré de cTab) : page publique sans compte, ouverte
 * en scannant le QR affiché en jeu ou en saisissant le code court, affichant la diapositive
 * de briefing en cours pour la communauté du pairing.
 */
final class AtakPhoneConnectController
{
    private TacticalPhonePairingRepository $pairingRepository;
    private TacticalBriefingSlideRepository $briefingSlideRepository;
    private TenantRepository $tenantRepository;

    public function __construct(
        ?TacticalPhonePairingRepository $pairingRepository = null,
        ?TacticalBriefingSlideRepository $briefingSlideRepository = null,
        ?TenantRepository $tenantRepository = null,
    ) {
        $this->pairingRepository = $pairingRepository ?? new TacticalPhonePairingRepository();
        $this->briefingSlideRepository = $briefingSlideRepository ?? new TacticalBriefingSlideRepository();
        $this->tenantRepository = $tenantRepository ?? new TenantRepository();
    }

    /** Saisie manuelle du code (pas de token dans l’URL). */
    public function codeForm(Request $request, array $params = []): Response
    {
        return Response::view('atak.connect_code', [
            'title' => 'Connexion téléphone — ATAK',
        ]);
    }

    public function codeSubmit(Request $request, array $params = []): Response
    {
        if (!Csrf::validate((string) $request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée, réessayez.');

            return Response::redirect(url('atak/connect'));
        }
        $code = trim((string) $request->input('code', ''));
        $pairing = $this->pairingRepository->findValidByCode($code);
        if ($pairing === null) {
            Session::flash('error', 'Code invalide ou expiré. Générez un nouveau code depuis la tablette en jeu.');
            try {
                $guessTenant = (int) (getenv('ATAK_DEFAULT_TENANT_ID') ?: 0);
                if ($guessTenant > 0) {
                    (new \App\Services\Tactical\AtakActivityLogService())->recordAuthAttempt(
                        $guessTenant,
                        false,
                        'Connexion téléphone refusée — code invalide ou expiré',
                        ['reason' => 'invalid_or_expired_code', 'method' => 'phone']
                    );
                }
            } catch (\Throwable) {
                // Best-effort.
            }

            return Response::redirect(url('atak/connect'));
        }

        return Response::redirect(url('atak/connect/' . (string) $pairing['token']));
    }

    /** Ouverture directe par token (QR scanné). */
    public function show(Request $request, array $params = []): Response
    {
        $token = trim((string) ($params['token'] ?? ''));
        $pairing = $this->pairingRepository->findValidByToken($token);
        if ($pairing === null) {
            return Response::view('atak.connect_expired', [
                'title' => 'Connexion expirée — ATAK',
            ]);
        }
        $this->pairingRepository->markPaired($token);

        $tenantId = (int) ($pairing['tenant_id'] ?? 0);
        // Journal Activité : premier heartbeat présence (connect_slides) — pas ici,
        // pour éviter un doublon « Accès / Briefing » à l’ouverture.
        $tenant = $tenantId > 0 ? $this->tenantRepository->findById($tenantId) : null;
        $tenantName = $tenant ? (function_exists('community_display_name') ? community_display_name($tenant) : (string) ($tenant['name'] ?? 'Communauté')) : 'Communauté';
        $slides = $tenantId > 0 ? $this->briefingSlideRepository->listActiveForTenant($tenantId) : [];

        return Response::view('atak.connect_slides', [
            'title' => 'Briefing tactique — ' . $tenantName,
            'atakTenantName' => $tenantName,
            'atakSlides' => $slides,
            'atakPairingToken' => $token,
            'atakPresenceUrl' => url('api/atak/briefing-presence'),
            'atakCommentsBaseUrl' => url('api/atak/briefing-slides'),
        ]);
    }
}
