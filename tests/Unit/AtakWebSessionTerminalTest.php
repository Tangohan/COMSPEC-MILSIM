<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\AtakRealismRepository;
use PHPUnit\Framework\TestCase;

final class AtakWebSessionTerminalTest extends TestCase
{
    public function testGameTabletIsPhysicalTerminal(): void
    {
        self::assertFalse(AtakRealismRepository::isWebSessionTerminal([
            'terminal_type' => 'tablet',
            'terminal_uid' => 'OW-AB12-123456',
            'platform_label' => 'Arma 3 · COMSPEC 1.4.2',
        ]));
    }

    public function testExplicitWebTypeIsSession(): void
    {
        self::assertTrue(AtakRealismRepository::isWebSessionTerminal([
            'terminal_type' => 'web',
            'terminal_uid' => 'WEB-AABBCCDDEEFF',
            'platform_label' => 'Session web Athena',
        ]));
    }

    public function testWebUidPrefixIsSession(): void
    {
        self::assertTrue(AtakRealismRepository::isWebSessionTerminal([
            'terminal_type' => 'phone',
            'terminal_uid' => 'WEB-001122334455',
            'platform_label' => '',
        ]));
    }

    public function testBrowserPlatformIsSession(): void
    {
        self::assertTrue(AtakRealismRepository::isWebSessionTerminal([
            'terminal_type' => 'desktop',
            'terminal_uid' => 'OW-FF00-999999',
            'platform_label' => 'Navigateur — poste de commandement',
        ]));
    }

    public function testDesktopWithoutPlatformIsSession(): void
    {
        self::assertTrue(AtakRealismRepository::isWebSessionTerminal([
            'terminal_type' => 'desktop',
            'terminal_uid' => 'OW-AA11-000001',
            'platform_label' => '',
        ]));
    }

    public function testPartitionKeepsPhysicalAndWebApart(): void
    {
        $split = AtakRealismRepository::partitionTerminals([
            ['id' => 1, 'terminal_type' => 'tablet', 'terminal_uid' => 'OW-1', 'platform_label' => 'Arma 3 · COMSPEC'],
            ['id' => 2, 'terminal_type' => 'web', 'terminal_uid' => 'WEB-2', 'platform_label' => 'Session web Athena'],
        ]);

        self::assertCount(1, $split['physical']);
        self::assertCount(1, $split['web']);
        self::assertSame(1, (int) $split['physical'][0]['id']);
        self::assertSame(2, (int) $split['web'][0]['id']);
    }

    public function testCollapseKeepsNewestGameTerminalPerOperator(): void
    {
        $rows = [
            [
                'id' => 10,
                'terminal_type' => 'tablet',
                'terminal_uid' => 'OW-NEW',
                'operator_callsign' => 'N-10',
                'operator_military_id' => 'MID-MTXN',
                'platform_label' => 'Arma 3 · COMSPEC 1.4.53',
                'last_seen_at' => '2026-08-24 15:10:00',
            ],
            [
                'id' => 3,
                'terminal_type' => 'tablet',
                'terminal_uid' => 'OW-OLD',
                'operator_callsign' => 'N-10',
                'operator_military_id' => 'MID-MTXN',
                'platform_label' => 'Arma 3 · COMSPEC 1.3.0',
                'last_seen_at' => '2026-07-28 15:56:00',
            ],
            [
                'id' => 8,
                'terminal_type' => 'phone',
                'terminal_uid' => 'PH-1',
                'operator_callsign' => 'N-10',
                'operator_military_id' => 'MID-MTXN',
                'platform_label' => 'Téléphone ATAK',
            ],
        ];

        $collapsed = AtakRealismRepository::collapsePhysicalDuplicates($rows);
        self::assertCount(2, $collapsed);
        self::assertSame(10, (int) $collapsed[0]['id']);
        self::assertSame(8, (int) $collapsed[1]['id']);
    }

    public function testMysqlUtcToIsoAddsZulu(): void
    {
        self::assertSame('2026-08-24T15:10:00Z', AtakRealismRepository::mysqlUtcToIso('2026-08-24 15:10:00'));
        self::assertSame('2026-08-24T15:10:00Z', AtakRealismRepository::mysqlUtcToIso('2026-08-24T15:10:00Z'));
    }
}
