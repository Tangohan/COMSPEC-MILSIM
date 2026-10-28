<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Middleware\RateLimitMiddleware;
use App\Services\Security\FileRateLimiter;
use App\Support\ComspecApiKeyAuth;
use PDO;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * NET-028 : POST /api/chat (session web) et POST /api/atak/salute n'avaient aucun frein. Plafonds posés dans
 * RateLimitMiddleware : 120 écritures du tchat et 30 SALUTE par minute, par compte pour une session web, par IP sinon.
 * Usage réel bien en dessous : les pages web n'écrivent qu'à chaque envoi d'un joueur, et un SALUTE se remplit à la
 * main. Le tchat du jeu (clé ou jeton valide, sans compte web) reste compté par joueur par AtakArmaWriteGuard
 * (150 écritures du jeu par minute) : pas de second plafond par IP, partagé par des joueurs derrière une même adresse.
 *
 * NET-031 : seule une clé valide soustrait l'écriture du tchat à ce plafond, et non une clé seulement présentée. La
 * session de la page téléphone (appariement par QR, sans compte web) est comptée par session, comme une session web
 * par compte, et non plus par IP.
 */
final class ChatWriteRateLimitTest extends TestCase
{
    private const SERVER_KEYS = [
        'REQUEST_METHOD', 'REQUEST_URI', 'SCRIPT_NAME', 'REMOTE_ADDR',
        'HTTP_AUTHORIZATION', 'HTTP_X_COMSPEC_KEY', 'HTTP_X_ATAK_TOKEN',
    ];

    private const TENANT = 7;

    /** Jeton d'une session de jeu valide (64 caractères hexadécimaux : cas normal de la DLL, en Bearer). */
    private const GAME_TOKEN = 'd63d591cf5a34cb1f8622b3b2e23712319f679b3e410c235bec9dee3e006b80c';

    /** Clé d'accès de la communauté (générée en back-office, envoyée par la DLL en X-COMSPEC-KEY). */
    private const COMMUNITY_KEY = 'cle-communaute-valide-0001';

    /** @var array<string, mixed> */
    private array $savedServer = [];

    /** @var array{set: bool, value: mixed} */
    private array $savedSession = ['set' => false, 'value' => null];

    private bool $hadPost = false;

    private mixed $savedPost = null;

    private ?PDO $savedPdo = null;

    /** @var list<string> */
    private array $keys = [];

    protected function setUp(): void
    {
        parent::setUp();
        // Un test précédent peut avoir retiré $_POST (unset) : Request le copie dans une propriété typée array.
        $this->hadPost = isset($_POST);
        $this->savedPost = $_POST ?? null;
        $_POST = [];
        foreach (self::SERVER_KEYS as $key) {
            if (array_key_exists($key, $_SERVER)) {
                $this->savedServer[$key] = $_SERVER[$key];
            }
            // Aucune clé héritée d'un test précédent : chaque test pose la sienne.
            if (str_starts_with($key, 'HTTP_')) {
                unset($_SERVER[$key]);
            }
        }
        $this->savedSession = ['set' => isset($_SESSION), 'value' => $_SESSION ?? null];
        $_SESSION = [];
        // La validité d'une clé présentée est lue en base (requestPresentsValidKey) : base SQLite du test.
        $this->savedPdo = self::databasePdo();
        self::setDatabasePdo(self::databaseWithGameSession());
        // Corps JSON gardé en cache statique (clé « api_key ») : vidé avant et après.
        ComspecApiKeyAuth::resetForTests();
    }

    protected function tearDown(): void
    {
        $limiter = new FileRateLimiter();
        foreach (array_unique($this->keys) as $key) {
            $limiter->clear($key);
        }
        foreach (self::SERVER_KEYS as $key) {
            if (array_key_exists($key, $this->savedServer)) {
                $_SERVER[$key] = $this->savedServer[$key];
            } else {
                unset($_SERVER[$key]);
            }
        }
        if ($this->savedSession['set']) {
            $_SESSION = $this->savedSession['value'];
        } else {
            unset($_SESSION);
        }
        if ($this->hadPost) {
            $_POST = $this->savedPost;
        } else {
            unset($_POST);
        }
        self::setDatabasePdo($this->savedPdo);
        ComspecApiKeyAuth::resetForTests();
        parent::tearDown();
    }

