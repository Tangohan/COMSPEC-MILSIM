<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Sse\SseDigitalLabService;
use PHPUnit\Framework\TestCase;

final class SseDigitalLabTerrainIngestTest extends TestCase
{
    public function testPhoneSourceMapsToTelephone(): void
    {
        self::assertSame('telephone', SseDigitalLabService::mapTerrainDeviceType('PHONE'));
        self::assertSame('telephone', SseDigitalLabService::mapTerrainDeviceType('smartphone'));
    }

    public function testComputerSourceMapsToOrdinateur(): void
    {
        self::assertSame('ordinateur', SseDigitalLabService::mapTerrainDeviceType('LAPTOP'));
        self::assertSame('ordinateur', SseDigitalLabService::mapTerrainDeviceType('computer'));
    }

    public function testUnknownFallsBackToPhoneSummary(): void
    {
        self::assertSame(
            'telephone',
            SseDigitalLabService::mapTerrainDeviceType('unknown', [
                'ok' => true,
                'deviceType' => 'PHONE',
                'contacts' => 3,
                'messages' => 1,
            ])
        );
    }
}
