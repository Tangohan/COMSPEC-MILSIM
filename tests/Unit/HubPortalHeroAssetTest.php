<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class HubPortalHeroAssetTest extends TestCase
{
    public function testHubPageHasMilitaryHeroAndAnnouncements(): void
    {
        $hub = (string) file_get_contents(dirname(__DIR__, 2) . '/views/hub/index.php');
        $ctrl = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Web/HubController.php');
        $home = (string) file_get_contents(dirname(__DIR__, 2) . '/views/home/index.php');
        $marketing = (string) file_get_contents(dirname(__DIR__, 2) . '/views/layout/marketing.php');
        $frHome = (string) file_get_contents(dirname(__DIR__, 2) . '/lang/fr/home.php');

        self::assertStringContainsString('hub-hero', $hub);
        self::assertStringContainsString('fog-team.jpg', $hub);
        self::assertStringContainsString('announce_tiles.php', $hub);
        self::assertStringContainsString('hub_announce_items', $ctrl);
        self::assertStringContainsString('AlertPresentationService', $ctrl);
        self::assertMatchesRegularExpression(
            "/url\\('dashboard'\\)\\s*\\?>[^>]*>\\s*<\\?= htmlspecialchars\\(__\\('common\\.ops'\\)/",
            $home
        );
        self::assertDoesNotMatchRegularExpression(
            "/url\\('hub'\\)\\s*\\?>[^>]*>\\s*<\\?= htmlspecialchars\\(__\\('common\\.ops'\\)/",
            $home
        );
        self::assertMatchesRegularExpression(
            "/url\\('dashboard'\\)[^\\n]*site-marketing__ops[^\\n]*__\\('common\\.ops'\\)/",
            $marketing
        );
        self::assertStringContainsString('Athena Comspec — Portail MILSIM', $frHome);
        self::assertStringNotContainsString('Athena Compsec', $frHome);
    }
}