    /**
     * NET-031 : la vraie clé d'une communauté (flux de la DLL, sans compte web) n'est jamais freinée par ce middleware :
     * 130 envois dans la minute, plus que le plafond de 120, passent tous, et aucun compteur n'est ouvert pour l'adresse.
     * Processus séparé : TenantAtakConfigRepository garde en mémoire statique, pour tout le processus, la présence de la
     * colonne access_key, qu'un autre test peut y avoir notée absente.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRealCommunityKeyIsNeverBrakedByTheMiddleware(): void
    {
        self::setDatabasePdo(self::databaseWithCommunityKey());
        $ip = self::randomIp();
        $ipKey = 'rl:chat_write:ip:' . $ip;
        $this->keys[] = $ipKey;
        $_SERVER['HTTP_X_COMSPEC_KEY'] = self::COMMUNITY_KEY;

        self::assertSame(130, $this->writes(130, $ip));
        self::assertFileDoesNotExist(base_path('storage/cache/ratelimit') . '/' . hash('sha256', $ipKey) . '.json');
        // La clé du test est bien une clé valide, celle de la communauté.
        self::assertTrue(ComspecApiKeyAuth::requestPresentsValidKey());
        self::assertSame(self::TENANT, ComspecApiKeyAuth::matchedTenantId());
    }

    public function testWebSessionWritesAreCountedPerAccount(): void
    {
        $_SESSION = ['user_id' => 42, 'tenant_id' => 7];

        self::assertSame([['rl:chat_write:uid:42', 120, 60]], $this->countedFor('POST', '/api/chat', '203.0.113.21'));
        self::assertSame([['rl:salute:uid:42', 30, 60]], $this->countedFor('POST', '/api/atak/salute', '203.0.113.21'));
    }

    /** Ni compte web, ni clé : par IP. */
    public function testWithoutAccountOrKeyWritesAreCountedPerIp(): void
    {
        self::assertSame([['rl:chat_write:ip:203.0.113.22', 120, 60]], $this->countedFor('POST', '/api/chat', '203.0.113.22'));
        self::assertSame([['rl:salute:ip:203.0.113.22', 30, 60]], $this->countedFor('POST', '/api/atak/salute', '203.0.113.22'));
    }

    /**
     * Tchat du jeu : la DLL présente toujours un jeton (Bearer) ou la clé de la communauté (X-COMSPEC-KEY, voir le test
     * en processus séparé ci-dessus). Avec un jeton valide, il reste compté par joueur dans AtakArmaWriteGuard, pas par
     * IP ici. SALUTE, qu'aucun client du jeu n'appelle, reste compté par IP ; une session web reste comptée par compte,
     * même avec un jeton valide.
     */
    public function testGameChatIsLeftToTheArmaGuardButSaluteAndWebSessionsAreStillCounted(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . self::GAME_TOKEN;
        self::assertSame([], $this->countedFor('POST', '/api/chat', '203.0.113.25'), 'jeton du jeu valide');
        self::assertTrue(ComspecApiKeyAuth::requestPresentsValidKey(), 'le jeton du test est valide');
        self::assertSame(self::TENANT, ComspecApiKeyAuth::matchedTenantId());
        self::assertSame([['rl:salute:ip:203.0.113.25', 30, 60]], $this->countedFor('POST', '/api/atak/salute', '203.0.113.25'));

        $_SESSION = ['user_id' => 42, 'tenant_id' => 7];
        self::assertSame([['rl:chat_write:uid:42', 120, 60]], $this->countedFor('POST', '/api/chat', '203.0.113.25'), 'session web avec un jeton valide');
    }

    /**
     * NET-031 : une clé ou un jeton seulement présenté, qui n'ouvre aucune communauté, ne soustrait pas l'écriture au
     * plafond. Sans session, la garde tactique refuse déjà une telle requête (401) ; ici, elle est comptée par IP.
     */
    public function testAKeyThatIsNotValidDoesNotExemptTheWrite(): void
    {
        $_SERVER['HTTP_X_COMSPEC_KEY'] = 'cle-communaute-inconnue';
        self::assertSame([['rl:chat_write:ip:203.0.113.26', 120, 60]], $this->countedFor('POST', '/api/chat', '203.0.113.26'), 'clé inconnue');

        unset($_SERVER['HTTP_X_COMSPEC_KEY']);
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . hash('sha256', 'jeton-inconnu');
        self::assertSame([['rl:chat_write:ip:203.0.113.26', 120, 60]], $this->countedFor('POST', '/api/chat', '203.0.113.26'), 'jeton inconnu');
        self::assertNull(ComspecApiKeyAuth::matchedTenantId());
    }

