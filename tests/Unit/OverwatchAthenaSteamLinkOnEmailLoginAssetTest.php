<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Email\EmailEvents;
use PHPUnit\Framework\TestCase;

final class OverwatchAthenaSteamLinkOnEmailLoginAssetTest extends TestCase
{
    public function testEmailLoginAssociatesSteamAndNotifies(): void
    {
        $root = dirname(__DIR__, 2);
        $svc = (string) file_get_contents($root . '/app/Services/Game/GameAuthService.php');
        $repo = (string) file_get_contents($root . '/app/Repositories/AthenaAccountRepository.php');
        $events = (string) file_get_contents($root . '/app/Services/Email/EmailEvents.php');
        $prefs = (string) file_get_contents($root . '/app/Controllers/Web/AccountController.php');
        $dll = (string) file_get_contents($root . '/mod/UptoDate/COMSPECExtension/GameAuth.cs');
        $cells = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/auth/fn_authStateCells.sqf');
        $poll = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/auth/fn_pollAuth.sqf');

        self::assertContains(EmailEvents::GAME_STEAM_LINKED_MEMBER, EmailEvents::EMAIL_EVENTS);
        self::assertContains(EmailEvents::GAME_STEAM_LINKED_STAFF, EmailEvents::EMAIL_EVENTS);
        self::assertStringContainsString('GAME_STEAM_LINKED_MEMBER', $events);
        self::assertStringContainsString('GAME_STEAM_LINKED_STAFF', $events);

        self::assertStringContainsString('attachSteamFromEmailLogin', $svc);
        self::assertStringContainsString('issueForAccount($account, $body, null, true)', $svc);
        self::assertStringContainsString('listGovernanceEmailsForTenant', $svc);
        self::assertStringContainsString('assignSteamIdIfEmpty', $repo);
        self::assertStringContainsString('Association Steam depuis le jeu', $prefs);
        self::assertStringContainsString('Association Steam d’un opérateur (encadrement)', $prefs);

        self::assertStringContainsString('steam_id', $dll);
        self::assertStringContainsString('_gameSteamNotice', $dll);
        self::assertStringContainsString('TabCell(_gameSteamLinked)', $dll);
        self::assertStringContainsString('TabCell(_gameSteamNotice)', $dll);
        self::assertStringContainsString('"steam_message"', $dll);

        self::assertStringContainsString('["steam_linked", [18] call _fnc_cell]', $cells);
        self::assertStringContainsString('["steam_notice", [19] call _fnc_cell]', $cells);
        self::assertStringContainsString('STEAM        NON ASSOCIÉ', $poll);
        self::assertStringContainsString('_steamNotice', $poll);
        self::assertStringContainsString('Un courriel de confirmation vous a été envoyé', $svc);
    }
}
