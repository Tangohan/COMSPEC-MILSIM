<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Support\AtakMapPhotoStore;
use App\Support\ComspecApiKeyAuth;

/**
 * Fond « Photo COMSPEC » (capture de la carte du mod) : tuiles publiques et envoi par la clé plateforme.
 *   GET  /map-photo/{carte}/{rendu}/{z}/{x}/{y}.webp   calque Leaflet du site
 *   GET  /map-photo/{carte}/{rendu}/{z}/{x}/{y}.jpg    même tuile en JPEG, téléchargée en jeu par la DLL (fond PHOTO)
 *   GET  /map-photo/{carte}/{rendu}/meta.json          meta du fond + nombre et poids des tuiles par format
 *   GET  /map-photo/{carte}/{rendu}/index-jpg.txt      tuiles JPEG qui existent, « z/x/y » par ligne
 *   GET  /api/atak/map-photo/list                      fonds publiés [{world, mode, maxZoom, tiles, bytes, created, jpgTiles, jpgBytes}]
 *   POST /api/atak/map-photo/upload?world=&mode=&reset=1&done=1   corps : paquet ZIP (tools/map_stitch.py --upload)
 */
final class AtakMapPhotoController
{
    private const MAX_UPLOAD_BYTES = 64 * 1024 * 1024;

    public function __construct(private ?AtakMapPhotoStore $store = null)
    {
        $this->store ??= new AtakMapPhotoStore();
    }

    public function tile(Request $request, array $params = []): Response
    {
        $world = AtakMapPhotoStore::world((string) ($params['world'] ?? ''));
        $mode = AtakMapPhotoStore::mode((string) ($params['mode'] ?? ''));
        $file = (string) ($params['file'] ?? '');
        if ($world === null || $mode === null || preg_match('/^(\d{1,7})\.(webp|jpg)$/', $file, $m) !== 1) {
            return $this->notFound();
        }
        $path = $this->store->tileFile($world, $mode, (int) ($params['z'] ?? -1), (int) ($params['x'] ?? -1), (int) $m[1], $m[2]);
        if ($path === null) {
            return $this->notFound();
        }
        $etag = '"mp-' . substr(sha1($path . '|' . (string) filemtime($path)), 0, 16) . '"';
        $resp = new Response();
        if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
            $resp->setStatusCode(304)->header('ETag', $etag)->header('Cache-Control', 'public, max-age=86400');

            return $resp;
        }
        $resp->setStatusCode(200)
            ->header('Content-Type', $m[2] === 'jpg' ? 'image/jpeg' : 'image/webp')
            ->header('Cache-Control', 'public, max-age=86400')
            ->header('ETag', $etag)
            ->header('Access-Control-Allow-Origin', '*')
            ->setBody((string) file_get_contents($path));

        return $resp;
    }

    /**
     * meta.json (meta du fond, nombre et poids des tuiles par format) ou index-jpg.txt (tuiles JPEG qui existent).
     */
    public function file(Request $request, array $params = []): Response
    {
        $world = AtakMapPhotoStore::world((string) ($params['world'] ?? ''));
        $mode = AtakMapPhotoStore::mode((string) ($params['mode'] ?? ''));
        $file = (string) ($params['file'] ?? '');
        if ($world === null || $mode === null) {
            return $this->notFound();
        }
        if ($file === 'meta.json') {
            $meta = $this->store->meta($world, $mode);
            if ($meta === null) {
                return $this->notFound();
            }
            $st = $this->store->stats($world, $mode);
            $meta['world'] = $world;
            $meta['mode'] = $mode;
            $meta['jpg'] = $st['jpgTiles'] > 0;

            return Response::json($meta + $st)
                ->header('Cache-Control', 'public, max-age=300')
                ->header('Access-Control-Allow-Origin', '*');
        }
        if ($file === 'index-jpg.txt') {
            $txt = $this->store->jpgIndex($world, $mode);
            if ($txt === null) {
                return $this->notFound();
            }
            $resp = new Response();
            $resp->setStatusCode(200)
                ->header('Content-Type', 'text/plain; charset=utf-8')
                ->header('Cache-Control', 'public, max-age=300')
                ->header('Access-Control-Allow-Origin', '*')
                ->setBody($txt);

            return $resp;
        }

        return $this->notFound();
    }

    /**
     * Fonds publiés (public : la DLL du jeu les propose sans être liée à un compte).
     */
    public function list(Request $request, array $params = []): Response
    {
        return Response::json($this->store->listAll())
            ->header('Cache-Control', 'public, max-age=300')
            ->header('Access-Control-Allow-Origin', '*');
    }

    public function upload(Request $request, array $params = []): Response
    {
        // Clé plateforme seulement (X_COMSPEC_KEY du .env), pas une clé de communauté.
        $expected = ComspecApiKeyAuth::expectedSecret();
        $presented = trim((string) ($_SERVER['HTTP_X_COMSPEC_KEY'] ?? ''));
        if ($expected === '' || $presented === '' || !hash_equals($expected, $presented)) {
            return Response::json(['ok' => false, 'error' => 'platform_key_required'], 403);
        }
        $world = AtakMapPhotoStore::world((string) $request->query('world', ''));
        $mode = AtakMapPhotoStore::mode((string) $request->query('mode', 'sat'));
        if ($world === null || $mode === null) {
            return Response::json(['ok' => false, 'error' => 'bad_world_or_mode'], 422);
        }
        $raw = (string) file_get_contents('php://input');
        if ($raw === '' || strlen($raw) > self::MAX_UPLOAD_BYTES || !str_starts_with($raw, "PK")) {
            return Response::json(['ok' => false, 'error' => 'zip_body_required'], 422);
        }
        $tmp = tempnam(sys_get_temp_dir(), 'mphoto');
        if ($tmp === false || file_put_contents($tmp, $raw) === false) {
            return Response::json(['ok' => false, 'error' => 'tmp_not_writable'], 500);
        }
        try {
            $result = $this->store->importZip(
                $world,
                $mode,
                $tmp,
                (string) $request->query('reset', '') === '1',
                (string) $request->query('done', '') === '1'
            );
        } finally {
            @unlink($tmp);
        }

        return Response::json($result + ['world' => $world, 'mode' => $mode], $result['ok'] ? 200 : 422);
    }

    private function notFound(): Response
    {
        $resp = new Response();
        $resp->setStatusCode(404)->header('Content-Type', 'text/plain; charset=utf-8')
            ->header('Access-Control-Allow-Origin', '*')->setBody('Introuvable');

        return $resp;
    }
}
