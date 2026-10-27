<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Api\AtakApiController;
use App\Core\Container;
use App\Core\Database;
use App\Core\Request;
use App\Repositories\TacticalGameLinkRepository;
use App\Repositories\TenantAtakConfigRepository;
use App\Repositories\UserRepository;
use App\Services\Game\GameAuthService;
use App\Services\Tactical\AtakActivityLogService;
use App\Support\AtakArmaWriteGuard;
use App\Support\ComspecApiKeyAuth;
use PDO;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * POST /api/atak/game-link/redeem : le code de liaison à usage unique, généré par le membre connecté au site, remet en
 * plus son jeton d'accès jeu personnel à la DLL qui annonce son poste (device_id). Les DLL publiées n'envoient pas de
 * device_id : leur réponse ne change pas et Game Auth n'est pas appelé. Un refus ou une panne de Game Auth laisse la
 * liaison par clé réussir comme avant.
 *
 * Processus séparés : la clé de communauté est lue par TenantAtakConfigRepository, qui garde en variable statique,
 * pour tout le processus, la présence de la colonne access_key ; un test précédent sur une autre base la figerait.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AtakGameLinkPersonalTokenTest extends TestCase
{
    private const SB = '76561190000000002';
    private const DEVICE = '0123456789abcdef0123456789abcdef';
    private const CKEY = 'cle-communaute-test-code-liaison';
    private const ISSUED = 'cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc';

    private int $tenantId;

    private ?PDO $savedPdo = null;

    /** @var array<string, object> */
    private array $savedContainer = [];

    /** @var list<array{0: int, 1: int, 2: array<string, mixed>}> appels à GameAuthService::issueForUser */
    private array $issueCalls = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantId = 1400000 + random_int(1, 99999);
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->sqliteCreateFunction('NOW', static fn (): string => gmdate('Y-m-d H:i:s'), 0);
        $pdo->sqliteCreateFunction('DATABASE', static fn (): string => 'main', 0);
        $pdo->exec("ATTACH DATABASE ':memory:' AS information_schema");
        $pdo->exec('CREATE TABLE information_schema.COLUMNS (TABLE_SCHEMA TEXT, TABLE_NAME TEXT, COLUMN_NAME TEXT)');
        $pdo->prepare('INSERT INTO information_schema.COLUMNS VALUES (?, ?, ?)')->execute(['main', 'tenant_atak_config', 'access_key']);
        $pdo->exec('CREATE TABLE tenant_atak_config (tenant_id INTEGER, access_key TEXT, access_key_prefix TEXT)');
        $pdo->prepare('INSERT INTO tenant_atak_config (tenant_id, access_key, access_key_prefix) VALUES (?, ?, ?)')
            ->execute([$this->tenantId, self::CKEY, substr(self::CKEY, 0, 10)]);

        $db = new \ReflectionProperty(Database::class, 'pdo');
        $saved = $db->getValue();
        $this->savedPdo = $saved instanceof PDO ? $saved : null;
        $db->setValue(null, $pdo);
        /** @var array<string, object> $instances */
        $instances = (new \ReflectionProperty(Container::class, 'instances'))->getValue();
        $this->savedContainer = $instances;
        ComspecApiKeyAuth::resetForTests();
    }

    protected function tearDown(): void
    {
        ComspecApiKeyAuth::resetForTests();
        (new \ReflectionProperty(Container::class, 'instances'))->setValue(null, $this->savedContainer);
        (new \ReflectionProperty(Database::class, 'pdo'))->setValue(null, $this->savedPdo);
        parent::tearDown();
    }

    /** DLL publiées (pas de device_id) : réponse inchangée, Game Auth n'est pas appelé. */
    public function testLinkCodeWithoutDeviceIdKeepsTheOldResponse(): void
    {
        $this->authReturns(['ok' => true, 'status' => 200, 'payload' => ['tokens' => $this->issuedTokens()]]);
        [$code, $json] = $this->redeem(['code' => 'ABCD12', 'steam_uid' => self::SB]);
        self::assertSame(200, $code);
        self::assertSame(['ok', 'api_url', 'api_key', 'tenant_id'], array_keys($json));
        self::assertSame(self::CKEY, $json['api_key']);
        self::assertSame((string) $this->tenantId, $json['tenant_id']);
        self::assertSame([], $this->issueCalls);
    }

    /** DLL qui annonce son poste : le jeton personnel du membre qui a généré le code, en plus de la clé. */
    public function testLinkCodeWithDeviceIdAlsoReturnsThePersonalToken(): void
    {
        $this->authReturns(['ok' => true, 'status' => 200, 'payload' => ['tokens' => $this->issuedTokens()]]);
        [$code, $json] = $this->redeem(['code' => 'ABCD12', 'steam_uid' => self::SB, 'device_id' => self::DEVICE, 'mod_version' => '2.0.99']);
        self::assertSame(200, $code);
        self::assertSame(self::CKEY, $json['api_key'] ?? null);
        self::assertSame($this->issuedTokens(), $json['tokens'] ?? null);
        self::assertCount(1, $this->issueCalls);
        [$userId, $tenantId, $fields] = $this->issueCalls[0];
        self::assertSame(102, $userId);
        self::assertSame($this->tenantId, $tenantId);
        self::assertSame(self::DEVICE, $fields['device_id'] ?? null);
        self::assertSame(self::SB, $fields['steam_id'] ?? null);
        self::assertSame('2.0.99', $fields['mod_version'] ?? null);

        // Sans Steam détecté en jeu : Game Auth reprend celui de la fiche (aucun steam_id vide imposé).
        $this->redeem(['code' => 'ABCD12', 'device_id' => self::DEVICE]);
        self::assertArrayNotHasKey('steam_id', $this->issueCalls[1][2]);
    }

    /** Refus ou panne de Game Auth : la liaison par clé réussit comme avant, sans jeton. */
    public function testLinkCodeStillSucceedsWithoutTokenWhenGameAuthRefuses(): void
    {
        foreach ([
            'refus' => ['ok' => false, 'status' => 401, 'payload' => ['error' => 'INVALID_CREDENTIALS']],
            'jeton vide' => ['ok' => true, 'status' => 200, 'payload' => ['tokens' => ['access_token' => '']]],
            'panne' => new \RuntimeException('base injoignable'),
        ] as $label => $result) {
            $this->authReturns($result);
            [$code, $json] = $this->redeem(['code' => 'ABCD12', 'steam_uid' => self::SB, 'device_id' => self::DEVICE]);
            self::assertSame(200, $code, $label);
            self::assertSame(self::CKEY, $json['api_key'] ?? null, $label);
            self::assertArrayNotHasKey('tokens', $json, $label);
        }
    }

    // --- Outillage ---------------------------------------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function issuedTokens(): array
    {
        return ['access_token' => self::ISSUED, 'refresh_token' => str_repeat('d', 64), 'expires_in' => 7200, 'device_id' => self::DEVICE, 'pairing_token' => str_repeat('e', 64)];
    }

    /** GameAuthService simulé dans le conteneur : résultat d'issueForUser, ou exception levée. */
    private function authReturns(array|\Throwable $result): void
    {
        $auth = $this->createMock(GameAuthService::class);
        $auth->method('issueForUser')->willReturnCallback(function (int $userId, int $tenantId, array $body) use ($result): array {
            $this->issueCalls[] = [$userId, $tenantId, $body];
            if ($result instanceof \Throwable) {
                throw $result;
            }

            return $result;
        });
        /** @var array<string, object> $instances */
        $instances = (new \ReflectionProperty(Container::class, 'instances'))->getValue();
        $instances[GameAuthService::class] = $auth;
        (new \ReflectionProperty(Container::class, 'instances'))->setValue(null, $instances);
    }

    /**
     * Code ABCD12 généré par le membre B (102) ; contrôleur réel, dépôts simulés.
     *
     * @param array<string, mixed> $body
     * @return array{0: int, 1: array<string, mixed>}
     */
    private function redeem(array $body): array
    {
        $userB = ['id' => 102, 'tenant_id' => $this->tenantId, 'steam_id' => self::SB, 'status' => 'active', 'callsign' => 'BRAVO', 'display_name' => 'Membre B'];
        $users = $this->createMock(UserRepository::class);
        $users->method('findById')->willReturn($userB);
        $users->method('update')->willReturn(true);
        $links = $this->createMock(TacticalGameLinkRepository::class);
        $links->method('findValidByCode')->willReturnCallback(fn (string $code): ?array => $code === 'ABCD12'
            ? ['id' => 9, 'tenant_id' => $this->tenantId, 'user_id' => 102]
            : null);
        $config = $this->createMock(TenantAtakConfigRepository::class);
        $config->method('getByTenantId')->willReturn([]);
        $guard = $this->createMock(AtakArmaWriteGuard::class);
        $guard->method('assertModNotBlocked')->willReturn(null);
        $controller = (new \ReflectionClass(AtakApiController::class))->newInstanceWithoutConstructor();
        foreach ([
            'userRepository' => $users,
            'gameLinkRepository' => $links,
            'tenantAtakConfigRepository' => $config,
            'activityLog' => $this->createMock(AtakActivityLogService::class),
            'armaGuard' => $guard,
            'jsonBodyCache' => $body,
        ] as $name => $value) {
            (new \ReflectionProperty(AtakApiController::class, $name))->setValue($controller, $value);
        }
        $response = $controller->gameLinkRedeem(new Request());
        $json = json_decode($response->body(), true);

        return [$response->statusCode(), is_array($json) ? $json : []];
    }
}
