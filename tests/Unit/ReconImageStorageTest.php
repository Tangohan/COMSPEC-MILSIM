<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ReconImageStorage;
use PHPUnit\Framework\TestCase;

final class ReconImageStorageTest extends TestCase
{
    public function testSafeFileName(): void
    {
        self::assertTrue(ReconImageStorage::isSafeFileName('recon_20260928114955_YA1.jpg'));
        self::assertFalse(ReconImageStorage::isSafeFileName('../evil.jpg'));
        self::assertFalse(ReconImageStorage::isSafeFileName('a/b.jpg'));
        self::assertFalse(ReconImageStorage::isSafeFileName(''));
    }

    public function testRelativeAndPublicUrl(): void
    {
        self::assertSame('recon/recon_x.jpg', ReconImageStorage::relativeImagePath('recon_x.jpg'));
        $url = ReconImageStorage::publicUrl('recon_x.jpg');
        self::assertStringContainsString('/uploads/recon/recon_x.jpg', $url);
        self::assertStringNotContainsString('/public/uploads/', $url);
    }

    public function testStoreFallsBackAndIsReadable(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'recon_t_');
        self::assertNotFalse($tmp);
        self::assertNotFalse(file_put_contents($tmp, "\xFF\xD8\xFF\xD9fake"));
        $name = 'recon_test_' . bin2hex(random_bytes(4)) . '.jpg';
        $stored = ReconImageStorage::storeFromTemp($tmp, $name);
        self::assertNotNull($stored);
        self::assertFileExists($stored);
        $readable = ReconImageStorage::absoluteReadable($name);
        self::assertSame(realpath($stored), realpath((string) $readable));
        ReconImageStorage::delete($name);
        self::assertNull(ReconImageStorage::absoluteReadable($name));
    }

    public function testControllerUsesReconImageStorage(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Api/AtakApiController.php');
        self::assertStringContainsString('ReconImageStorage::storeFromTemp', $src);
        self::assertStringContainsString('ReconImageStorage::absoluteReadable', $src);
        self::assertStringContainsString('ReconImageStorage::publicUrl', $src);
        $index = (string) file_get_contents(dirname(__DIR__, 2) . '/public/index.php');
        self::assertStringContainsString("str_starts_with(\$requestPath, '/uploads/')", $index);
        self::assertStringContainsString('storage', $index);
    }
}
