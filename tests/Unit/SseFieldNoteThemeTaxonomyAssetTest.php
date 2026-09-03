<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Les fiches FRS / FRC / FRM exposent 17 thèmes colorés, côté portail et jeu.
 */
final class SseFieldNoteThemeTaxonomyAssetTest extends TestCase
{
    public function testAtakComposerRendersAllThemeTones(): void
    {
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/atak.css');
        self::assertStringContainsString('.frs-tone-critical', $css);
        self::assertStringContainsString('.frs-tone-warning', $css);
        self::assertStringContainsString('.frs-tone-caution', $css);
        self::assertStringContainsString('.frs-tone-stable', $css);
        self::assertStringContainsString('.frs-tone-info', $css);
        self::assertStringContainsString('frs-source-grid', (string) file_get_contents(dirname(__DIR__, 2) . '/views/atak.php'));
        self::assertStringContainsString('frs-title', (string) file_get_contents(dirname(__DIR__, 2) . '/views/atak.php'));
    }

    public function testGameDialogHasSeventeenThemeToggles(): void
    {
        $hpp = dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/display_intel_note.hpp';
        if (!is_file($hpp)) {
            self::markTestSkipped('Sources du mod absentes de cette copie de travail.');
        }
        $contents = (string) file_get_contents($hpp);
        self::assertStringContainsString('idc = 9676', $contents);
        self::assertStringContainsString('[16] call comspec_overwatch_connect_fnc_intelNoteToggleTheme', $contents);
        self::assertStringContainsString('idc = 9658', $contents);
        self::assertStringContainsString("size='0.68'", $contents);
        self::assertStringContainsString("size='0.82'", $contents);
        self::assertStringNotContainsString("size='0.40'", $contents);
        self::assertStringContainsString('1.5.14', (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp'
        ));
    }

    public function testJsSubmitsSourceAndTitle(): void
    {
        $js = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-frs.js');
        self::assertStringContainsString('intel_source', $js);
        self::assertStringContainsString('frs_intel_source', $js);
        self::assertStringContainsString('#frs-title', $js);
        self::assertStringContainsString('default_urgency', $js);
    }
}
