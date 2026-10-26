<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Api\AtakApiController;
use App\Core\Database;
use App\Core\Session;
use App\Repositories\TenantRepository;
use App\Services\Tactical\AtakActivityLogService;
use App\Support\ComspecApiKeyAuth;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Refus d’une requête qui n’a prouvé aucune communauté : n’importe qui peut l’envoyer avec le tenant_id de
 * son choix. Avant, chaque requête réécrivait tout le journal de la carte 1 de cette communauté sous verrou,
 * créait un journal pour un identifiant inventé, et 5 000 requêtes chassaient les vrais événements.
 * Le diagnostic reste : une entrée par minute pour un même refus, et seulement pour une communauté qui existe.
 */
final class AtakUnverifiedAuthFailureJournalTest extends TestCase
{
    private const PATH = '/api/units';

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

    private AtakActivityLogService $svc;

    private int $tenantId;

    private int $unknownTenantId;

    /** @var array<string, array{in_env: bool, env: mixed, getenv: string|false}> */
    private array $savedEnv = [];

    private ?PDO $savedPdo = null;

    private bool $savedSessionStarted = false;

    /** @var array{set: bool, value: mixed} */
    private array $savedSession = ['set' => false, 'value' => null];

    /** @var array{set: bool, value: mixed} */
    private array $savedGetTenant = ['set' => false, 'value' => null];

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new AtakActivityLogService();
        $this->tenantId = 920000 + random_int(1, 49999);
        $this->unknownTenantId = $this->tenantId + 50000;
        $this->svc->purgeAllForTenant($this->tenantId);
        $this->svc->purgeAllForTenant($this->unknownTenantId);

        foreach (self::ENV_KEYS as $key) {
            $this->savedEnv[$key] = [
                'in_env' => array_key_exists($key, $_ENV),
                'env' => $_ENV[$key] ?? null,
                'getenv' => getenv($key),
            ];
        }
        // Production (clé obligatoire), sans clé plateforme, sans passe-droit de session navigateur,
        // sans communauté par défaut : seule la communauté de l’URL peut être journalisée.
        $this->setEnv('APP_ENV', 'production');
        $this->setEnv('TACTICAL_API_STRICT', null);
        $this->setEnv('TACTICAL_API_ALLOW_SESSION', 'false');
        $this->setEnv('X_COMSPEC_KEY', null);
        $this->setEnv('ATAK_INTEL_SECRET', null);
        $this->setEnv('ATAK_DEFAULT_TENANT_ID', null);
        $this->setEnv('APP_ATAK_DEFAULT_TENANT_ID', null);

        $this->savedPdo = self::databasePdo();
        self::setDatabasePdo(self::pdoWithTenant($this->tenantId));
        // Session déjà « ouverte » et vide : le refus 401 lit la session sans session_start() en CLI.
        $started = new \ReflectionProperty(Session::class, 'started');
        $this->savedSessionStarted = (bool) $started->getValue();
        $started->setValue(null, true);
        $this->savedSession = ['set' => isset($_SESSION), 'value' => $_SESSION ?? null];
        $_SESSION = [];
        $this->savedGetTenant = ['set' => array_key_exists('tenant_id', $_GET), 'value' => $_GET['tenant_id'] ?? null];

