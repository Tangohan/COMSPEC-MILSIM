<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Rbac\AccessRoleColor;
use PHPUnit\Framework\TestCase;

final class AccessRoleColorTest extends TestCase
{
    public function testNormalizeAcceptsPresetsHexAndEmptyOnly(): void
    {
        self::assertSame(['color' => 'blue'], AccessRoleColor::normalize('Blue'));
        self::assertSame(['hex' => '#0f766e'], AccessRoleColor::normalize('#0F766E'));
        self::assertSame([], AccessRoleColor::normalize(''));
        self::assertNull(AccessRoleColor::normalize('red;background:url(x)'));
        self::assertNull(AccessRoleColor::normalize('#12345'));
        self::assertNull(AccessRoleColor::normalize('rgb(1,2,3)'));
    }

    public function testDisplayColorReadsStoredStyle(): void
    {
        self::assertSame('#2563eb', AccessRoleColor::hexFor('{"color":"blue"}'));
        self::assertSame('#0f766e', AccessRoleColor::hexFor(['hex' => '#0f766e', 'color' => 'blue']));
        self::assertNull(AccessRoleColor::hexFor('{"hex":"javascript:1"}'));
        self::assertNull(AccessRoleColor::hexFor(null));
        self::assertSame('amber', AccessRoleColor::inputFor('{"color":"amber"}'));
        self::assertSame('#0f766e', AccessRoleColor::inputFor('{"hex":"#0f766e"}'));
    }
}
