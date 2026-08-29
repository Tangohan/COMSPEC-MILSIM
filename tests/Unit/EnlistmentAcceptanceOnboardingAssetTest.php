<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\SteamId;
use PHPUnit\Framework\TestCase;

final class EnlistmentAcceptanceOnboardingAssetTest extends TestCase
{
    public function testProvisioningExposesGuidedOnboardingApi(): void
    {
        $src = (string) file_get_contents(
            dirname(__DIR__, 2) . '/app/Services/Recruitment/EnlistmentAcceptanceProvisioningService.php'
        );
        $controller = (string) file_get_contents(
            dirname(__DIR__, 2) . '/app/Controllers/Admin/AdminRecruitmentsController.php'
        );
        $routes = (string) file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');
        $view = (string) file_get_contents(
            dirname(__DIR__, 2) . '/views/admin/recruitments/onboarding.php'
        );

        self::assertStringContainsString('completeAcceptanceOnboarding', $src);
        self::assertStringContainsString('needsAcceptanceOnboarding', $src);
        self::assertStringContainsString('steam_profile', $src);
        self::assertStringContainsString('acceptance_onboarding', $controller);
        self::assertStringContainsString("'/onboarding'", $routes);
        self::assertStringContainsString('steam_profile', $view);
        self::assertStringContainsString('Finaliser l’intégration', $view);
        self::assertFileExists(dirname(__DIR__, 2) . '/public/assets/css/recruitment_onboarding.css');
    }

    public function testSteamProfileUrlNormalizesToSteamId64(): void
    {
        $sid = SteamId::normalize('https://steamcommunity.com/profiles/76561198000000000');
        self::assertSame('76561198000000000', $sid);
    }
}
