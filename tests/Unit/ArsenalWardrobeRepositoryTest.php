<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\ArsenalWardrobeRepository;
use PHPUnit\Framework\TestCase;

final class ArsenalWardrobeRepositoryTest extends TestCase
{
    public function testSlugifyNormalizesNames(): void
    {
        self::assertSame('assaut-nocturne', ArsenalWardrobeRepository::slugify('Assaut nocturne'));
        self::assertSame('loadout-a-1', ArsenalWardrobeRepository::slugify('Loadout A / 1'));
        self::assertSame('wardrobe', ArsenalWardrobeRepository::slugify('@@@'));
    }

    public function testWardrobeRoutesRegistered(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');
        self::assertIsString($routes);
        self::assertStringContainsString("/api/atak/wardrobes", $routes);
        self::assertStringContainsString("/api/atak/wardrobes/sync", $routes);
        self::assertStringContainsString("/api/atak/wardrobe-collections", $routes);
        self::assertStringContainsString("equipment/wardrobes", $routes);
        self::assertStringContainsString('AtakWardrobeApiController', $routes);
        self::assertStringContainsString('ArsenalWardrobeController', $routes);
    }

    public function testMigrationBootstrapExists(): void
    {
        $path = dirname(__DIR__, 2) . '/bootstrap/arsenal_wardrobe_migration.php';
        self::assertFileExists($path);
        $sql = dirname(__DIR__, 2) . '/migrations/arsenal_wardrobe.sql';
        self::assertFileExists($sql);
        $sqlBody = file_get_contents($sql);
        self::assertIsString($sqlBody);
        self::assertStringContainsString('arsenal_wardrobes', $sqlBody);
        self::assertStringContainsString('arsenal_equipment_collections', $sqlBody);
    }

    public function testModExtensionAndSqfWired(): void
    {
        $ext = file_get_contents(dirname(__DIR__, 2) . '/mod/UptoDate/COMSPECExtension/Extension.cs');
        self::assertIsString($ext);
        self::assertStringContainsString('ListWardrobes', $ext);
        self::assertStringContainsString('SyncWardrobe', $ext);
        self::assertStringContainsString('GetWardrobe', $ext);
        self::assertStringContainsString('SimplifyWardrobesListJson', $ext);

        $cfg = file_get_contents(
            dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp'
        );
        self::assertIsString($cfg);
        self::assertStringContainsString('class arsenalPushAll', $cfg);
        self::assertStringContainsString('class arsenalInitOverlay', $cfg);

        self::assertFileExists(
            dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_arsenalOverlayShow.sqf'
        );
        self::assertFileExists(
            dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_arsenalPushAll.sqf'
        );
    }

    public function testNavigationExposesWardrobesPage(): void
    {
        $nav = file_get_contents(dirname(__DIR__, 2) . '/config/navigation.php');
        self::assertIsString($nav);
        self::assertStringContainsString('equipment/wardrobes', $nav);
    }
}
