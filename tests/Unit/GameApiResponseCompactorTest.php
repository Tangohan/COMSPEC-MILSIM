<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Request;
use App\Core\Response;
use App\Support\GameApiResponseCompactor;
use PHPUnit\Framework\TestCase;

final class GameApiResponseCompactorTest extends TestCase
{
    private const KEYS = ['REQUEST_METHOD', 'REQUEST_URI', 'HTTP_USER_AGENT', 'HTTP_IF_NONE_MATCH', 'HTTP_ACCEPT_ENCODING'];

    protected function tearDown(): void
    {
        foreach (self::KEYS as $k) {
            unset($_SERVER[$k]);
        }
        parent::tearDown();
    }

    private function request(string $path, string $ua = 'COMSPECExtension/2.0.88', ?string $inm = null, ?string $enc = null, string $method = 'GET'): Request
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $path;
        $_SERVER['HTTP_USER_AGENT'] = $ua;
        unset($_SERVER['HTTP_IF_NONE_MATCH'], $_SERVER['HTTP_ACCEPT_ENCODING']);
        if ($inm !== null) {
            $_SERVER['HTTP_IF_NONE_MATCH'] = $inm;
        }
        if ($enc !== null) {
            $_SERVER['HTTP_ACCEPT_ENCODING'] = $enc;
        }

        return new Request();
    }

    public function testUnchangedListAnswers304WithoutBody(): void
    {
        $data = ['messages' => [['id' => 1, 'text' => 'Contact au nord']]];
        $first = Response::json($data);
        GameApiResponseCompactor::apply($this->request('/api/chat?mapId=1'), $first);
        $etag = $first->headerValue('ETag');
        self::assertNotNull($etag);
        self::assertSame(200, $first->statusCode());

        $second = Response::json($data);
        GameApiResponseCompactor::apply($this->request('/api/chat?mapId=1', inm: $etag), $second);
        self::assertSame(304, $second->statusCode());
        self::assertSame('', $second->body());
    }

    public function testChangedListIsSentInFull(): void
    {
        $old = GameApiResponseCompactor::etagFor((string) json_encode(['n' => 1]));
        $resp = Response::json(['n' => 2]);
        GameApiResponseCompactor::apply($this->request('/api/atak/orders', inm: $old), $resp);
        self::assertSame(200, $resp->statusCode());
        self::assertSame('{"n":2}', $resp->body());
    }

    public function testLargeBodyIsGzippedForTheGame(): void
    {
        $rows = [];
        for ($i = 0; $i < 200; $i++) {
            $rows[] = ['id' => $i, 'label' => 'Marqueur ' . $i, 'x' => 1000 + $i, 'y' => 2000];
        }
        $resp = Response::json($rows);
        $plain = $resp->body();
        GameApiResponseCompactor::apply($this->request('/api/atak/markers', enc: 'gzip, deflate'), $resp);
        self::assertSame('gzip', $resp->headerValue('Content-Encoding'));
        self::assertLessThan(strlen($plain), strlen($resp->body()));
        self::assertSame($plain, gzdecode($resp->body()));
    }

    public function testBrowsersAndOtherRoutesAreUntouched(): void
    {
        $resp = Response::json(['a' => 1]);
        GameApiResponseCompactor::apply($this->request('/api/chat', 'Mozilla/5.0'), $resp);
        self::assertNull($resp->headerValue('ETag'));

        $resp2 = Response::json(['a' => 1]);
        GameApiResponseCompactor::apply($this->request('/dashboard'), $resp2);
        self::assertNull($resp2->headerValue('ETag'));

        $resp3 = Response::json(['a' => 1]);
        GameApiResponseCompactor::apply($this->request('/api/atak/operator/sync', method: 'POST'), $resp3);
        self::assertNull($resp3->headerValue('ETag'));

        $resp4 = Response::json(['error' => 'x'], 404);
        GameApiResponseCompactor::apply($this->request('/api/chat'), $resp4);
        self::assertNull($resp4->headerValue('ETag'));
    }
}
