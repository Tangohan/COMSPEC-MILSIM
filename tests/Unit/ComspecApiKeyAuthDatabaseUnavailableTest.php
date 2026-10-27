<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use App\Core\Session;
use App\Repositories\TenantAtakConfigRepository;
use App\Support\ComspecApiKeyAuth;
use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\TestCase;

/**
 * NET-001 : une base indisponible pendant la vérification de la clé ne doit pas passer pour une clé fausse.
 * Clé fausse ou absente : 401 « unauthorized », inchangé. Vérification impossible : 503 database_unavailable
 * + Retry-After 30, que la DLL traite comme une panne passagère (ni verrou de refus, ni envoi abandonné).
 */
final class ComspecApiKeyAuthDatabaseUnavailableTest extends TestCase
{
    private const PATH = '/api/atak/orders';

    /** Réglages lus par ComspecApiKeyAuth, sauvegardés puis rendus tels quels. */
    private const ENV_KEYS = [
        'APP_ENV',
        'TACTICAL_API_STRICT',
        'TACTICAL_API_ALLOW_SESSION',
        'X_COMSPEC_KEY',
        'ATAK_INTEL_SECRET',
        'ATAK_DEFAULT_TENANT_ID',
        'APP_ATAK_DEFAULT_TENANT_ID',
    ];

    /** @var array<string, array{in_env: bool, env: mixed, getenv: string|false}> */
    private array $savedEnv = [];

    private ?PDO $savedPdo = null;

    private bool $savedSessionStarted = false;

    /** @var array{set: bool, value: mixed} */
    private array $savedSession = ['set' => false, 'value' => null];

    protected function setUp(): void
    {
        parent::setUp();
        foreach (self::ENV_KEYS as $key) {
            $this->savedEnv[$key] = [
                'in_env' => array_key_exists($key, $_ENV),
                'env' => $_ENV[$key] ?? null,
                'getenv' => getenv($key),
            ];
        }
        // Production (clé obligatoire), sans clé plateforme, sans passe-droit de session navigateur,
        // sans communauté par défaut (pas de journal d'activité à écrire pendant le test).
        $this->setEnv('APP_ENV', 'production');
        $this->setEnv('TACTICAL_API_STRICT', null);
        $this->setEnv('TACTICAL_API_ALLOW_SESSION', 'false');
        $this->setEnv('X_COMSPEC_KEY', null);
        $this->setEnv('ATAK_INTEL_SECRET', null);
        $this->setEnv('ATAK_DEFAULT_TENANT_ID', null);
        $this->setEnv('APP_ATAK_DEFAULT_TENANT_ID', null);

        $this->savedPdo = self::databasePdo();
        // Session déjà « ouverte » et vide : le refus 401 lit la session sans session_start() en CLI.
        $started = new \ReflectionProperty(Session::class, 'started');
        $this->savedSessionStarted = (bool) $started->getValue();
        $started->setValue(null, true);
        $this->savedSession = ['set' => isset($_SESSION), 'value' => $_SESSION ?? null];
        $_SESSION = [];

        unset($_SERVER['HTTP_X_COMSPEC_KEY'], $_SERVER['HTTP_X_ATAK_TOKEN'], $_SERVER['HTTP_AUTHORIZATION']);
        ComspecApiKeyAuth::resetForTests();
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_X_COMSPEC_KEY'], $_SERVER['HTTP_X_ATAK_TOKEN'], $_SERVER['HTTP_AUTHORIZATION']);
        ComspecApiKeyAuth::resetForTests();
        self::setDatabasePdo($this->savedPdo);
        (new \ReflectionProperty(Session::class, 'started'))->setValue(null, $this->savedSessionStarted);
        if ($this->savedSession['set']) {
            $_SESSION = $this->savedSession['value'];
        } else {
            unset($_SESSION);
        }
        foreach ($this->savedEnv as $key => $saved) {
            if ($saved['in_env']) {
                $_ENV[$key] = $saved['env'];
            } else {
                unset($_ENV[$key]);
            }
            putenv($saved['getenv'] === false ? $key : $key . '=' . $saved['getenv']);
        }
        parent::tearDown();
    }

    public function testDatabaseFailureDuringGameTokenCheckAnswers503(): void
    {
        self::setDatabasePdo(self::failingPdo());
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . str_repeat('a', 64);

        $response = ComspecApiKeyAuth::enforceForTacticalPath(self::PATH);

        self::assertNotNull($response);
        self::assertSame(503, $response->statusCode());
        self::assertSame('30', $response->headerValue('Retry-After'));
        $payload = json_decode($response->body(), true);
        self::assertIsArray($payload);
        self::assertSame('database_unavailable', $payload['error'] ?? null);
        self::assertTrue(ComspecApiKeyAuth::verificationUnavailable());
        // Mots que la DLL lit comme un refus définitif ou comme une maintenance du site entier.
        foreach (['unauthorized', 'maintenance', 'tenant', 'steam', 'SQLSTATE', 'gone away'] as $word) {
            self::assertStringNotContainsString(strtolower($word), strtolower($response->body()), $word);
        }

        // Même requête, seconde vérification (contrôleur après la garde) : même état, sans requête SQL.
        self::assertFalse(ComspecApiKeyAuth::requestPresentsValidKey());
        self::assertTrue(ComspecApiKeyAuth::verificationUnavailable());
    }