    /**
     * NET-031 : une session de la page téléphone est comptée par session, quelle que soit la clé qu'elle présente
     * (aucune, comme la page elle-même, ou une clé non valide différente à chaque envoi) : 120 écritures passent dans la
     * minute, la 121e est freinée, et toutes tombent dans le même compteur.
     */
    public function testPhoneSessionIsCountedPerSessionWhateverKeyItPresents(): void
    {
        $ip = self::randomIp();
        $_SESSION = self::phoneSession(self::phoneToken());
        $key = $this->chatKeyFor($ip);
        $this->keys[] = 'rl:chat_write:ip:' . $ip;

        $passed = 0;
        for ($i = 0; $i < 120; $i++) {
            unset($_SERVER['HTTP_X_COMSPEC_KEY'], $_SERVER['HTTP_AUTHORIZATION']);
            if ($i % 3 === 1) {
                $_SERVER['HTTP_X_COMSPEC_KEY'] = 'cle-quelconque-' . $i;
            } elseif ($i % 3 === 2) {
                $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . hash('sha256', 'jeton-quelconque-' . $i);
            }
            $passed += $this->writes(1, $ip);
        }
        unset($_SERVER['HTTP_AUTHORIZATION']);
        $_SERVER['HTTP_X_COMSPEC_KEY'] = 'cle-quelconque-encore-une';
        $refused = $this->through('POST', '/api/chat', $ip, new FileRateLimiter(), static fn () => new Response());

        self::assertSame(120, $passed, 'tout passe jusqu’au plafond');
        self::assertSame(429, $refused->statusCode(), 'la 121e écriture est freinée');
        self::assertGreaterThanOrEqual(1, (int) $refused->headerValue('Retry-After'));
        self::assertLessThanOrEqual(60, (int) $refused->headerValue('Retry-After'));
        self::assertSame(121, (new FileRateLimiter())->attempts($key, 60), 'un seul compteur, celui de la session');
        self::assertFileDoesNotExist(base_path('storage/cache/ratelimit') . '/' . hash('sha256', 'rl:chat_write:ip:' . $ip) . '.json');
    }

    /** NET-031 : deux sessions téléphone derrière la même adresse (réseau local, CGNAT) ont chacune leur plafond. */
    public function testTwoPhoneSessionsBehindOneAddressHaveSeparateCaps(): void
    {
        $ip = self::randomIp();
        $first = self::phoneSession(self::phoneToken());
        $second = self::phoneSession(self::phoneToken());
        $_SESSION = $first;
        $firstKey = $this->chatKeyFor($ip);
        $_SESSION = $second;
        $secondKey = $this->chatKeyFor($ip);
        $this->keys[] = 'rl:chat_write:ip:' . $ip;
        self::assertNotSame($firstKey, $secondKey);

        $_SESSION = $first;
        self::assertSame(120, $this->writes(120, $ip), 'première session : tout passe jusqu’au plafond');
        self::assertSame(0, $this->writes(1, $ip), 'première session : la 121e écriture est freinée');

        $_SESSION = $second;
        self::assertSame(1, $this->writes(1, $ip), 'seconde session, même adresse : son propre plafond');
        $_SESSION = [];
        self::assertSame(1, $this->writes(1, $ip), 'sans session, même adresse : compteur par IP à part');
        self::assertSame(1, (new FileRateLimiter())->attempts($secondKey, 60));
    }

