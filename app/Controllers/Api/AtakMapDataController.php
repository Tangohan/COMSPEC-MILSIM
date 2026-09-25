<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Support\AtakRemoteTileGuard;

/**
 * Extraits de carte du théâtre : cache Athena, puis source distante.
 * L’écran du téléphone ne voit que /map-data/{monde}/{z}/{x}/{y}.
 */
final class AtakMapDataController
{
    public function tile(Request $request, array $params = []): Response
    {
        $world = strtolower(preg_replace('/[^a-z0-9_-]/', '', (string) ($params['world'] ?? 'altis')) ?: 'altis');
        $z = max(0, min(7, (int) ($params['z'] ?? 0)));
        $x = max(0, min(4096, (int) ($params['x'] ?? 0)));
        $file = (string) ($params['file'] ?? ($params['y'] ?? '0.png'));
        if (!preg_match('/^(\d+)\.(png|webp)$/i', $file, $m)) {
            return $this->fail(404);
        }
        $y = max(0, min(4096, (int) $m[1]));
        $dir = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'map-tiles'
            . DIRECTORY_SEPARATOR . $world . DIRECTORY_SEPARATOR . $z;
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $local = $dir . DIRECTORY_SEPARATOR . $x . '_' . $y . '.png';
        if (!is_file($local) || filesize($local) < 32) {
            $cdn = function_exists('atak_tile_cdn_base') ? atak_tile_cdn_base() : 'https://jetelain.github.io/Arma3Map';
            $url = $cdn . '/maps/' . rawurlencode($world) . '/' . $z . '/' . $x . '/' . $y . '.png';
            $bin = $this->download($url);
            if ($bin === null || strlen($bin) < 32) {
                return $this->fail(404);
            }
            @file_put_contents($local, $bin);
        }
        $body = (string) file_get_contents($local);
        $resp = new Response();
        $resp->setStatusCode(200)
            ->header('Content-Type', 'image/png')
            ->header('Cache-Control', 'public, max-age=86400')
            ->setBody($body);
        $this->cors($resp);

        return $resp;
    }

    /**
     * Relais same-origin des tuiles Atlas / plan-ops pour la vue relief (canevas).
     * Note: endpoint public (pas de vérification session) car les tuiles sont des requêtes
     * fetch() sans credentials, et les URLs distantes sont déjà publiques (GitHub Pages).
     */
    public function proxy(Request $request, array $params = []): Response
    {
        $raw = (string) ($request->query('u') ?? $request->query('url') ?? '');
        $url = AtakRemoteTileGuard::normalize($raw);
        if ($url === null) {
            return $this->fail(404);
        }
        $host = (string) (parse_url($url, PHP_URL_HOST) ?: '');
        if ($host === '' || !AtakRemoteTileGuard::isPublicInternetIp($host)) {
            return $this->fail(404);
        }
        $ext = strtolower((string) pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        if (!in_array($ext, ['webp', 'png', 'jpg', 'jpeg'], true)) {
            return $this->fail(404);
        }
        $hash = hash('sha256', $url);
        $dir = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'map-tiles'
            . DIRECTORY_SEPARATOR . 'proxy' . DIRECTORY_SEPARATOR . substr($hash, 0, 2);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $local = $dir . DIRECTORY_SEPARATOR . $hash . '.' . $ext;
        if (!is_file($local) || filesize($local) < 32 || (time() - (int) filemtime($local)) > 604800) {
            $bin = $this->download($url, false);
            if ($bin === null || strlen($bin) < 32 || strlen($bin) > 800000) {
                return $this->fail(404);
            }
            @file_put_contents($local, $bin);
        }
        $body = (string) file_get_contents($local);
        $types = [
            'webp' => 'image/webp',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
        ];
        $resp = new Response();
        $resp->setStatusCode(200)
            ->header('Content-Type', $types[$ext] ?? 'image/webp')
            ->header('Cache-Control', 'private, max-age=86400')
            ->setBody($body);
        $this->cors($resp);

        return $resp;
    }

    private function fail(int $code): Response
    {
        $resp = new Response();
        $resp->setStatusCode($code)->header('Content-Type', 'text/plain; charset=utf-8')->setBody('Introuvable');
        $this->cors($resp);

        return $resp;
    }

    private function download(string $url, bool $follow = true): ?string
    {
        if (!function_exists('curl_init')) {
            $raw = @file_get_contents($url);

            return is_string($raw) && $raw !== '' ? $raw : null;
        }
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => $follow,
            CURLOPT_MAXREDIRS => $follow ? 2 : 0,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_PROTOCOLS => defined('CURLPROTO_HTTPS') ? CURLPROTO_HTTPS : 2,
            CURLOPT_REDIR_PROTOCOLS => defined('CURLPROTO_HTTPS') ? CURLPROTO_HTTPS : 2,
            CURLOPT_USERAGENT => 'COMSPEC-Athena-MapCache/1',
        ]);
        $bin = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (!is_string($bin) || $code < 200 || $code >= 300) {
            return null;
        }

        return $bin;
    }

    private function cors(Response $resp): void
    {
        $resp->header('Access-Control-Allow-Origin', '*');
        $resp->header('Access-Control-Allow-Methods', 'GET, OPTIONS');
    }
}
