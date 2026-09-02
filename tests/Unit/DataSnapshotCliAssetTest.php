<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class DataSnapshotCliAssetTest extends TestCase
{
    public function testCliExposesCreateListRestoreAndRefusesBareRestore(): void
    {
        $path = dirname(__DIR__, 2) . '/scripts/data-snapshot.php';
        self::assertFileExists($path);
        $src = (string) file_get_contents($path);
        self::assertStringContainsString('create', $src);
        self::assertStringContainsString('restore', $src);
        self::assertStringContainsString('--yes', $src);
        self::assertStringContainsString('CompleteDataSnapshotService', $src);
        self::assertStringContainsString('ligne de commande uniquement', $src);
        self::assertStringContainsString('écrase les données actuelles', $src);
    }
}
