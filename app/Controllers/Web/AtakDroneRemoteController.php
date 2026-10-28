<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AtakMapRepository;
use App\Services\Qr\QrPngGenerator;
use App\Support\AtakPlanAccess;
use App\Support\LoginIntendedDestination;
use App\Support\SteamId;

/**
 * Télécommande drone sur le vrai téléphone : page /telecommande-drone, ouverte en scannant le QR code
 * affiché dans l'app Drone de l'ATAK natif (onglet FLOTTE) ou dans le panneau Drones d'Overwatch.
 * Mêmes ordres que le poste (POST /api/atak/web-commands/uav) : le téléphone du pilote en jeu les exécute
 * s'il est en liaison avec le drone. Pas de pilotage au joystick ni de vidéo en direct.
 */
final class AtakDroneRemoteController
{
    public function show(Request $request, array $params = []): Response
    {
        if (!Session::get('user_id')) {
            LoginIntendedDestination::rememberFromRequest($request);

            return Response::redirect(url('login'));
        }
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return Response::redirect(url('connect'));
        }
        if (!AtakPlanAccess::allows($tenantId)) {
            return Response::view('layout.main', [
                'title' => 'Télécommande drone',
                'content' => 'platform.upgrade',
                'feature' => 'atak',
                'planName' => 'standard',
            ]);
        }
        $mapId = 1;
        try {
            $maps = new AtakMapRepository();
            $map = $maps->getDefaultForTenant($tenantId) ?: $maps->getBySlug('altis');
            $mapId = (int) ($map['id'] ?? 1);
        } catch (\Throwable) {
        }

        return Response::view('atak.drone_remote', [
            'title' => 'Télécommande drone — COMSPEC',
            'mapId' => $mapId > 0 ? $mapId : 1,
            'steamUid' => $this->sessionSteam((int) Session::get('user_id')),
            'apiBase' => rtrim((string) url(''), '/'),
            'assetVer' => \App\Support\BuildVersion::id(),
        ]);
    }

    /** PNG du QR code de cette page (adresse publique du site), affiché dans le panneau Drones d'Overwatch. */
    public function qr(Request $request, array $params = []): Response
    {
        $png = (new QrPngGenerator())->png(url('telecommande-drone'), 360, 12, true);
        if ($png === null || strncmp($png['body'], "\x89PNG", 4) !== 0) {
            return (new Response())
                ->setStatusCode(503)
                ->header('Content-Type', 'text/plain; charset=UTF-8')
                ->setBody('QR unavailable');
        }

        return (new Response())
            ->setStatusCode(200)
            ->header('Content-Type', 'image/png')
            ->header('Cache-Control', 'public, max-age=3600')
            ->header('X-Content-Type-Options', 'nosniff')
            ->setBody($png['body']);
    }

    private function sessionSteam(int $userId): string
    {
        try {
            $pdo = \App\Core\Database::getPdo();
            $st = $pdo->prepare('SELECT steam_id FROM users WHERE id = ? LIMIT 1');
            $st->execute([$userId]);
            $raw = $st->fetchColumn();
        } catch (\Throwable) {
            return '';
        }

        return is_string($raw) ? (SteamId::normalize($raw) ?? '') : '';
    }
}