    /**
     * NET-031 : la session téléphone est désignée par une empreinte hachée et tronquée de son jeton d'appariement. Le
     * jeton n'apparaît ni dans la clé du limiteur, ni dans le nom ou le contenu d'un fichier de storage/cache/ratelimit.
     * Un jeton sans communauté n'est pas une session téléphone (par IP) ; une session web reste comptée par compte.
     */
    public function testPhoneSessionKeyIsAFingerprintNeverTheTokenItself(): void
    {
        $token = self::phoneToken();
        $ip = self::randomIp();
        $_SESSION = self::phoneSession($token);
        $chatKey = $this->chatKeyFor($ip);
        $salute = $this->countedFor('POST', '/api/atak/salute', $ip);

        self::assertMatchesRegularExpression('/^rl:chat_write:phone:[0-9a-f]{24}$/', $chatKey);
        self::assertSame([['rl:salute:phone:' . substr($chatKey, strlen('rl:chat_write:phone:')), 30, 60]], $salute, 'même empreinte pour le SALUTE');
        self::assertStringNotContainsString($token, $chatKey . "\n" . $salute[0][0]);

        self::assertSame(1, $this->writes(1, $ip));
        $dir = base_path('storage/cache/ratelimit');
        $file = $dir . '/' . hash('sha256', $chatKey) . '.json';
        self::assertFileExists($file);
        self::assertStringNotContainsString($token, (string) file_get_contents($file));
        foreach (scandir($dir) ?: [] as $name) {
            self::assertStringNotContainsString($token, $name);
        }

        $_SESSION = ['atak_phone_pairing_token' => $token];
        self::assertSame([['rl:chat_write:ip:' . $ip, 120, 60]], $this->countedFor('POST', '/api/chat', $ip), 'jeton sans communauté');
        $_SESSION = ['user_id' => 42, 'tenant_id' => self::TENANT, 'atak_phone_pairing_token' => $token];
        self::assertSame([['rl:chat_write:uid:42', 120, 60]], $this->countedFor('POST', '/api/chat', $ip), 'session web');
    }

    /** Lectures du tchat (relève du jeu, pages web) et autres routes du tchat : aucun nouveau frein. */
    public function testReadsAndOtherChatRoutesAreNotCounted(): void
    {
        self::assertSame([], $this->countedFor('GET', '/api/chat', '203.0.113.23'), 'relève, invité');
        self::assertSame([], $this->countedFor('POST', '/api/chat/channels', '203.0.113.23'));
        $_SESSION = ['user_id' => 42, 'tenant_id' => 7];
        self::assertSame([], $this->countedFor('GET', '/api/chat', '203.0.113.23'), 'relève, session web');
        self::assertSame([], $this->countedFor('GET', '/api/atak/salute', '203.0.113.23'));
    }

    public function testOverTheLimitTheWriteIsRefusedWith429AndTheTimeLeft(): void
    {
        foreach (['/api/chat', '/api/atak/salute'] as $path) {
            $limiter = $this->createMock(FileRateLimiter::class);
            $limiter->method('tooManyAttempts')->willReturn(true);
            $limiter->method('secondsUntilReset')->willReturn(23);

            $response = $this->through('POST', $path, '203.0.113.24', $limiter, static fn () => self::fail('la requête aurait dû être freinée'));

            self::assertSame(429, $response->statusCode(), $path);
            self::assertSame('23', $response->headerValue('Retry-After'), $path);
            $json = json_decode($response->body(), true);
            self::assertIsArray($json, $path);
            self::assertSame('too_many_requests', $json['error'] ?? null, $path);
            self::assertSame(23, $json['retry_after'] ?? null, $path);
        }
    }

    /** Vrai limiteur : 120 écritures passent dans la minute, la 121e est freinée ; 30 SALUTE, puis le 31e. */
    public function testRealLimiterLetsTheThresholdPassAndRefusesTheNextWrite(): void
    {
        $ip = self::randomIp();
        foreach (['/api/chat' => [120, 'rl:chat_write:ip:'], '/api/atak/salute' => [30, 'rl:salute:ip:']] as $path => [$max, $prefix]) {
            $this->keys[] = $prefix . $ip;
            $passed = 0;
            for ($i = 0; $i < $max; $i++) {
                $ok = new Response();
                if ($this->through('POST', $path, $ip, new FileRateLimiter(), static fn () => $ok) === $ok) {
                    ++$passed;
                }
            }
            $refused = $this->through('POST', $path, $ip, new FileRateLimiter(), static fn () => new Response());

            self::assertSame($max, $passed, $path . ' : tout passe jusqu’au plafond');
            self::assertSame(429, $refused->statusCode(), $path . ' : l’écriture suivante est freinée');
            self::assertGreaterThanOrEqual(1, (int) $refused->headerValue('Retry-After'));
            self::assertLessThanOrEqual(60, (int) $refused->headerValue('Retry-After'));
        }
    }

    /**
     * Clés et plafonds demandés au limiteur pour cette requête (le limiteur simulé laisse passer).
     *
     * @return list<array{0: string, 1: int, 2: int}>
     */
    private function countedFor(string $method, string $path, string $ip): array
    {
        $seen = [];
        $limiter = $this->createMock(FileRateLimiter::class);
        $limiter->method('tooManyAttempts')->willReturnCallback(
            static function (string $key, int $max, int $window) use (&$seen): bool {
                $seen[] = [$key, $max, $window];

                return false;
            }
        );
        $ok = new Response();
        $response = $this->through($method, $path, $ip, $limiter, static fn () => $ok);
        self::assertSame($ok, $response, $method . ' ' . $path . ' : la requête continue');

        return $seen;
    }

