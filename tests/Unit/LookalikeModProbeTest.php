<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Admin\LookalikeModProbe;
use App\Support\SteamWorkshop;
use PHPUnit\Framework\TestCase;

final class LookalikeModProbeTest extends TestCase
{
    public function testOfficialWorkshopPackIsIgnored(): void
    {
        $hit = LookalikeModProbe::classify(
            '@COMSPECOverwatch',
            'COMPSECOverwatch',
            SteamWorkshop::ATAK_NATIVE_ID
        );
        self::assertFalse($hit['matched']);
    }

    public function testSameNameWithoutWorkshopIsFlagged(): void
    {
        $hit = LookalikeModProbe::classify('@COMSPEC_ATAK_Native', 'COMSPEC ATAK', '');
        self::assertTrue($hit['matched']);
        self::assertSame('bad', $hit['severity']);
        self::assertNotEmpty($hit['reasons']);
    }

    public function testSameNameWithForeignWorkshopIsFlagged(): void
    {
        $hit = LookalikeModProbe::classify('@Athena_Clone', 'Athena Overwatch Phone', '9999999999');
        self::assertTrue($hit['matched']);
        self::assertSame('bad', $hit['severity']);
    }

    public function testUnrelatedModIsIgnored(): void
    {
        $hit = LookalikeModProbe::classify('@CBA_A3', 'CBA_A3', SteamWorkshop::CBA_ID);
        self::assertFalse($hit['matched']);
        $hit2 = LookalikeModProbe::classify('@RHSUSAF', 'RHSUSAF', '843577117');
        self::assertFalse($hit2['matched']);
    }

    public function testOfficialIdsIncludeAtakNative(): void
    {
        self::assertContains(SteamWorkshop::ATAK_NATIVE_ID, LookalikeModProbe::officialWorkshopIds());
    }
}
