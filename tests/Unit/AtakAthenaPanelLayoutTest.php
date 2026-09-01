<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakAthenaPanelLayoutTest extends TestCase
{
    public function testAthenaHomeHasFourScreensNotEightTabs(): void
    {
        $root = dirname(__DIR__, 2);
        $hpp = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/ui/athena_page.hpp'
        );
        $upd = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updatePanel.sqf'
        );
        $home = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_selectHome.sqf'
        );
        $lay = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_applyHomeLayout.sqf'
        );
        $cfg = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp'
        );

        self::assertStringContainsString('text = "Journal"', $hpp);
        self::assertStringContainsString('text = "Alerter"', $hpp);
        self::assertStringContainsString('text = "Rapporter"', $hpp);
        self::assertStringContainsString('text = "Poste"', $hpp);
        self::assertStringContainsString('idc = 9760', $hpp);
        self::assertStringContainsString('idc = 9761', $hpp);
        self::assertStringNotContainsString('NOTIFICATIONS', $hpp);
        self::assertStringNotContainsString('ALERTES RAPIDES', $hpp);
        self::assertStringNotContainsString('COMPTES-RENDUS', $hpp);
        self::assertStringNotContainsString('TX SEEK', $hpp);
        self::assertStringContainsString('Relevé SEEK', $hpp);
        self::assertStringContainsString('COMSPEC_Athena_HomeSection', $home);
        self::assertStringContainsString('applyHomeLayout', $upd);
        self::assertStringContainsString('En liaison', $upd);
        self::assertStringNotContainsString('packet_loss', $upd);
        self::assertStringContainsString('ctrlSetFontHeight', $lay);
        self::assertStringContainsString('ctrlPosition _group', $lay);
        self::assertStringContainsString('sizeEx = QUOTE(0.034)', $hpp);
        self::assertStringContainsString('colorText[] = ATAK_LIST_TEXT', $hpp);
        self::assertStringContainsString('case "alerter"', $lay);
        self::assertStringContainsString('athena_selectHome', $cfg);
        self::assertStringContainsString('1.0.67', $cfg);
    }
}
