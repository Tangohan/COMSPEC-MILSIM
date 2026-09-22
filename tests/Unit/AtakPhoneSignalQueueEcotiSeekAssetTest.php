<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakPhoneSignalQueueEcotiSeekAssetTest extends TestCase
{
    public function testPhoneShowsRelayBarsFloorSliderSeekJournalAndQueueBadge(): void
    {
        $root = dirname(__DIR__, 2);
        $cfgA = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp');
        $cfgC = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp');
        $chrome = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_updateAtakLinkChrome.sqf');
        $sig = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_atakSignalState.sqf');
        $relay = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updateRelay.sqf');
        $sheet = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updateBuildingSheet.sqf');
        $setFloor = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_ecotiSetFloor.sqf');
        $cycle = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_ecotiCycleFloor.sqf');
        $bii = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/ui/bii_page.hpp');
        $biiOpen = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_biiOnOpened.sqf');
        $hist = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_seekHistoryPush.sqf');
        $query = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_sseIdentityQuery.sqf');
        $queue = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updateQueueBadge.sqf');
        $pending = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_pendingSyncCount.sqf');
        $strip = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updateLinkStrip.sqf');
        $ext = (string) file_get_contents($root . '/mod/UptoDate/COMSPECExtension/Extension.cs');

        self::assertStringContainsString('versionStr = "1.0.165"', $cfgA);
        self::assertStringContainsString('versionStr = "1.6.9"', $cfgC);
        self::assertStringContainsString('class atakSignalState {}', $cfgC);
        self::assertStringContainsString('class ecotiSetFloor {}', $cfgC);
        self::assertStringContainsString('class seekHistoryPush {}', $cfgC);
        self::assertStringContainsString('class pendingSyncCount {}', $cfgC);
        self::assertStringContainsString('class athena_updateQueueBadge {}', $cfgA);
        self::assertStringContainsString('class athena_updateBuildingSheet {}', $cfgA);

        self::assertStringContainsString('COMSPEC_AtakSigBar_', $chrome);
        self::assertStringContainsString('atakSignalState', $chrome);
        self::assertStringContainsString('Hors portée', $sig);
        self::assertStringContainsString('détruit', $sig);
        self::assertStringContainsString('Brouillé', $sig);
        self::assertStringContainsString('Signal', $relay);

        self::assertStringContainsString('RscXSliderH', $sheet);
        self::assertStringContainsString('Étage', $sheet);
        self::assertStringContainsString('ecotiSetFloor', $sheet);
        self::assertStringContainsString('ecotiSetFloor', $cycle);
        self::assertStringContainsString('COMSPEC_EcotiCutawayFloor', $setFloor);

        self::assertStringContainsString('Journal des identifications', $bii);
        self::assertStringContainsString('idc = 9820', $bii);
        self::assertStringContainsString('COMSPEC_SeekQueryHistory', $biiOpen);
        self::assertStringContainsString('seekHistoryPush', $query);
        self::assertStringContainsString('Confirmé', $hist);

        self::assertStringContainsString('en attente de synchro', $queue);
        self::assertStringContainsString('pendingSyncCount', $queue);
        self::assertStringContainsString('GetPendingQueueCount', $pending);
        self::assertStringContainsString('athena_updateQueueBadge', $strip);
        self::assertStringContainsString('athena_updateBuildingSheet', $strip);

        self::assertStringContainsString('2.0.51', $ext);
        self::assertStringContainsString('GetPendingQueueCount', $ext);
    }
}
