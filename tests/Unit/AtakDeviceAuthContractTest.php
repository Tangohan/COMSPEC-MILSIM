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
        self::assertStringContainsString('atak_terminal_id INT UNSIGNED', $sql);
        self::assertStringContainsString('certificate_id INT UNSIGNED', $sql);
    }

    public function testDeviceSecurityControllersAreWiredInTheContainer(): void
    {
        $di = (string) file_get_contents(base_path('app/Core/ContainerIntegrations.php'));
        self::assertStringContainsString('AtakDeviceSecurityController::class', $di);
        self::assertStringContainsString('AtakDeviceAuthApiController::class', $di);
        self::assertStringContainsString('AtakDeviceAuthService::class', $di);
        self::assertStringContainsString('AtakDeviceAuthRepository::class', $di);
    }

    public function testAtakWebUsesDeviceInitiatedPairingInsteadOfLegacyCodeGeneration(): void
    {
        $view = file_get_contents(base_path('views/atak.php'));
        self::assertIsString($view);
        self::assertStringContainsString('id="atak-device-pair-code"', $view);
        self::assertStringContainsString("url('atak/device-pairing/lookup')", $view);
        self::assertStringContainsString("url('atak/device-pairing/decision')", $view);
        self::assertStringContainsString('Valable 10 min', $view);
        self::assertStringNotContainsString('Sécurisé · 10 min', $view);
        self::assertStringNotContainsString('function initGameLink()', $view);
        self::assertStringNotContainsString('data-game-link-url=', $view);
    }

    public function testPairingPanelStylesAreOnTheServedStylesheet(): void
    {
        $css = (string) file_get_contents(base_path('public/assets/css/atak.css'));
        self::assertStringContainsString('.atak-device-pair-code {', $css);
        self::assertStringContainsString('width: 100%;', $css);
        self::assertStringContainsString('.atak-account-section--game-link {', $css);
        self::assertStringContainsString('border-left: 3px solid var(--atak-accent);', $css);
        self::assertStringNotContainsString('linear-gradient(160deg, rgba(52, 211, 153, 0.12)', $css);
    }

    public function testAtakWebOffersRecoverySetupModalWhenNoCodesAreStored(): void
    {
        $view = (string) file_get_contents(base_path('views/atak.php'));
        $js = (string) file_get_contents(base_path('public/assets/js/atak-recovery-setup.js'));
        $css = (string) file_get_contents(base_path('public/assets/css/atak.css'));
        $ctrl = (string) file_get_contents(base_path('app/Controllers/Web/AtakController.php'));
        $repo = (string) file_get_contents(base_path('app/Repositories/AtakDeviceAuthRepository.php'));

        self::assertStringContainsString('id="atak-recovery-setup-modal"', $view);
        self::assertStringContainsString('Aucun code de secours n’est enregistré', $view);
        self::assertStringContainsString('Générer mes codes de secours', $view);
        self::assertStringContainsString('#recovery', $view);
        self::assertStringContainsString('atak-recovery-setup.js', $view);
        self::assertStringContainsString('window.ATAK_DEVICE_SECURITY', $view);
        self::assertStringNotContainsString('endpoint', $view);

        self::assertStringContainsString('needsRecoveryCodes', $js);
        self::assertStringContainsString('ATAKSessionProfile.onReady', $js);
        self::assertStringContainsString('ATAK_POPOUT', $js);

        self::assertStringContainsString('.atak-recovery-setup-modal', $css);

        self::assertStringContainsString('deviceSecurityPayload', $ctrl);
        self::assertStringContainsString('hasActiveRecoveryCodes', $ctrl);
        self::assertStringContainsString('hasActiveRecoveryCodes', $repo);
        self::assertStringContainsString('atak_recovery_code_sets', $repo);
    }
}