        unset($_SERVER['HTTP_X_COMSPEC_KEY'], $_SERVER['HTTP_X_ATAK_TOKEN'], $_SERVER['HTTP_AUTHORIZATION']);
        ComspecApiKeyAuth::resetForTests();
    }

    protected function tearDown(): void
    {
        $this->svc->purgeAllForTenant($this->tenantId);
        $this->svc->purgeAllForTenant($this->unknownTenantId);
        unset($_SERVER['HTTP_X_COMSPEC_KEY'], $_SERVER['HTTP_X_ATAK_TOKEN'], $_SERVER['HTTP_AUTHORIZATION']);
        if ($this->savedGetTenant['set']) {
            $_GET['tenant_id'] = $this->savedGetTenant['value'];
        } else {
            unset($_GET['tenant_id']);
        }
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

    public function testSameRefusalIsJournaledOncePerMinute(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->svc->recordUnverifiedAuthFailure($this->tenantId, 'Liaison en jeu refusée — code inconnu', [
                'reason' => 'unknown_code',
                'method' => 'code',
            ]);
        }

        self::assertCount(1, $this->authEvents($this->tenantId));
    }

    public function testDifferentRefusalsAreEachJournaled(): void
    {
        $this->svc->recordUnverifiedAuthFailure($this->tenantId, 'Liaison en jeu refusée — code inconnu', [
            'reason' => 'unknown_code',
            'method' => 'code',
        ]);
        $this->svc->recordUnverifiedAuthFailure($this->tenantId, 'Liaison en jeu refusée — code expiré', [
            'reason' => 'expired',
            'method' => 'code',
        ]);

        self::assertCount(2, $this->authEvents($this->tenantId));
    }

    public function testSameRefusalIsJournaledAgainAfterAMinute(): void
    {
        $label = 'Tentative de connexion refusée — clé d’accès manquante';
        $meta = ['reason' => 'missing_key', 'path_hint' => 'position'];
        $this->svc->recordUnverifiedAuthFailure($this->tenantId, $label, $meta);
        $witnesses = glob(base_path('storage/cache/atak-activity/witness/t' . $this->tenantId . '_m1_authfail_*.txt')) ?: [];
        self::assertCount(1, $witnesses);
        touch((string) $witnesses[0], time() - 61);

        $this->svc->recordUnverifiedAuthFailure($this->tenantId, $label, $meta);

        self::assertCount(2, $this->authEvents($this->tenantId));
    }

    public function testRecordedRefusalKeepsItsLabelAndMeta(): void
    {
        $this->svc->recordUnverifiedAuthFailure($this->tenantId, 'Connexion téléphone refusée — compte non lié (clé manquante)', [
            'reason' => 'missing_key',
            'path_hint' => 'connexion_telephone',
            'token' => 'jamais-journalise',
        ]);

        $events = $this->authEvents($this->tenantId);
        self::assertCount(1, $events);
        self::assertSame('Connexion téléphone refusée — compte non lié (clé manquante)', $events[0]['label']);
        self::assertSame('missing_key', $events[0]['meta']['reason'] ?? null);
        self::assertSame('connexion_telephone', $events[0]['meta']['path_hint'] ?? null);
        self::assertFalse($events[0]['meta']['ok'] ?? null);
        self::assertArrayNotHasKey('token', $events[0]['meta'] ?? []);
    }

    public function testAnonymousRequestsForAnExistingCommunityAreJournaledOnce(): void
    {
        $_GET['tenant_id'] = (string) $this->tenantId;

        for ($i = 0; $i < 3; $i++) {
            ComspecApiKeyAuth::resetForTests();
            $response = ComspecApiKeyAuth::enforceForTacticalPath(self::PATH);
            self::assertNotNull($response);
            self::assertSame(401, $response->statusCode());
        }

        $events = $this->authEvents($this->tenantId);
        self::assertCount(1, $events);
        self::assertSame('Tentative de connexion refusée — clé d’accès manquante', $events[0]['label']);
    }

    public function testAnonymousRequestForAnUnknownCommunityCreatesNoJournal(): void
    {
        $_GET['tenant_id'] = (string) $this->unknownTenantId;

        $response = ComspecApiKeyAuth::enforceForTacticalPath(self::PATH);

        self::assertNotNull($response);
        self::assertSame(401, $response->statusCode());
        self::assertFileDoesNotExist(base_path('storage/cache/atak-activity/t' . $this->unknownTenantId . '_m1.json'));
        self::assertSame([], glob(base_path('storage/cache/atak-activity/witness/t' . $this->unknownTenantId . '_m1_*.txt')) ?: []);
    }

    public function testTenantIdThatIsNotAPlainNumberIsIgnored(): void
    {
        // tenant_id[]=N donnait (int) d’un tableau = 1 ; « Nabc » donnait N.
        $_GET['tenant_id'] = $this->tenantId . 'abc';
        self::assertNotNull(ComspecApiKeyAuth::enforceForTacticalPath(self::PATH));
        ComspecApiKeyAuth::resetForTests();
        $_GET['tenant_id'] = [(string) $this->tenantId];
        self::assertNotNull(ComspecApiKeyAuth::enforceForTacticalPath(self::PATH));

        self::assertSame([], $this->authEvents($this->tenantId));
    }

    public function testDatabaseFailureDuringTheCommunityCheckJournalsNothing(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        self::setDatabasePdo($pdo);
        $_GET['tenant_id'] = (string) $this->tenantId;

        $response = ComspecApiKeyAuth::enforceForTacticalPath(self::PATH);

        self::assertNotNull($response);
        self::assertSame(401, $response->statusCode());
        self::assertSame([], $this->authEvents($this->tenantId));
    }

    public function testGameLinkRefusalIsJournaledOnceAndOnlyForAnExistingCommunity(): void
    {
        // Route sans clé (le code de liaison fait office de secret) : la communauté vient du corps ou de l’URL.
        $controller = (new \ReflectionClass(AtakApiController::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(AtakApiController::class, 'tenantRepository'))
            ->setValue($controller, new TenantRepository(self::pdoWithTenant($this->tenantId)));
        (new \ReflectionProperty(AtakApiController::class, 'activityLog'))->setValue($controller, $this->svc);
        $log = new \ReflectionMethod(AtakApiController::class, 'logUnverifiedLinkRefusal');
        $meta = ['reason' => 'unknown_code', 'method' => 'code'];

        for ($i = 0; $i < 3; $i++) {
            $log->invoke($controller, $this->tenantId, 'Liaison en jeu refusée — code inconnu', $meta);
            $log->invoke($controller, $this->unknownTenantId, 'Liaison en jeu refusée — code inconnu', $meta);
        }

        self::assertCount(1, $this->authEvents($this->tenantId));
        self::assertFileDoesNotExist(base_path('storage/cache/atak-activity/t' . $this->unknownTenantId . '_m1.json'));
    }

    /** @return list<array<string, mixed>> */
    private function authEvents(int $tenantId): array
    {
        $list = $this->svc->listFiltered($tenantId, AtakActivityLogService::AUTH_MAP_ID, [
            'type' => AtakActivityLogService::TYPE_AUTH,
            'limit' => 50,
        ]);

        return array_values($list['events'] ?? []);
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

    /** Base qui répond : une seule communauté, aucune clé ni session jeu (requête sans clé). */
    private static function pdoWithTenant(int $tenantId): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE tenants (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo->prepare('INSERT INTO tenants (id, name) VALUES (?, ?)')->execute([$tenantId, 'Communauté de test']);
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
}
