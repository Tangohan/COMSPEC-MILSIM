<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakReportsLayoutAssetTest extends TestCase
{
    public function testReportsPageIsRelayoutAndNotHijackedByAthena(): void
    {
        $root = dirname(__DIR__, 2);
        $fix = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_fixReportsLayout.sqf'
        );
        $install = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_installReportsLayout.sqf'
        );
        $resolve = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_resolveAthenaGroup.sqf'
        );
        $upd = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updatePanel.sqf'
        );
        $lay = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_applyHomeLayout.sqf'
        );
        $check = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_ATAK_Check_Layout.sqf'
        );
        $post = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/XEH_postInitClient.sqf'
        );
        $cfg = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp'
        );
        $bug = (string) file_get_contents(
            $root . '/docs/bugs/2026-09-01-atak-reports-chevauchement.md'
        );

        self::assertStringContainsString('Iceman_ATAK_Reports', $fix);
        self::assertStringContainsString('Comptes-rendus', $fix);
        self::assertStringContainsString('Localiser', $fix);
        self::assertStringContainsString('Aucun compte rendu pour le moment.', $fix);
        self::assertStringContainsString('9610', $fix);
        self::assertStringContainsString('9611', $fix);
        self::assertStringContainsString('9613', $fix);

        self::assertStringContainsString('Iceman_fnc_alerts_onOpened', $install);
        self::assertStringContainsString('Iceman_fnc_alerts_updatePanel', $install);
        self::assertStringContainsString('Iceman_fnc_alerts_initButtons', $install);

        self::assertStringContainsString('COMSPEC_ATAK_Athena', $resolve);
        self::assertStringContainsString('resolveAthenaGroup', $upd);
        self::assertStringContainsString('resolveAthenaGroup', $lay);

        self::assertStringContainsString('athena_fixReportsLayout', $cfg);
        self::assertStringContainsString('athena_installReportsLayout', $cfg);
        self::assertStringContainsString('athena_hideForeignPages', $cfg);
        self::assertStringContainsString('athena_resolveAthenaGroup', $cfg);
        self::assertStringContainsString('1.0.77', $cfg);
        self::assertStringContainsString('athena_installReportsLayout', $post);
        self::assertStringContainsString('athena_fixReportsLayout', $check);
        self::assertStringContainsString('athena_hideForeignPages', $check);

        self::assertStringContainsString('Iceman_ATAK_Reports_form', $fix);
        self::assertStringContainsString('EAGLE_DOWN', $fix);
        self::assertStringContainsString('hideForeignPages', $fix);

        self::assertStringContainsString('chevauch', strtolower($bug));
        self::assertStringContainsString('Localiser', $bug);
        self::assertStringNotContainsString('endpoint', $bug);
    }
}
