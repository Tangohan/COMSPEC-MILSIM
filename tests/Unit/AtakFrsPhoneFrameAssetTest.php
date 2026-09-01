<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakFrsPhoneFrameAssetTest extends TestCase
{
    public function testFrsEditorFitsPhonePanelWithReadableSubmit(): void
    {
        $root = dirname(__DIR__, 2);
        $hpp = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/display_intel_note.hpp'
        );
        $geom = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_intelNoteApplyGeometry.sqf'
        );
        $show = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_intelNoteShow.sqf'
        );
        $desk = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_installDesktopShortcut.sqf'
        );
        $cfg = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp'
        );
        $note = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/ui/note_page.hpp'
        );
        $post = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/XEH_postInitClient.sqf'
        );
        $bug = (string) file_get_contents(
            $root . '/docs/bugs/2026-09-01-atak-frs-texte-trop-petit.md'
        );

        self::assertStringContainsString('#define NT_SHEET_W  (NT_W)', $hpp);
        self::assertStringContainsString('sizeEx = 0.036', $hpp);
        self::assertStringContainsString('VALIDER ET TRANSMETTRE', $hpp);
        self::assertStringContainsString("size='0.72'", $hpp);

        self::assertStringContainsString('displayCtrl 4660', $geom);
        self::assertStringContainsString('_onPhone', $geom);

        self::assertStringContainsString('createDisplay "COMSPEC_IntelNote_Dialog"', $show);
        self::assertStringContainsString('intelNoteApplyGeometry', $show);

        self::assertStringContainsString('FRS/FRM', $desk);
        self::assertStringContainsString('atak_note', $desk);
        self::assertStringContainsString('FRS/FRM', $cfg);
        self::assertStringContainsString('Rédiger une fiche', $note);
        self::assertStringContainsString('AtakNote', $post);

        self::assertStringContainsString('Valider et transmettre', $bug);
        self::assertStringNotContainsString('endpoint', $bug);
    }
}
