<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Admin\DataRegistryService;
use PHPUnit\Framework\TestCase;

final class DataRegistryPageAssetTest extends TestCase
{
    public function testDatasetsIncludeLookalikesAndUsers(): void
    {
        self::assertArrayHasKey('users', DataRegistryService::DATASETS);
        self::assertArrayHasKey('lookalikes', DataRegistryService::DATASETS);
        self::assertSame('Mods proches', DataRegistryService::DATASETS['lookalikes']);
    }

    public function testViewExposesSteamToolAndLookalikesTab(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root . '/views/admin/system/data_registry.php');
        self::assertStringContainsString('adr-steam-form', $view);
        self::assertStringContainsString('donnees/outils', $view);
        self::assertStringContainsString('adr-goto-lookalikes', $view);
        self::assertStringContainsString('adresse de profil Steam', $view);

        $js = (string) file_get_contents($root . '/public/assets/js/admin-data-registry.js');
        self::assertStringContainsString('adr-steam-form', $js);
        self::assertStringContainsString('lookalikes', $js);
        self::assertStringContainsString('resolved_steam', $js);

        $routes = (string) file_get_contents($root . '/routes/web.php');
        self::assertStringContainsString("donnees/outils", $routes);
    }

    public function testSteamUrlNormalizesOfflineWithoutApi(): void
    {
        $svc = new DataRegistryService();
        $res = $svc->resolveSteamQuery('https://steamcommunity.com/profiles/76561198000000000');
        self::assertSame('76561198000000000', $res['steam']);
        self::assertNotSame('', $res['note']);
    }
}
