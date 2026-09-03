<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Terminal SEEK — page Contexte : champs pleine largeur, plus grands, contrastés.
 */
final class SseSeekContexteLayoutAssetTest extends TestCase
{
    public function testContexteStacksFullWidthReadableFields(): void
    {
        $hpp = (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/display_sse_person.hpp'
        );

        self::assertStringContainsString('#define ROW_H       (0.0315 * safezoneH)', $hpp);
        self::assertStringContainsString('#define SEEK_FONT   0.024', $hpp);
        self::assertStringContainsString('#define SEEK_EDIT_EX 0.028', $hpp);
        self::assertStringContainsString('#define CTX_MARKS_H', $hpp);
        self::assertStringContainsString("color='#c8eadc'>STATUT", $hpp);
        self::assertStringContainsString('idc = 9505;', $hpp);
        self::assertStringContainsString('w = IN_W; h = FIELD_H;', $hpp);
        self::assertStringContainsString('y = ROW(1);', $hpp);
        self::assertStringContainsString('idc = 9506; y = (ROW(1) + LBL_H);', $hpp);
        self::assertStringContainsString('style = 16;', $hpp);
        self::assertStringContainsString('h = CTX_MARKS_H;', $hpp);
        self::assertStringContainsString('colorBackground[] = {0.055, 0.102, 0.098, 0.98};', $hpp);
        self::assertStringContainsString('colorSelectBackground[] = {0.14, 0.42, 0.36, 1};', $hpp);
        self::assertStringContainsString('font = "PuristaMedium";', $hpp);
        self::assertStringNotContainsString("color='#8aa0b0'>STATUT", $hpp);
        self::assertStringNotContainsString('w = HALF_W; h = (ROW_H - LBL_H);', $hpp);
    }

    public function testConnectPackBumpedForSeekReadability(): void
    {
        $cfg = (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp'
        );
        $page = (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_sseTerminalPage.sqf'
        );

        self::assertStringContainsString('1.5.16', $cfg);
        self::assertStringContainsString("size='0.64' align='center' color='#f2fff8'", $page);
    }
}