    public function testDatabaseFailureDuringCommunityKeyCheckAnswers503(): void
    {
        self::setDatabasePdo(self::failingPdo());
        $_SERVER['HTTP_X_COMSPEC_KEY'] = 'ow_' . str_repeat('0', 48);

        $response = ComspecApiKeyAuth::enforceForTacticalPath(self::PATH);

        self::assertNotNull($response);
        self::assertSame(503, $response->statusCode());
        self::assertSame('30', $response->headerValue('Retry-After'));
        self::assertTrue(ComspecApiKeyAuth::verificationUnavailable());
    }

    public function testWrongKeyStillAnswers401(): void
    {
        self::setDatabasePdo(self::workingPdo());
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . str_repeat('b', 64);

        $response = ComspecApiKeyAuth::enforceForTacticalPath(self::PATH);

        self::assertNotNull($response);
        self::assertSame(401, $response->statusCode());
        self::assertNull($response->headerValue('Retry-After'));
        $payload = json_decode($response->body(), true);
        self::assertIsArray($payload);
        self::assertSame('unauthorized', $payload['error'] ?? null);
        self::assertFalse(ComspecApiKeyAuth::verificationUnavailable());
    }

    public function testMissingKeyStillAnswers401(): void
    {
        self::setDatabasePdo(self::failingPdo());

        $response = ComspecApiKeyAuth::enforceForTacticalPath(self::PATH);

        self::assertNotNull($response);
        self::assertSame(401, $response->statusCode());
        self::assertFalse(ComspecApiKeyAuth::verificationUnavailable());
    }

    public function testValidGameSessionIsStillAccepted(): void
    {
        $token = str_repeat('c', 64);
        $pdo = self::workingPdo();
        $pdo->prepare(
            'INSERT INTO game_sessions (access_token_hash, revoked_at, expires_at, tenant_id, user_id, steam_id)
             VALUES (?, NULL, ?, 7, 41, ?)'
        )->execute([hash('sha256', $token), gmdate('Y-m-d H:i:s', time() + 3600), '76561198000000001']);
        self::setDatabasePdo($pdo);
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;

        self::assertNull(ComspecApiKeyAuth::enforceForTacticalPath(self::PATH));
        self::assertSame(7, ComspecApiKeyAuth::matchedTenantId());
        self::assertSame(41, ComspecApiKeyAuth::matchedUserId());
        self::assertSame('76561198000000001', ComspecApiKeyAuth::matchedSteamId());
        self::assertFalse(ComspecApiKeyAuth::verificationUnavailable());
    }

    public function testUnavailableAnswerSkipsTheAuthFailureJournal(): void
    {
        // Le refus 401 réécrit le journal d'activité de la communauté (NET-004) : le 503 passe avant.
        $method = new \ReflectionMethod(ComspecApiKeyAuth::class, 'enforceForTacticalPath');
        $body = self::methodSource($method);
        $unavailable = strpos($body, 'json503DatabaseUnavailable()');
        $journal = strpos($body, 'logAuthFailure(');
        self::assertNotFalse($unavailable);
        self::assertNotFalse($journal);
        self::assertLessThan($journal, $unavailable);
    }

    public function testCommunityKeyLookupNoLongerHidesDatabaseErrors(): void
    {
        $body = self::methodSource(new \ReflectionMethod(TenantAtakConfigRepository::class, 'findTenantIdByAccessKey'));
        self::assertStringNotContainsString('catch', $body);
    }

    private function setEnv(string $key, ?string $value): void
    {
        if ($value === null) {
            unset($_ENV[$key]);
            putenv($key);

            return;
        }
        $_ENV[$key] = $value;
        putenv($key . '=' . $value);
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

    /** Base qui répond : sessions jeu vides (clé fausse), schéma minimal lu par la vérification. */
    private static function workingPdo(): PDO
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
        $pdo->exec('CREATE TABLE tenant_atak_config (tenant_id INTEGER, access_key TEXT, access_key_prefix TEXT)');

        return $pdo;
    }

    /** Base coupée pendant la requête : toute requête SQL lève une PDOException (MySQL « gone away »). */
    private static function failingPdo(): PDO
    {
        return new class ('sqlite::memory:') extends PDO {
            public function prepare(string $query, array $options = []): PDOStatement|false
            {
                throw self::gone();
            }

            public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
            {
                throw self::gone();
            }

            public function exec(string $statement): int|false
            {
                throw self::gone();
            }

            private static function gone(): PDOException
            {
                $e = new PDOException('SQLSTATE[HY000]: General error: 2006 MySQL server has gone away');
                $e->errorInfo = ['HY000', 2006, 'MySQL server has gone away'];

                return $e;
            }
        };
    }

    private static function methodSource(\ReflectionMethod $method): string
    {
        $lines = file((string) $method->getFileName());
        self::assertIsArray($lines);

        return implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));
    }
}
