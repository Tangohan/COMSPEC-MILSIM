<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\IntelPhotoRecompress;
use PHPUnit\Framework\TestCase;

final class AtakCameraWallpaperAssetTest extends TestCase
{
    public function testCameraAndWallpaperAssetsAreBundled(): void
    {
        $root = dirname(__DIR__, 2);
        $addon = $root . '/mod/Overwatch 2026/ProdVersion/@COMSPEC_ATAK/addons/comspec_atak_core';

        self::assertFileExists($addon . '/web/media/camera-overlay.png');
        self::assertFileExists($addon . '/data/camera_overlay_ca.png');
        self::assertFileExists($addon . '/web/media/wallpapers/ops.jpg');
        self::assertFileExists($addon . '/web/assets/desktop-wallpaper.svg');
        self::assertGreaterThan(1000, (int) filesize($addon . '/web/media/camera-overlay.png'));
        self::assertGreaterThan(1000, (int) filesize($addon . '/web/media/wallpapers/ops.jpg'));

        $phone = (string) file_get_contents($addon . '/web/phone.html');
        self::assertStringContainsString('Appareil photo', $phone);
        self::assertStringContainsString('camera-jpeg-badge', $phone);
        self::assertStringContainsString('>JPEG<', $phone);
        self::assertStringContainsString('media/camera-overlay.png', $phone);
        self::assertStringContainsString('media/wallpapers/ops.jpg', $phone);
        self::assertStringContainsString('assets/desktop-wallpaper.svg', $phone);
        self::assertStringContainsString('data-wallpaper', $phone);
        self::assertStringContainsString('1.8.22', $phone);
        self::assertStringContainsString('isAllowedWallpaper', $phone);

        $cfg = (string) file_get_contents($addon . '/config.cpp');
        self::assertStringContainsString('class cameraOpen', $cfg);
        self::assertStringContainsString('class cameraShot', $cfg);
        self::assertStringContainsString('class cameraClose', $cfg);
        self::assertStringContainsString('ui/camera.hpp', $cfg);
        self::assertStringContainsString('1.8.22', $cfg);

        $dialog = (string) file_get_contents($addon . '/functions/fn_webJSDialog.sqf');
        self::assertStringContainsString('camera:open', $dialog);
        self::assertStringContainsString('camera:shot', $dialog);
        self::assertStringContainsString('COMSPEC_fnc_cameraOpen', $dialog);

        $shot = (string) file_get_contents($addon . '/functions/fn_cameraShot.sqf');
        self::assertStringContainsString('StageCapture', $shot);
        self::assertStringContainsString('NotifyNewPhoto', $shot);
        self::assertStringContainsString('Connectez-vous au poste pour envoyer le cliché.', $shot);
        self::assertStringContainsString('Cliché envoyé au poste.', $shot);
        self::assertStringNotContainsString('comspec_overwatch_connect_fnc_', $shot);

        $store = (string) file_get_contents($root . '/app/Controllers/Api/AtakApiController.php');
        self::assertStringContainsString('IntelPhotoRecompress', $store);
    }

    public function testIntelPhotoRecompressesToJpegWhenGdAvailable(): void
    {
        $dir = sys_get_temp_dir() . '/comspec-intel-photo-' . bin2hex(random_bytes(4));
        self::assertTrue(@mkdir($dir, 0777, true) || is_dir($dir));
        $png = $dir . '/shot.png';

        if (function_exists('imagecreatetruecolor') && function_exists('imagepng') && function_exists('imagejpeg')) {
            $im = imagecreatetruecolor(24, 16);
            self::assertNotFalse($im);
            imagefilledrectangle($im, 0, 0, 23, 15, imagecolorallocate($im, 40, 80, 60));
            self::assertTrue(imagepng($im, $png));
            imagedestroy($im);

            $out = IntelPhotoRecompress::recompressFile($png);
            self::assertTrue($out['compressed']);
            self::assertFileExists($out['path']);
            self::assertStringEndsWith('.jpg', strtolower($out['filename']));
            $info = getimagesize($out['path']);
            self::assertNotFalse($info);
            self::assertSame(IMAGETYPE_JPEG, $info[2] ?? 0);
        } else {
            file_put_contents($png, 'not-an-image');
            $out = IntelPhotoRecompress::recompressFile($png);
            self::assertFalse($out['compressed']);
            self::assertSame($png, $out['path']);
        }

        foreach (glob($dir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($dir);
    }
}
