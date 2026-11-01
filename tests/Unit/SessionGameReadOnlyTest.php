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

    public function testWritesAndPagesKeepAWritableSession(): void
    {
        self::assertFalse(Session::isReadOnlyRequest('POST', '/api/atak/positions', true));
        self::assertFalse(Session::isReadOnlyRequest('POST', '/api/chat', false));
        self::assertFalse(Session::isReadOnlyRequest('GET', '/atak', true));
        self::assertFalse(Session::isReadOnlyRequest('GET', '/atak', false));
        self::assertFalse(Session::isReadOnlyRequest('GET', '/api/game/v1/profile', false));
        self::assertFalse(Session::isReadOnlyRequest('GET', '/api/me/preferences', false));
    }

    public function testBrowserMapPollsDoNotLockTheSession(): void
    {
        foreach (['/api/atak/orders', '/api/units', '/api/chat', '/api/pings', '/api/map-shapes', '/api/recon/images'] as $path) {
            self::assertTrue(Session::isReadOnlyRequest('GET', $path, false), $path);
        }
    }

    public function testPairingAndAdminRoutesStayWritable(): void
    {
        foreach ([true, false] as $key) {
            self::assertFalse(Session::isReadOnlyRequest('GET', '/api/atak/pair/status', $key));
            self::assertFalse(Session::isReadOnlyRequest('GET', '/api/atak/relays/1/abc', $key));
        }
    }

    public function testVersionPollStaysReadOnly(): void
    {
        self::assertTrue(Session::isReadOnlyRequest('GET', '/api/system/version'));
    }
}
