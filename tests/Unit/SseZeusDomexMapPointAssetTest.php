<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SseZeusDomexMapPointAssetTest extends TestCase
{
    public function testMapPointNeverCallsIsNullOnUncheckedObject(): void
    {
        $root = dirname(__DIR__, 2);
        $reg = (string) file_get_contents(
            $root . '/mod/@COMSPEC_SSE/addons/zeus/functions/fn_registerZenDomexLive.sqf'
        );
        $pick = (string) file_get_contents(
            $root . '/mod/@COMSPEC_SSE/addons/zeus/functions/fn_domexPickObject.sqf'
        );
        $ver = (string) file_get_contents(
            $root . '/mod/@COMSPEC_SSE/addons/main/script_mod.hpp'
        );

        self::assertStringContainsString('COMSPEC_SSE_DomexResolveZenArgs', $reg);
        self::assertStringContainsString('isEqualType objNull', $reg);
        self::assertStringContainsString('(_pos select 0) isEqualType 0', $reg);
        self::assertStringContainsString('COMSPEC_SSE_DomexInvokeOpener', $reg);
        self::assertStringNotContainsString('if (!isNull _obj && {!(_obj isKindOf "CAManBase")}) then { _obj }', $reg);

        self::assertStringContainsString('isEqualType objNull', $pick);
        self::assertStringContainsString('(_pos select 0) isEqualType 0', $pick);

        self::assertStringContainsString('0.7.19', $ver);
    }
}
