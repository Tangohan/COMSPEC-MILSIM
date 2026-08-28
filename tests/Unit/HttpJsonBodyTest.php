<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ComspecApiKeyAuth;
use App\Support\HttpJsonBody;
use PHPUnit\Framework\TestCase;

final class HttpJsonBodyTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['CONTENT_TYPE'], $_SERVER['HTTP_CONTENT_TYPE'], $_SERVER['CONTENT_LENGTH']);
        $_POST = [];
        $_FILES = [];
        ComspecApiKeyAuth::resetForTests();
        parent::tearDown();
    }

    public function testMultipartIsDetectedFromContentType(): void
    {
        $_SERVER['CONTENT_TYPE'] = 'multipart/form-data; boundary=----WebKitFormBoundary';
        self::assertTrue(HttpJsonBody::isMultipart());
        self::assertSame('', HttpJsonBody::rawJson());
    }

    public function testMultipartIsDetectedFromFiles(): void
    {
        $_FILES['image'] = ['name' => 'shot.png', 'tmp_name' => '/tmp/x', 'error' => 0, 'size' => 12];
        self::assertTrue(HttpJsonBody::isMultipart());
    }

    public function testPostFieldsReturnedForMultipartPeek(): void
    {
        $_SERVER['CONTENT_TYPE'] = 'multipart/form-data; boundary=x';
        $_POST = ['author' => 'N-10', 'session_token' => 'abc'];
        ComspecApiKeyAuth::resetForTests();
        $peeked = ComspecApiKeyAuth::peekJsonObject();
        self::assertSame('N-10', $peeked['author']);
        self::assertSame('abc', $peeked['session_token']);
    }

    public function testOversizedDeclaredLengthIsRejected(): void
    {
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $_SERVER['CONTENT_LENGTH'] = (string) (HttpJsonBody::MAX_BYTES + 10);
        self::assertSame('', HttpJsonBody::rawJson());
    }
}
