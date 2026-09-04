<?php
declare(strict_types=1);
namespace Tests\Unit;
use App\Services\Atak\AtakDeviceAuthService;
use PHPUnit\Framework\TestCase;

final class AtakDeviceAuthContractTest extends TestCase
{
    public function testPairingCodeNormalizationRejectsAmbiguousOrWrongLength(): void
    {
        self::assertSame('H7KM-29QP', AtakDeviceAuthService::normalizeUserCode('h7km 29qp'));
        self::assertSame('', AtakDeviceAuthService::normalizeUserCode('H7KM'));
    }
    public function testTerminalUidValidationIsStrict(): void
    {
        self::assertTrue(AtakDeviceAuthService::validTerminal('ATAK-A72F0012'));
        self::assertFalse(AtakDeviceAuthService::validTerminal('../ATAK-A72F'));
        self::assertFalse(AtakDeviceAuthService::validTerminal('PHONE-A72F'));
    }
    public function testPublicRoutesKeepLegacyModContract(): void
    {
        $routes=file_get_contents(base_path('routes/web.php'));
        self::assertStringContainsString("'/api/atak/pair/start'",$routes);
        self::assertStringContainsString("'/api/atak/pair/status'",$routes);
        self::assertStringContainsString("'/api/atak/recovery/redeem'",$routes);
    }
    public function testSchemaEnforcesSingleUseAndRevocationRelations(): void
    {
        $sql=file_get_contents(base_path('migrations/20260904120000_atak_secure_device_auth.sql'));
        self::assertStringContainsString('UNIQUE KEY uk_atak_pair_device_code', $sql);
        self::assertStringContainsString('used_at DATETIME DEFAULT NULL', $sql);
        self::assertStringContainsString('certificate_id BIGINT UNSIGNED', $sql);
    }

    public function testAtakWebUsesDeviceInitiatedPairingInsteadOfLegacyCodeGeneration(): void
    {
        $view = file_get_contents(base_path('views/atak.php'));
        self::assertIsString($view);
        self::assertStringContainsString('id="atak-device-pair-code"', $view);
        self::assertStringContainsString("url('atak/device-pairing/lookup')", $view);
        self::assertStringContainsString("url('atak/device-pairing/decision')", $view);
        self::assertStringNotContainsString('function initGameLink()', $view);
        self::assertStringNotContainsString('data-game-link-url=', $view);
    }
}
