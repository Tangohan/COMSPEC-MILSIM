<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Atak\OperatorDeviceActivityService;
use PHPUnit\Framework\TestCase;

final class OperatorDeviceActivityServiceTest extends TestCase
{
    public function testDigestGroupsLogsByModuleAndCountsRecentErrors(): void
    {
        $now = 1_800_000_000;
        $digest = OperatorDeviceActivityService::digestLogs([
            ['level' => 'ERROR', 'channel' => 'cot', 'message' => 'Envoi refusé', 'logged_at' => gmdate('Y-m-d H:i:s', $now - 60)],
            ['level' => 'info', 'channel' => 'cot', 'message' => 'Connecté', 'logged_at' => gmdate('Y-m-d H:i:s', $now - 120)],
            ['level' => 'warn', 'channel' => 'gps', 'message' => 'Signal faible', 'logged_at' => gmdate('Y-m-d H:i:s', $now - 3 * 86400)],
        ], $now);

        self::assertCount(2, $digest['modules']);
        self::assertSame(1, $digest['errors_24h']);
        self::assertSame(0, $digest['warnings_24h']);
        self::assertSame($now - 60, $digest['last_at']);
        self::assertSame('bad', $digest['modules'][0]['state']);
        self::assertSame(2, $digest['modules'][0]['entries']);
        self::assertCount(2, $digest['errors']);
    }

    public function testSyncTableFlagsStaleAndMissingSyncs(): void
    {
        $now = 1_800_000_000;
        $rows = OperatorDeviceActivityService::syncTable(
            ['last_seen_at' => gmdate('Y-m-d H:i:s', $now - 30)],
            ['identity_revision' => 0, 'device_seen_at' => gmdate('Y-m-d H:i:s', $now - 7200)],
            ['last_at' => null, 'modules' => []],
            [],
            $now,
        );
        $byItem = array_column($rows, 'state', 'item');

        self::assertSame('never', $byItem['Profil du téléphone']);
        self::assertSame('stale', $byItem['État matériel']);
        self::assertSame('ok', $byItem['Enregistrement du terminal']);
        self::assertSame('never', $byItem['Certificat client']);
        self::assertSame('never', $byItem['Données tactiques']);
    }
}
