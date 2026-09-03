<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakAthenaJournalLogAssetTest extends TestCase
{
    public function testJournalScreenShowsSessionLogAccountAndPhotoTools(): void
    {
        $root = dirname(__DIR__, 2);
        $hpp = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/ui/athena_page.hpp'
        );
        $upd = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updatePanel.sqf'
        );
        $lay = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_applyHomeLayout.sqf'
        );
        $log = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_collectSessionLog.sqf'
        );
        $force = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_forcePhotoSend.sqf'
        );
        $folder = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_showPhotoFolder.sqf'
        );
        $filter = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_selectFilter.sqf'
        );
        $cfg = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp'
        );
        $connect = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp'
        );
        $respawn = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_onPlayerRespawn.sqf'
        );
        $med = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_selfCancelMedicalAlert.sqf'
        );
        $ext = (string) file_get_contents(
            $root . '/mod/UptoDate/COMSPECExtension/Extension.cs'
        );
        $bug = (string) file_get_contents(
            $root . '/docs/bugs/2026-09-01-athena-tuile-journal-vide.md'
        );

        self::assertStringContainsString('text = "Envoyer photos"', $hpp);
        self::assertStringContainsString('text = "Dossier photos"', $hpp);
        self::assertStringContainsString('idc = 9765', $hpp);
        self::assertStringContainsString('idc = 9766', $hpp);
        self::assertStringContainsString('athena_forcePhotoSend', $hpp);
        self::assertStringContainsString('athena_showPhotoFolder', $hpp);

        self::assertStringContainsString('athena_collectSessionLog', $upd);
        self::assertStringContainsString('comspec_profile_name', $upd);
        self::assertStringContainsString('getUnitsList', $upd);
        self::assertStringContainsString('opérateurs en liaison', $upd);
        self::assertStringContainsString('Compte :', $upd);
        self::assertStringContainsString('Compte non connecté', $upd);
        self::assertStringContainsString('Liaison et envois', $upd);

        self::assertStringContainsString('9770', $lay);
        self::assertStringContainsString('9771', $lay);

        self::assertStringContainsString('GetLogTail', $log);
        self::assertStringContainsString('COMSPEC_DiagLog', $log);
        self::assertStringContainsString('manifeste de vol', $log);
        self::assertStringContainsString('COMSPEC_Athena_LogFilter', $filter);

        self::assertStringContainsString('bridgeIcemanPhoto', $force);
        self::assertStringContainsString('COMSPEC_Athena_PhotoForceBusy', $force);
        self::assertStringContainsString('GetPhotoSaveDir', $folder);
        self::assertStringContainsString('copyToClipboard', $folder);
        self::assertStringContainsString('Arma 3 - COMSPEC', $folder);

        self::assertStringContainsString('COMSPEC_RespawnGraceLoggedAt', $respawn);
        self::assertStringContainsString('COMSPEC_MedicalSilentClosedId', $med);

        self::assertStringContainsString('GetScreenshotDirs', $ext);
        self::assertStringContainsString('GetPhotoSaveDir', $ext);
        self::assertStringContainsString('ExtensionVersion = "1.18.8"', $ext);

        self::assertStringContainsString('1.0.78', $cfg);
        self::assertStringContainsString('athena_collectSessionLog', $cfg);
        self::assertStringContainsString('athena_forcePhotoSend', $cfg);
        self::assertStringContainsString('athena_showPhotoFolder', $cfg);
        self::assertStringContainsString('1.5.14', $connect);

        self::assertStringContainsString('journal', strtolower($bug));
        self::assertStringNotContainsString('endpoint', $bug);
    }
}
