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

    public function testGameResendNeverWipesSiteFields(): void
    {
        $repo = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Repositories/ArsenalWardrobeRepository.php');
        // Le mod n’envoie que nom + contenu : collection, note, favori, source restent ceux du site.
        self::assertStringContainsString('collection_id = COALESCE(?, collection_id)', $repo);
        self::assertStringContainsString('notes = COALESCE(?, notes)', $repo);
        self::assertStringContainsString("array_key_exists('is_favorite', \$data)", $repo);
        self::assertStringContainsString('updated_at = IF(payload_sha256 <=> ?, updated_at, NOW())', $repo);
        self::assertLessThan(
            strpos($repo, 'payload_sha256 = ?'),
            strpos($repo, 'updated_at = IF(payload_sha256 <=> ?'),
        );

        $api = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Api/AtakWardrobeApiController.php');
        self::assertStringContainsString("\$body['item_catalog']", $api);
        self::assertStringContainsString('recordCatalog(', $api);
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
        // Panneau « Tenues Athena » refondu : onglets Mes tenues / Communauté, filtres et pastilles de
        // collection (arsenalOverlayShow), liste multicolonne (arsenalOverlayFill), actions dans arsenalOverlayAction.
        self::assertStringContainsString('"COMMUNAUTÉ", "tabCloud"', $overlay);
        self::assertStringContainsString('"MES TENUES", "tabLocal"', $overlay);
        self::assertStringContainsString('13 * _gridW', $overlay);
        self::assertStringContainsString('ctrlShow false', $overlay);
        self::assertStringContainsString('ATHENA · TENUES', $overlay);
        self::assertStringContainsString('884400', $overlay);
        self::assertStringContainsString('884404', $overlay);
        self::assertStringContainsString('"ENFILER", "apply"', $overlay);
        self::assertStringContainsString('"keep"', $overlay);
        self::assertStringContainsString('"remove", "COMSPEC_RscButtonAtakDanger"', $overlay);
        self::assertStringContainsString('"bulk"', $overlay);
        self::assertStringContainsString('"ENREGISTRER ET ENVOYER", "saveCurrent"', $overlay);
        // Panneau placé entre les deux colonnes de l'arsenal (plus de hauteur fixe 0.72 * safezoneH).
        self::assertStringContainsString('private _px0 = safeZoneX + _sideW + _gap;', $overlay);
        self::assertStringNotContainsString('safeZoneW - 0.28', $overlay);
        self::assertStringNotContainsString('WARDROBES', $overlay);

        $action = (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_arsenalOverlayAction.sqf'
        );
        self::assertStringContainsString('Envoyer au site %1 tenue(s)', $action);
        self::assertStringContainsString('Importer %1 tenue(s) dans votre arsenal', $action);
        self::assertStringContainsString('Supprimer « %1 » de votre arsenal', $action);
        self::assertStringContainsString('BIS_fnc_guiMessage', $action);
        self::assertStringContainsString('arsenalDeleteLocal', $action);
        self::assertStringContainsString('arsenalDeleteCloud', $action);
        self::assertStringContainsString('arsenalPullAll', $action);
        self::assertStringContainsString('arsenalPushAll', $action);

        $refresh = (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_arsenalOverlayRefresh.sqf'
        );
        // Colonne auteur (index 7) de ListWardrobes, lue sans splitString.
        self::assertStringContainsString('["_upd", ""], ["_owner", ""]', $refresh);
        self::assertStringContainsString('arsenalCollectionName', $refresh);
        self::assertStringContainsString('arsenalListWardrobes', $refresh);

        $fill = (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_arsenalOverlayFill.sqf'
        );
        self::assertStringContainsString("Aucune tenue partagée par l'organisation pour l'instant.", $fill);
        self::assertStringContainsString('Aucune tenue dans votre arsenal', $fill);
        self::assertStringContainsString('lnbSetPicture', $fill);
        // Les groupes repliables (▸ / ▾) ont été remplacés par des pastilles de collection.
        self::assertStringContainsString('COMSPEC_ArsenalChips', $fill);
        self::assertStringContainsString('COMSPEC_ArsenalColl', $fill);
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
