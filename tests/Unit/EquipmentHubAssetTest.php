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
        $compression = (string) file_get_contents($root . '/app/Services/Media/ImageCompressionService.php');
        $repo = (string) file_get_contents($root . '/app/Repositories/ArsenalWardrobeRepository.php');
        $dispatch = (string) file_get_contents($root . '/app/Support/DevDispatchCatalog.php');

        self::assertFileExists($root . '/public/assets/css/equipment-hub.css');
        self::assertStringContainsString("\$router->get('/equipment', [ArsenalWardrobeController::class, 'index']", $routes);
        self::assertStringContainsString("\$router->get('/equipment/covers/{tenantId}/{file}'", $routes);
        self::assertStringContainsString("\$router->get('/equipment/collections/{id}'", $routes);
        self::assertStringContainsString("\$router->get('/equipment/tenues/{id}'", $routes);
        self::assertStringContainsString("\$router->get('/equipment/{slug}', [EquipmentController::class, 'show']", $routes);
        $coversPos = strpos($routes, "/equipment/covers/{tenantId}/{file}");
        $slugPos = strpos($routes, "/equipment/{slug}");
        self::assertNotFalse($coversPos);
        self::assertNotFalse($slugPos);
        self::assertLessThan($slugPos, $coversPos);
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
        self::assertStringContainsString('ensureWritableDir', $storage);
        self::assertStringContainsString("storage/' . \$rel", $storage);
        self::assertStringContainsString('streamCover', $storage);
        self::assertStringContainsString('function streamCover', $controller);
        self::assertStringContainsString('uploadErrorMessage', $storage);
        self::assertStringContainsString('looksLikeHeic', $storage);
        self::assertStringContainsString('Photo trop volumineuse', $storage);
        self::assertStringNotContainsString('mkdir', $hub);
        self::assertStringNotContainsString('endpoint', $storage);
        self::assertStringContainsString('EquipmentCoverStorage::hintText', $hub);
        self::assertStringContainsString('EquipmentCoverStorage::hintText', $collection);
        self::assertStringContainsString('EquipmentCoverStorage::hintText', $tenue);
        $userIni = (string) file_get_contents($root . '/public/.user.ini');
        self::assertStringContainsString('upload_max_filesize', $userIni);
        self::assertStringContainsString('setCollectionCover', $controller);
        self::assertStringContainsString('setWardrobeCover', $repo);
        self::assertStringContainsString('cover_url', $repo);
        self::assertStringContainsString('Les tenues se rangent en collections', $dispatch);
        self::assertStringContainsString('Les photos de présentation des collections', $dispatch);
        self::assertStringContainsString('TerrainUploadedImage::move', $compression);
        self::assertStringContainsString('0775', $compression);
        self::assertStringContainsString('is_writable($destDirAbs)', $compression);
        self::assertStringNotContainsString('is_uploaded_file', $compression);
    }

    public function testCoverStorageRejectsUnsafeNamesAndCrossTenant(): void
    {
        self::assertTrue(\App\Support\EquipmentCoverStorage::isSafeFileName('c_1234_ab.jpg'));
        self::assertTrue(\App\Support\EquipmentCoverStorage::isSafeFileName('t-abc.webp'));
        self::assertFalse(\App\Support\EquipmentCoverStorage::isSafeFileName('../x.jpg'));
        self::assertFalse(\App\Support\EquipmentCoverStorage::isSafeFileName('a/b.jpg'));
        self::assertFalse(\App\Support\EquipmentCoverStorage::isSafeFileName(''));
        $cross = \App\Support\EquipmentCoverStorage::streamCover(1, 2, 'c.jpg');
        self::assertSame(404, $cross->statusCode());
        $missing = \App\Support\EquipmentCoverStorage::streamCover(1, 1, 'c-missing.jpg');
        self::assertSame(404, $missing->statusCode());
    }
}
