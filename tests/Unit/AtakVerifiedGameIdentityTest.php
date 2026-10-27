<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Api\AtakApiController;
use App\Core\Container;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Middleware\ComspecTacticalApiMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Repositories\AtakDataRepository;
use App\Repositories\AtakOrderRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;
use App\Services\Moderation\IndicatorBlocklistService;
use App\Services\Platform\FeatureGateService;
use App\Services\Security\FileRateLimiter;
use App\Services\Tactical\AtakActivityLogService;
use App\Support\AtakAdminBlockGate;
use App\Support\AtakArmaWriteGuard;
use App\Support\AtakGameSession;
use App\Support\ComspecApiKeyAuth;
use PDO;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** PDO sqlite qui traduit « SHOW TABLES LIKE » (MySQL) pour les dépôts qui sondent le schéma. */
final class VerifiedIdentityTestPdo extends PDO
{
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): \PDOStatement|false
    {
        if (preg_match("/^\\s*SHOW TABLES LIKE '([^']+)'/i", $query, $m) === 1) {
            $query = "SELECT name FROM sqlite_master WHERE type = 'table' AND name LIKE '" . $m[1] . "'";
        }

        return $fetchMode === null ? parent::query($query) : parent::query($query, $fetchMode, ...$fetchModeArgs);
    }
}

