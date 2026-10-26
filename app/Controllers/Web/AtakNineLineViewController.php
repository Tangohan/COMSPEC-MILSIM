<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Qr\QrPngGenerator;
use App\Support\AtakPlanAccess;
use App\Support\LoginIntendedDestination;

/**
 * 9-LINE du JTAC sur un autre écran : page /9-line, ouverte en scannant le QR code de l'app JTAC de l'ATAK natif
 * (onglet 9-LINE) ou par son bouton OUVRIR LA PAGE 9-LINE (seconde fenêtre du PC). Montre en grand le dernier 9-LINE
 * transmis par la communauté (toutes cartes), les précédents, et un calculateur IP → cible (cap magnétique, distance).
 * Lecture seule : rien n'est renvoyé au jeu.
 */
final class AtakNineLineViewController
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
                'title' => '9-LINE',
                'content' => 'platform.upgrade',
                'feature' => 'atak',
                'planName' => 'standard',
            ]);
        }

        return Response::view('atak.nine_line_view', [
            'title' => '9-LINE — COMSPEC',
            'apiBase' => rtrim((string) url(''), '/'),
            'assetVer' => \App\Support\BuildVersion::id(),
            'lang' => function_exists('locale') && str_starts_with((string) locale(), 'fr') ? 'fr' : 'en',
        ]);
    }

    /** Derniers 9-LINE de la communauté (toutes cartes), du plus récent au plus ancien. */
    public function feed(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!Session::get('user_id') || $tenantId < 1) {
            return Response::json(['ok' => false, 'error' => 'unauthorized'], 401);
        }
        if (!AtakPlanAccess::allows($tenantId)) {
            return Response::json(['ok' => false, 'error' => 'plan'], 403);
        }
        try {
            $st = \App\Core\Database::getPdo()->prepare(
                'SELECT n.id, n.map_id, n.mission_kind, n.author, n.assigned_aircraft, n.line1, n.line2, n.line3, n.line4, n.line5,
                        n.line6, n.line7, n.line8, n.line9, n.status, n.created_at, n.updated_at, m.label AS map_name
                 FROM atak_nine_line n LEFT JOIN atak_maps m ON m.id = n.map_id
                 WHERE n.tenant_id = ? ORDER BY COALESCE(n.updated_at, n.created_at) DESC, n.id DESC LIMIT 15'
            );
            $st->execute([$tenantId]);
            $rows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            $rows = [];
        }

        return (new Response())
            ->setStatusCode(200)
            ->header('Content-Type', 'application/json; charset=UTF-8')
            ->header('Cache-Control', 'no-store')
            ->setBody((string) json_encode(['ok' => true, 'items' => $rows], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** PNG du QR code de cette page (adresse publique du site). */
    public function qr(Request $request, array $params = []): Response
    {
        $png = (new QrPngGenerator())->png(url('9-line'), 360, 12, true);
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
}
