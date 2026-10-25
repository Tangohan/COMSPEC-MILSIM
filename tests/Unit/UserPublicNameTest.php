<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\UserPublicName;
use PHPUnit\Framework\TestCase;

final class UserPublicNameTest extends TestCase
{
    public function testNormalizeStripsControlsAndTags(): void
    {
        self::assertSame('Ghost 21', UserPublicName::normalize("  Ghost \t 21\x00 "));
        self::assertSame('bscript', UserPublicName::normalize('<b>script'));
        self::assertSame(UserPublicName::MAX, mb_strlen(UserPublicName::normalize(str_repeat('é', 60))));
    }

    public function testValidation(): void
    {
        self::assertNull(UserPublicName::validationError(''));
        self::assertSame('site.public_name_too_short', UserPublicName::validationError('a'));
        self::assertNull(UserPublicName::validationError('Ghost'));
    }

    public function testSqlNeverFallsBackToRealName(): void
    {
        $sql = UserPublicName::sql('u', 'Membre');
        self::assertStringContainsString('u.callsign', $sql);
        self::assertStringNotContainsString('display_name', $sql);
        self::assertStringContainsString("'Membre'", $sql);
    }
}
