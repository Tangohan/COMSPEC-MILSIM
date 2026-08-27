<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakPollBackoffAssetTest extends TestCase
{
    public function testWebClientPausesPollsOnForbiddenAndUnavailable(): void
    {
        $js = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-socket.js');
        self::assertStringContainsString('function countsTowardUnavailable(url, method)', $js);
        self::assertStringContainsString('countsTowardUnavailable(url, method)', $js);
        self::assertStringContainsString('res.status === 403 || res.status === 429 || res.status === 503', $js);
        self::assertStringContainsString("noteUnavailable(retry, res.status === 403 ? 'forbidden' : 'unavailable')", $js);
        self::assertStringNotContainsString('if (ours && res && !heartbeat && (res.status === 403', $js);
        self::assertStringContainsString('SEND_BACKOFF_LADDER_SEC = [45, 75, 150, 300, 600]', $js);
        self::assertStringContainsString('noteSendSuccess()', $js);
        self::assertStringContainsString('Accès au poste momentanément refusé', $js);
        self::assertStringContainsString('Les mises à jour reprendront toutes seules', $js);
        self::assertStringNotContainsString('method === \'GET\' && isApiPaused()', $js);
    }

    public function testWebClientDoesNotPauseAllGetReadsOnOneUnavailable(): void
    {
        $js = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-socket.js');
        self::assertStringContainsString('function shouldShortCircuitPaused(url, method)', $js);
        self::assertStringContainsString('function isMutatingMethod(method)', $js);
        self::assertStringContainsString('shouldShortCircuitPaused(url, method)', $js);
        self::assertStringContainsString('isCoreRosterUrl(url) || heartbeat', $js);
        self::assertStringContainsString('function countsTowardUnavailable(url, method)', $js);
        self::assertStringContainsString('Différé · mauvaise connexion', $js);
        self::assertStringNotContainsString('if (ours && isApiPaused() && !heartbeat)', $js);
        self::assertStringNotContainsString('Aucun rendu visuel', $js);
    }

    public function testLaserCodesAndMarkersDoNotWipeOnReadFailure(): void
    {
        $root = dirname(__DIR__, 2);
        $laser = (string) file_get_contents($root . '/public/assets/js/atak-laser-codes.js');
        $markers = (string) file_get_contents($root . '/public/assets/js/atak-map.js');
        $scene = (string) file_get_contents($root . '/public/assets/js/atak-scene-3d.js');
        $air = (string) file_get_contents($root . '/public/assets/js/atak-air-assets.js');

        self::assertStringContainsString('if (!Array.isArray(data)) return;', $laser);
        self::assertStringNotContainsString('Aucun rendu visuel', $laser);
        self::assertStringNotContainsString('Aucun rendu visuel', $markers);
        self::assertStringContainsString('conserver les marqueurs déjà affichés', $markers);
        self::assertStringNotContainsString('Aucun rendu visuel', $scene);
        self::assertStringContainsString('if (!data || !Array.isArray(data.objects))', $scene);
        self::assertStringContainsString('if (!Array.isArray(data)) return;', $air);
        self::assertStringContainsString('.catch(function () {});', $air);
    }

    public function testServiceWorkerDoesNotFailAtakNavigation(): void
    {
        $sw = (string) file_get_contents(dirname(__DIR__, 2) . '/public/sw.js');
        self::assertStringContainsString('function shouldBypassServiceWorker(request)', $sw);
        self::assertStringContainsString("url.indexOf('/atak')", $sw);
        self::assertStringContainsString("url.indexOf('/api/')", $sw);
        self::assertStringContainsString("url.indexOf('/uploads/')", $sw);
        self::assertStringContainsString("url.indexOf('/public/atak')", $sw);
        self::assertStringContainsString("status: 504", $sw);
        self::assertStringNotContainsString('Response.error()', $sw);
        self::assertStringNotContainsString('return fetch(event.request);', $sw);
    }

    public function testApacheDoesNotForbidExtensionOrAtakRoutes(): void
    {
        $ht = (string) file_get_contents(dirname(__DIR__, 2) . '/public/.htaccess');
        self::assertStringContainsString('RewriteCond %{HTTP:X-COMSPEC-KEY} ^$', $ht);
        self::assertStringContainsString('RewriteCond %{HTTP_USER_AGENT} !COMSPECExtension [NC]', $ht);
        self::assertStringContainsString('RewriteCond %{REQUEST_URI} !/api/atak/ [NC]', $ht);
    }

    public function testMapTilesStayInsideWorldBounds(): void
    {
        $map = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-map.js');
        self::assertStringContainsString('noWrap: true', $map);
        self::assertStringContainsString('L.latLngBounds(L.latLng(0, 0), L.latLng(worldSize, worldSize))', $map);
        self::assertStringContainsString('errorTileUrl:', $map);
    }
}
