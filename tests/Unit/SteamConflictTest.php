<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\SteamConflictController;
use App\Core\Request;
use App\Middleware\AuthMiddleware;
use App\Repositories\MemberNotificationRepository;
use App\Repositories\UserRepository;
use App\Services\Account\SteamConflictService;
use App\Services\Audit\AuditService;
use App\Support\ArmaText;
use App\Support\AtakFleetLink;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Steam relié aux comptes Athena de deux personnes : détection, page imposée, les trois choix, l'audit.
 * Base SQLite jetable : les requêtes du service restent du SQL portable.
 */
final class SteamConflictTest extends TestCase
{
    private const STEAM = '76561198000005374';

    private PDO $pdo;
    /** @var list<array{0: string, 1: ?int, 2: int}> action, tenant, fiche visée */
    private array $audit = [];
    /** @var list<array{0: int, 1: int, 2: string}> */
    private array $notified = [];

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, tenant_id INT, email TEXT, status TEXT, callsign TEXT, created_at TEXT, steam_id TEXT, deleted_at TEXT)');
        $this->pdo->exec('CREATE TABLE athena_accounts (id INTEGER PRIMARY KEY, email TEXT, steam_id TEXT)');
        $this->pdo->exec('CREATE TABLE game_sessions (id INTEGER PRIMARY KEY, user_id INT, steam_id TEXT, revoked_at TEXT)');
        $this->pdo->exec("CREATE TABLE steam_conflict_reports (id INTEGER PRIMARY KEY AUTOINCREMENT, steam_id TEXT, reporter_user_id INT, reporter_tenant_id INT,
            removed_user_ids TEXT DEFAULT '', other_user_ids TEXT DEFAULT '', status TEXT DEFAULT 'open', created_at TEXT, resolved_at TEXT, resolved_by INT)");
        $ins = $this->pdo->prepare('INSERT INTO users (id, tenant_id, email, status, callsign, created_at, steam_id, deleted_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        // Moi : deux communautés, même e-mail. L'autre personne : autre e-mail, même Steam.
        $ins->execute([10, 1, 'moi@ex.fr', 'active', 'Venny', '2025-01-10 10:00:00', self::STEAM, null]);
        $ins->execute([11, 2, 'MOI@ex.fr', 'active', 'Venny', '2025-02-10 10:00:00', self::STEAM, null]);
        $ins->execute([20, 3, 'autre@ex.fr', 'active', 'Bravo', '2025-03-05 10:00:00', self::STEAM, null]);
        // Fiche supprimée qui garde le Steam : ignorée.
        $ins->execute([30, 4, 'x@deleted.invalid', 'deleted', '', '2024-01-01 00:00:00', self::STEAM, null]);
        $this->pdo->exec("INSERT INTO athena_accounts (id, email, steam_id) VALUES (1, 'moi@ex.fr', NULL), (2, 'autre@ex.fr', '" . self::STEAM . "')");
        $this->pdo->exec("INSERT INTO game_sessions (id, user_id, steam_id, revoked_at) VALUES (1, 20, '" . self::STEAM . "', NULL)");
        $_SESSION = [];
        $this->audit = [];
        $this->notified = [];
    }

    private function service(): SteamConflictService
    {
        $audit = $this->createMock(AuditService::class);
        $audit->method('log')->willReturnCallback(function (string $action, ?int $tenantId = null, ?int $userId = null, ?string $type = null, ?int $entityId = null): void {
            $this->audit[] = [$action, $tenantId, (int) $entityId];
        });
        $notes = $this->createMock(MemberNotificationRepository::class);
        $notes->method('create')->willReturnCallback(function (int $tenantId, int $userId, string $type): int {
            $this->notified[] = [$tenantId, $userId, $type];

            return 1;
        });

        return new SteamConflictService($this->pdo, $audit, $notes);
    }

    /** @return array<string, mixed> */
    private function user(int $id): array
    {
        $st = $this->pdo->prepare('SELECT * FROM users WHERE id = ?');
        $st->execute([$id]);

        return $st->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    private function steamOf(int $id): ?string
    {
        $v = $this->pdo->query('SELECT steam_id FROM users WHERE id = ' . $id)->fetchColumn();

        return $v === null || $v === false ? null : (string) $v;
    }

    public function testDetectsTwoPeopleButNotOnePersonInTwoCommunities(): void
    {
        $svc = $this->service();
        self::assertSame(2, $svc->ownerCount(self::STEAM));
        $conflict = $svc->conflictFor($this->user(10));
        self::assertNotNull($conflict);
        self::assertSame([20], array_column($conflict['others'], 'id'), 'ma fiche de l’autre communauté et la fiche supprimée ne comptent pas');

        $this->pdo->exec('UPDATE users SET steam_id = NULL WHERE id = 20');
        self::assertNull($svc->conflictFor($this->user(10)));
        self::assertSame(1, $svc->ownerCount(self::STEAM));
    }

    public function testFleetCountsPeopleNotMemberRecords(): void
    {
        $me = ['id' => 10, 'tenant_id' => 1, 'status' => 'active', 'email' => 'moi@ex.fr', 'steam_id' => self::STEAM];
        $sameMe = ['id' => 11, 'tenant_id' => 2] + $me;
        $other = ['id' => 20, 'email' => 'autre@ex.fr'] + $me;
        self::assertSame('ok', AtakFleetLink::classify(true, 1, $me, [$me, $sameMe], self::STEAM));
        self::assertSame('duplicate', AtakFleetLink::classify(true, 1, $me, [$me, $other], self::STEAM));
        self::assertContains('Steam en double', AtakFleetLink::denialReasons('duplicate', 'full', null));
    }

    public function testKeepHereWithSteamProofMovesSteamAuditsAndNotifies(): void
    {
        $result = $this->service()->keepHere($this->user(10), self::STEAM);

        self::assertTrue($result['ok']);
        self::assertSame([20], $result['removed']);
        self::assertNull($this->steamOf(20), 'retiré de l’autre personne');
        self::assertSame(self::STEAM, $this->steamOf(10));
        self::assertSame(self::STEAM, $this->steamOf(11), 'mes autres fiches gardent le Steam');
        self::assertNull($this->pdo->query('SELECT steam_id FROM athena_accounts WHERE id = 2')->fetchColumn() ?: null);
        self::assertNotNull($this->pdo->query('SELECT revoked_at FROM game_sessions WHERE id = 1')->fetchColumn() ?: null, 'le jeu ouvert sur l’autre compte se reconnecte');
        self::assertContains(['user.steam_conflict_kept', 1, 10], $this->audit);
        self::assertContains(['user.steam_removed_by_owner', 3, 20], $this->audit);
        self::assertSame([[3, 20, 'steam_removed_by_owner']], $this->notified);
        self::assertNull($this->service()->conflictFor($this->user(10)));
    }

    public function testKeepHereIsRefusedWhenTheOtherAccountIsSuspended(): void
    {
        $this->pdo->exec("UPDATE users SET status = 'banned' WHERE id = 20");
        $result = $this->service()->keepHere($this->user(10), self::STEAM);

        self::assertFalse($result['ok']);
        self::assertSame(self::STEAM, $this->steamOf(20));
        self::assertSame([], $this->audit);
    }

    public function testChoosingKeepWithoutSteamProofChangesNothing(): void
    {
        $users = $this->createMock(UserRepository::class);
        $users->method('findById')->willReturn($this->user(10));
        $_SESSION = ['user_id' => 10, 'tenant_id' => 1];

        $response = (new SteamConflictController($this->service(), $users))->keep(new Request());

        self::assertSame(302, $response->statusCode());
        self::assertStringContainsString('account/steam/connect', (string) $response->headerValue('Location'));
        self::assertSame(self::STEAM, $_SESSION[SteamConflictController::SESSION_KEEP]);
        self::assertSame(self::STEAM, $this->steamOf(20), 'rien n’est retiré avant la preuve Steam');
        self::assertSame([], $this->audit);
        // Une preuve qui n'est pas un compte Steam valide ne retire rien non plus.
        self::assertFalse($this->service()->keepHere($this->user(10), 'pas-un-steam')['ok']);
        self::assertSame(self::STEAM, $this->steamOf(20));
    }

    public function testSteamCallbackOnlyTrustsTheVerifiedSteamMatchingTheChoice(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Web/AccountController.php');
        $callback = substr($src, (int) strpos($src, 'public function steamCallback'));
        $verify = strpos($callback, '$openid->verify($query)');
        $mismatch = strpos($callback, '$keep !== $steamId');
        $keepHere = strpos($callback, '->keepHere($user, $steamId)');
        self::assertNotFalse($verify);
        self::assertNotFalse($mismatch);
        self::assertNotFalse($keepHere);
        self::assertTrue($verify < $mismatch && $mismatch < $keepHere, 'preuve Steam vérifiée, puis même Steam que le choix, puis retrait');
    }

    public function testReleaseKeepsTheOtherAccountAndClearsOnlyMine(): void
    {
        $removed = $this->service()->release($this->user(10));

        self::assertSame([10, 11], $removed);
        self::assertSame(self::STEAM, $this->steamOf(20));
        self::assertContains(['user.steam_conflict_released', 1, 10], $this->audit);
        self::assertSame([], $this->notified);
    }

    public function testReportSetsSteamAsideOpensACaseAndAdminCanRestore(): void
    {
        $svc = $this->service();
        $id = $svc->report($this->user(10));

        self::assertGreaterThan(0, $id);
        self::assertNull($this->steamOf(10));
        self::assertNull($this->steamOf(11));
        self::assertSame(self::STEAM, $this->steamOf(20), 'l’autre compte n’est pas touché sans preuve');
        self::assertSame(SteamConflictService::STATE_REPORTED, $svc->stateFor(self::STEAM));
        self::assertContains(['user.steam_conflict_reported', 1, 10], $this->audit);

        self::assertTrue($svc->adminDecide($id, 'restore', 99));
        self::assertSame(self::STEAM, $this->steamOf(10));
        self::assertSame(self::STEAM, $this->steamOf(11));
        self::assertSame(SteamConflictService::STATE_PENDING, $svc->stateFor(self::STEAM), 'de nouveau en attente du choix du joueur');
        self::assertContains(['admin.steam_conflict_restored', 1, 10], $this->audit);
        self::assertFalse($svc->adminDecide($id, 'close', 99), 'déjà traité');
    }

    public function testEveryPageRedirectsToTheChoiceWhileTheConflictLasts(): void
    {
        $svc = $this->service();
        $me = $this->user(10);
        $go = static function (string $method, string $path, bool $browser = true) use ($svc, $me): ?string {
            $_SERVER['REQUEST_METHOD'] = $method;
            $_SERVER['REQUEST_URI'] = $path;
            $_SERVER['HTTP_ACCEPT'] = $browser ? 'text/html' : 'application/json';
            unset($_SERVER['HTTP_SEC_FETCH_DEST']);
            $r = AuthMiddleware::steamConflictStep(new Request(), $me, $svc);

            return $r?->headerValue('Location');
        };

        self::assertStringContainsString('account/steam-doublon', (string) $go('GET', '/dashboard'));
        self::assertStringContainsString('account/steam-doublon', (string) $go('GET', '/account/preferences'));
        self::assertStringContainsString('account/steam-doublon', (string) $go('POST', '/lier'), 'liaison du jeu bloquée');
        self::assertNull($go('GET', '/account/steam-doublon'), 'la page de choix reste ouverte');
        self::assertNull($go('GET', '/account/steam/callback'), 'la preuve Steam reste ouverte');
        self::assertNull($go('GET', '/notifications/poll', false), 'les appels en arrière-plan passent');

        $this->pdo->exec('UPDATE users SET steam_id = NULL WHERE id = 20');
        self::assertNull($go('GET', '/dashboard'), 'réglé : plus de redirection');
    }

    public function testOtherAccountHintRevealsNoPersonalData(): void
    {
        $hint = SteamConflictService::maskedHint(['callsign' => 'Bravo', 'created_at' => '2025-03-05 10:00:00']);
        self::assertSame('indicatif B•••o, créé en mars 2025', $hint);
        self::assertStringNotContainsString('autre@ex.fr', $hint);
    }

    public function testRawArmaTranslationKeysAreResolved(): void
    {
        self::assertSame('Commandant', ArmaText::label('STR_role_default_commander_role_name'));
        self::assertSame('Commandant', ArmaText::label('$STR_role_default_commander_role_name'));
        self::assertSame('SLOT:Commandant', ArmaText::roleLabel('SLOT:STR_ROLE_DEFAULT_COMMANDER_ROLE_NAME'));
        self::assertSame('Rifleman at', ArmaText::label('STR_A3_CfgRoles_Rifleman_AT'));
        self::assertSame('Auxiliaire sanitaire', ArmaText::label('Auxiliaire sanitaire'));
        self::assertSame('', ArmaText::label('STR_role_default_name'));

        $items = \App\Repositories\AtakScreenTimeRepository::normalizeItems([
            ['kind' => 'role', 'key' => 'SLOT:STR_ROLE_DEFAULT_COMMANDER_ROLE_NAME', 'label' => 'STR_role_default_commander_role_name', 'seconds' => 60],
        ]);
        self::assertSame('Commandant', $items[0]['label']);
        self::assertSame('Commandant', \App\Support\AtakRoleDetection::fallbackLabel('SLOT:STR_ROLE_DEFAULT_COMMANDER_ROLE_NAME'));
        self::assertSame('Commandant', \App\Services\Game\GameAuthService::usableIdentityText('STR_role_default_commander_role_name'));
    }
}
