<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Admin\AdminAtakFleetController;
use App\Repositories\AtakAdminControlRepository as Fleet;
use App\Support\AtakAdminBlockGate;
use PHPUnit\Framework\TestCase;

/**
 * Parc ATAK des joueurs (/admin/parc-atak) : blocages, rôles, tri, et câblage du blocage dans l'API.
 */
final class AtakAdminFleetTest extends TestCase
{
    private function read(string $rel): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $rel);
    }

    public function testBlockMatchesPlayerSteamOrTerminalInItsScope(): void
    {
        $terminal = ['tenant_id' => 3, 'user_id' => 42, 'steam_id' => '76561198000000001', 'terminal_uid' => 'OW-AAA'];
        self::assertNotNull(Fleet::blockMatching($terminal, [['tenant_id' => 3, 'user_id' => 42]]));
        self::assertNotNull(Fleet::blockMatching($terminal, [['tenant_id' => null, 'steam_uid' => '76561198000000001']]));
        self::assertNotNull(Fleet::blockMatching($terminal, [['tenant_id' => 3, 'terminal_uid' => 'OW-AAA']]));
        // Blocage d'une autre communauté : sans effet ici.
        self::assertNull(Fleet::blockMatching($terminal, [['tenant_id' => 9, 'user_id' => 42]]));
        self::assertNull(Fleet::blockMatching($terminal, [['tenant_id' => 3, 'user_id' => 7, 'terminal_uid' => 'OW-BBB']]));
    }

    public function testTopRolesSeparatesSlotFromRole(): void
    {
        [$role, $slot] = Fleet::topRoles([
            ['key' => 'FUS', 'label' => 'Fusilier', 'seconds' => 600],
            ['key' => 'MED', 'label' => 'Infirmier', 'seconds' => 1200],
            ['key' => 'SLOT:Rifleman', 'label' => 'SLOT:Rifleman', 'seconds' => 3000],
        ]);
        self::assertSame('Infirmier', $role['label'] ?? null);
        self::assertSame('Rifleman', $slot['label'] ?? null);
    }

    public function testSortByPlayTimeAndRecency(): void
    {
        $rows = [
            ['id' => 1, 'operator_callsign' => 'B', 'seen_ago' => 50, 'metrics' => ['play' => 10, 'total_m' => 0]],
            ['id' => 2, 'operator_callsign' => 'A', 'seen_ago' => 5, 'metrics' => ['play' => 99, 'total_m' => 0]],
            ['id' => 3, 'operator_callsign' => 'C', 'seen_ago' => null, 'metrics' => null],
        ];
        self::assertSame([2, 1, 3], array_column(AdminAtakFleetController::sortRows($rows, 'recent'), 'id'));
        self::assertSame([2, 1, 3], array_column(AdminAtakFleetController::sortRows($rows, 'jeu'), 'id'));
        self::assertSame([2, 1, 3], array_column(AdminAtakFleetController::sortRows($rows, 'nom'), 'id'));
    }

    public function testBlockPayloadFallsBackToDefaultMessage(): void
    {
        $p = AtakAdminBlockGate::payload(['player_message' => '', 'expires_at' => null]);
        self::assertSame('atak_blocked', $p['error']);
        self::assertNotSame('', $p['message']);
        self::assertSame('Lisez le règlement.', AtakAdminBlockGate::playerMessage(['player_message' => 'Lisez le règlement.']));
    }

    public function testBlockIsEnforcedByGameApiPaths(): void
    {
        self::assertStringContainsString('AtakAdminBlockGate::deny', $this->read('app/Support/AtakArmaWriteGuard.php'));
        self::assertStringContainsString('ATAK_BLOCKED', $this->read('app/Services/Game/GameAuthService.php'));
        self::assertStringContainsString('AtakAdminBlockGate::deny', $this->read('app/Controllers/Api/AtakRealismApiController.php'));
        $routes = $this->read('routes/web.php');
        self::assertStringContainsString("'/admin/parc-atak'", $routes);
        self::assertStringContainsString("'/api/atak/admin-control/pending'", $routes);
        self::assertStringContainsString('run_atak_admin_control_migration', $this->read('run-migrations.php'));
    }
}
