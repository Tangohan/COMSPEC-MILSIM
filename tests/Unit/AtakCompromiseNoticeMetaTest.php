<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\AtakCompromiseNoticeMeta;
use PHPUnit\Framework\TestCase;

final class AtakCompromiseNoticeMetaTest extends TestCase
{
    public function testCompromiseNoticeCarriesSourceStateAndRevokedCount(): void
    {
        $meta = AtakCompromiseNoticeMeta::build(
            ['kind' => 'compromise', 'terminal_uid' => 'ANDROID-1234', 'created_at' => '2026-10-19 12:56:10'],
            [
                ['id' => 9, 'source' => 'web', 'state' => 'captured', 'certificate_ids' => null, 'actor_label' => '', 'created_at' => '2026-10-19 13:30:00'],
                ['id' => 7, 'source' => 'admin', 'state' => 'compromised', 'certificate_ids' => '3,4', 'actor_label' => 'TTRD', 'created_at' => '2026-10-19 12:56:09'],
            ]
        );

        self::assertSame('terminal=ANDROID-1234;source=admin;state=compromised;revoked=2;ref=E7;by=TTRD', $meta);
    }

    public function testCommandPostEventRecordedJustAfterTheNoticeIsKept(): void
    {
        $meta = AtakCompromiseNoticeMeta::build(
            ['kind' => 'compromise', 'terminal_uid' => 'T1', 'created_at' => '2026-10-19 12:56:10'],
            [['id' => 3, 'source' => 'web', 'state' => 'captured', 'certificate_ids' => '', 'actor_label' => 'PC; zone=1', 'created_at' => '2026-10-19 12:56:11']]
        );

        self::assertSame('terminal=T1;source=web;state=captured;revoked=0;ref=E3;by=PC  zone 1', $meta);
    }

    public function testOtherNoticesHaveNoMeta(): void
    {
        self::assertSame('', AtakCompromiseNoticeMeta::build(['kind' => 'message', 'terminal_uid' => 'T1'], []));
        self::assertSame('terminal=T1', AtakCompromiseNoticeMeta::build(['kind' => 'compromise', 'terminal_uid' => 'T1'], []));
    }

    public function testNativeAtakShowsTheCompromisedTerminalInsideThePhone(): void
    {
        $root = dirname(__DIR__, 2) . '/';
        $main = $root . 'mod/COMSPEC_ATAK_Native/Sources/addons/main/';
        self::assertStringContainsString('class compromiseView {}', (string) file_get_contents($main . 'config.cpp'));
        // COMSPEC Link transmet la méta (7e colonne), l'avis met l'ATAK en mode compromis ou le lève.
        self::assertStringContainsString('Clean(m, "meta")', (string) file_get_contents($root . 'mod/UptoDate/COMSPECExtension/Extension.cs'));
        $notices = (string) file_get_contents($main . 'functions/shell/fn_adminNotices.sqf');
        self::assertStringContainsString('["set", createHashMapFromArray [["origin", "manual"]', $notices);
        self::assertStringContainsString('case "compromise_cleared": { ["clear"] call comspec_atak_native_fnc_compromiseView; };', $notices);
        // Capture validée sur le terrain : origine automatique.
        self::assertStringContainsString('["origin", "auto"], ["source", "field"]', (string) file_get_contents($main . 'functions/shell/fn_compromise.sqf'));
        // Logo COMSPEC Link rouge et carte dégradée.
        self::assertStringContainsString('comspec_atak_native_fnc_compromiseView', (string) file_get_contents($main . 'functions/ui/fn_statusUpdate.sqf'));
        self::assertStringContainsString('["draw", _map] call comspec_atak_native_fnc_compromiseView', (string) file_get_contents($main . 'functions/map/fn_mapOnDraw.sqf'));
        self::assertStringContainsString('"meta" => $meta', str_replace("'", '"', (string) file_get_contents($root . 'app/Controllers/Api/AtakApiController.php')));
    }
}
