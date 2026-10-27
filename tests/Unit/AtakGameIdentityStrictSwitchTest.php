<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Api\AtakApiController;
use App\Core\Container;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\AtakAdminControlRepository;
use App\Repositories\AthenaAccountRepository;
use App\Repositories\TenantAtakConfigRepository;
use App\Repositories\TenantBrandingRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserNotificationPreferencesRepository;
use App\Repositories\UserProfileRepository;
use App\Repositories\UserRepository;
use App\Services\EmailService;
use App\Services\Game\GameAuthService;
use App\Services\Game\GameOverwatchExperienceService;
use App\Services\Tactical\AtakActivityLogService;
use App\Support\AtakAdminBlockGate;
use App\Support\AtakArmaWriteGuard;
use App\Support\AtakGameIdentityPolicy;
use App\Support\ComspecApiKeyAuth;
use App\Support\GameClientVersionGate;
use PDO;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Interrupteur du mode strict (AtakGameIdentityPolicy) et chemins de connexion qu'il touche.
 *
 * - Variable absente ou illisible : rien n'est lu (ni plancher, ni base) et rien ne change.
 * - Il suit le plancher plateforme des DLL (game_min_link_version) : l'abaisser sous la version configurée le coupe.
 * - steam/exchange (Steam déjà relié) demande alors le jeton de poste ; game-link/by-steam reste fermé même ouvert par
 *   le portail. Interrupteur coupé, ces deux chemins se comportent comme avant.
 *
 * Processus séparés : la recherche de la clé de communauté garde en variable statique, pour tout le processus, la
 * présence de la colonne access_key (TenantAtakConfigRepository::hasAccessKeyColumn) ; un test précédent sur une base
 * sans cette colonne la figerait.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AtakGameIdentityStrictSwitchTest extends TestCase
{
    private const SB = '76561190000000002';
    private const DEVICE = '0123456789abcdef0123456789abcdef';
    private const CKEY = 'cle-communaute-test-interrupteur';

    private int $tenantId;

    private PDO $pdo;

    private ?PDO $savedPdo = null;

    /** @var array<string, object> */
    private array $savedContainer = [];

    /** @var array<string, mixed>|null */
    private ?array $savedFloorMemo = null;

    /** @var list<array<string, mixed>> sessions de jeu enregistrées par GameAuthService */
    private array $sessions = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantId = 1300000 + random_int(1, 99999);
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->sqliteCreateFunction('NOW', static fn (): string => gmdate('Y-m-d H:i:s'), 0);
        $pdo->sqliteCreateFunction('DATABASE', static fn (): string => 'main', 0);
        $pdo->exec("ATTACH DATABASE ':memory:' AS information_schema");
        $pdo->exec('CREATE TABLE information_schema.COLUMNS (TABLE_SCHEMA TEXT, TABLE_NAME TEXT, COLUMN_NAME TEXT)');
        $pdo->exec('CREATE TABLE information_schema.TABLES (TABLE_SCHEMA TEXT, TABLE_NAME TEXT)');
        $pdo->prepare('INSERT INTO information_schema.COLUMNS VALUES (?, ?, ?)')->execute(['main', 'tenant_atak_config', 'access_key']);
        $pdo->exec('CREATE TABLE tenant_atak_config (tenant_id INTEGER, access_key TEXT, access_key_prefix TEXT)');
        $pdo->prepare('INSERT INTO tenant_atak_config (tenant_id, access_key, access_key_prefix) VALUES (?, ?, ?)')
            ->execute([$this->tenantId, self::CKEY, substr(self::CKEY, 0, 10)]);
        $pdo->exec('CREATE TABLE platform_settings (setting_key TEXT PRIMARY KEY, value TEXT)');
        $this->pdo = $pdo;

        $db = new \ReflectionProperty(Database::class, 'pdo');
        $saved = $db->getValue();
        $this->savedPdo = $saved instanceof PDO ? $saved : null;
        $db->setValue(null, $pdo);
        /** @var array<string, object> $instances */
        $instances = (new \ReflectionProperty(Container::class, 'instances'))->getValue();
        $this->savedContainer = $instances;
        $memo = new \ReflectionProperty(GameClientVersionGate::class, 'memo');
        $floor = $memo->getValue();
        $this->savedFloorMemo = is_array($floor) ? $floor : null;
        $memo->setValue(null, null);
        $blocks = $this->createMock(AtakAdminControlRepository::class);
        $blocks->method('activeBlockFor')->willReturn(null);
        AtakAdminBlockGate::useRepository($blocks);
        ComspecApiKeyAuth::resetForTests();
        $this->strict(null);
    }

    protected function tearDown(): void
    {
        $this->strict(null);
        putenv('ATAK_GAME_LINK_BY_STEAM');
        unset($_ENV['ATAK_GAME_LINK_BY_STEAM']);
        AtakAdminBlockGate::useRepository(null);
        ComspecApiKeyAuth::resetForTests();
        (new \ReflectionProperty(GameClientVersionGate::class, 'memo'))->setValue(null, $this->savedFloorMemo);
        (new \ReflectionProperty(Container::class, 'instances'))->setValue(null, $this->savedContainer);
        (new \ReflectionProperty(Database::class, 'pdo'))->setValue(null, $this->savedPdo);
        parent::tearDown();
    }

    // --- Interrupteur ------------------------------------------------------------------------------------------------

    /** Variable absente, vide ou illisible : mode strict coupé, sans lire le plancher. */
    public function testSwitchIsOffWithoutAReadableVersionAndReadsNothing(): void
    {
        $reads = 0;
        AtakGameIdentityPolicy::useFloorReaderForTests(static function () use (&$reads): string {
            ++$reads;

            return '9.9.9';
        });
        self::assertSame('', AtakGameIdentityPolicy::configuredMinLink());
        self::assertFalse(AtakGameIdentityPolicy::strict(), 'variable absente');
        foreach (['', '   ', 'oui', '2.0.x', '2.0.99-native', '>=2.0.99'] as $raw) {
            putenv(AtakGameIdentityPolicy::ENV_STRICT_MIN_LINK . '=' . $raw);
            $_ENV[AtakGameIdentityPolicy::ENV_STRICT_MIN_LINK] = $raw;
            self::assertFalse(AtakGameIdentityPolicy::strict(), 'valeur « ' . $raw . ' »');
        }
        self::assertSame(0, $reads, 'le plancher n’est jamais lu');
    }

    /** Version configurée : actif seulement quand le plancher des DLL l'atteint ; plancher illisible ou base en panne : coupé. */
    public function testSwitchFollowsThePlatformFloor(): void
    {
        foreach ([
            ['', false], ['2.0.96', false], ['2.0.98', false], ['2.0.99', true], ['2.0.99.0', true], ['v2.0.99', true],
            ['2.1', true], ['3.0.0', true], ['illisible', false],
        ] as [$floor, $expected]) {
            $this->strict('2.0.99', $floor);
            self::assertSame($expected, AtakGameIdentityPolicy::strict(), 'plancher « ' . $floor . ' »');
        }
        $this->strict('v2.0.99', '2.0.99');
        self::assertTrue(AtakGameIdentityPolicy::strict(), 'version configurée avec un « v » initial');

        $this->strict('2.0.99');
        AtakGameIdentityPolicy::useFloorReaderForTests(static function (): string {
            throw new \RuntimeException('base injoignable');
        });
        self::assertFalse(AtakGameIdentityPolicy::strict(), 'lecture du plancher en échec');
    }

    /** Sans lecteur de test : le réglage plateforme réel (platform_settings), et l'abaisser coupe le mode strict. */
    public function testSwitchReadsThePlatformSetting(): void
    {
        $this->strict('2.0.99');
        AtakGameIdentityPolicy::useFloorReaderForTests(null);
        foreach ([[null, false], ['2.0.98', false], ['2.0.99', true], ['2.0.97', false]] as [$floor, $expected]) {
            $this->pdo->exec('DELETE FROM platform_settings');
            if ($floor !== null) {
                $this->pdo->prepare('INSERT INTO platform_settings (setting_key, value) VALUES (?, ?)')
                    ->execute(['game_min_link_version', $floor]);
            }
            (new \ReflectionProperty(GameClientVersionGate::class, 'memo'))->setValue(null, null);
            self::assertSame($expected, AtakGameIdentityPolicy::strict(), 'plancher ' . var_export($floor, true));
        }
    }

    // --- steam/exchange ----------------------------------------------------------------------------------------------

    /** Interrupteur coupé : le Steam relié suffit, sans jeton de poste, comme avant. */
    public function testSteamExchangeIsUnchangedWithTheSwitchOff(): void
    {
        $res = $this->steamService()->steamExchange(['steam_id' => self::SB, 'device_id' => self::DEVICE]);
        self::assertTrue($res['ok']);
        self::assertSame(200, $res['status']);
        self::assertSame(64, strlen((string) ($res['payload']['tokens']['access_token'] ?? '')));
        self::assertCount(1, $this->sessions);
        self::assertSame(102, $this->sessions[0]['user_id'] ?? null);
    }

    /** Mode strict : sans jeton de poste valide, plus de session ; avec, la connexion par Steam reste possible. */
    public function testSteamExchangeNeedsThePairedDeviceInStrictMode(): void
    {
        $this->strict('2.0.99', '2.0.99');
        $pairing = str_repeat('a1', 32);

        foreach (['sans jeton de poste' => '', 'jeton de poste faux' => str_repeat('b2', 32)] as $label => $presented) {
            $res = $this->steamService($pairing)->steamExchange(['steam_id' => self::SB, 'device_id' => self::DEVICE, 'pairing_token' => $presented]);
            self::assertFalse($res['ok'], $label);
            self::assertSame(401, $res['status'], $label);
            self::assertSame('STEAM_NOT_LINKED', $res['payload']['error'] ?? null, $label);
            self::assertSame('pairing_required', $res['payload']['reason'] ?? null, $label);
            self::assertArrayNotHasKey('tokens', $res['payload'], $label);
        }
        self::assertSame([], $this->sessions, 'aucune session enregistrée');

        $res = $this->steamService($pairing)->steamExchange(['steam_id' => self::SB, 'device_id' => self::DEVICE, 'pairing_token' => $pairing]);
        self::assertTrue($res['ok'], 'jeton de poste valide');
        self::assertSame(64, strlen((string) ($res['payload']['tokens']['access_token'] ?? '')));
        self::assertCount(1, $this->sessions);

        // Steam inconnu : même réponse qu'avant (rien n'indique qu'un jeton de poste aurait suffi).
        $res = $this->steamService($pairing)->steamExchange(['steam_id' => '76561190000000099', 'device_id' => self::DEVICE]);
        self::assertSame(401, $res['status']);
        self::assertArrayNotHasKey('reason', $res['payload']);
    }

    // --- game-link/by-steam ------------------------------------------------------------------------------------------

    public function testLinkBySteamStaysClosedInStrictModeEvenWhenThePortalOpensIt(): void
    {
        [$code, $json] = $this->bySteam();
        self::assertSame(403, $code, 'fermé par défaut, comme avant');
        self::assertSame('steam_link_disabled', $json['reason'] ?? null);

        putenv('ATAK_GAME_LINK_BY_STEAM=1');
        $_ENV['ATAK_GAME_LINK_BY_STEAM'] = '1';
        [$code, $json] = $this->bySteam();
        self::assertSame(200, $code, 'ouvert par le portail, interrupteur coupé : inchangé');
        self::assertSame(self::CKEY, $json['api_key'] ?? null);

        $this->strict('2.0.99', '2.0.99');
        [$code, $json] = $this->bySteam();
        self::assertSame(403, $code);
        self::assertSame('unauthorized', $json['error'] ?? null);
        self::assertSame('steam_link_disabled', $json['reason'] ?? null);
        self::assertArrayNotHasKey('api_key', $json);
    }

    // --- Outillage ---------------------------------------------------------------------------------------------------

    /** Version configurée (null : variable absente) et plancher lu par la politique (null : lecteur de test retiré). */
    private function strict(?string $min, ?string $floor = null): void
    {
        if ($min === null) {
            putenv(AtakGameIdentityPolicy::ENV_STRICT_MIN_LINK);
            unset($_ENV[AtakGameIdentityPolicy::ENV_STRICT_MIN_LINK]);
        } else {
            putenv(AtakGameIdentityPolicy::ENV_STRICT_MIN_LINK . '=' . $min);
            $_ENV[AtakGameIdentityPolicy::ENV_STRICT_MIN_LINK] = $min;
        }
        AtakGameIdentityPolicy::useFloorReaderForTests($floor !== null ? static fn (): string => $floor : null);
    }

    /** GameAuthService réel ; dépôts simulés pour le membre B (102), relié à SB, compte Athena 202. */
    private function steamService(?string $pairing = null): GameAuthService
    {
        $userB = ['id' => 102, 'tenant_id' => $this->tenantId, 'steam_id' => self::SB, 'status' => 'active', 'email' => 'b@example.test', 'callsign' => 'BRAVO', 'display_name' => 'Membre B'];
        $accountB = ['id' => 202, 'public_id' => 'acc-b', 'email' => 'b@example.test', 'steam_id' => self::SB, 'status' => 'active', 'display_name' => 'Membre B'];
        $membership = ['tenant_id' => $this->tenantId, 'user_id' => 102, 'status' => 'active', 'tenant_name' => 'Test', 'user_steam_id' => self::SB, 'display_name' => 'Membre B'];

        $users = $this->createMock(UserRepository::class);
        $users->method('findBySteamId')->willReturnCallback(static fn (string $s): ?array => $s === self::SB ? $userB : null);
        $users->method('findById')->willReturn($userB);
        $accounts = $this->createMock(AthenaAccountRepository::class);
        $accounts->method('findByEmail')->willReturn($accountB);
        $accounts->method('listActiveMemberships')->willReturn([$membership]);
        $accounts->method('findMembership')->willReturn($membership);
        $accounts->method('findPairing')->willReturnCallback(static fn (int $accountId, string $deviceId): ?array => $pairing !== null && $accountId === 202 && $deviceId === self::DEVICE
            ? ['id' => 5, 'pairing_token_hash' => hash('sha256', $pairing)]
            : null);
        $accounts->method('insertSession')->willReturnCallback(function (array $fields): int {
            $this->sessions[] = $fields;

            return count($this->sessions);
        });
        $tenants = $this->createMock(TenantRepository::class);
        $tenants->method('findById')->willReturn(['id' => $this->tenantId, 'name' => 'Test', 'slug' => 'test']);
        $experience = $this->createMock(GameOverwatchExperienceService::class);
        $experience->method('get')->willReturn(['display_name' => '']);

        return new GameAuthService(
            $accounts,
            $users,
            $tenants,
            $this->createMock(EmailService::class),
            $experience,
            $this->createMock(UserProfileRepository::class),
            $this->createMock(TenantBrandingRepository::class),
            $this->createMock(UserNotificationPreferencesRepository::class),
        );
    }

    private function controller(): AtakApiController
    {
        $userB = ['id' => 102, 'tenant_id' => $this->tenantId, 'steam_id' => self::SB, 'status' => 'active', 'callsign' => 'BRAVO', 'display_name' => 'Membre B'];
        $users = $this->createMock(UserRepository::class);
        $users->method('findBySteamId')->willReturnCallback(static fn (string $s): ?array => $s === self::SB ? $userB : null);
        $config = $this->createMock(TenantAtakConfigRepository::class);
        $config->method('getByTenantId')->willReturn([]);
        $guard = $this->createMock(AtakArmaWriteGuard::class);
        $guard->method('assertModNotBlocked')->willReturn(null);
        $controller = (new \ReflectionClass(AtakApiController::class))->newInstanceWithoutConstructor();
        foreach ([
            'userRepository' => $users,
            'tenantAtakConfigRepository' => $config,
            'activityLog' => $this->createMock(AtakActivityLogService::class),
            'armaGuard' => $guard,
        ] as $name => $value) {
            (new \ReflectionProperty(AtakApiController::class, $name))->setValue($controller, $value);
        }

        return $controller;
    }

    /** @return array{0: int, 1: array<string, mixed>} */
    private function bySteam(): array
    {
        return $this->call(['steam_uid' => self::SB], static fn (AtakApiController $c, Request $r): Response => $c->gameLinkBySteam($r));
    }

    /**
     * @param array<string, mixed> $body
     * @param callable(AtakApiController, Request): Response $action
     * @return array{0: int, 1: array<string, mixed>}
     */
    private function call(array $body, callable $action): array
    {
        $controller = $this->controller();
        (new \ReflectionProperty(AtakApiController::class, 'jsonBodyCache'))->setValue($controller, $body);
        $response = $action($controller, new Request());
        $json = json_decode($response->body(), true);

        return [$response->statusCode(), is_array($json) ? $json : []];
    }
}
