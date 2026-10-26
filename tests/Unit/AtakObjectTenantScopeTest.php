<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Api\AtakApiController;
use App\Core\Container;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\AtakMedevacRepository;
use App\Repositories\AtakPoiRepository;
use App\Services\Platform\FeatureGateService;
use App\Support\AtakArmaWriteGuard;
use App\Support\AtakModulesSchema;
use App\Support\ComspecApiKeyAuth;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Objets tactiques désignés par leur seul identifiant dans l’adresse : POI (PUT/PATCH /api/atak/poi/{id}),
 * MEDEVAC étendu (GET /api/atak/medevac/{id}, PATCH …/status, POST …/assign, POST …/patients), QRF
 * (POST /api/atak/qrf/{id}/assign, /position, /sitrep) et demande de service véhicule
 * (POST /api/atak/vehicles/{id}/service).
 *
 * Un appelant de la communauté 7 (clé communauté ou session web) qui donne l’id d’un objet de la communauté 3
 * reçoit la même réponse que pour un id inexistant (404, enveloppe habituelle de la route) et rien n’est écrit.
 * Ses propres objets restent servis. Base SQLite en mémoire : ce sont les vraies requêtes des dépôts qui sont
 * exercées (tables et vues reprises du schéma, colonnes utiles seulement). L’authentification n’est pas l’objet
 * du test : la communauté de l’appelant est posée comme la garde tactique la laisse (clé ou session).
 */
final class AtakObjectTenantScopeTest extends TestCase
{
    private const CALLER = 7;

    private const FOREIGN_POI = 31;

    private const OWN_POI = 71;

    private const FOREIGN_MEDEVAC = 32;

    private const OWN_MEDEVAC = 72;

    private const FOREIGN_QRF = 33;

    private const OWN_QRF = 73;

    private const FOREIGN_VEHICLE = 34;

    private const OWN_VEHICLE = 74;

    /** Réglages lus par ComspecApiKeyAuth::armaInlineAuthOk(), sauvegardés puis rendus tels quels. */
    private const ENV_KEYS = ['APP_ENV', 'TACTICAL_API_STRICT', 'X_COMSPEC_KEY', 'ATAK_INTEL_SECRET'];

    private ?PDO $pdo = null;

    private ?PDO $savedPdo = null;

    private bool $savedSchemaEnsured = false;

    /** @var array{set: bool, value: mixed} */
    private array $savedGate = ['set' => false, 'value' => null];

    /** @var array<string, array{in_env: bool, env: mixed, getenv: string|false}> */
    private array $savedEnv = [];

    private bool $hadSession = false;

    private mixed $savedSession = null;

    /** @var array<string, mixed> */
    private array $savedGet = [];

    /** @var array<string, mixed> */
    private array $savedServer = [];

    private bool $hadPost = false;

