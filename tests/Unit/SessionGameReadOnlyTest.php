<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Session;
use PHPUnit\Framework\TestCase;

final class SessionGameReadOnlyTest extends TestCase
{
    public function testGameGetPollWithKeyIsReadOnly(): void
    {
        self::assertTrue(Session::isReadOnlyRequest('GET', '/api/atak/orders', true));
        self::assertTrue(Session::isReadOnlyRequest('GET', '/api/game/v1/profile', true));
    }

    public function testWithoutGameKeyOrOnWritesSessionStaysWritable(): void
    {
        self::assertFalse(Session::isReadOnlyRequest('GET', '/api/atak/orders', false));
        self::assertFalse(Session::isReadOnlyRequest('POST', '/api/atak/positions', true));
        self::assertFalse(Session::isReadOnlyRequest('GET', '/atak', true));
    }

    public function testPairingAndAdminRoutesStayWritable(): void
    {
        self::assertFalse(Session::isReadOnlyRequest('GET', '/api/atak/pair/status', true));
        self::assertFalse(Session::isReadOnlyRequest('GET', '/api/atak/relays/1/abc', true));
    }

    public function testVersionPollStaysReadOnly(): void
    {
        self::assertTrue(Session::isReadOnlyRequest('GET', '/api/system/version'));
    }
}
