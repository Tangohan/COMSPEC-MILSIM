<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakLinkStripHiddenAssetTest extends TestCase
{
    public function testBottomDataBarIsRemovedAndNeverPainted(): void
    {
        $root = dirname(__DIR__, 2);
        $strip = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updateLinkStrip.sqf'
        );
        $cfg = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp'
        );
        $preInit = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/XEH_preInit.sqf'
        );
        $postInit = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/XEH_postInit.sqf'
        );
        $settings = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/ui/settings_page.hpp'
        );
        $bug = (string) file_get_contents(
            $root . '/docs/bugs/2026-09-16-atak-barre-donnees-bas.md'
        );

        self::assertStringContainsString('1.0.158', $cfg);
        self::assertStringContainsString('99871', $strip);
        self::assertStringContainsString('ctrlShow false', $strip);
        self::assertStringContainsString('ctrlDelete', $strip);
        self::assertStringNotContainsString('ctrlCreate', $strip);
        self::assertStringNotContainsString('fiab.', $strip);
        self::assertStringNotContainsString('RscStructuredText', $strip);
        self::assertStringNotContainsString('ctrlSetPosition', $strip);

        self::assertStringContainsString('"comspec_overwatch_show_link_strip"', $preInit);
        self::assertMatchesRegularExpression(
            '/"comspec_overwatch_show_link_strip"[\s\S]{0,800}?false\s*\] call CBA_fnc_addSetting/',
            $preInit
        );
        self::assertStringContainsString('[false, true] call comspec_overwatch_connect_fnc_linkStripApplySetting', $postInit);
        self::assertStringNotContainsString('COMSPEC_LinkStripVisible", "UNSET"', $postInit);

        self::assertStringContainsString('plus affich', $settings);
        self::assertStringContainsString('barre de données', strtolower($bug));
        self::assertStringContainsString('1.0.143', $bug);
        self::assertStringNotContainsString('endpoint', $bug);
    }
}
