<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;

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

    private function fail(int $code): Response
    {
        $resp = new Response();
        $resp->setStatusCode($code)->header('Content-Type', 'text/plain; charset=utf-8')->setBody('Introuvable');
        $this->cors($resp);

        return $resp;
    }

    private function download(string $url): ?string
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
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_CONNECTTIMEOUT => 4,
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
