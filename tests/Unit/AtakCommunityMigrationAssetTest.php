<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakCommunityMigrationAssetTest extends TestCase
{
    private static function read(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path);
    }

    public function testConsolidationMigratesAtak(): void
    {
        $svc = self::read('app/Services/Community/CommunityMembershipConsolidationService.php');
        $atak = self::read('app/Services/Community/AtakCommunityMigrationService.php');
        $leave = self::read('app/Services/Community/LeaveCommunityService.php');
        $container = self::read('app/Core/Container.php');
        $fr = self::read('lang/fr/auth.php');
        $en = self::read('lang/en/auth.php');

        self::assertStringContainsString('AtakCommunityMigrationService', $svc);
        self::assertStringContainsString('migrateToDefinitiveCommunity', $svc);
        self::assertStringContainsString('function migrateToDefinitiveCommunity', $atak);
        self::assertStringContainsString('ensureAccountForUser', $atak);
        self::assertStringContainsString('revokeGameSessions', $atak);
        self::assertStringContainsString('rehomeTerminals', $atak);
        self::assertStringContainsString('detachFromCommunity', $leave);
        self::assertStringContainsString('AtakCommunityMigrationService::class', $container);
        self::assertStringContainsString('ATAK / Steam', $fr);
        self::assertStringContainsString('ATAK / Steam', $en);
    }
}
