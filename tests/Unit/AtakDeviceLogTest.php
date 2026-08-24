<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\AtakDeviceLog;
use PHPUnit\Framework\TestCase;

final class AtakDeviceLogTest extends TestCase
{
    public function testNormalizeLevelMapsFrenchAndAliases(): void
    {
        self::assertSame(AtakDeviceLog::LEVEL_ERROR, AtakDeviceLog::normalizeLevel('ERROR'));
        self::assertSame(AtakDeviceLog::LEVEL_ERROR, AtakDeviceLog::normalizeLevel('fatal'));
        self::assertSame(AtakDeviceLog::LEVEL_WARN, AtakDeviceLog::normalizeLevel('WARNING'));
        self::assertSame(AtakDeviceLog::LEVEL_DEBUG, AtakDeviceLog::normalizeLevel('TRACE'));
        self::assertSame(AtakDeviceLog::LEVEL_INFO, AtakDeviceLog::normalizeLevel('INFO'));
        self::assertSame(AtakDeviceLog::LEVEL_INFO, AtakDeviceLog::normalizeLevel('anything'));
    }

    public function testLevelAndChannelLabelsAreHuman(): void
    {
        self::assertSame('Erreur', AtakDeviceLog::levelLabel('error'));
        self::assertSame('Alerte', AtakDeviceLog::levelLabel('WARN'));
        self::assertSame('Liaison', AtakDeviceLog::channelLabel('Etat'));
        self::assertSame('Démarrage', AtakDeviceLog::channelLabel('Boot'));
        self::assertSame('Carte web', AtakDeviceLog::channelLabel('web'));
    }

    public function testParseAppDataLineSplitsLevelChannelMessageAndDetail(): void
    {
        $parsed = AtakDeviceLog::parseAppDataLine(
            '[COMSPEC Overwatch][WARN][Etat] HAWK-1 — Hors liaison | latence 240 ms'
        );

        self::assertSame(AtakDeviceLog::LEVEL_WARN, $parsed['level']);
        self::assertSame('Etat', $parsed['channel']);
        self::assertSame('HAWK-1 — Hors liaison', $parsed['message']);
        self::assertSame('latence 240 ms', $parsed['detail']);
    }

    public function testNormalizeLineAcceptsStructuredPayload(): void
    {
        $line = AtakDeviceLog::normalizeLine([
            'level' => 'ERROR',
            'channel' => 'Terminal',
            'message' => 'Écran endommagé',
            'detail' => 'impact',
        ]);

        self::assertNotNull($line);
        self::assertSame(AtakDeviceLog::LEVEL_ERROR, $line['level']);
        self::assertSame('Terminal', $line['channel']);
        self::assertSame('Écran endommagé', $line['message']);
        self::assertSame('impact', $line['detail']);
    }

    public function testNormalizeLineParsesRawAppDataWhenMessageMissing(): void
    {
        $line = AtakDeviceLog::normalizeLine([
            'line' => '[COMSPEC Overwatch][INFO][Boot] PostInit client',
        ]);

        self::assertNotNull($line);
        self::assertSame(AtakDeviceLog::LEVEL_INFO, $line['level']);
        self::assertSame('Boot', $line['channel']);
        self::assertSame('PostInit client', $line['message']);
    }

    public function testNormalizeLineRejectsEmptyMessage(): void
    {
        self::assertNull(AtakDeviceLog::normalizeLine(['message' => '   ']));
    }
}