/**
 * Identité de jeu par la VRAIE chaîne : ComspecTacticalApiMiddleware -> RateLimitMiddleware -> AtakApiController
 * (chatStore, clientInit, routingIdentity, adminControlPlayer) -> AtakArmaWriteGuard réelle, dépôt des membres réel
 * sur sqlite, clé de communauté et jeton d'accès jeu personnel (game_sessions) réels.
 *
 * Membres de la communauté : A (101) et B (102) actifs, D (104) désactivé ; C (103) dans une autre communauté.
 * L'auteur retenu se lit dans le journal Activité du message (user_id, Steam), comme sur le site.
 *
 * Seul le Steam d'un autre membre présenté avec un jeton personnel change de sort (403 steam_mismatch).
 *
 * Processus séparés : la chaîne réelle garde des caches de schéma statiques pour tout le processus
 * (TenantAtakConfigRepository::hasAccessKeyColumn, UserRepository) ; un test précédent sur une autre base les figerait,
 * et ceux de ce test ne doivent pas déborder sur les suivants.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AtakVerifiedGameIdentityTest extends TestCase
{
    private const OTHER_TENANT = 9999;
    private const SA = '76561190000000001';
    private const SB = '76561190000000002';
    private const SC = '76561190000000003';
    private const SD = '76561190000000004';
    private const CKEY = 'cle-communaute-test-identite-jeu';
    private const TOKEN_A = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const TOKEN_A_EMAIL = 'eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee';
    private const TOKEN_A_EXPIRED = 'ffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff';

    private int $tenantId;

    private PDO $pdo;

    private ?PDO $savedPdo = null;

    /** @var array<string, object> */
    private array $savedContainer = [];

    private bool $savedStarted = false;

    /** @var array<string, mixed> */
    private array $savedServer = [];

    /** @var list<string> raisons passées au journal Liaison par la garde */
    private array $guardLog = [];

    /** @var list<string> */
    private array $sessionTokens = [];

    private const SERVER_KEYS = [
        'REQUEST_METHOD', 'REQUEST_URI', 'REMOTE_ADDR', 'SCRIPT_NAME', 'HTTP_X_COMSPEC_KEY', 'HTTP_X_ATAK_TOKEN',
        'HTTP_AUTHORIZATION', 'HTTP_X_COMSPEC_SESSION', 'HTTP_X_COMSPEC_STEAM', 'HTTP_X_ATAK_STEAM', 'HTTP_X_ATAK_SESSION',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantId = 1200000 + random_int(1, 99999);
        foreach (self::SERVER_KEYS as $key) {
            if (array_key_exists($key, $_SERVER)) {
                $this->savedServer[$key] = $_SERVER[$key];
            }
        }

        $pdo = new VerifiedIdentityTestPdo('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->sqliteCreateFunction('UTC_TIMESTAMP', static fn (): string => gmdate('Y-m-d H:i:s'), 0);
        $pdo->sqliteCreateFunction('NOW', static fn (): string => gmdate('Y-m-d H:i:s'), 0);
        $pdo->sqliteCreateFunction('DATABASE', static fn (): string => 'main', 0);
        $pdo->exec("ATTACH DATABASE ':memory:' AS information_schema");
        $pdo->exec('CREATE TABLE information_schema.COLUMNS (TABLE_SCHEMA TEXT, TABLE_NAME TEXT, COLUMN_NAME TEXT)');
        $pdo->exec('CREATE TABLE information_schema.TABLES (TABLE_SCHEMA TEXT, TABLE_NAME TEXT)');
        foreach ([['tenant_atak_config', 'access_key'], ['tenant_atak_config', 'access_key_prefix'], ['users', 'steam_id'], ['game_sessions', 'access_token_hash']] as [$table, $column]) {
            $pdo->prepare('INSERT INTO information_schema.COLUMNS VALUES (?, ?, ?)')->execute(['main', $table, $column]);
        }
        $pdo->exec('CREATE TABLE tenant_atak_config (tenant_id INTEGER, access_key TEXT, access_key_prefix TEXT, maintenance_enabled INTEGER DEFAULT 0, maintenance_message TEXT)');
        $pdo->prepare('INSERT INTO tenant_atak_config (tenant_id, access_key, access_key_prefix) VALUES (?, ?, ?)')
            ->execute([$this->tenantId, self::CKEY, substr(self::CKEY, 0, 10)]);
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, tenant_id INTEGER, steam_id TEXT, status TEXT, callsign TEXT, display_name TEXT, email TEXT, role_id INTEGER)');
        $insert = $pdo->prepare('INSERT INTO users (id, tenant_id, steam_id, status, callsign, display_name, email, role_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $insert->execute([101, $this->tenantId, self::SA, 'active', 'ALPHA', 'Membre A', 'a@example.test', 11]);
        $insert->execute([102, $this->tenantId, self::SB, 'active', 'BRAVO', 'Membre B', 'b@example.test', 12]);
        $insert->execute([103, self::OTHER_TENANT, self::SC, 'active', 'CHARLIE', 'Membre C', 'c@example.test', null]);
        $insert->execute([104, $this->tenantId, self::SD, 'disabled', 'DELTA', 'Membre D', 'd@example.test', null]);
        $pdo->exec('CREATE TABLE roles (id INTEGER PRIMARY KEY, tenant_id INTEGER, slug TEXT, name TEXT)');
        $pdo->prepare('INSERT INTO roles (id, tenant_id, slug, name) VALUES (?, ?, ?, ?)')->execute([11, $this->tenantId, 'medecin', 'Médecin']);
        $pdo->prepare('INSERT INTO roles (id, tenant_id, slug, name) VALUES (?, ?, ?, ?)')->execute([12, $this->tenantId, 'chef-de-groupe', 'Chef de groupe']);
        $pdo->exec('CREATE TABLE game_sessions (id INTEGER PRIMARY KEY, public_id TEXT, account_id INTEGER, user_id INTEGER, tenant_id INTEGER, device_id TEXT, access_token_hash TEXT, refresh_token_hash TEXT, steam_id TEXT, pairing_token_hash TEXT, mod_version TEXT, extension_version TEXT, expires_at TEXT, refresh_expires_at TEXT, last_seen_at TEXT, revoked_at TEXT)');
        $session = $pdo->prepare('INSERT INTO game_sessions (user_id, tenant_id, steam_id, access_token_hash, expires_at, revoked_at) VALUES (?, ?, ?, ?, ?, NULL)');
        $session->execute([101, $this->tenantId, self::SA, hash('sha256', self::TOKEN_A), gmdate('Y-m-d H:i:s', time() + 3600)]);
        // Connexion e-mail sans Steam enregistré sur la session (GameAuthService::issueForAccount, steam_id NULL).
        $session->execute([101, $this->tenantId, null, hash('sha256', self::TOKEN_A_EMAIL), gmdate('Y-m-d H:i:s', time() + 3600)]);
        $session->execute([101, $this->tenantId, self::SA, hash('sha256', self::TOKEN_A_EXPIRED), gmdate('Y-m-d H:i:s', time() - 60)]);
        $pdo->exec('CREATE TABLE atak_chat_messages (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, map_id INTEGER NOT NULL, author TEXT, body TEXT, source TEXT, channel_key TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $this->pdo = $pdo;

        $db = new \ReflectionProperty(Database::class, 'pdo');
        $saved = $db->getValue();
        $this->savedPdo = $saved instanceof PDO ? $saved : null;
        $db->setValue(null, $pdo);

        // Formule de la communauté : accès ATAK accordé (le contrôle de formule n'est pas l'objet de ce test).
        $gate = $this->createMock(FeatureGateService::class);
        $gate->method('allows')->willReturn(true);
        $instances = new \ReflectionProperty(Container::class, 'instances');
        /** @var array<string, object> $current */
        $current = $instances->getValue();
        $this->savedContainer = $current;
        $instances->setValue(null, array_merge($current, [FeatureGateService::class => $gate]));

        $started = new \ReflectionProperty(Session::class, 'started');
        $this->savedStarted = (bool) $started->getValue();
        $started->setValue(null, true);
        $_SESSION = [];
        putenv('APP_ENV=production');
        $_ENV['APP_ENV'] = 'production';
        $_SERVER['REMOTE_ADDR'] = '198.51.100.' . random_int(2, 250);
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        AtakAdminBlockGate::useRepository(null);
        ComspecApiKeyAuth::resetForTests();
    }

    protected function tearDown(): void
    {
        (new AtakActivityLogService())->purgeAllForTenant($this->tenantId);
        $limiter = new FileRateLimiter();
        foreach (['steam_spoof', 'invalid_session_ignored', 'steam_required'] as $reason) {
            $limiter->clear('atak:guard-log:' . $this->tenantId . ':' . $reason);
        }
        foreach ([self::SA, self::SB, self::SC, self::SD] as $steam) {
            $limiter->clear('atak:write:' . $this->tenantId . ':steam:' . $steam);
        }
        foreach ($this->sessionTokens as $token) {
            @unlink(base_path('storage/cache/atak_sessions/' . hash('sha256', $token) . '.json'));
        }
        putenv('APP_ENV');
        unset($_ENV['APP_ENV'], $_SESSION);
        ComspecApiKeyAuth::resetForTests();
        (new \ReflectionProperty(Database::class, 'pdo'))->setValue(null, $this->savedPdo);
        (new \ReflectionProperty(Container::class, 'instances'))->setValue(null, $this->savedContainer);
        (new \ReflectionProperty(Session::class, 'started'))->setValue(null, $this->savedStarted);
        foreach (self::SERVER_KEYS as $key) {
            if (array_key_exists($key, $this->savedServer)) {
                $_SERVER[$key] = $this->savedServer[$key];
            } else {
                unset($_SERVER[$key]);
            }
        }
        parent::tearDown();
    }

    /**
     * Cas de la contre-vérification sans jeton personnel (clé de communauté seule) : réponses et auteurs inchangés. La clé
     * partagée seule n'apporte aucune identité vérifiée : les cas 2, 5, 8a et 8b restent ouverts.
     */
    public function testSharedKeyCasesAreUnchanged(): void
    {
        $this->assertChat($this->key(self::SA), 201, '', 101, 'cas 1 : clé de communauté + Steam de A');
        $this->assertChat($this->key(self::SB), 201, '', 102, 'cas 2 : inchangé (clé partagée seule)');
        $this->assertChat($this->key(self::SB) + ['HTTP_X_COMSPEC_SESSION' => $this->session(self::SA, false)], 403, 'steam_mismatch', null, 'cas 4');
        $this->assertChat($this->key(self::SB) + ['HTTP_X_COMSPEC_SESSION' => $this->session(self::SA, true)], 201, '', 102, 'cas 5 : inchangé (clé partagée seule)');
        $this->assertChat($this->key(self::SC), 403, 'steam_not_linked', null, 'cas 6');
        $this->assertChat($this->key(self::SD), 403, 'account_disabled', null, 'cas 7');

        [$code, $json] = $this->clientInit($this->key(self::SB));
        self::assertSame(200, $code, 'cas 8a : client-init clé + Steam de B, inchangé');
        self::assertSame(self::SB, $json['steam_uid'] ?? null);
        $sessionB = (string) ($json['session_token'] ?? '');
        $this->sessionTokens[] = $sessionB;
        self::assertNotSame('', $sessionB);
        $this->assertChat($this->key(self::SB) + ['HTTP_X_COMSPEC_SESSION' => $sessionB], 201, '', 102, 'cas 8b');
    }

    /**
     * Jeton personnel de A présenté avec le Steam de B (cas 3, cas 8c, comptes rendus routés, avis d'administration) :
     * refus 403 steam_mismatch. Avant : écriture au nom de B, session émise pour B, rôles de B, avis de B.
     */
    public function testPersonalTokenCannotActUnderAnotherMembersSteam(): void
    {
        $this->assertChat($this->token(self::TOKEN_A, self::SB), 403, 'steam_mismatch', null, 'cas 3 : jeton de A + Steam de B');
        // Session e-mail sans Steam enregistré : le membre vérifié reste la référence, pas un Steam vide.
        $this->assertChat($this->token(self::TOKEN_A_EMAIL, self::SB), 403, 'steam_mismatch', null, 'session e-mail de A + Steam de B');

        [$code, $json] = $this->clientInit($this->token(self::TOKEN_A, self::SB));
        self::assertSame(403, $code, 'cas 8c : client-init avec le jeton de A et le Steam de B');
        self::assertSame('steam_mismatch', $json['error'] ?? null);
        self::assertArrayNotHasKey('session_token', $json, 'aucune session émise au nom de B');

        [$code, $out] = $this->routing($this->token(self::TOKEN_A, self::SB));
        self::assertSame(403, $code, 'comptes rendus routés : avant, rôles et unités de B');
        self::assertSame('steam_mismatch', $out['error'] ?? null);

        [$code, $out] = $this->adminPlayer($this->token(self::TOKEN_A, self::SB));
        self::assertSame(403, $code, 'avis d’administration : avant, [communauté, 101, Steam de B]');
        self::assertSame('steam_mismatch', $out['error'] ?? null);
    }

    /** Clients admis : chacun agit sous son propre Steam, comme avant. */
    public function testLegitimateClientsAreUnchanged(): void
    {
        $this->assertChat($this->token(self::TOKEN_A, self::SA), 201, '', 101, 'jeton personnel + son Steam');
        $this->assertChat($this->token(self::TOKEN_A, null), 201, '', 101, 'jeton personnel sans Steam : Steam de la session');
        $this->assertChat($this->token(self::TOKEN_A, self::SA) + ['HTTP_X_COMSPEC_SESSION' => $this->session(self::SA, false)], 201, '', 101, 'jeton + session');
        $this->assertChat($this->key(self::SA) + ['HTTP_X_COMSPEC_SESSION' => $this->session(self::SA, false)], 201, '', 101, 'clé + session valide');
        $this->assertChat($this->key(self::SA) + ['HTTP_X_COMSPEC_SESSION' => $this->session(self::SA, true)], 201, '', 101, 'clé + session expirée');
        $this->assertChat($this->key(self::SA), 201, '', 101, 'clé sans session (ATAK natif, client-init sans Steam)');
        $this->assertChat($this->token(self::TOKEN_A_EMAIL, self::SA), 201, '', 101, 'session e-mail sans Steam enregistré');
        // Jeton expiré : la DLL repart sur la clé de communauté jointe (X-COMSPEC-KEY), comme avant.
        $this->assertChat($this->token(self::TOKEN_A_EXPIRED, self::SA), 201, '', 101, 'jeton expiré + clé jointe');

        foreach ([$this->token(self::TOKEN_A, self::SA), $this->token(self::TOKEN_A, null), $this->key(self::SA)] as $headers) {
            [$code, $json] = $this->clientInit($headers);
            self::assertSame(200, $code);
            self::assertSame(self::SA, $json['steam_uid'] ?? null);
            $this->sessionTokens[] = (string) ($json['session_token'] ?? '');
        }

        // Comptes rendus routés : membre et rôles du joueur lui-même.
        foreach ([$this->token(self::TOKEN_A, null), $this->token(self::TOKEN_A, self::SA), $this->key(self::SA)] as $headers) {
            [$code, $out] = $this->routing($headers);
            self::assertSame(200, $code);
            self::assertSame(101, $out['user_id'] ?? null);
            self::assertContains(['type' => 'ROLE', 'identifier' => 'medecin'], $out['recipients'] ?? []);
        }
        // Avis d'administration : le joueur lui-même.
        foreach ([$this->token(self::TOKEN_A, self::SA), $this->key(self::SA)] as $headers) {
            [$code, $out] = $this->adminPlayer($headers);
            self::assertSame(200, $code);
            self::assertSame([$this->tenantId, 101, self::SA], $out);
        }
        // Seule entrée possible : la session expirée, journalisée comme avant (au plus une fois par fenêtre).
        self::assertSame([], array_values(array_diff($this->guardLog, ['invalid_session_ignored'])), 'aucun refus journalisé');
    }

    /** Deux fiches de la communauté portent le Steam de A : le membre vérifié n'est pas refusé à tort. */
    public function testDuplicateSteamOnTwoRecordsDoesNotRefuseTheVerifiedMember(): void
    {
        $this->pdo->prepare('INSERT INTO users (id, tenant_id, steam_id, status, callsign, display_name, email) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([90, $this->tenantId, self::SA, 'active', 'ALPHA-ANCIEN', 'Doublon de A', 'old@example.test']);
        $first = (new UserRepository())->findBySteamIdForTenant($this->tenantId, self::SA);
        self::assertSame(90, (int) ($first['id'] ?? 0), 'précondition : la recherche par Steam rend le doublon');

        [$code, $json] = $this->chat($this->token(self::TOKEN_A, self::SA));
        self::assertSame(201, $code, (string) ($json['error'] ?? ''));
        [$code] = $this->clientInit($this->token(self::TOKEN_A, self::SA));
        self::assertSame(200, $code);
        [$code, $out] = $this->routing($this->token(self::TOKEN_A, self::SA));
        self::assertSame(200, $code);
        self::assertSame(101, $out['user_id'] ?? null, 'le membre du jeton, pas la première fiche trouvée');
    }

    /** Le journal Liaison garde une trace sans être inondé : une entrée steam_spoof par minute et par communauté. */
    public function testSpoofRefusalIsJournaledOncePerWindow(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->assertChat($this->token(self::TOKEN_A, self::SB), 403, 'steam_mismatch', null, 'essai ' . $i);
        }
        self::assertSame(['steam_spoof'], $this->guardLog);
    }

    // --- Outillage -------------------------------------------------------------------------------------------------

    /** @return array<string, string> */
    private function key(?string $steam): array
    {
        return ['HTTP_X_COMSPEC_KEY' => self::CKEY] + ($steam !== null ? ['HTTP_X_COMSPEC_STEAM' => $steam] : []);
    }

    /**
     * En-têtes de la DLL 2.0.98 avec un jeton personnel : Bearer + X-ATAK-TOKEN + clé de communauté jointe.
     *
     * @return array<string, string>
     */
    private function token(string $token, ?string $steam): array
    {
        return ['HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'HTTP_X_ATAK_TOKEN' => $token, 'HTTP_X_COMSPEC_KEY' => self::CKEY]
            + ($steam !== null ? ['HTTP_X_COMSPEC_STEAM' => $steam] : []);
    }

    private function session(string $steam, bool $expired): string
    {
        $token = AtakGameSession::issue($this->tenantId, $steam, self::CKEY)['token'];
        $this->sessionTokens[] = $token;
        if ($expired) {
            (new \ReflectionMethod(AtakGameSession::class, 'write'))->invoke(null, $token, [
                'tenant_id' => $this->tenantId,
                'steam_uid' => $steam,
                'key_fp' => AtakGameSession::keyFingerprint(self::CKEY),
                'expires_at' => time() - 10,
                'issued_at' => time() - 20,
            ]);
        }

        return $token;
    }

    private function controller(): AtakApiController
    {
        $log = $this->createMock(AtakActivityLogService::class);
        $log->method('recordAuthAttempt')->willReturnCallback(function (int $tenantId, bool $ok, string $label, array $meta = []): void {
            $this->guardLog[] = (string) ($meta['reason'] ?? '');
        });
        $blocklist = $this->createMock(IndicatorBlocklistService::class);
        $blocklist->method('checkModAccessBlock')->willReturn(['blocked' => false, 'reason' => null]);
        $users = new UserRepository();
        $units = $this->createMock(UnitRepository::class);
        $units->method('unitIdsForUser')->willReturn([]);
        $controller = (new \ReflectionClass(AtakApiController::class))->newInstanceWithoutConstructor();
        foreach ([
            'atak' => new AtakDataRepository($this->pdo),
            'orderRepository' => new AtakOrderRepository($this->pdo),
            'activityLog' => new AtakActivityLogService(),
            'armaGuard' => new AtakArmaWriteGuard($users, $log, new FileRateLimiter(), $blocklist),
            'userRepository' => $users,
            'unitRepository' => $units,
        ] as $name => $value) {
            (new \ReflectionProperty(AtakApiController::class, $name))->setValue($controller, $value);
        }

        return $controller;
    }

    /**
     * Requête à travers ComspecTacticalApiMiddleware puis RateLimitMiddleware, jusqu'à l'action du contrôleur.
     *
     * @param array<string, string> $headers
     * @param callable(AtakApiController, Request): Response $action
     * @return array{0: int, 1: array<string, mixed>}
     */
    private function through(string $path, array $headers, array $body, callable $action): array
    {
        ComspecApiKeyAuth::resetForTests();
        // Le middleware lit config/tactical_api.php à côté de sa propre source : on lui passe le fichier du dépôt, pour
        // que la protection de /api/chat ne dépende pas de l'outil qui charge les classes.
        (new \ReflectionProperty(ComspecApiKeyAuth::class, 'tacticalConfigCache'))->setValue(null, require base_path('config/tactical_api.php'));
        self::assertTrue(ComspecApiKeyAuth::pathRequiresProtection($path), $path . ' exige une clé');
        foreach (self::SERVER_KEYS as $key) {
            if (str_starts_with($key, 'HTTP_')) {
                unset($_SERVER[$key]);
            }
        }
        foreach ($headers as $key => $value) {
            $_SERVER[$key] = $value;
        }
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $path;
        $_SESSION = [];
        $controller = $this->controller();
        (new \ReflectionProperty(AtakApiController::class, 'jsonBodyCache'))->setValue($controller, $body);
        $final = static fn (Request $request): Response => $action($controller, $request);
        $rate = new RateLimitMiddleware(new FileRateLimiter());
        $response = (new ComspecTacticalApiMiddleware())(new Request(), static fn (Request $request): Response => $rate($request, $final));
        $json = json_decode($response->body(), true);

        return [$response->statusCode(), is_array($json) ? $json : []];
    }

    /**
     * POST /api/chat (message privé, journalisé avec l'auteur retenu).
     *
     * @param array<string, string> $headers
     * @return array{0: int, 1: array<string, mixed>, 2: ?int}
     */
    private function chat(array $headers): array
    {
        $tag = 'T' . random_int(100000, 999999);
        [$code, $json] = $this->through('/api/chat', $headers, ['mapId' => 1, 'author' => $tag, 'body' => 'MP|' . $tag . '|TOC|essai'],
            static fn (AtakApiController $c, Request $r): Response => $c->chatStore($r));
        $author = null;
        foreach ((new AtakActivityLogService())->listRecent($this->tenantId, 1, 50) as $event) {
            if (str_contains((string) ($event['label'] ?? ''), $tag)) {
                $meta = is_array($event['meta'] ?? null) ? $event['meta'] : [];
                $author = isset($meta['user_id']) ? (int) $meta['user_id'] : 0;
            }
        }

        return [$code, $json, $author];
    }

    /** @param array<string, string> $headers */
    private function assertChat(array $headers, int $status, string $error, ?int $author, string $label): void
    {
        [$code, $json, $who] = $this->chat($headers);
        self::assertSame($status, $code, $label . ' ' . json_encode($json));
        self::assertSame($error, (string) ($json['error'] ?? ''), $label);
        self::assertSame($author, $who, $label . ' : auteur retenu');
    }

    /**
     * @param array<string, string> $headers
     * @return array{0: int, 1: array<string, mixed>}
     */
    private function clientInit(array $headers): array
    {
        return $this->through('/api/atak/client-init', $headers, ['mapId' => 1],
            static fn (AtakApiController $c, Request $r): Response => $c->clientInit($r));
    }

    /**
     * Méthode privée du contrôleur appelée au bout de la chaîne (comme tacticalReportsAcknowledge).
     *
     * @param array<string, string> $headers
     * @return array{0: int, 1: array<mixed>}
     */
    private function privateThrough(string $method, string $path, array $headers): array
    {
        $result = null;
        [$code, $json] = $this->through($path, $headers, [], function (AtakApiController $c, Request $r) use ($method, &$result): Response {
            $args = $method === 'routingIdentity' ? [$r, $this->tenantId] : [$r];
            $out = (new \ReflectionMethod(AtakApiController::class, $method))->invoke($c, ...$args);
            if ($out instanceof Response) {
                return $out;
            }
            $result = $out;

            return Response::json(['ok' => true]);
        });

        return [$code, is_array($result) ? $result : $json];
    }

    /**
     * @param array<string, string> $headers
     * @return array{0: int, 1: array<mixed>}
     */
    private function routing(array $headers): array
    {
        return $this->privateThrough('routingIdentity', '/api/atak/reports/1/acknowledge', $headers);
    }

    /**
     * @param array<string, string> $headers
     * @return array{0: int, 1: array<mixed>}
     */
    private function adminPlayer(array $headers): array
    {
        return $this->privateThrough('adminControlPlayer', '/api/atak/admin-control/mine', $headers);
    }
}
