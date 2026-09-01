<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakCommunitySwitchAssetTest extends TestCase
{
    public function testAtakHeaderExposesCommunityAndEmptyHint(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/atak.php');
        $js = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-units.js');
        $ctrl = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Web/CommunityController.php');

        self::assertStringContainsString('atak-header-cluster--community', $view);
        self::assertStringContainsString('Communauté', $view);
        self::assertStringContainsString("name=\"return_to\"", $view);
        self::assertStringContainsString('value="atak"', $view);
        self::assertStringContainsString('ATAK_MULTI_COMMUNITY', $view);
        self::assertStringContainsString('plusieurs communautés', $view);

        self::assertStringContainsString('ATAK_MULTI_COMMUNITY', $js);
        self::assertStringContainsString('indiquée en haut', $js);

        self::assertStringContainsString("returnTo === 'atak'", $ctrl);
        self::assertStringContainsString('Vous êtes maintenant sur', $ctrl);
        $dash = (string) file_get_contents(dirname(__DIR__, 2) . '/views/partials/dashboard_idstrip.php');
        self::assertStringContainsString('Vous êtes sur', $dash);
        self::assertStringContainsString('return_to', $dash);
        self::assertStringContainsString('value="atak"', $dash);
    }

    public function testControllerPassesMembershipsToAtakView(): void
    {
        $php = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Web/AtakController.php');
        self::assertStringContainsString("'atakTenantLabel'", $php);
        self::assertStringContainsString("'atakCommunityMemberships'", $php);
        self::assertStringContainsString('filterSwitchableTenantsForUser', $php);
    }
}
