<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakPollBackoffAssetTest extends TestCase
{
    public function testWebClientPausesPollsOnForbiddenAndUnavailable(): void
    {
        $js = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-socket.js');
        self::assertStringContainsString('res.status === 403 || res.status === 429 || res.status === 503', $js);
        self::assertStringContainsString("noteUnavailable(retry, res.status === 403 ? 'forbidden' : 'unavailable')", $js);
        self::assertStringContainsString('SEND_BACKOFF_LADDER_SEC = [45, 75, 150, 300, 600]', $js);
        self::assertStringContainsString('noteSendSuccess()', $js);
        self::assertStringContainsString('Accès au poste momentanément refusé', $js);
        self::assertStringContainsString('Les mises à jour reprendront toutes seules', $js);
        self::assertStringNotContainsString('method === \'GET\' && isApiPaused()', $js);
    }

    public function testServiceWorkerDoesNotFailAtakNavigation(): void
    {
        $sw = (string) file_get_contents(dirname(__DIR__, 2) . '/public/sw.js');
        self::assertStringContainsString('function shouldBypassServiceWorker(request)', $sw);
        self::assertStringContainsString("url.indexOf('/atak')", $sw);
        self::assertStringContainsString("url.indexOf('/api/')", $sw);
        self::assertStringContainsString("url.indexOf('/uploads/')", $sw);
        self::assertStringNotContainsString('Response.error()', $sw);
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
