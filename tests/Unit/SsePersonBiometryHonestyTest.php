<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Sse\SseTerrainService;
use PHPUnit\Framework\TestCase;

final class SsePersonBiometryHonestyTest extends TestCase
{
    public function testEmptySamplesStayAbsentAndDoNotInventQuality(): void
    {
        self::assertSame('Non relevées', SseTerrainService::biometricModalityLabel([], 'empreintes'));
        self::assertSame('Non relevé', SseTerrainService::biometricModalityLabel([], 'iris'));
        self::assertSame('Non relevé', SseTerrainService::biometricModalityLabel([], 'adn'));
        self::assertSame('—', SseTerrainService::personTerminalLabel([]));
    }

    public function testSampleQualityIsShownWhenTransmitted(): void
    {
        $samples = [[
            'kind' => 'iris',
            'quality' => 81,
            'quality_label' => 'Excellente',
        ]];
        self::assertSame('Relevé — Excellente (81 %)', SseTerrainService::biometricModalityLabel($samples, 'iris'));
        self::assertSame('Non relevées', SseTerrainService::biometricModalityLabel($samples, 'empreintes'));
    }

    public function testSignedTerrainTerminalIsNotGenericSeekAtak(): void
    {
        $label = SseTerrainService::personTerminalLabel([
            'from_arma' => true,
            'signature' => ['callsign' => 'YA1 / Bravo', 'terminal_uid' => 'abc'],
        ]);
        self::assertSame('Signé depuis le terrain', $label);
        self::assertStringNotContainsString('SEEK / ATAK', $label);
    }

    public function testIdentityShowDoesNotInventCaptureQuality(): void
    {
        $controller = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Web/SsePortalController.php');
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/atak/sse/person_show.php');

        self::assertStringNotContainsString("? 72 : 48", $controller);
        self::assertStringNotContainsString("'SEEK / ATAK'", $controller);
        self::assertStringNotContainsString("'source' => 'C'", $controller);
        self::assertStringContainsString('SseTerrainService::biometricModalityLabel', $controller);
        self::assertStringNotContainsString("?? 'SEEK / ATAK'", $view);
        self::assertStringContainsString("?? null) !== null ? ((int) \$confidence['technical'] . ' %') : '—'", $view);
    }
}
