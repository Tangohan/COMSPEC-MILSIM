<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Gate;
use App\Support\MaintenanceGuard;
use App\Support\MaintenanceService;
use App\Support\MaintenanceSessionEpoch;
use PDO;
use PHPUnit\Framework\TestCase;

/** Maintenance globale : tout le monde est déconnecté sauf les administrateurs plateforme ; le jeu la reconnaît. */
final class MaintenanceLogoutTest extends TestCase
{
    private string $epochFile = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->epochFile = sys_get_temp_dir() . '/athena_epoch_' . bin2hex(random_bytes(4)) . '.json';
        MaintenanceSessionEpoch::usePath($this->epochFile);
    }

    protected function tearDown(): void
    {
        Gate::reset();
        MaintenanceSessionEpoch::usePath(null);
        @unlink($this->epochFile);
        parent::tearDown();
    }

    public function testCommunityManagersNoLongerBypassMaintenance(): void
    {
        $service = new MaintenanceService(new PDO('sqlite::memory:'));
        $rule = ['allow_admin_bypass' => 1];

        Gate::getInstance()->setPermissions(['admin.access', 'admin.organization']);
        Gate::getInstance()->setPlatformAdmin(false);
        self::assertFalse($service->shouldBypass($rule, ['user_id' => 7], '203.0.113.10'));

        Gate::getInstance()->setPlatformAdmin(true);
        self::assertTrue($service->shouldBypass($rule, ['user_id' => 7], '203.0.113.10'));
    }

    public function testLoginPagesStayReachableForAdminsOnly(): void
    {
        foreach (['/login', '/login/otp', '/login/select-community', '/logout'] as $path) {
            self::assertTrue(MaintenanceGuard::isAdminLoginPath($path), $path);
        }
        foreach (['/', '/register', '/lier', '/dashboard', '/api/atak/pair/start'] as $path) {
            self::assertFalse(MaintenanceGuard::isAdminLoginPath($path), $path);
        }
        // Les pages de connexion restent couvertes par la maintenance (pas des points d'infrastructure).
        self::assertFalse(MaintenanceGuard::isInfrastructurePath('/login'));
    }

    public function testApiCallsGetAMaintenanceJson(): void
    {
        self::assertTrue(MaintenanceGuard::wantsJson('/api/atak/pair/start'));
        self::assertTrue(MaintenanceGuard::wantsJson('/api/atak/telemetry/batch'));
        $payload = MaintenanceGuard::jsonPayload(['title' => 'Mise à jour', 'ends_at' => '2026-10-25 12:00:00'], 'Retour vers midi.');
        self::assertFalse($payload['ok']);
        self::assertSame('maintenance', $payload['error']);
        self::assertSame('MAINTENANCE', $payload['code']);
        self::assertSame('Mise à jour', $payload['title']);
        self::assertNotNull($payload['ends_at']);
    }

    public function testEpochIsSetOncePerActivation(): void
    {
        self::assertSame(0, MaintenanceSessionEpoch::epoch());
        $rule = ['id' => 3, 'starts_at' => null, 'updated_at' => '2026-10-25 08:00:00'];
        self::assertSame(1000, MaintenanceSessionEpoch::mark($rule, 1000));
        // Même activation : l'horodatage ne bouge pas (sinon les admins connectés pendant seraient fermés).
        self::assertSame(1000, MaintenanceSessionEpoch::mark($rule, 2000));
        self::assertSame(1000, MaintenanceSessionEpoch::epoch());
        // Règle réactivée (updated_at change) : nouvelle maintenance, nouvelles fermetures.
        $rule['updated_at'] = '2026-10-26 08:00:00';
        self::assertSame(3000, MaintenanceSessionEpoch::mark($rule, 3000));
        self::assertSame(3000, MaintenanceSessionEpoch::epoch());
    }

    public function testAuthMiddlewareClosesSessionsOpenedBeforeMaintenance(): void
    {
        $mw = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Middleware/AuthMiddleware.php');
        self::assertStringContainsString('MaintenanceSessionEpoch::epoch()', $mw);
        self::assertStringContainsString('isPlatformAdmin()', $mw);
    }
}
