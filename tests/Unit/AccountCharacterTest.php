<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\AccountCharacterController as C;
use PHPUnit\Framework\TestCase;

final class AccountCharacterTest extends TestCase
{
    public function testValidation(): void
    {
        $ok = ['first_name' => 'Jean', 'last_name' => 'Martin', 'callsign' => 'Faucon', 'motto' => ''];
        self::assertNull(C::validate($ok));
        self::assertTrue(is_string(C::validate(array_merge($ok, ['last_name' => '']))));
        self::assertTrue(is_string(C::validate(array_merge($ok, ['callsign' => '']))));
        self::assertTrue(is_string(C::validate(array_merge($ok, ['callsign' => str_repeat('a', 51)]))));
    }

    public function testNameRoundTrip(): void
    {
        self::assertSame('Jean Le Gall', C::fullName('Jean', 'Le Gall'));
        self::assertSame(['Jean', 'Le Gall'], C::splitName('Jean Le Gall'));
        self::assertSame(['', ''], C::splitName(''));
    }

    public function testCharacterIsScopedToActiveTenant(): void
    {
        $root = dirname(__DIR__, 2);
        $src = (string) file_get_contents($root . '/app/Controllers/Web/AccountCharacterController.php');
        self::assertStringContainsString('getByUserId($uid, $tenantId)', $src);
        self::assertStringContainsString('ensureRecord($uid, $tenantId)', $src);
        self::assertStringContainsString('Csrf::validate', $src);
        $hub = (string) file_get_contents($root . '/app/Controllers/Web/AccountController.php');
        self::assertStringContainsString('getByUserId($uid, $tenantId)', $hub);
        self::assertStringContainsString('/account/personnage', (string) file_get_contents($root . '/routes/web.php'));
    }
}
