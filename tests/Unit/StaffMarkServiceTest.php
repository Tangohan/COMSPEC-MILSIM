<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Platform\StaffMarkService;
use PHPUnit\Framework\TestCase;

final class StaffMarkServiceTest extends TestCase
{
    protected function setUp(): void
    {
        StaffMarkService::seed([7 => StaffMarkService::ADMIN, 9 => StaffMarkService::SUPPORT], [3 => ['ttrd' => StaffMarkService::ADMIN]]);
    }

    protected function tearDown(): void
    {
        StaffMarkService::seed([]);
    }

    public function testAdminNameIsRedWithEaglePastille(): void
    {
        $html = StaffMarkService::name('TTRD <b>', 7);
        self::assertStringContainsString('is-staff--admin', $html);
        self::assertStringContainsString('TTRD &lt;b&gt;', $html);
        self::assertStringContainsString('ath-staff-mark--admin', $html);
        self::assertStringContainsString('aria-label="Administrateur Athena"', $html);
        self::assertStringNotContainsString('<b>', $html);
    }

    public function testSupportNameIsGreen(): void
    {
        self::assertSame(' is-staff is-staff--support', StaffMarkService::nameClass(9));
        self::assertStringContainsString('title="Support Athena"', StaffMarkService::mark(9));
        self::assertStringContainsString('ath-staff-mark--lg', StaffMarkService::mark(9, true));
    }

    public function testOtherMembersAreLeftUntouched(): void
    {
        self::assertSame('Jean', StaffMarkService::name('Jean', 8));
        self::assertSame('', StaffMarkService::mark(0));
        self::assertSame('', StaffMarkService::nameClass(8));
    }

    public function testClientMapExposesOnlyIdsAndCurrentCommunitySlugs(): void
    {
        $map = StaffMarkService::clientMap(3);
        self::assertSame(['7' => 'admin', '9' => 'support'], $map['u']);
        self::assertSame(['ttrd' => 'admin'], $map['s']);
        self::assertSame([], StaffMarkService::clientMap(4)['s']);
    }
}
