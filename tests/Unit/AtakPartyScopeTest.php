<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Api\AtakApiController;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Repositories\AtakDataRepository;
use App\Support\AtakPartyPurgeCli;
use App\Support\AtakPartyScope;
use App\Support\ComspecApiKeyAuth;
use App\Support\ShortCache;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Cloisonnement des parties de la communauté par défaut (comptes sans communauté), sur une vraie base sqlite, avec
 * le vrai contrôle du jeton de jeu (game_sessions), le vrai dépôt des pistes et la vraie portée de requête.
 *
 * Avant : tous ces comptes écrivaient sur la même carte ; GET /api/units rendait à chacun les pistes de parties sans
 * rapport (autres serveurs, autres terrains) et des pistes d'IA alliées vieilles de plusieurs semaines.
 */
final class AtakPartyScopeTest extends TestCase
{
    private const DEFAULT_TENANT = 1;
    private const REAL_TENANT = 7;
    private const KEY_ALTIS = 'Patrouille@Altis#202611151400';
    private const KEY_STRATIS = 'Patrouille@Stratis#202611151402';

    private PDO $pdo;

    private AtakDataRepository $units;

    private ?PDO $savedPdo = null;

    private bool $savedSessionStarted = false;

    /** @var array{set: bool, value: mixed} */
    private array $savedSession = ['set' => false, 'value' => null];

    /** @var array<string, mixed> */
    private array $savedServer = [];

    /** @var array<string, mixed> */
    private array $savedGet = [];

    private ?bool $savedShortCache = null;