    /** Clé du limiteur pour une écriture du tchat de la session en cours, relevée sans rien compter et effacée à la fin. */
    private function chatKeyFor(string $ip): string
    {
        $seen = $this->countedFor('POST', '/api/chat', $ip);
        self::assertCount(1, $seen);
        $this->keys[] = $seen[0][0];

        return $seen[0][0];
    }

    /** Écritures du tchat passées par le vrai limiteur, parmi $count envois. */
    private function writes(int $count, string $ip): int
    {
        $passed = 0;
        for ($i = 0; $i < $count; $i++) {
            $ok = new Response();
            if ($this->through('POST', '/api/chat', $ip, new FileRateLimiter(), static fn () => $ok) === $ok) {
                ++$passed;
            }
        }

        return $passed;
    }

    /** Une requête : les caches de ComspecApiKeyAuth (clé vérifiée, corps JSON) repartent de zéro, comme en production. */
    private function through(string $method, string $path, string $ip, FileRateLimiter $limiter, callable $next): Response
    {
        ComspecApiKeyAuth::resetForTests();
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $path;
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['REMOTE_ADDR'] = $ip;

        return (new RateLimitMiddleware($limiter))(new Request(), $next);
    }

    /**
     * Session de la page téléphone après appariement : communauté et jeton, sans compte web.
     *
     * @return array<string, mixed>
     */
    private static function phoneSession(string $token): array
    {
        return ['tenant_id' => self::TENANT, 'atak_phone_pairing_token' => $token];
    }

    private static function phoneToken(): string
    {
        return 'jeton-appariement-test-' . bin2hex(random_bytes(12));
    }

    /** Adresse tirée au hasard : un compteur laissé par un essai précédent (fenêtre de 60 s) ne gêne pas. */
    private static function randomIp(): string
    {
        return '198.18.' . random_int(1, 254) . '.' . random_int(1, 254);
    }

    private static function databasePdo(): ?PDO
    {
        $value = (new \ReflectionProperty(Database::class, 'pdo'))->getValue();

        return $value instanceof PDO ? $value : null;
    }

    private static function setDatabasePdo(?PDO $pdo): void
    {
        (new \ReflectionProperty(Database::class, 'pdo'))->setValue(null, $pdo);
    }

    /** Base du test : une session de jeu valide (GAME_TOKEN) ; table des clés de communauté vide. */
    private static function databaseWithGameSession(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE game_sessions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                access_token_hash TEXT NOT NULL,
                revoked_at TEXT NULL,
                expires_at TEXT NULL,
                tenant_id INTEGER NOT NULL,
                user_id INTEGER NULL,
                steam_id TEXT NULL
            )'
        );
        $pdo->prepare("INSERT INTO game_sessions (access_token_hash, expires_at, tenant_id, steam_id) VALUES (?, '2099-01-01 00:00:00', ?, '76561190000000007')")
            ->execute([hash('sha256', self::GAME_TOKEN), self::TENANT]);
        $pdo->exec('CREATE TABLE tenant_atak_config (tenant_id INTEGER, access_key TEXT, access_key_prefix TEXT)');

        return $pdo;
    }

    /** Même base, avec la clé de la communauté et le schéma que TenantAtakConfigRepository lit (information_schema). */
    private static function databaseWithCommunityKey(): PDO
    {
        $pdo = self::databaseWithGameSession();
        $pdo->sqliteCreateFunction('DATABASE', static fn (): string => 'main', 0);
        $pdo->exec("ATTACH DATABASE ':memory:' AS information_schema");
        $pdo->exec('CREATE TABLE information_schema.COLUMNS (TABLE_SCHEMA TEXT, TABLE_NAME TEXT, COLUMN_NAME TEXT)');
        $pdo->exec("INSERT INTO information_schema.COLUMNS VALUES ('main', 'tenant_atak_config', 'access_key')");
        $pdo->prepare('INSERT INTO tenant_atak_config (tenant_id, access_key, access_key_prefix) VALUES (?, ?, ?)')
            ->execute([self::TENANT, self::COMMUNITY_KEY, substr(self::COMMUNITY_KEY, 0, 10)]);

        return $pdo;
    }
}
