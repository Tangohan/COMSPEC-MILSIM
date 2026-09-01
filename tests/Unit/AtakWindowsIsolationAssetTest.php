<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakWindowsIsolationAssetTest extends TestCase
{
    public function testAthenaDoesNotStayPaintedOverOtherAtakApps(): void
    {
        $root = dirname(__DIR__, 2);
        $hide = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_hideForeignPages.sqf'
        );
        $opened = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_onOpened.sqf'
        );
        $home = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_applyHomeLayout.sqf'
        );
        $panel = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updatePanel.sqf'
        );
        $note = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_noteOnOpened.sqf'
        );
        $show = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_intelNoteShow.sqf'
        );
        $open = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_openNote.sqf'
        );
        $overlay = (string) file_get_contents(
            $root . '/docs/bugs/2026-09-01-atak-athena-superposition.md'
        );
        $rens = (string) file_get_contents(
            $root . '/docs/bugs/2026-09-01-atak-rens-ne-souvre-pas.md'
        );

        self::assertStringContainsString('comspec_atak_athena', $hide);
        self::assertStringContainsString('iceman_atak_reports', $hide);
        self::assertStringContainsString('comspec_atak_note', $hide);
        self::assertStringContainsString('DESKTOP', $hide);

        self::assertStringContainsString('hideForeignPages', $opened);
        self::assertStringContainsString('showMenu', $home);
        self::assertStringContainsString('showMenu', $panel);
        self::assertStringContainsString('ctrlShown', $home);

        self::assertStringContainsString('uiSleep 0.45', $note);
        self::assertStringContainsString('hideForeignPages', $note);
        self::assertStringContainsString('createDisplay', $show);
        self::assertStringContainsString('intelNoteShow', $open);
        self::assertStringContainsString('Rédiger une fiche', $show);

        self::assertStringContainsString('superpos', strtolower($overlay));
        self::assertStringContainsString('RENS', $rens);
        self::assertStringNotContainsString('endpoint', $overlay);
        self::assertStringNotContainsString('endpoint', $rens);
    }
}