    private string $tmpDir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->sqliteCreateFunction('NOW', static fn (): string => date('Y-m-d H:i:s'), 0);
        $this->pdo->exec('CREATE TABLE tenants (id INTEGER PRIMARY KEY, slug TEXT NOT NULL)');
        $this->pdo->exec("INSERT INTO tenants (id, slug) VALUES (1, 'default'), (7, 'les-loups')");
        $this->pdo->exec(
            'CREATE TABLE game_sessions (
                id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, user_id INTEGER, steam_id TEXT,
                access_token_hash TEXT NOT NULL, expires_at TEXT NOT NULL, revoked_at TEXT
            )'
        );
        $this->pdo->exec(
            'CREATE TABLE atak_units (
                id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, map_id INTEGER NOT NULL DEFAULT 1,
                call_sign TEXT NOT NULL, role TEXT, status TEXT, grid_ref TEXT, heading REAL, extra TEXT, updated_at TEXT
            )'
        );
        $this->pdo->exec('CREATE TABLE atak_last_activity (tenant_id INTEGER NOT NULL, map_id INTEGER NOT NULL, last_activity_at TEXT)');
        $db = new \ReflectionProperty(Database::class, 'pdo');
        $saved = $db->getValue();
        $this->savedPdo = $saved instanceof PDO ? $saved : null;
        $db->setValue(null, $this->pdo);

        $started = new \ReflectionProperty(Session::class, 'started');
        $this->savedSessionStarted = (bool) $started->getValue();
        $started->setValue(null, true);
        $this->savedSession = ['set' => isset($_SESSION), 'value' => $_SESSION ?? null];
        $_SESSION = [];
        $this->savedGet = $_GET;
        foreach ($this->serverKeys() as $key) {
            if (array_key_exists($key, $_SERVER)) {
                $this->savedServer[$key] = $_SERVER[$key];
            }
            unset($_SERVER[$key]);
        }
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $this->savedShortCache = (new \ReflectionProperty(ShortCache::class, 'enabled'))->getValue();
        ShortCache::setEnabled(false);
        ComspecApiKeyAuth::resetForTests();
        AtakPartyScope::resetForTests();
        $this->units = new AtakDataRepository($this->pdo);
    }

    protected function tearDown(): void
    {
        ComspecApiKeyAuth::resetForTests();
        AtakPartyScope::resetForTests();
        ShortCache::setEnabled($this->savedShortCache);
        (new \ReflectionProperty(Database::class, 'pdo'))->setValue(null, $this->savedPdo);
        (new \ReflectionProperty(Session::class, 'started'))->setValue(null, $this->savedSessionStarted);
        if ($this->savedSession['set']) {
            $_SESSION = $this->savedSession['value'];
        } else {
            unset($_SESSION);
        }
        $_GET = $this->savedGet;
        foreach ($this->serverKeys() as $key) {
            if (array_key_exists($key, $this->savedServer)) {
                $_SERVER[$key] = $this->savedServer[$key];
            } else {
                unset($_SERVER[$key]);
            }
        }
        if ($this->tmpDir !== '' && is_dir($this->tmpDir)) {
            foreach (glob($this->tmpDir . '/*/*') ?: [] as $f) {
                @unlink($f);
            }
            foreach (glob($this->tmpDir . '/*') ?: [] as $d) {
                is_dir($d) ? @rmdir($d) : @unlink($d);
            }
            @rmdir($this->tmpDir);
        }
        parent::tearDown();
    }

    public function testTwoUnlinkedAccountsOnDifferentPartiesDoNotSeeEachOther(): void
    {
        $this->position(287, self::DEFAULT_TENANT, 'U-00287', 4200.0, 5100.0, self::KEY_ALTIS);
        $this->ally(287, self::DEFAULT_TENANT, 'ALLY-2:15', 4210.0, 5110.0);
        $this->position(310, self::DEFAULT_TENANT, '1D10', 1800.0, 2900.0, self::KEY_STRATIS);
        $this->ally(310, self::DEFAULT_TENANT, 'ALLY-2:15', 1810.0, 2910.0);

        self::assertSame(['ALLY-2:15', 'U-00287'], $this->seenBy(287, self::DEFAULT_TENANT));
        self::assertSame(['1D10', 'ALLY-2:15'], $this->seenBy(310, self::DEFAULT_TENANT));
        // Même identifiant d'IA (netId) sur deux serveurs : deux lignes, chacune à sa place, plus de ligne disputée.
        self::assertSame(['4210 5110'], $this->grids(287, 'ALLY-2:15'));
        self::assertSame(['1810 2910'], $this->grids(310, 'ALLY-2:15'));
        self::assertSame(4, (int) $this->pdo->query('SELECT COUNT(*) FROM atak_units')->fetchColumn());
    }

    public function testTwoPlayersOfTheSamePartySeeEachOtherAndTheirAllies(): void
    {
        $this->position(287, self::DEFAULT_TENANT, 'U-00287', 4200.0, 5100.0, self::KEY_ALTIS);
        $this->position(401, self::DEFAULT_TENANT, 'Raider', 4300.0, 5200.0, self::KEY_ALTIS);
        $this->ally(401, self::DEFAULT_TENANT, 'ALLY-3:7', 4310.0, 5210.0);

        $expected = ['ALLY-3:7', 'Raider', 'U-00287'];
        self::assertSame($expected, $this->seenBy(287, self::DEFAULT_TENANT));
        self::assertSame($expected, $this->seenBy(401, self::DEFAULT_TENANT));
    }

    public function testAccountWithoutPartyKeyOnlySeesItsOwnTracks(): void
    {
        // Serveur sans ATAK natif : le jeu n'envoie aucune clé de partie.
        $this->position(287, self::DEFAULT_TENANT, 'Bonin', 4200.0, 5100.0, null);
        $this->ally(287, self::DEFAULT_TENANT, 'ALLY-2:15', 4210.0, 5110.0);
        $this->position(310, self::DEFAULT_TENANT, 'Shadow', 4205.0, 5105.0, null);

        self::assertSame(['ALLY-2:15', 'Bonin'], $this->seenBy(287, self::DEFAULT_TENANT));
        self::assertSame(['Shadow'], $this->seenBy(310, self::DEFAULT_TENANT));
    }

    public function testClientCannotChooseTheMapInTheDefaultCommunity(): void
    {
        // Pistes d'avant le correctif, sur la carte commune (mapId 1) : plus jamais servies, quelle que soit la carte demandée.
        $this->legacyRow(self::DEFAULT_TENANT, 1, 'ALLY-9:9', 'linked', 30);
        $this->position(287, self::DEFAULT_TENANT, 'U-00287', 4200.0, 5100.0, self::KEY_ALTIS);

        $this->asPlayer(287, self::DEFAULT_TENANT);
        $scope = AtakPartyScope::forRequest(self::DEFAULT_TENANT, ['mapId' => 1], '127.0.0.1');
        self::assertNotNull($scope['map_id']);
        self::assertTrue(AtakPartyScope::isReservedMapId((int) $scope['map_id']));
        self::assertSame(['U-00287'], $this->seenBy(287, self::DEFAULT_TENANT));

        // Par la vraie garde du contrôleur : la carte du client (query et corps) est remplacée avant toute lecture.
        AtakPartyScope::resetForTests();
        $_GET = ['mapId' => '1'];
        $request = new Request();
        $controller = (new \ReflectionClass(AtakApiController::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(AtakApiController::class, 'jsonBodyCache'))->setValue($controller, ['mapId' => 1, 'map_id' => 1]);
        (new \ReflectionMethod(AtakApiController::class, 'applyPartyScope'))->invoke($controller, $request, self::DEFAULT_TENANT);
        self::assertSame($scope['map_id'], $request->query('mapId'));
        $body = (new \ReflectionProperty(AtakApiController::class, 'jsonBodyCache'))->getValue($controller);
        self::assertSame($scope['map_id'], $body['mapId']);
        self::assertFalse(array_key_exists('map_id', $body));
        $mapId = (new \ReflectionMethod(AtakApiController::class, 'mapId'))->invoke($controller, $request, false);
        self::assertSame($scope['map_id'], $mapId);
        $rows = (new \ReflectionProperty(AtakApiController::class, 'atak'));
        $rows->setValue($controller, $this->units);
        $served = (new \ReflectionMethod(AtakApiController::class, 'unitsForTransmission'))->invoke($controller, self::DEFAULT_TENANT, $mapId);
        self::assertSame(['U-00287'], array_column($served, 'call_sign'), 'la carte commune ne complète plus la carte réservée');
    }

    public function testStaleTracksAreNotServedInTheDefaultCommunity(): void
    {
        $this->position(287, self::DEFAULT_TENANT, 'U-00287', 4200.0, 5100.0, self::KEY_ALTIS);
        $this->position(401, self::DEFAULT_TENANT, 'Raider', 4300.0, 5200.0, self::KEY_ALTIS);
        $this->ally(401, self::DEFAULT_TENANT, 'ALLY-3:7', 4310.0, 5210.0);
        // Raider et son IA ne donnent plus signe de vie depuis 20 minutes (plus que le quart d'heure servi).
        $this->age("call_sign IN ('Raider', 'ALLY-3:7')", 20 * 60);

        self::assertSame(['U-00287'], $this->seenBy(287, self::DEFAULT_TENANT));
    }

    public function testAnotherTerrainIsFilteredAndRealCommunityIsOtherwiseUnchanged(): void
    {
        // Communauté réelle : une seule carte partagée, comme avant (aucune carte réservée).
        $this->position(50, self::REAL_TENANT, 'Loup-1', 4200.0, 5100.0, self::KEY_ALTIS);
        $this->position(51, self::REAL_TENANT, 'Loup-2', 4300.0, 5200.0, self::KEY_ALTIS);
        $this->position(52, self::REAL_TENANT, 'Loup-3', 1800.0, 2900.0, self::KEY_STRATIS);
        $this->position(53, self::REAL_TENANT, 'Loup-4', 4400.0, 5300.0, null);
        // Membre hors liaison depuis deux jours, avec ses notes TOC : toujours servi, notes intactes.
        $this->legacyRow(self::REAL_TENANT, 1, 'Loup-5', 'offline', 2 * 86400, ['toc_note' => 'permission', 'toc_radio' => '41.5']);
        // IA alliée muette depuis deux jours : plus servie. Depuis une heure : servie.
        $this->legacyRow(self::REAL_TENANT, 1, 'ALLY-4:4', 'offline', 2 * 86400, ['ally_ai' => true, 'source' => 'ally', 'ally_id' => 'ALLY-4:4']);
        $this->legacyRow(self::REAL_TENANT, 1, 'ALLY-5:5', 'offline', 3600, ['ally_ai' => true, 'source' => 'ally', 'ally_id' => 'ALLY-5:5']);

        $this->asPlayer(50, self::REAL_TENANT);
        $scope = AtakPartyScope::forRequest(self::REAL_TENANT, [], '127.0.0.1');
        self::assertNull($scope['map_id'], 'communauté réelle : la carte reste celle du client');
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(DISTINCT map_id) FROM atak_units WHERE tenant_id = 7')->fetchColumn());

        // Le jeu, sur Altis : Loup-3 (Stratis) retiré ; Loup-4 (terrain inconnu) et Loup-5 (hors liaison) gardés.
        self::assertSame(['ALLY-5:5', 'Loup-1', 'Loup-2', 'Loup-4', 'Loup-5'], $this->seenBy(50, self::REAL_TENANT));
        // Le joueur de Stratis ne voit pas ceux d'Altis.
        self::assertSame(['ALLY-5:5', 'Loup-3', 'Loup-4', 'Loup-5'], $this->seenBy(52, self::REAL_TENANT));
        // Poste web (pas le jeu, pas de world_name) : toute la communauté, comme avant.
        self::assertSame(
            ['ALLY-5:5', 'Loup-1', 'Loup-2', 'Loup-3', 'Loup-4', 'Loup-5'],
            $this->seenBy(50, self::REAL_TENANT, '', false)
        );
        // world_name envoyé par le client : filtre appliqué même hors du jeu.
        self::assertSame(['ALLY-5:5', 'Loup-3', 'Loup-4', 'Loup-5'], $this->seenBy(50, self::REAL_TENANT, 'Stratis', false));

        $extra = AtakDataRepository::decodeExtra(
            $this->pdo->query("SELECT extra FROM atak_units WHERE call_sign = 'Loup-5'")->fetchColumn()
        );
        self::assertSame('permission', $extra['toc_note'] ?? null);
        self::assertSame('41.5', $extra['toc_radio'] ?? null);
    }

    public function testInternalMarksAreNotPublished(): void
    {
        $this->position(287, self::DEFAULT_TENANT, 'U-00287', 4200.0, 5100.0, self::KEY_ALTIS);
        $rows = $this->served(287, self::DEFAULT_TENANT);
        self::assertCount(1, $rows);
        self::assertFalse(array_key_exists('party_owner', $rows[0]['extra']));
        $stored = AtakDataRepository::decodeExtra($this->pdo->query('SELECT extra FROM atak_units')->fetchColumn());
        self::assertSame('u:287', $stored['party_owner'] ?? null);
        self::assertSame(self::KEY_ALTIS, $stored['party_key'] ?? null);
        self::assertSame('altis', $stored['party_world'] ?? null);
    }

    public function testPartyKeyParsing(): void
    {
        self::assertSame(self::KEY_ALTIS, AtakPartyScope::missionKeyFromBody(['extra' => ['mission_key' => self::KEY_ALTIS]]));
        self::assertSame(self::KEY_ALTIS, AtakPartyScope::missionKeyFromBody(['events' => [['t' => 'wx'], ['t' => 'pos', 'extra' => ['mission_key' => self::KEY_ALTIS]]]]));
        self::assertSame(self::KEY_ALTIS, AtakPartyScope::missionKeyFromBody(['extra' => json_encode(['mission_key' => self::KEY_ALTIS])]));
        self::assertSame('', AtakPartyScope::missionKeyFromBody(['mission_key' => 'Patrouille@Altis']), 'sans horodatage : pas une clé de partie');
        self::assertSame('', AtakPartyScope::missionKeyFromBody(['mission_key' => ['x']]));
        self::assertSame('altis', AtakPartyScope::worldOf('Op @ nuit@Altis#202611151400'));
        self::assertNotSame(AtakPartyScope::mapIdFor('u:1', self::KEY_ALTIS), AtakPartyScope::mapIdFor('u:1', self::KEY_STRATIS));
        self::assertSame(AtakPartyScope::mapIdFor('u:1', self::KEY_ALTIS), AtakPartyScope::mapIdFor('u:2', self::KEY_ALTIS));
        self::assertNotSame(AtakPartyScope::mapIdFor('u:1', ''), AtakPartyScope::mapIdFor('u:2', ''));
        self::assertFalse(AtakPartyScope::isReservedMapId(99));
        self::assertSame('ip:' . substr(sha1('10.0.0.1'), 0, 20), AtakPartyScope::ownerKey(null, '', '', '10.0.0.1'));
    }

    public function testCatchUpToolSimulatesThenAppliesAndIsIdempotent(): void
    {
        // Communauté par défaut : 3 pistes périmées (IA, compte, indicatif) et 1 piste en liaison.
        $this->legacyRow(self::DEFAULT_TENANT, 1, 'ALLY-2:15', 'offline', 8 * 86400, ['ally_ai' => true]);
        $this->legacyRow(self::DEFAULT_TENANT, 1, 'U-00042', 'offline', 3 * 86400);
        $this->legacyRow(self::DEFAULT_TENANT, 1, 'Shadow', 'linked', 2 * 86400);
        $this->position(287, self::DEFAULT_TENANT, 'U-00287', 4200.0, 5100.0, self::KEY_ALTIS);
        // Communauté réelle : piste hors liaison depuis 10 jours avec notes TOC, jamais touchée.
        $this->legacyRow(self::REAL_TENANT, 1, 'Loup-5', 'offline', 10 * 86400, ['toc_note' => 'permission']);

        $this->tmpDir = sys_get_temp_dir() . '/atak-party-' . bin2hex(random_bytes(6));
        mkdir($this->tmpDir . '/atak-activity', 0775, true);
        $reserved = AtakPartyScope::mapIdFor('u:287', self::KEY_ALTIS);
        $old = $this->tmpDir . '/atak-activity/t1_m' . $reserved . '.json';
        $manual = $this->tmpDir . '/atak-activity/t1_m1.json';
        $other = $this->tmpDir . '/atak-activity/t7_m' . $reserved . '.json';
        foreach ([$old, $manual, $other] as $f) {
            file_put_contents($f, '{}');
            touch($f, time() - 3 * 86400);
        }
        $log = $this->tmpDir . '/purge.log';
        $lines = [];
        $out = static function (string $line) use (&$lines): void {
            $lines[] = $line;
        };

        self::assertSame(0, AtakPartyPurgeCli::run($this->pdo, false, null, $this->tmpDir, $log, $out));
        self::assertSame(5, (int) $this->pdo->query('SELECT COUNT(*) FROM atak_units')->fetchColumn(), 'simulation : rien n’est écrit');
        self::assertTrue(is_file($old));
        self::assertStringContainsString('SIMULATION', $lines[0]);
        self::assertStringContainsString('3 piste(s)', implode(' ', $lines));
        self::assertStringContainsString('IA alliées 1, comptes U-… 1, indicatifs 1', implode(' ', $lines));
        self::assertStringContainsString('supprimées : 0', implode(' ', $lines));

        self::assertSame(2, AtakPartyPurgeCli::run($this->pdo, true, self::REAL_TENANT, $this->tmpDir, $log, $out), 'communauté réelle refusée');
        self::assertSame(5, (int) $this->pdo->query('SELECT COUNT(*) FROM atak_units')->fetchColumn());

        $lines = [];
        self::assertSame(0, AtakPartyPurgeCli::run($this->pdo, true, self::DEFAULT_TENANT, $this->tmpDir, $log, $out));
        self::assertStringContainsString('supprimées : 3', implode(' ', $lines));
        $left = $this->pdo->query('SELECT call_sign FROM atak_units ORDER BY call_sign')->fetchAll(PDO::FETCH_COLUMN);
        self::assertSame(['Loup-5', 'U-00287'], $left);
        self::assertFalse(is_file($old), 'fichier d’état d’une carte réservée périmée supprimé');
        self::assertTrue(is_file($manual), 'carte choisie à la main : jamais touchée');
        self::assertTrue(is_file($other), 'autre communauté : jamais touchée');

        $lines = [];
        self::assertSame(0, AtakPartyPurgeCli::run($this->pdo, true, null, $this->tmpDir, $log, $out));
        self::assertStringContainsString('0 piste(s)', implode(' ', $lines), 'idempotent');
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM atak_units')->fetchColumn());
        $journal = (string) file_get_contents($log);
        self::assertSame(2, substr_count($journal, ' simulation '));
        self::assertSame(4, substr_count($journal, ' apply '));
    }

    public function testWiringOfTheGuardAndOfTheCatchUpTool(): void
    {
        $root = dirname(__DIR__, 2);
        $controller = (string) file_get_contents($root . '/app/Controllers/Api/AtakApiController.php');
        self::assertStringContainsString('$this->applyPartyScope($request, $id);', $controller);
        self::assertStringContainsString('AtakPartyScope::stampForScope($this->partyScope, $extra, $body, $isProxyTerrain)', $controller);
        self::assertStringContainsString('AtakPartyScope::servedUnits(', $controller);
        foreach (['AtakOverwatchIntelController', 'AtakWaypointApiController', 'AtakAssignmentApiController'] as $name) {
            self::assertStringContainsString('AtakPartyScope::forRequest(', (string) file_get_contents($root . '/app/Controllers/Api/' . $name . '.php'), $name);
        }
        $job = (string) file_get_contents($root . '/app/Services/Cron/Jobs/PlayerDataRetentionCronJob.php');
        self::assertStringContainsString('AtakPartyScope::purgeStaleDefaultUnits($this->pdo, $tenantId, true)', $job);
        $tool = (string) file_get_contents($root . '/tools/atak-purge-default-units.php');
        self::assertStringContainsString("isset(\$opts['apply'])", $tool);
        self::assertStringContainsString('AtakPartyPurgeCli::run(', $tool);
    }

    /** @return list<string> */
    private function serverKeys(): array
    {
        return ['REMOTE_ADDR', 'HTTP_X_COMSPEC_KEY', 'HTTP_X_ATAK_TOKEN', 'HTTP_AUTHORIZATION', 'HTTP_X_COMSPEC_STEAM', 'CONTENT_TYPE'];
    }

    /** Requête du jeu d'un compte : vrai jeton de jeu, vérifié par ComspecApiKeyAuth dans game_sessions. */
    private function asPlayer(int $userId, int $tenantId): void
    {
        $token = hash('sha256', 'jeton-de-test-' . $tenantId . '-' . $userId);
        $st = $this->pdo->prepare('SELECT 1 FROM game_sessions WHERE access_token_hash = ?');
        $st->execute([hash('sha256', $token)]);
        if (!$st->fetchColumn()) {
            $this->pdo->prepare(
                'INSERT INTO game_sessions (tenant_id, user_id, steam_id, access_token_hash, expires_at) VALUES (?, ?, ?, ?, ?)'
            )->execute([$tenantId, $userId, null, hash('sha256', $token), gmdate('Y-m-d H:i:s', time() + 3600)]);
        }
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        ComspecApiKeyAuth::resetForTests();
        AtakPartyScope::resetForTests();
        self::assertTrue(ComspecApiKeyAuth::requestPresentsValidKey());
        self::assertSame($userId, ComspecApiKeyAuth::matchedUserId());
        self::assertSame($tenantId, ComspecApiKeyAuth::matchedTenantId());
    }

    /** Position du joueur, comme POST /api/atak/position : portée de la requête, marque, enregistrement. */
    private function position(int $userId, int $tenantId, string $callSign, float $x, float $y, ?string $missionKey): void
    {
        $extra = ['role' => 'Fusilier', 'health' => 'ok'];
        if ($missionKey !== null) {
            $extra['mission_key'] = $missionKey;
        }
        $this->ingest($userId, $tenantId, $callSign, $x, $y, $extra, false);
    }

    /** IA alliée remontée par le joueur (fn_reportAllyPosition) : aucune clé de partie dans la charge. */
    private function ally(int $userId, int $tenantId, string $allyId, float $x, float $y): void
    {
        $this->ingest($userId, $tenantId, $allyId, $x, $y, ['ally_ai' => true, 'is_ai' => true, 'source' => 'ally', 'ally_id' => $allyId], true);
    }

    /** @param array<string, mixed> $extra */
    private function ingest(int $userId, int $tenantId, string $callSign, float $x, float $y, array $extra, bool $proxy): void
    {
        $this->asPlayer($userId, $tenantId);
        $body = ['mapId' => 1, 'call_sign' => $callSign, 'pos_x' => $x, 'pos_y' => $y, 'extra' => $extra];
        $scope = AtakPartyScope::forRequest($tenantId, $body, '127.0.0.1');
        $extra = AtakPartyScope::stampForScope($scope, $extra, $body, $proxy);
        $this->units->upsertUnitPosition($tenantId, $scope['map_id'] ?? 1, $callSign, $x, $y, null, 'Fusilier', (string) json_encode($extra));
    }

    /**
     * Lecture comme GET /api/units?mapId=1 : portée de la requête, pistes de la carte, filtre de service.
     *
     * @return list<array<string, mixed>>
     */
    private function served(int $userId, int $tenantId, string $worldName = '', bool $gameClient = true): array
    {
        $this->asPlayer($userId, $tenantId);
        $scope = AtakPartyScope::forRequest($tenantId, [], '127.0.0.1');
        $rows = \App\Services\Atak\AtakUnitRowDedupe::byCallSign($this->units->getUnits($tenantId, $scope['map_id'] ?? 1));

        return array_values(AtakPartyScope::servedUnits($rows, $scope, $worldName, $gameClient));
    }

    /** @return list<string> */
    private function seenBy(int $userId, int $tenantId, string $worldName = '', bool $gameClient = true): array
    {
        $names = array_map(static fn (array $r): string => (string) $r['call_sign'], $this->served($userId, $tenantId, $worldName, $gameClient));
        sort($names);

        return $names;
    }

    /** @return list<string> */
    private function grids(int $userId, string $callSign): array
    {
        $out = [];
        foreach ($this->served($userId, self::DEFAULT_TENANT) as $row) {
            if ($row['call_sign'] === $callSign) {
                $out[] = (string) $row['grid_ref'];
            }
        }

        return $out;
    }

    /** @param array<string, mixed> $extra */
    private function legacyRow(int $tenantId, int $mapId, string $callSign, string $status, int $ageSeconds, array $extra = []): void
    {
        $this->pdo->prepare(
            'INSERT INTO atak_units (tenant_id, map_id, call_sign, role, status, grid_ref, extra, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$tenantId, $mapId, $callSign, '', $status, '4000 5000', json_encode($extra), date('Y-m-d H:i:s', time() - $ageSeconds)]);
    }

    private function age(string $where, int $seconds): void
    {
        $this->pdo->prepare('UPDATE atak_units SET updated_at = ? WHERE ' . $where)->execute([date('Y-m-d H:i:s', time() - $seconds)]);
    }
}
