<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Admin\AdminAtakFleetController;
use App\Repositories\AtakAdminControlRepository as Fleet;
use App\Support\AtakAdminBlockGate;
use App\Support\AtakFleetLink;
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
        self::assertStringContainsString('run_atak_admin_control_migration', \Tests\Support\MigrationPipelineSource::read(dirname(__DIR__, 2)));
    }

    public function testCompromiseHistoryIsNewestFirst(): void
    {
        $sorted = AdminAtakFleetController::sortCompromises([
            ['id' => 0, 'ts' => 100, 'legacy' => true],
            ['id' => 4, 'ts' => 300],
            ['id' => 5, 'ts' => 300],
            ['id' => 2, 'ts' => 200],
        ]);
        self::assertSame([5, 4, 2, 0], array_column($sorted, 'id'));
    }

    public function testCompromisesAreRecordedAndUndoable(): void
    {
        // Les compromissions du jeu, du poste de commandement et des positions alimentent l'historique de la fiche.
        self::assertStringContainsString('recordCompromise(', $this->read('app/Controllers/Api/AtakRealismApiController.php'));
        self::assertStringContainsString('recordCompromise(', $this->read('app/Controllers/Api/AtakApiController.php'));
        self::assertStringContainsString('atak_compromise_events', $this->read('bootstrap/atak_admin_control_migration.php'));
        $routes = $this->read('routes/web.php');
        foreach (['compromission', 'compromission/annuler', 'certificat', 'note'] as $path) {
            self::assertStringContainsString("'/admin/parc-atak/{$path}'", $routes);
        }
        self::assertArrayHasKey('compromis', Fleet::STATES);
        foreach (['compromise', 'compromise_undo', 'cert_revoke', 'cert_restore', 'note'] as $action) {
            self::assertArrayHasKey($action, Fleet::ACTIONS);
        }
    }

    public function testAdminNoticesReachThePlayerAtak(): void
    {
        $routes = $this->read('routes/web.php');
        self::assertStringContainsString("'/api/atak/admin-control/mine'", $routes);
        self::assertStringContainsString("'/api/atak/admin-control/mine/ack'", $routes);
        foreach (['compromise', 'compromise_cleared', 'reset', 'certificate', 'phone'] as $kind) {
            self::assertArrayHasKey($kind, Fleet::NOTICE_KINDS);
        }
        // Le site envoie l'avis, COMSPEC Link le récupère et l'ATAK natif l'affiche.
        self::assertStringContainsString("notifyPlayer(", $this->read('app/Controllers/Admin/AdminAtakFleetController.php'));
        self::assertStringContainsString('"AdminControl.Poll"', $this->read('mod/UptoDate/COMSPECExtension/Extension.cs'));
        self::assertStringContainsString('AdminControl.Poll', $this->read('mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/shell/fn_adminNotices.sqf'));
        self::assertStringContainsString('class adminNotices {}', $this->read('mod/COMSPEC_ATAK_Native/Sources/addons/main/config.cpp'));
    }

    public function testBackOfficeFleetIsLimitedToTheActiveCommunity(): void
    {
        $routes = $this->read('routes/web.php');
        self::assertStringContainsString("'/back-office/atak/parc-atak'", $routes);
        self::assertStringContainsString("'/back-office/atak/parc-atak/compromission/annuler'", $routes);
        // Le retrait définitif reste à l'administration de la plateforme.
        self::assertStringNotContainsString("'/back-office/atak/parc-atak/retirer'", $routes);
        self::assertMatchesRegularExpression('/\$boFleetMw = \[AuthMiddleware::class, TenantResourceAdminMiddleware::class\]/', $routes);

        $controller = (new \ReflectionClass(\App\Controllers\Admin\BackOfficeAtakFleetController::class))->newInstanceWithoutConstructor();
        $scope = new \ReflectionMethod($controller, 'scopeTenantId');
        $previous = $_SESSION ?? null;
        try {
            $_SESSION = ['tenant_id' => 7];
            self::assertSame(7, $scope->invoke($controller));
            // Sans communauté active : jamais « toutes les communautés ».
            $_SESSION = [];
            self::assertNotSame(0, $scope->invoke($controller));
        } finally {
            $_SESSION = $previous ?? [];
        }
        $admin = (new \ReflectionClass(AdminAtakFleetController::class))->newInstanceWithoutConstructor();
        self::assertSame(0, (new \ReflectionMethod($admin, 'scopeTenantId'))->invoke($admin));
        self::assertArrayHasKey('gestion', Fleet::COMPROMISE_SOURCES);
        self::assertArrayHasKey('note_gestion', Fleet::ACTIONS);
    }

    public function testSteamCorrelationMatchesGameLoginRules(): void
    {
        $member = ['id' => 18, 'tenant_id' => 3, 'status' => 'active', 'email' => 'a@b.fr', 'steam_id' => '76561198000000101'];
        $on = [$member];
        self::assertSame('ok', AtakFleetLink::classify(true, 3, $member, $on, '76561198000000101'));
        self::assertSame('steam_mismatch', AtakFleetLink::classify(true, 3, $member, $on, '76561198000000999'));
        self::assertSame('no_steam', AtakFleetLink::classify(true, 3, ['steam_id' => ''] + $member, []));
        self::assertSame('duplicate', AtakFleetLink::classify(true, 3, $member, [$member, ['id' => 99] + $member]));
        self::assertSame('disabled', AtakFleetLink::classify(true, 3, ['status' => 'banned'] + $member, $on));
        self::assertSame('bad_email', AtakFleetLink::classify(true, 3, ['email' => ''] + $member, $on));
        self::assertSame('unlinked', AtakFleetLink::classify(true, 3, null, []));
        // Joueur connu par son seul Steam (vu en jeu ou par les rapports du mod).
        self::assertSame('no_account', AtakFleetLink::classify(false, 7, null, []));
        self::assertSame('account_only', AtakFleetLink::classify(false, 7, null, [], '', true));
        self::assertSame('no_terminal', AtakFleetLink::classify(false, 3, null, $on));
        self::assertSame('other_tenant', AtakFleetLink::classify(false, 7, null, $on));
        self::assertSame('no_terminal', AtakFleetLink::classify(false, 0, null, $on));
    }

    public function testDenialReasonsExplainA403(): void
    {
        self::assertSame([], AtakFleetLink::denialReasons('ok', 'full', null));
        self::assertSame([], AtakFleetLink::denialReasons('no_terminal', 'basic', null));
        $why = AtakFleetLink::denialReasons('no_account', 'none', ['tenant_id' => null]);
        self::assertCount(3, $why);
        self::assertStringContainsString('toutes les communautés', $why[0]);
        self::assertSame(AtakFleetLink::STATES['no_account'][0], $why[1]);
        self::assertSame('Formule sans la carte', $why[2]);
        // Ordre du tri « Compte Athena » : les cas bloquants d'abord, « Corrélé » en dernier.
        self::assertLessThan(AtakFleetLink::rank('ok'), AtakFleetLink::rank('no_account'));
        self::assertSame(count(AtakFleetLink::STATES) - 1, AtakFleetLink::rank('ok'));
    }

    public function testNewSortsAndReverseOrder(): void
    {
        $rows = [
            ['id' => 1, 'operator_callsign' => 'A', 'seen_ago' => 10, 'link' => 'ok', 'plan_access' => 'full', 'tenant' => ['name' => 'Zulu', 'plan' => 'pro'], 'block' => null, 'denials' => []],
            ['id' => 2, 'operator_callsign' => 'B', 'seen_ago' => 20, 'link' => 'no_account', 'plan_access' => 'none', 'tenant' => ['name' => 'Alpha', 'plan' => 'free'], 'block' => null, 'denials' => ['x']],
            ['id' => 3, 'operator_callsign' => 'C', 'seen_ago' => 30, 'link' => 'steam_mismatch', 'plan_access' => 'basic', 'tenant' => ['name' => 'Mike', 'plan' => 'free'], 'block' => ['id' => 1], 'denials' => ['y']],
        ];
        self::assertSame([2, 3, 1], array_column(AdminAtakFleetController::sortRows($rows, 'compte'), 'id'));
        self::assertSame([2, 3, 1], array_column(AdminAtakFleetController::sortRows($rows, 'communaute'), 'id'));
        self::assertSame([2, 3, 1], array_column(AdminAtakFleetController::sortRows($rows, 'formule'), 'id'));
        self::assertSame([3, 2, 1], array_column(AdminAtakFleetController::sortRows($rows, 'etat'), 'id'));
        self::assertSame([3, 2, 1], array_column(AdminAtakFleetController::sortRows($rows, 'recent', true), 'id'));
    }

    public function testSteamPlayersWithoutAccountAreListedForThePlatformOnly(): void
    {
        foreach (['steam', 'a_verifier', 'sans_carte'] as $state) {
            self::assertArrayHasKey($state, Fleet::STATES);
            self::assertContains($state, Fleet::PLATFORM_ONLY);
        }
        $controller = $this->read('app/Controllers/Admin/AdminAtakFleetController.php');
        self::assertStringContainsString('steamPlayers(', $controller);
        self::assertStringContainsString('steamFromModReports(', $controller);
        self::assertStringContainsString("'steam_keys'", $controller);
        $view = $this->read('views/admin/atak-fleet/index.php');
        self::assertStringContainsString('name="steam_keys[]"', $view);
        self::assertStringContainsString("admin/formules-offertes", $view);
        // Libellés traduits en anglais (parité i18n).
        $en = require dirname(__DIR__, 2) . '/lang/en/site_auto.php';
        foreach (AtakFleetLink::STATES as $info) {
            self::assertArrayHasKey($info[0], $en);
            self::assertArrayHasKey($info[2], $en);
        }
        foreach (AtakFleetLink::PLAN_ACCESS as $info) {
            self::assertArrayHasKey($info[0], $en);
        }
    }
}
