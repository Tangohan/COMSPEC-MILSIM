<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Game\GameAuthService;
use PHPUnit\Framework\TestCase;

final class OverwatchReadyIdentityAssetTest extends TestCase
{
    public function testPayloadExposesOperatorIdentityNotBrandingUrl(): void
    {
        $svc = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/Game/GameAuthService.php');
        self::assertStringContainsString("'role' => \$role", $svc);
        self::assertStringContainsString("'function' => \$function", $svc);
        self::assertStringContainsString("'avatar' => \$avatar", $svc);
        self::assertStringContainsString('usableIdentityText', $svc);
        self::assertStringContainsString('user_site_avatar_url', $svc);
        self::assertStringContainsString('personnel_assigned_grade_label', $svc);
        self::assertStringContainsString('personnel_profile_job_roles', $svc);
        self::assertStringNotContainsString("'unit' => \$logo", $svc);
        self::assertStringContainsString("'unit' => \$unit", $svc);
        self::assertStringContainsString('render_url', $svc);
        self::assertStringContainsString("'branding' =>", $svc);
    }

    public function testUsableIdentityTextRejectsInternalAddresses(): void
    {
        self::assertSame('', GameAuthService::usableIdentityText(''));
        self::assertSame('', GameAuthService::usableIdentityText('-'));
        self::assertSame(
            '',
            GameAuthService::usableIdentityText('https://athena.ttrd.fr/public/api/game/v1/branding/render/soar-milsim-group')
        );
        self::assertSame('TA1', GameAuthService::usableIdentityText('TA1'));
        self::assertSame('Sergent', GameAuthService::usableIdentityText('Sergent'));
        self::assertSame('', GameAuthService::usableRoleLabel('community_owner', 'community_owner'));
        self::assertSame('Opérateur', GameAuthService::usableRoleLabel('Opérateur', 'member'));
    }

    public function testExtensionKeepsEmptyAuthCellsStable(): void
    {
        $dll = (string) file_get_contents(dirname(__DIR__, 2) . '/mod/UptoDate/COMSPECExtension/GameAuth.cs');
        self::assertStringContainsString('TabCell(_gameAuthError)', $dll);
        self::assertStringContainsString('CallsignCell(_gameProfileCallsign)', $dll);
        self::assertStringContainsString('IdentityCell(_gameProfileUnit)', $dll);
        self::assertStringContainsString('TabCell(_gameProfileAvatar)', $dll);
        self::assertStringContainsString('IdentityCell(_gameProfileRole)', $dll);
        self::assertStringContainsString('IdentityCell(_gameProfileFunction)', $dll);
        self::assertStringContainsString('LooksLikeInternalUrl', $dll);
        self::assertStringContainsString('ReadProfileText(prof, "callsign")', $dll);
        self::assertStringContainsString('ReadProfileText(prof, "unit")', $dll);
        self::assertStringContainsString('_gameProfileAvatar', $dll);
        self::assertStringNotContainsString('_gameProfileUnit = brand', $dll);
    }

    public function testReadyScreenShowsOperatorIdentityAndHidesMissingPhoto(): void
    {
        $root = dirname(__DIR__, 2);
        $poll = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/auth/fn_pollAuth.sqf');
        $cells = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/auth/fn_authStateCells.sqf');
        $boot = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/auth/fn_applyBootstrap.sqf');
        $display = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/display_athena_auth.hpp');
        $cfg = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp');

        self::assertStringContainsString('class authStateCells', $cfg);
        self::assertStringContainsString('idc = 9431', $display);
        self::assertStringContainsString('show = 0', $display);
        self::assertStringContainsString('Indicatif :', $poll);
        self::assertStringContainsString('Rôle :', $poll);
        self::assertStringContainsString('Grade :', $poll);
        self::assertStringContainsString('Fonction :', $poll);
        self::assertStringContainsString('Communauté :', $poll);
        self::assertStringContainsString('ctrlShow false', $poll);
        self::assertStringContainsString('DownloadBriefingSlideImage', $poll);
        self::assertStringContainsString('_s isEqualTo "-"', $cells);
        self::assertStringNotContainsString('_s = str _s', $poll);
        self::assertStringNotContainsString('_s = str _s', $cells);
        self::assertStringNotContainsString('Indicatif : %4', $poll);
        self::assertStringContainsString('fnc_authStateCells', $boot);
        self::assertStringContainsString('isUsableCallsign', $boot);
        self::assertStringContainsString('splitKeepEmpty', $cells);
        self::assertStringContainsString('Unité :', $poll);
        self::assertStringNotContainsString('htmlLoad _unit', $poll);
    }
}