    private mixed $savedPost = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hadSession = isset($_SESSION);
        $this->savedSession = $_SESSION ?? null;
        $this->savedGet = $_GET;
        $this->savedServer = $_SERVER;
        $this->hadPost = isset($_POST);
        $this->savedPost = $_POST ?? null;
        $_SESSION = [];
        $_GET = [];
        $_POST = [];
        unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['HTTP_X_COMSPEC_KEY'], $_SERVER['HTTP_X_ATAK_TOKEN'], $_SERVER['CONTENT_TYPE']);
        ComspecApiKeyAuth::resetForTests();

        // Écritures jeu (authArma) : ni clé plateforme ni mode strict, la communauté vient de la clé ou de la session.
        foreach (self::ENV_KEYS as $key) {
            $this->savedEnv[$key] = [
                'in_env' => array_key_exists($key, $_ENV),
                'env' => $_ENV[$key] ?? null,
                'getenv' => getenv($key),
            ];
        }
        self::setEnv('APP_ENV', 'testing');
        self::setEnv('TACTICAL_API_STRICT', null);
        self::setEnv('X_COMSPEC_KEY', null);
        self::setEnv('ATAK_INTEL_SECRET', null);

        // Formule : la carte complète est ouverte aux deux communautés.
        $instances = (new \ReflectionProperty(Container::class, 'instances'))->getValue();
        $this->savedGate = [
            'set' => is_array($instances) && array_key_exists(FeatureGateService::class, $instances),
            'value' => is_array($instances) ? ($instances[FeatureGateService::class] ?? null) : null,
        ];
        $gate = $this->createMock(FeatureGateService::class);
        $gate->method('allows')->willReturn(true);
        $gate->method('allowsWithCatalogDefault')->willReturn(true);
        $instances = is_array($instances) ? $instances : [];
        $instances[FeatureGateService::class] = $gate;
        (new \ReflectionProperty(Container::class, 'instances'))->setValue(null, $instances);

        $this->savedSchemaEnsured = (bool) (new \ReflectionProperty(AtakModulesSchema::class, 'ensured'))->getValue();
        (new \ReflectionProperty(AtakModulesSchema::class, 'ensured'))->setValue(null, true);
        $saved = (new \ReflectionProperty(Database::class, 'pdo'))->getValue();
        $this->savedPdo = $saved instanceof PDO ? $saved : null;

        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = self::seededPdo();
        (new \ReflectionProperty(Database::class, 'pdo'))->setValue(null, $this->pdo);
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(Database::class, 'pdo'))->setValue(null, $this->savedPdo);
        (new \ReflectionProperty(AtakModulesSchema::class, 'ensured'))->setValue(null, $this->savedSchemaEnsured);
        $instances = (new \ReflectionProperty(Container::class, 'instances'))->getValue();
        $instances = is_array($instances) ? $instances : [];
        if ($this->savedGate['set']) {
            $instances[FeatureGateService::class] = $this->savedGate['value'];
        } else {
            unset($instances[FeatureGateService::class]);
        }
        (new \ReflectionProperty(Container::class, 'instances'))->setValue(null, $instances);
        foreach ($this->savedEnv as $key => $saved) {
            if ($saved['in_env']) {
                $_ENV[$key] = $saved['env'];
            } else {
                unset($_ENV[$key]);
            }
            if ($saved['getenv'] === false) {
                putenv($key);
            } else {
                putenv($key . '=' . $saved['getenv']);
            }
        }
        ComspecApiKeyAuth::resetForTests();
        if ($this->hadSession) {
            $_SESSION = $this->savedSession;
        } else {
            unset($_SESSION);
        }
        $_GET = $this->savedGet;
        $_SERVER = $this->savedServer;
        if ($this->hadPost) {
            $_POST = $this->savedPost;
        } else {
            unset($_POST);
        }
        parent::tearDown();
    }

    /** @return array<string, array{0: string}> */
    public static function callerModes(): array
    {
        return [
            'clé communauté ou jeton de jeu' => ['cle'],
            'session web' => ['session'],
        ];
    }

    #[DataProvider('callerModes')]
    public function testPoiOfAnotherCommunityIsNeitherUpdatedNorReturned(string $mode): void
    {
        $this->actAs($mode);
        $before = $this->row('atak_poi', self::FOREIGN_POI);

        $response = $this->call('poiUpdate', 'PUT', '/api/atak/poi/' . self::FOREIGN_POI, self::FOREIGN_POI, [
            'status' => 'NEUTRALIZED',
            'description' => 'Modifié par la communauté 7',
        ]);

        self::assertSame(404, $response->statusCode());
        self::assertSame(['error' => 'POI not found'], self::json($response));
        self::assertStringNotContainsString('obs3@example.test', $response->body());
        self::assertSame($before, $this->row('atak_poi', self::FOREIGN_POI));
    }

    #[DataProvider('callerModes')]
    public function testMedevacOfAnotherCommunityIsNotShown(string $mode): void
    {
        $this->actAs($mode);

        $response = $this->call('medevacShow', 'GET', '/api/atak/medevac/' . self::FOREIGN_MEDEVAC, self::FOREIGN_MEDEVAC);

        self::assertSame(404, $response->statusCode());
        self::assertSame(['error' => 'MEDEVAC not found'], self::json($response));
        self::assertStringNotContainsString('medic3@example.test', $response->body());
        self::assertStringNotContainsString('Patient Trois', $response->body());
    }

    #[DataProvider('callerModes')]
    public function testMedevacStatusOfAnotherCommunityIsNotChanged(string $mode): void
    {
        $this->actAs($mode);
        $before = $this->row('atak_medevac_requests', self::FOREIGN_MEDEVAC);

        $response = $this->call('medevacUpdateStatus', 'PATCH', '/api/atak/medevac/' . self::FOREIGN_MEDEVAC . '/status', self::FOREIGN_MEDEVAC, [
            'status' => 'CANCELLED',
            'message' => 'Annulée par la communauté 7',
        ]);

        self::assertSame(404, $response->statusCode());
        self::assertSame(['error' => 'MEDEVAC not found'], self::json($response));
        self::assertSame($before, $this->row('atak_medevac_requests', self::FOREIGN_MEDEVAC));
    }

    #[DataProvider('callerModes')]
    public function testMedevacOfAnotherCommunityGetsNoAsset(string $mode): void
    {
        $this->actAs($mode);
        $before = $this->row('atak_medevac_requests', self::FOREIGN_MEDEVAC);

        $response = $this->call('medevacAssignAsset', 'POST', '/api/atak/medevac/' . self::FOREIGN_MEDEVAC . '/assign', self::FOREIGN_MEDEVAC, [
            'asset_callsign' => 'DUSTOFF 7',
        ]);

        self::assertSame(404, $response->statusCode());
        self::assertSame(['error' => 'MEDEVAC not found'], self::json($response));
        self::assertSame($before, $this->row('atak_medevac_requests', self::FOREIGN_MEDEVAC));
    }

    #[DataProvider('callerModes')]
    public function testMedevacOfAnotherCommunityGetsNoPatient(string $mode): void
    {
        $this->actAs($mode);

        $response = $this->call('medevacAddPatient', 'POST', '/api/atak/medevac/' . self::FOREIGN_MEDEVAC . '/patients', self::FOREIGN_MEDEVAC, [
            'patient_name' => 'Intrus de la 7',
            'triage_category' => 'T1',
        ]);

        self::assertSame(404, $response->statusCode());
        self::assertSame(['error' => 'MEDEVAC not found'], self::json($response));
        self::assertSame(['Patient Trois'], $this->column(
            'SELECT patient_name FROM atak_medevac_patients WHERE medevac_request_id = ? ORDER BY id',
            [self::FOREIGN_MEDEVAC]
        ));
        self::assertSame([], $this->column("SELECT id FROM atak_medevac_patients WHERE patient_name = 'Intrus de la 7'"));
    }

    #[DataProvider('callerModes')]
    public function testQrfOfAnotherCommunityIsNotAssigned(string $mode): void
    {
        $this->actAs($mode);
        $before = $this->row('atak_qrf_requests', self::FOREIGN_QRF);

        $response = $this->call('qrfAssign', 'POST', '/api/atak/qrf/' . self::FOREIGN_QRF . '/assign', self::FOREIGN_QRF, [
            'qrf_unit' => '2e section',
            'qrf_callsign' => 'VIPER 7',
        ]);

        self::assertSame(404, $response->statusCode());
        self::assertSame(['error' => 'QRF request not found'], self::json($response));
        self::assertSame($before, $this->row('atak_qrf_requests', self::FOREIGN_QRF));
    }

    #[DataProvider('callerModes')]
    public function testQrfOfAnotherCommunityKeepsItsPosition(string $mode): void
    {
        $this->actAs($mode);
        $before = $this->row('atak_qrf_requests', self::FOREIGN_QRF);

        $response = $this->call('qrfUpdatePosition', 'POST', '/api/atak/qrf/' . self::FOREIGN_QRF . '/position', self::FOREIGN_QRF, [
            'pos_x' => 1200.5,
            'pos_y' => 3400.25,
            'eta' => '5 min',
        ]);

        self::assertSame(404, $response->statusCode());
        self::assertSame(['error' => 'QRF request not found'], self::json($response));
        self::assertSame($before, $this->row('atak_qrf_requests', self::FOREIGN_QRF));
    }

    #[DataProvider('callerModes')]
    public function testQrfOfAnotherCommunityGetsNoSitrep(string $mode): void
    {
        $this->actAs($mode);

        $response = $this->call('qrfAddSitrep', 'POST', '/api/atak/qrf/' . self::FOREIGN_QRF . '/sitrep', self::FOREIGN_QRF, [
            'update_message' => 'Faux point de situation',
        ]);

        self::assertSame(404, $response->statusCode());
        self::assertSame(['error' => 'QRF request not found'], self::json($response));
        self::assertSame([], $this->column('SELECT id FROM atak_qrf_sitrep_updates WHERE qrf_request_id = ?', [self::FOREIGN_QRF]));
    }

    #[DataProvider('callerModes')]
    public function testVehicleOfAnotherCommunityGetsNoServiceRequest(string $mode): void
    {
        $this->actAs($mode);

        $response = $this->call('vehiclesServiceRequest', 'POST', '/api/atak/vehicles/' . self::FOREIGN_VEHICLE . '/service', self::FOREIGN_VEHICLE, [
            'request_type' => 'REFUEL',
            'priority' => 'HIGH',
        ]);

        self::assertSame(404, $response->statusCode());
        self::assertSame(['error' => 'Vehicle not found'], self::json($response));
        self::assertSame([], $this->column('SELECT id FROM atak_vehicle_service_requests WHERE vehicle_tracking_id = ?', [self::FOREIGN_VEHICLE]));
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function callerModesAndRoutes(): array
    {
        $cases = [];
        foreach (self::callerModes() as $label => [$mode]) {
            foreach (['qrfAssign', 'poiUpdate', 'medevacShow', 'vehiclesServiceRequest'] as $action) {
                $cases[$label . ' · ' . $action] = [$mode, $action];
            }
        }

        return $cases;
    }

    /** La communauté ne vient jamais d’un paramètre du client quand la clé ou la session la donne déjà. */
    #[DataProvider('callerModesAndRoutes')]
    public function testCommunityNamedByTheClientIsIgnored(string $mode, string $action): void
    {
        $this->actAs($mode);
        $named = ['tenant_id' => '3', 'tenant_slug' => 'communaute-3'];
        [$method, $path, $id, $table, $body] = match ($action) {
            'qrfAssign' => ['POST', '/api/atak/qrf/' . self::FOREIGN_QRF . '/assign', self::FOREIGN_QRF, 'atak_qrf_requests',
                ['qrf_unit' => '2e section', 'qrf_callsign' => 'VIPER 7']],
            'poiUpdate' => ['PUT', '/api/atak/poi/' . self::FOREIGN_POI, self::FOREIGN_POI, 'atak_poi',
                ['status' => 'NEUTRALIZED', 'description' => 'Modifié par la communauté 7']],
            'medevacShow' => ['GET', '/api/atak/medevac/' . self::FOREIGN_MEDEVAC, self::FOREIGN_MEDEVAC, 'atak_medevac_requests', []],
            'vehiclesServiceRequest' => ['POST', '/api/atak/vehicles/' . self::FOREIGN_VEHICLE . '/service', self::FOREIGN_VEHICLE,
                'atak_vehicle_tracking', ['request_type' => 'REFUEL', 'priority' => 'HIGH']],
        };
        $before = $this->row($table, $id);

        $response = $this->call($action, $method, $path, $id, $body + $named, $named);

        self::assertSame(404, $response->statusCode());
        self::assertStringNotContainsString('obs3@example.test', $response->body());
        self::assertSame($before, $this->row($table, $id));
        self::assertSame([], $this->column('SELECT id FROM atak_vehicle_service_requests WHERE vehicle_tracking_id = ?', [self::FOREIGN_VEHICLE]));
    }

    /** Id inexistant : plus de ligne orpheline (patient, point de situation, demande de service) acceptée en 201. */
    public function testUnknownParentGetsNoChildRow(): void
    {
        $this->actAs('cle');

        $patient = $this->call('medevacAddPatient', 'POST', '/api/atak/medevac/999/patients', 999, ['patient_name' => 'Personne']);
        $sitrep = $this->call('qrfAddSitrep', 'POST', '/api/atak/qrf/999/sitrep', 999, ['update_message' => 'Rien']);
        $service = $this->call('vehiclesServiceRequest', 'POST', '/api/atak/vehicles/999/service', 999, ['request_type' => 'REPAIR']);

        self::assertSame([404, 404, 404], [$patient->statusCode(), $sitrep->statusCode(), $service->statusCode()]);
        self::assertSame([], $this->column('SELECT id FROM atak_medevac_patients WHERE medevac_request_id = 999'));
        self::assertSame([], $this->column('SELECT id FROM atak_qrf_sitrep_updates WHERE qrf_request_id = 999'));
        self::assertSame([], $this->column('SELECT id FROM atak_vehicle_service_requests WHERE vehicle_tracking_id = 999'));
    }

    #[DataProvider('callerModes')]
    public function testOwnPoiIsUpdated(string $mode): void
    {
        $this->actAs($mode);

        $response = $this->call('poiUpdate', 'PATCH', '/api/atak/poi/' . self::OWN_POI, self::OWN_POI, [
            'status' => 'NEUTRALIZED',
        ]);

        self::assertSame(200, $response->statusCode());
        $poi = self::json($response);
        self::assertSame(self::OWN_POI, (int) ($poi['id'] ?? 0));
        self::assertSame('NEUTRALIZED', $poi['status'] ?? null);
        self::assertSame('NEUTRALIZED', $this->row('atak_poi', self::OWN_POI)['status']);
    }

    public function testOwnMedevacIsShownWithItsPatientsOnly(): void
    {
        $this->actAs('cle');

        $response = $this->call('medevacShow', 'GET', '/api/atak/medevac/' . self::OWN_MEDEVAC, self::OWN_MEDEVAC);

        self::assertSame(200, $response->statusCode());
        $medevac = self::json($response);
        self::assertSame(self::OWN_MEDEVAC, (int) ($medevac['id'] ?? 0));
        self::assertSame(['Patient Sept'], array_column($medevac['patients'] ?? [], 'patient_name'));
    }

    public function testOwnMedevacIsUpdatedAssignedAndGetsAPatient(): void
    {
        $this->actAs('cle');

        $status = $this->call('medevacUpdateStatus', 'PATCH', '/api/atak/medevac/' . self::OWN_MEDEVAC . '/status', self::OWN_MEDEVAC, ['status' => 'ACKNOWLEDGED']);
        $assign = $this->call('medevacAssignAsset', 'POST', '/api/atak/medevac/' . self::OWN_MEDEVAC . '/assign', self::OWN_MEDEVAC, ['asset_callsign' => 'DUSTOFF 7']);
        $patient = $this->call('medevacAddPatient', 'POST', '/api/atak/medevac/' . self::OWN_MEDEVAC . '/patients', self::OWN_MEDEVAC, ['patient_name' => 'Blessé 2']);

        self::assertSame([200, 200, 201], [$status->statusCode(), $assign->statusCode(), $patient->statusCode()]);
        $row = $this->row('atak_medevac_requests', self::OWN_MEDEVAC);
        self::assertSame('ACKNOWLEDGED', $row['status']);
        self::assertNotNull($row['acknowledged_at']);
        self::assertSame('DUSTOFF 7', $row['assigned_asset_callsign']);
        $patientId = (int) (self::json($patient)['patient_id'] ?? 0);
        self::assertGreaterThan(0, $patientId);
        self::assertSame([(string) self::OWN_MEDEVAC], $this->column(
            'SELECT medevac_request_id FROM atak_medevac_patients WHERE id = ?',
            [$patientId]
        ));
    }

    public function testOwnQrfIsAssignedMovedAndGetsASitrep(): void
    {
        $this->actAs('cle');

        $assign = $this->call('qrfAssign', 'POST', '/api/atak/qrf/' . self::OWN_QRF . '/assign', self::OWN_QRF, ['qrf_unit' => '2e section', 'qrf_callsign' => 'VIPER 7']);
        $position = $this->call('qrfUpdatePosition', 'POST', '/api/atak/qrf/' . self::OWN_QRF . '/position', self::OWN_QRF, ['pos_x' => 1200.5, 'pos_y' => 3400.25]);
        $sitrep = $this->call('qrfAddSitrep', 'POST', '/api/atak/qrf/' . self::OWN_QRF . '/sitrep', self::OWN_QRF, ['update_message' => 'En route']);

        self::assertSame([200, 200, 201], [$assign->statusCode(), $position->statusCode(), $sitrep->statusCode()]);
        $row = $this->row('atak_qrf_requests', self::OWN_QRF);
        self::assertSame('QRF_ASSIGNED', $row['status']);
        self::assertSame('VIPER 7', $row['assigned_qrf_callsign']);
        self::assertEquals(1200.5, $row['qrf_current_pos_x']);
        $updateId = (int) (self::json($sitrep)['update_id'] ?? 0);
        self::assertGreaterThan(0, $updateId);
        self::assertSame([(string) self::OWN_QRF], $this->column('SELECT qrf_request_id FROM atak_qrf_sitrep_updates WHERE id = ?', [$updateId]));
    }

    /** Parcours de la DLL (PostVehicleServiceSync) : l’id vient de l’upsert de la même communauté. */
    public function testOwnVehicleGetsAServiceRequest(): void
    {
        $this->actAs('cle');

        $response = $this->call('vehiclesServiceRequest', 'POST', '/api/atak/vehicles/' . self::OWN_VEHICLE . '/service', self::OWN_VEHICLE, [
            'request_type' => 'REFUEL',
            'priority' => 'HIGH',
        ]);

        self::assertSame(201, $response->statusCode());
        $serviceId = (int) (self::json($response)['service_request_id'] ?? 0);
        self::assertGreaterThan(0, $serviceId);
        self::assertSame([(string) self::OWN_VEHICLE], $this->column(
            'SELECT vehicle_tracking_id FROM atak_vehicle_service_requests WHERE id = ?',
            [$serviceId]
        ));
    }

    /** Lectures des dépôts bornées à la communauté donnée (patients bornés par leur demande MEDEVAC). */
    public function testRepositoryReadsStayInsideTheGivenCommunity(): void
    {
        $db = Database::getInstance();
        $poi = new AtakPoiRepository($db);
        $medevac = new AtakMedevacRepository($db);

        self::assertNull($poi->findById(self::FOREIGN_POI, self::CALLER));
        self::assertSame(self::FOREIGN_POI, (int) ($poi->findById(self::FOREIGN_POI, 3)['id'] ?? 0));
        self::assertNull($medevac->findById(self::FOREIGN_MEDEVAC, self::CALLER));
        self::assertSame([], $medevac->getPatients(self::FOREIGN_MEDEVAC, self::CALLER));
        self::assertSame(['Patient Trois'], array_column($medevac->getPatients(self::FOREIGN_MEDEVAC, 3), 'patient_name'));
    }

    private function actAs(string $mode): void
    {
        if ($mode === 'cle') {
            (new \ReflectionProperty(ComspecApiKeyAuth::class, 'matchedTenantId'))->setValue(null, self::CALLER);

            return;
        }
        $_SESSION = ['user_id' => 70, 'tenant_id' => self::CALLER];
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, mixed> $query
     */
    private function call(string $action, string $method, string $path, int $id, array $body = [], array $query = []): Response
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $path;
        $_GET = $query;
        $controller = (new \ReflectionClass(AtakApiController::class))->newInstanceWithoutConstructor();
        $guard = $this->createMock(AtakArmaWriteGuard::class);
        $guard->method('assertActor')->willReturn(['steam_uid' => '76561190000000007', 'session_ok' => true]);
        (new \ReflectionProperty(AtakApiController::class, 'armaGuard'))->setValue($controller, $guard);
        (new \ReflectionProperty(AtakApiController::class, 'jsonBodyCache'))->setValue($controller, $body);

        return $controller->$action(new Request(), ['id' => (string) $id]);
    }

    /** @return array<string, mixed> */
    private static function json(Response $response): array
    {
        $decoded = json_decode($response->body(), true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @return array<string, mixed> */
    private function row(string $table, int $id): array
    {
        $st = $this->db()->prepare('SELECT * FROM ' . $table . ' WHERE id = ?');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : [];
    }

    /**
     * @param list<mixed> $params
     * @return list<string>
     */
    private function column(string $sql, array $params = []): array
    {
        $st = $this->db()->prepare($sql);
        $st->execute($params);

        return array_map('strval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    private function db(): PDO
    {
        if (!$this->pdo instanceof PDO) {
            self::fail('Base de test absente');
        }

        return $this->pdo;
    }

    private static function setEnv(string $key, ?string $value): void
    {
        if ($value === null) {
            unset($_ENV[$key]);
            putenv($key);

            return;
        }
        $_ENV[$key] = $value;
        putenv($key . '=' . $value);
    }

    /** Tables et vues de migrations/2026_07_24_00{2,4,5,6} et bootstrap/atak_modules_schema_migration.php, réduites. */
    private static function seededPdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->sqliteCreateFunction('NOW', static fn (): string => date('Y-m-d H:i:s'), 0);
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT, callsign TEXT, display_name TEXT)');
        $pdo->exec('CREATE TABLE atak_poi (
            id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, context_id INTEGER NOT NULL,
            poi_name TEXT, poi_code TEXT, category TEXT, affiliation TEXT, certainty TEXT,
            pos_x REAL, pos_y REAL, pos_z REAL, grid_reference TEXT, description TEXT, observed_activity TEXT,
            threat_level TEXT, status TEXT DEFAULT \'ACTIVE\', last_observed_at TEXT, source_type TEXT, source_reliability TEXT,
            reported_by_user_id INTEGER, reported_by_callsign TEXT, properties TEXT, icon_type TEXT, marker_color TEXT,
            is_visible INTEGER DEFAULT 1, visibility_level TEXT, parent_poi_id INTEGER, related_report_id INTEGER,
            created_by_user_id INTEGER, updated_by_user_id INTEGER, deleted_at TEXT)');
        $pdo->exec('CREATE TABLE atak_poi_photos (id INTEGER PRIMARY KEY, poi_id INTEGER)');
        $pdo->exec('CREATE TABLE atak_poi_observations (id INTEGER PRIMARY KEY, poi_id INTEGER, observed_at TEXT)');
        $pdo->exec("CREATE VIEW v_atak_poi AS
            SELECT p.*,
                   reporter_u.email AS reported_by_username,
                   COALESCE(NULLIF(TRIM(p.reported_by_callsign), ''), NULLIF(TRIM(reporter_u.callsign), ''), NULLIF(TRIM(reporter_u.display_name), ''), 'Compte inconnu') AS reporter_display_name,
                   creator_u.email AS created_by_username,
                   (SELECT COUNT(*) FROM atak_poi_photos WHERE poi_id = p.id) AS photo_count,
                   (SELECT COUNT(*) FROM atak_poi_observations WHERE poi_id = p.id) AS observation_count,
                   (SELECT MAX(observed_at) FROM atak_poi_observations WHERE poi_id = p.id) AS last_observation_timestamp
            FROM atak_poi p
            LEFT JOIN users reporter_u ON p.reported_by_user_id = reporter_u.id
            LEFT JOIN users creator_u ON p.created_by_user_id = creator_u.id
            WHERE p.deleted_at IS NULL");
        $pdo->exec('CREATE TABLE atak_medevac_requests (
            id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, context_id INTEGER NOT NULL,
            medevac_number TEXT, priority TEXT, status TEXT DEFAULT \'REQUESTED\', pickup_grid TEXT, radio_frequency TEXT,
            equipment_needed TEXT, requested_by_user_id INTEGER, requested_by_callsign TEXT,
            assigned_asset_callsign TEXT, assigned_pilot_user_id INTEGER, assigned_at TEXT,
            acknowledged_at TEXT, arrived_at TEXT, completed_at TEXT, cancelled_at TEXT, requested_at TEXT)');
        $pdo->exec('CREATE TABLE atak_medevac_patients (
            id INTEGER PRIMARY KEY AUTOINCREMENT, medevac_request_id INTEGER NOT NULL,
            patient_name TEXT, patient_callsign TEXT, patient_unit TEXT, patient_steam_id TEXT,
            triage_category TEXT, triaged_by_callsign TEXT, consciousness TEXT, breathing TEXT, circulation TEXT,
            injuries TEXT, primary_injury TEXT, treatments_given TEXT, medications_given TEXT,
            is_stabilized INTEGER, requires_litter INTEGER, can_walk INTEGER)');
        $pdo->exec("CREATE VIEW v_atak_active_medevac AS
            SELECT m.*,
                   requester_u.email AS requested_by_username,
                   pilot_u.email AS assigned_pilot_username,
                   (SELECT COUNT(*) FROM atak_medevac_patients WHERE medevac_request_id = m.id) AS actual_patient_count,
                   (SELECT COUNT(*) FROM atak_medevac_patients WHERE medevac_request_id = m.id AND is_stabilized = 1) AS stabilized_patient_count
            FROM atak_medevac_requests m
            LEFT JOIN users requester_u ON m.requested_by_user_id = requester_u.id
            LEFT JOIN users pilot_u ON m.assigned_pilot_user_id = pilot_u.id
            WHERE m.status NOT IN ('COMPLETED', 'CANCELLED')");
        $pdo->exec('CREATE TABLE atak_qrf_requests (
            id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, context_id INTEGER NOT NULL,
            qrf_number TEXT, status TEXT DEFAULT \'REQUESTED\', requesting_unit TEXT,
            assigned_qrf_unit TEXT, assigned_qrf_callsign TEXT, assigned_qrf_leader_user_id INTEGER, assigned_at TEXT,
            qrf_current_pos_x REAL, qrf_current_pos_y REAL, qrf_eta TEXT)');
        $pdo->exec('CREATE TABLE atak_qrf_sitrep_updates (
            id INTEGER PRIMARY KEY AUTOINCREMENT, qrf_request_id INTEGER NOT NULL, update_type TEXT, update_message TEXT,
            pos_x REAL, pos_y REAL, updated_by_callsign TEXT, updated_by_user_id INTEGER, is_from_qrf INTEGER,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $pdo->exec('CREATE TABLE atak_vehicle_tracking (
            id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, context_id INTEGER NOT NULL,
            vehicle_callsign TEXT, status TEXT DEFAULT \'ACTIVE\')');
        $pdo->exec('CREATE TABLE atak_vehicle_service_requests (
            id INTEGER PRIMARY KEY AUTOINCREMENT, vehicle_tracking_id INTEGER NOT NULL, request_type TEXT, priority TEXT,
            request_details TEXT, requested_by_callsign TEXT, service_pos_x REAL, service_pos_y REAL,
            status TEXT DEFAULT \'REQUESTED\', requested_at TEXT DEFAULT CURRENT_TIMESTAMP)');

        $pdo->exec("INSERT INTO users (id, email, callsign, display_name) VALUES
            (30, 'medic3@example.test', 'DOC 3', 'Médecin trois'),
            (31, 'obs3@example.test', 'OBS 3', 'Observateur trois'),
            (70, 'bravo7@example.test', 'BRAVO 7', 'Chef de groupe sept')");
        $pdo->exec("INSERT INTO atak_poi (id, tenant_id, context_id, poi_name, category, status, description, reported_by_user_id, created_by_user_id) VALUES
            (31, 3, 1, 'Dépôt de munitions', 'CACHE', 'ACTIVE', 'Repéré par la 3', 31, 31),
            (71, 7, 1, 'Point de contrôle', 'CHECKPOINT', 'ACTIVE', 'Repéré par la 7', 70, 70)");
        $pdo->exec("INSERT INTO atak_medevac_requests (id, tenant_id, context_id, medevac_number, priority, pickup_grid, radio_frequency, requested_by_user_id, requested_at) VALUES
            (32, 3, 1, 'MEDEVAC-3', 'URGENT', '031 045', '45.500', 30, '2026-10-26 10:00:00'),
            (72, 7, 1, 'MEDEVAC-7', 'URGENT', '071 089', '47.250', 70, '2026-10-26 10:05:00')");
        $pdo->exec("INSERT INTO atak_medevac_patients (medevac_request_id, patient_name, patient_steam_id, triage_category) VALUES
            (32, 'Patient Trois', '76561190000000003', 'T1'),
            (72, 'Patient Sept', '76561190000000077', 'T2')");
        $pdo->exec("INSERT INTO atak_qrf_requests (id, tenant_id, context_id, qrf_number, requesting_unit) VALUES
            (33, 3, 1, 'QRF-3', 'Section trois'),
            (73, 7, 1, 'QRF-7', 'Section sept')");
        $pdo->exec("INSERT INTO atak_vehicle_tracking (id, tenant_id, context_id, vehicle_callsign) VALUES
            (34, 3, 1, 'HUNTER 3'),
            (74, 7, 1, 'HUNTER 7')");

        return $pdo;
    }
}
