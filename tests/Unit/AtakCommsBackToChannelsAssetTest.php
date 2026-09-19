<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakCommsBackToChannelsAssetTest extends TestCase
{
    public function testMessagerieReturnsToChannelListWithoutLiveFeedOverlay(): void
    {
        $root = dirname(__DIR__, 2);
        $back = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_commsBack.sqf'
        );
        $select = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_commsSelectChannel.sqf'
        );
        $update = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updateComms.sqf'
        );
        $opened = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_commsOnOpened.sqf'
        );
        $footer = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_commsFooter.sqf'
        );
        $chrome = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_commsApplyChrome.sqf'
        );
        $title = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_commsTitleClick.sqf'
        );
        $hide = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_hideForeignPages.sqf'
        );
        $post = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/XEH_postInitClient.sqf'
        );
        $page = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/ui/comms_page.hpp'
        );
        $cfg = (string) file_get_contents(
            $root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp'
        );
        $bug = (string) file_get_contents(
            $root . '/docs/bugs/2026-09-14-atak-messagerie-retour-canaux.md'
        );

        self::assertStringContainsString('COMSPEC_ATAK_Comms_ignoreSelUntil', $back);
        self::assertStringContainsString('COMSPEC_Comms_View", "list"', $back);
        self::assertStringContainsString('lbSetCurSel -1', $back);

        self::assertStringContainsString('COMSPEC_ATAK_Comms_rebuilding', $select);
        self::assertStringContainsString('ignoreSelUntil', $select);

        self::assertStringContainsString('lbSetCurSel -1', $update);
        self::assertStringContainsString('COMSPEC_ATAK_Comms_listSig', $update);
        self::assertStringContainsString('commsApplyChrome', $update);

        self::assertStringContainsString('_fresh', $opened);
        self::assertStringContainsString('commsApplyChrome', $opened);
        self::assertStringContainsString('commsIsOpen', $opened);

        self::assertStringContainsString('Canaux', $footer);
        self::assertStringContainsString('commsBack', $footer);
        self::assertStringContainsString('BCE_fnc_ATAK_LastPage', $footer);

        self::assertStringContainsString('displayCtrl 46600', $chrome);
        self::assertStringContainsString('IcemanGroupCtrl', $chrome);
        self::assertStringContainsString('controlsGroupCtrl 9924', $chrome);
        self::assertStringContainsString('controlsGroupCtrl 9925', $chrome);
        self::assertStringContainsString('idc in [5, 6, 10, 11', $chrome);

        self::assertStringContainsString('commsBack', $title);
        self::assertStringContainsString('toggleSubListMenu', $title);

        self::assertStringContainsString('case "group"', $hide);
        self::assertStringContainsString('atak_message', $hide);

        self::assertStringContainsString('openAtakApp', $post);
        self::assertStringNotContainsString('athena_openComms', $post);
        self::assertStringContainsString('0.2, 0.55, 1.1', $opened);

        self::assertStringContainsString('Retour aux canaux', $page);
        self::assertStringContainsString('athena_commsTitleClick', $page);
        self::assertStringContainsString('athena_commsBack', $page);

        self::assertStringContainsString('COMSPEC_Comms_Menu', $cfg);
        self::assertStringContainsString('athena_commsFooter', $cfg);
        self::assertStringContainsString('1.0.153', $cfg);

        self::assertStringContainsString('liste des canaux', strtolower($bug));
        self::assertStringContainsString('Live Feed', $bug);
        self::assertStringNotContainsString('endpoint', $bug);
    }
}
