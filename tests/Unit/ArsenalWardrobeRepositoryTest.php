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
        self::assertStringContainsString("equipment/collections", $routes);
        self::assertStringContainsString("equipment/tenues", $routes);
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
        self::assertStringContainsString('cover_image_path', $sqlBody);
    }

    public function testModExtensionAndSqfWired(): void
    {
        $ext = file_get_contents(dirname(__DIR__, 2) . '/mod/UptoDate/COMSPECExtension/Extension.cs');
        self::assertIsString($ext);
        self::assertStringContainsString('ListWardrobes', $ext);
        self::assertStringContainsString('SyncWardrobe', $ext);
        self::assertStringContainsString('GetWardrobe', $ext);
        self::assertStringContainsString('DeleteWardrobe', $ext);
        self::assertStringContainsString('BuildWardrobeSyncJson', $ext);
        self::assertStringContainsString('payload_text', $ext);
        self::assertStringNotContainsString('JsonSerializer.Serialize(syncObj)', $ext);
        self::assertStringContainsString('SimplifyWardrobesListJson', $ext);

        $cfg = file_get_contents(
            dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp'
        );
        self::assertIsString($cfg);
        self::assertStringContainsString('class arsenalPushAll', $cfg);
        self::assertStringContainsString('class arsenalInitOverlay', $cfg);
        self::assertStringContainsString('class arsenalItemPicture', $cfg);
        self::assertStringContainsString('class arsenalOverlayPreview', $cfg);
        self::assertStringContainsString('class arsenalCollectionName', $cfg);
        self::assertStringContainsString('class arsenalDeleteLocal', $cfg);
        self::assertStringContainsString('class arsenalDeleteCloud', $cfg);

        $overlay = (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_arsenalOverlayShow.sqf'
        );
        self::assertStringContainsString('Tenues de la communauté', $overlay);
        self::assertStringContainsString('13 * _gridW', $overlay);
        self::assertStringContainsString('ctrlShow false', $overlay);
        self::assertStringContainsString('Athena', $overlay);
        self::assertStringContainsString('884400', $overlay);
        self::assertStringContainsString('884405', $overlay);
        self::assertStringContainsString('Mes tenues', $overlay);
        self::assertStringContainsString('Envoyer cette', $overlay);
        self::assertStringContainsString('Envoyer toutes', $overlay);
        self::assertStringContainsString('Récupérer cette', $overlay);
        self::assertStringContainsString('Récupérer toutes', $overlay);
        self::assertStringContainsString('Supprimer', $overlay);
        self::assertStringContainsString('BIS_fnc_guiMessage', $overlay);
        self::assertStringContainsString('arsenalDeleteLocal', $overlay);
        self::assertStringContainsString('arsenalDeleteCloud', $overlay);
        self::assertStringContainsString('0.72 * safezoneH', $overlay);
        self::assertStringContainsString('arsenalPullAll', $overlay);
        self::assertStringNotContainsString('safeZoneW - 0.28', $overlay);
        self::assertStringNotContainsString('WARDROBES', $overlay);
        $refresh = (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_arsenalOverlayRefresh.sqf'
        );
        self::assertStringContainsString('aucune tenue dans la communauté', $refresh);
        self::assertStringContainsString('aucune tenue dans cet arsenal', $refresh);
        self::assertStringContainsString('_parts select 7', $refresh);
        self::assertStringContainsString('lbSetPicture', $refresh);
        self::assertStringContainsString('arsenalCollectionName', $refresh);
        self::assertStringContainsString('COMSPEC_ArsenalCollapsedLocal', $refresh);
        self::assertStringContainsString('"▸"', $refresh);
        self::assertStringContainsString('"▾"', $refresh);
        $groupFn = (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_arsenalCollectionName.sqf'
        );
        self::assertStringContainsString('Autres', $groupFn);
        self::assertStringContainsString('find " - "', $groupFn);
        $delLocal = (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_arsenalDeleteLocal.sqf'
        );
        self::assertStringContainsString('ace_arsenal_saved_loadouts', $delLocal);
        self::assertStringContainsString('saveProfileNamespace', $delLocal);
        $delCloud = (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_arsenalDeleteCloud.sqf'
        );
        self::assertStringContainsString('DeleteWardrobe', $delCloud);
        self::assertStringContainsString('tenues que vous avez envoyées', $delCloud);
        $icons = (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_arsenalLoadoutIcons.sqf'
        );
        self::assertStringContainsString('CfgWeapons', $icons);
        self::assertStringContainsString('arsenalItemPicture', $icons);
        $repo = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Repositories/ArsenalWardrobeRepository.php');
        self::assertStringContainsString('owner_label', $repo);
        self::assertStringContainsString('WHERE w.tenant_id = ?', $repo);
        self::assertStringContainsString('LEFT JOIN users u ON u.id = w.user_id', $repo);
        $ext = (string) file_get_contents(dirname(__DIR__, 2) . '/mod/UptoDate/COMSPECExtension/Extension.cs');
        self::assertStringContainsString('owner_label', $ext);
        self::assertFileExists(
            dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_arsenalPushAll.sqf'
        );
        $pushAll = (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_arsenalPushAll.sqf'
        );
        self::assertStringContainsString('COMSPEC_AthenaReady', $pushAll);
        self::assertStringContainsString('COMSPEC_ArsenalPushBusy', $pushAll);
        self::assertStringContainsString('_filterNames', $pushAll);
        self::assertStringContainsString('false, false, "arsenal", false', $pushAll);
        self::assertStringNotContainsString('comspec_overwatch_api_key', $pushAll);
        $pullAll = (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_arsenalPullAll.sqf'
        );
        self::assertStringContainsString('COMSPEC_AthenaReady', $pullAll);
        self::assertStringContainsString('_onlyIds', $pullAll);
        self::assertStringNotContainsString('comspec_overwatch_api_key', $pullAll);
        $outbox = (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_outboxPush.sqf'
        );
        self::assertStringContainsString('syncwardrobe', $outbox);
        self::assertStringNotContainsString('["INFO", "Outbox"', $outbox);
        $pause = (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_pauseManagerShow.sqf'
        );
        self::assertStringNotContainsString('pause-open', $pause);
        $tx = (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_logTransmission.sqf'
        );
        self::assertSame(2, substr_count($tx, '["syncwardrobe", "syncwardrobesbatch"]'));
    }

    public function testNavigationExposesEquipmentHub(): void
    {
        $nav = file_get_contents(dirname(__DIR__, 2) . '/config/navigation.php');
        self::assertIsString($nav);
        self::assertStringContainsString("'path' => 'equipment'", $nav);
        self::assertStringContainsString('Collections, tenues et fiches matériel', $nav);
        self::assertStringNotContainsString('equipment/wardrobes', $nav);
    }
}
