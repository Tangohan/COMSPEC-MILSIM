<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ComspecApiKeyAuth;
use PHPUnit\Framework\TestCase;

final class ComspecApiKeyAuthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ComspecApiKeyAuth::resetForTests();
        unset($_SERVER['HTTP_X_COMSPEC_KEY'], $_SERVER['HTTP_X_ATAK_TOKEN'], $_SERVER['HTTP_AUTHORIZATION']);
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_X_COMSPEC_KEY'], $_SERVER['HTTP_X_ATAK_TOKEN'], $_SERVER['HTTP_AUTHORIZATION']);
        ComspecApiKeyAuth::resetForTests();
        parent::tearDown();
    }

    public function testKeyFromJsonObjectReadsKnownFields(): void
    {
        self::assertSame('abc', ComspecApiKeyAuth::keyFromJsonObject(['api_key' => 'abc']));
        self::assertSame('def', ComspecApiKeyAuth::keyFromJsonObject(['access_key' => ' def ']));
        self::assertSame('ghi', ComspecApiKeyAuth::keyFromJsonObject(['x_comspec_key' => 'ghi']));
        self::assertSame('first', ComspecApiKeyAuth::keyFromJsonObject([
            'api_key' => 'first',
            'access_key' => 'second',
        ]));
        self::assertSame('', ComspecApiKeyAuth::keyFromJsonObject(['foo' => 'bar']));
        self::assertSame('', ComspecApiKeyAuth::keyFromJsonObject([]));
    }

    public function testExtractPresentedKeyReadsComspecHeader(): void
    {
        $_SERVER['HTTP_X_COMSPEC_KEY'] = ' header-key ';
        self::assertSame('header-key', ComspecApiKeyAuth::extractPresentedKey());
    }

    public function testExtractPresentedKeyReadsAtakTokenHeader(): void
    {
        $_SERVER['HTTP_X_ATAK_TOKEN'] = 'atak-token';
        self::assertSame('atak-token', ComspecApiKeyAuth::extractPresentedKey());
    }

    public function testExtractPresentedKeyReadsBearerAuthorization(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer bearer-secret';
        self::assertSame('bearer-secret', ComspecApiKeyAuth::extractPresentedKey());
    }

    public function testExtractPresentedKeyPrefersComspecHeaderOverBearer(): void
    {
        $_SERVER['HTTP_X_COMSPEC_KEY'] = 'from-header';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer from-bearer';
        self::assertSame('from-header', ComspecApiKeyAuth::extractPresentedKey());
    }

    public function testExtractPresentedKeyFallsBackToJsonObjectCache(): void
    {
        $prop = new \ReflectionProperty(ComspecApiKeyAuth::class, 'jsonObjectCache');
        $prop->setValue(null, ['api_key' => 'from-json']);
        self::assertSame('from-json', ComspecApiKeyAuth::extractPresentedKey());
    }

    public function testExtractPresentedKeyIsEmptyWithoutHeaderOrBody(): void
    {
        self::assertSame('', ComspecApiKeyAuth::extractPresentedKey());
    }
}
