<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakZeusGroupIdCallsignAssetTest extends TestCase
{
    public function testGroupIdPrefillsFromOperatorCallsign(): void
    {
        $root = dirname(__DIR__, 2);
        $apply = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_applyGroupIdFromCallsign.sqf'
        );
        $fill = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_fillZeusGroupId.sqf'
        );
        $set = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_setCallsign.sqf'
        );
        $inject = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_zeusAttributesInject.sqf'
        );
        $register = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_registerZeusAttributeButtons.sqf'
        );
        $xeh = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/CfgEventHandlers.hpp'
        );
        $cfg = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp'
        );
        $bug = (string) file_get_contents(
            $root . '/docs/bugs/2026-09-03-groupe-identifiant-indicatif.md'
        );

        self::assertStringContainsString('class applyGroupIdFromCallsign', $cfg);
        self::assertStringContainsString('class fillZeusGroupId', $cfg);
        self::assertStringContainsString('1.5.16', $cfg);

        self::assertStringContainsString('setGroupIdGlobal', $apply);
        self::assertStringContainsString('profileName', $apply);
        self::assertStringContainsString('isUsableCallsign', $apply);
        self::assertStringNotContainsString('setGroupIdGlobal', $set);
        self::assertStringContainsString('applyGroupIdFromCallsign', $set);

        self::assertStringContainsString('identifiant du groupe', $fill);
        self::assertStringContainsString('ctrlSetText _cs', $fill);
        self::assertStringContainsString('fillZeusGroupId', $inject);
        self::assertStringContainsString('fillZeusGroupId', $register);
        self::assertStringContainsString('fillZeusGroupId', $xeh);

        self::assertStringContainsString('indicatif', strtolower($bug));
        self::assertStringNotContainsString('endpoint', $bug);
    }
}
