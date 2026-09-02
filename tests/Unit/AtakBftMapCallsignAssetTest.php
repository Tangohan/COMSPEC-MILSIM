<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakBftMapCallsignAssetTest extends TestCase
{
    public function testMapLabelsPreferAthenaCallsignOverGroupSlot(): void
    {
        $root = dirname(__DIR__, 2);
        $label = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_bftUnitLabel.sqf'
        );
        $relabel = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_relabelBft.sqf'
        );
        $install = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_installBftLabels.sqf'
        );
        $post = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/XEH_postInitClient.sqf'
        );
        $cfg = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp'
        );
        $note = (string) file_get_contents(
            $root . '/docs/bugs/2026-09-01-atak-carte-indicatif-01.md'
        );

        self::assertStringContainsString('getCallsign', $label);
        self::assertStringContainsString('COMSPEC_CallsignPublic', $label);
        self::assertStringContainsString('cTabBFTmembers', $relabel);
        self::assertStringContainsString('cTabBFTgroups', $relabel);
        self::assertStringContainsString('setMarkerTextLocal', $relabel);
        self::assertStringContainsString('cTab_fnc_updateLists', $install);
        self::assertStringContainsString('athena_relabelBft', $install);
        self::assertStringContainsString('athena_installBftLabels', $post);
        self::assertStringContainsString('athena_bftUnitLabel', $cfg);
        self::assertStringContainsString('1.0.76', $cfg);
        self::assertStringContainsString('COMSPEC_BftLabelMode', $label);
        self::assertStringContainsString('cs_role', $label);
        self::assertStringContainsString('01', $note);
        self::assertStringContainsString('indicatif', $note);
        self::assertStringNotContainsString('endpoint', $note);
    }
}
