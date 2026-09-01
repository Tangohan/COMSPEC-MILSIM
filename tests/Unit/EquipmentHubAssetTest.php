<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class EquipmentHubAssetTest extends TestCase
{
    public function testHubPageIsTheEquipmentCatalog(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $controller = (string) file_get_contents($root . '/app/Controllers/Web/ArsenalWardrobeController.php');
        $hub = (string) file_get_contents($root . '/views/equipment/hub.php');
        $collection = (string) file_get_contents($root . '/views/equipment/collection.php');
        $tenue = (string) file_get_contents($root . '/views/equipment/tenue.php');
        $layout = (string) file_get_contents($root . '/views/layout/main.php');
        $storage = (string) file_get_contents($root . '/app/Support/EquipmentCoverStorage.php');
        $repo = (string) file_get_contents($root . '/app/Repositories/ArsenalWardrobeRepository.php');
        $dispatch = (string) file_get_contents($root . '/app/Support/DevDispatchCatalog.php');

        self::assertFileExists($root . '/public/assets/css/equipment-hub.css');
        self::assertStringContainsString("\$router->get('/equipment', [ArsenalWardrobeController::class, 'index']", $routes);
        self::assertStringContainsString("\$router->get('/equipment/collections/{id}'", $routes);
        self::assertStringContainsString("\$router->get('/equipment/tenues/{id}'", $routes);
        self::assertStringContainsString("\$router->get('/equipment/{slug}', [EquipmentController::class, 'show']", $routes);
        self::assertStringContainsString('enctype="multipart/form-data"', $hub);
        self::assertStringContainsString('Nouvelle collection', $hub);
        self::assertStringContainsString('Photo de présentation', $hub);
        self::assertStringContainsString('name="wardrobe_ids[]"', $hub);
        self::assertStringNotContainsString('tags', $hub);
        self::assertStringContainsString('Qui peut s’en servir', $hub);
        self::assertStringContainsString('accept="image/jpeg,image/png,image/webp"', $collection);
        self::assertStringContainsString('Photo de présentation', $tenue);
        self::assertStringContainsString('Équipement', $tenue);
        self::assertStringContainsString('loadoutItems', $tenue);
        self::assertStringContainsString('eq-hub__items', $css = (string) file_get_contents($root . '/public/assets/css/equipment-hub.css'));
        self::assertStringContainsString('ArsenalLoadoutItems', $controller);
        self::assertStringContainsString('equipment-hub.css', $layout);
        self::assertStringContainsString('storeFromUpload', $storage);
        self::assertStringContainsString('ImageCompressionService', $storage);
        self::assertStringContainsString('uploadErrorMessage', $storage);
        self::assertStringContainsString('looksLikeHeic', $storage);
        self::assertStringContainsString('Photo trop volumineuse', $storage);
        self::assertStringContainsString('EquipmentCoverStorage::hintText', $hub);
        self::assertStringContainsString('EquipmentCoverStorage::hintText', $collection);
        self::assertStringContainsString('EquipmentCoverStorage::hintText', $tenue);
        $userIni = (string) file_get_contents($root . '/public/.user.ini');
        self::assertStringContainsString('upload_max_filesize', $userIni);
        self::assertStringContainsString('setCollectionCover', $controller);
        self::assertStringContainsString('setWardrobeCover', $repo);
        self::assertStringContainsString('cover_url', $repo);
        self::assertStringContainsString('Les tenues se rangent en collections', $dispatch);
    }
}
