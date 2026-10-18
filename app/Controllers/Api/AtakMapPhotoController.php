<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Support\AtakMapPhotoStore;
use App\Support\ComspecApiKeyAuth;

/**
 * Fond « Photo COMSPEC » (capture de la carte du mod) : tuiles publiques et envoi par la clé plateforme.
 *   GET  /map-photo/{carte}/{rendu}/{z}/{x}/{y}.webp
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
        if ($world === null || $mode === null || preg_match('/^(\d{1,7})\.webp$/', $file, $m) !== 1) {
            return $this->notFound();
        }
        $path = $this->store->tileFile($world, $mode, (int) ($params['z'] ?? -1), (int) ($params['x'] ?? -1), (int) $m[1]);
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
            ->header('Content-Type', 'image/webp')
            ->header('Cache-Control', 'public, max-age=86400')
            ->header('ETag', $etag)
            ->header('Access-Control-Allow-Origin', '*')
            ->setBody((string) file_get_contents($path));

        return $resp;
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
