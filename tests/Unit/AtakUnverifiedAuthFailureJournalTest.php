<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Api\AtakApiController;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Repositories\TacticalGameLinkRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
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
        'ATAK_GAME_LINK_BY_STEAM',
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

    private bool $hadPost = false;

    private mixed $savedPost = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hadPost = isset($_POST);
        $this->savedPost = $_POST ?? null;
        $_POST = [];
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
        if ($this->hadPost) {
            $_POST = $this->savedPost;
        } else {
            unset($_POST);
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
        // tenant_id[]=N donnait (int) d’un tableau = 1 : le refus allait dans le journal de la communauté 1,
        // qui existe dans cette base ; « Nabc » donnait N.
        self::setDatabasePdo(self::pdoWithTenant($this->tenantId, 1));
        $saved = self::setAsideCommunityOneJournal();
        try {
            $_GET['tenant_id'] = $this->tenantId . 'abc';
            self::assertNotNull(ComspecApiKeyAuth::enforceForTacticalPath(self::PATH));
            ComspecApiKeyAuth::resetForTests();
            $_GET['tenant_id'] = [(string) $this->tenantId];
            self::assertNotNull(ComspecApiKeyAuth::enforceForTacticalPath(self::PATH));

            self::assertSame([], $this->authEvents($this->tenantId));
            self::assertSame($saved['journal'], self::communityOneFiles(), 'rien d’écrit pour la communauté 1');
        } finally {
            self::restoreCommunityOneJournal($saved);
        }
    }

    public function testGameLinkRefusalUsesTheSameCommunityRuleAsTheGuard(): void
    {
        // Liaison refusée (code inconnu, Steam non reconnu) : la communauté du journal venait de resolveTenantId(),
        // (int) tenant_id : « tenant_id[]=N » allait dans la communauté 1, « Nabc » dans N.
        $pdo = self::pdoWithTenant($this->tenantId, 1);
        self::setDatabasePdo($pdo);
        $this->setEnv('ATAK_GAME_LINK_BY_STEAM', '1');
        $controller = $this->gameLinkController($pdo);
        $routes = [
            'gameLinkRedeem' => ['code' => 'ZZZZ9999'],
            'gameLinkBySteam' => ['steam_uid' => '76561198000000042'],
        ];
        $saved = self::setAsideCommunityOneJournal();
        try {
            foreach ($routes as $route => $body) {
                (new \ReflectionProperty(AtakApiController::class, 'jsonBodyCache'))->setValue($controller, $body);
                foreach ([[(string) $this->tenantId], $this->tenantId . 'abc'] as $tenantQuery) {
                    $_GET['tenant_id'] = $tenantQuery;
                    self::assertSame(404, $controller->{$route}(new Request())->statusCode(), $route);
                }
                self::assertSame([], $this->authEvents($this->tenantId), $route);
                self::assertSame($saved['journal'], self::communityOneFiles(), $route . ' : rien pour la communauté 1');
            }

            // La même communauté écrite en chiffres reste journalisée : une entrée par refus.
            $_GET['tenant_id'] = (string) $this->tenantId;
            foreach ($routes as $route => $body) {
                (new \ReflectionProperty(AtakApiController::class, 'jsonBodyCache'))->setValue($controller, $body);
                self::assertSame(404, $controller->{$route}(new Request())->statusCode(), $route);
            }
            self::assertCount(2, $this->authEvents($this->tenantId));
        } finally {
            self::restoreCommunityOneJournal($saved);
        }
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

    /** Contrôleur des routes de liaison : code et Steam toujours inconnus, journal et communautés de ce test. */
    private function gameLinkController(PDO $pdo): AtakApiController
    {
        $controller = (new \ReflectionClass(AtakApiController::class))->newInstanceWithoutConstructor();
        $set = static function (string $name, mixed $value) use ($controller): void {
            (new \ReflectionProperty(AtakApiController::class, $name))->setValue($controller, $value);
        };
        $links = $this->createMock(TacticalGameLinkRepository::class);
        $links->method('findValidByCode')->willReturn(null);
        $links->method('explainInvalidCode')->willReturn('');
        $users = $this->createMock(UserRepository::class);
        $users->method('findBySteamId')->willReturn(null);
        $set('tenantRepository', new TenantRepository($pdo));
        $set('activityLog', $this->svc);
        $set('gameLinkRepository', $links);
        $set('userRepository', $users);

        return $controller;
    }

    /**
     * Journal de la communauté 1 (t1_m*.json) et ses témoins anti-doublon : contenu et date de chaque fichier.
     *
     * @return array<string, array{content: string, mtime: int}>
     */
    private static function communityOneFiles(): array
    {
        $paths = array_merge(
            glob(base_path('storage/cache/atak-activity/t1_m*.json')) ?: [],
            glob(base_path('storage/cache/atak-activity/witness/t1_m*.txt')) ?: []
        );
        $files = [];
        foreach ($paths as $path) {
            $files[(string) $path] = [
                'content' => (string) file_get_contents((string) $path),
                'mtime' => (int) filemtime((string) $path),
            ];
        }
        ksort($files);

        return $files;
    }

    /**
     * Met de côté les témoins de la communauté 1 : un témoin récent (moins d’une minute) masquerait une écriture
     * fautive. Rend l’état d’origine, à restaurer, et le journal tel qu’il doit rester.
     *
     * @return array{original: array<string, array{content: string, mtime: int}>, journal: array<string, array{content: string, mtime: int}>}
     */
    private static function setAsideCommunityOneJournal(): array
    {
        $original = self::communityOneFiles();
        foreach (array_keys($original) as $path) {
            if (str_contains($path, '/witness/')) {
                @unlink($path);
            }
        }

        return ['original' => $original, 'journal' => self::communityOneFiles()];
    }

    /** @param array{original: array<string, array{content: string, mtime: int}>, journal: array<string, mixed>} $saved */
    private static function restoreCommunityOneJournal(array $saved): void
    {
        foreach (array_keys(self::communityOneFiles()) as $path) {
            if (!isset($saved['original'][$path])) {
                @unlink($path);
            }
        }
        foreach ($saved['original'] as $path => $file) {
            @file_put_contents($path, $file['content']);
            @touch($path, $file['mtime']);
        }
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

    /** Base qui répond : la communauté du test (et les autres données), aucune clé ni session jeu. */
    private static function pdoWithTenant(int $tenantId, int ...$otherTenantIds): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE tenants (id INTEGER PRIMARY KEY, name TEXT)');
        $insert = $pdo->prepare('INSERT INTO tenants (id, name) VALUES (?, ?)');
        foreach ([$tenantId, ...$otherTenantIds] as $id) {
            $insert->execute([$id, 'Communauté de test']);
        }
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
