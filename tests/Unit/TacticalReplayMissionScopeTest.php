<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Admin\AdminAarReportsController;
use App\Controllers\Api\OperationsApiController;
use App\Controllers\Api\ReplayController;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AssetLogisticsRepository;
use App\Repositories\AtakDataRepository;
use App\Services\Intel\IntelFusionService;
use App\Services\Logistics\AssetLogisticsEvaluator;
use App\Services\Replay\ReplayService;
use App\Support\ComspecApiKeyAuth;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * SEC-008 : relecture (/api/replay/*) et contrats /api/operations/* dont la mission vient de l’URL.
 * Un appelant de la communauté 7 (clé communauté ou session jeu, ou session web) ne relit pas la
 * mission d’une autre communauté : refus 403 avant toute lecture (positions, pertes, ordres, intel,
 * alertes médicales, logistique). Sa propre mission reste servie telle quelle.
 */
final class TacticalReplayMissionScopeTest extends TestCase
{
    private const OWN = 'mission_7_map_2';

    private const FOREIGN = 'mission_3_map_1';

    /** @var list<array{0: string, 1: string}> Lectures faites par les contrôleurs : [méthode, mission ou communauté:carte]. */
    private array $reads = [];

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
        // Un test précédent peut avoir retiré $_POST (unset) : Request le copie dans une propriété typée array.
        $this->hadPost = isset($_POST);
        $this->savedPost = $_POST ?? null;
        $_SESSION = [];
        $_GET = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/replay/mission/test';
        unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['HTTP_X_COMSPEC_KEY'], $_SERVER['HTTP_X_ATAK_TOKEN']);
        ComspecApiKeyAuth::resetForTests();
        $this->reads = [];
    }

    protected function tearDown(): void
    {
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

    /** @return array<string, array{0: string, 1: string}> */
    public static function missionRoutes(): array
    {
        return [
            'replay mission' => ['replay', 'mission'],
            'replay events' => ['replay', 'events'],
            'replay sessions' => ['replay', 'sessions'],
            'replay aar' => ['replay', 'aar'],
            'replay aar pdf' => ['replay', 'aarExportPdf'],
            'operations missions' => ['operations', 'missionsTimeline'],
            'operations missions events' => ['operations', 'missionsEvents'],
            'operations aar' => ['operations', 'aar'],
            'operations aar et retex json' => ['operations', 'aarExportJson'],
            'operations aar et retex pdf' => ['operations', 'aarExportPdf'],
            'operations readiness' => ['operations', 'readiness'],
            'operations medical' => ['operations', 'medical'],
            'operations comms' => ['operations', 'comms'],
        ];
    }

    /** @return array<string, array{0: string, 1: string, 2: string, 3: string}> */
    public static function foreignMissionCases(): array
    {
        $cases = [];
        foreach (self::missionRoutes() as $label => [$controller, $action]) {
            foreach (['clé communauté' => 'cle', 'session web' => 'session'] as $modeLabel => $mode) {
                // mission_70_map_1 : préfixe proche (communauté 70), ne doit pas passer pour la 7.
                foreach ([self::FOREIGN, 'mission_70_map_1'] as $foreign) {
                    $cases[$label . ', ' . $modeLabel . ', ' . $foreign] = [$controller, $action, $mode, $foreign];
                }
            }
        }

        return $cases;
    }

    /** Routes JSON (le PDF demanderait le moteur TCPDF, absent des tests unitaires). @return array<string, array{0: string, 1: string, 2: string}> */
    public static function ownMissionCases(): array
    {
        $cases = [];
        foreach (self::missionRoutes() as $label => [$controller, $action]) {
            if ($action === 'aarExportPdf') {
                continue;
            }
            foreach (['clé communauté' => 'cle', 'session web' => 'session'] as $modeLabel => $mode) {
                $cases[$label . ', ' . $modeLabel] = [$controller, $action, $mode];
            }
        }

        return $cases;
    }

    #[DataProvider('foreignMissionCases')]
    public function testForeignMissionIsRefusedBeforeAnyRead(string $controller, string $action, string $mode, string $missionId): void
    {
        $this->asCommunity7($mode);

        $response = $this->call($controller, $action, ['missionId' => $missionId]);

        self::assertSame([], $this->reads, 'aucune lecture pour la mission d’une autre communauté');
        self::assertSame(403, $response->statusCode());
        self::assertSame($controller === 'replay' ? 'mission_forbidden' : 'operations.mission_forbidden', $this->errorCode($response));
    }

    #[DataProvider('ownMissionCases')]
    public function testOwnMissionIsStillServed(string $controller, string $action, string $mode): void
    {
        $this->asCommunity7($mode);

        $response = $this->call($controller, $action, ['missionId' => self::OWN]);

        self::assertSame(200, $response->statusCode());
        self::assertNotEmpty($this->reads);
        foreach ($this->reads as [$method, $scope]) {
            self::assertContains($scope, [self::OWN, '7:2'], $method . ' lit la mission de l’appelant');
        }
    }

    /** Ni clé communauté ou session jeu, ni session web (clé plateforme seule, poste de dev ouvert) : rien n’est relu. */
    #[DataProvider('missionRoutes')]
    public function testWithoutCommunityNothingIsRead(string $controller, string $action): void
    {
        $response = $this->call($controller, $action, ['missionId' => self::FOREIGN]);

        self::assertSame([], $this->reads);
        self::assertSame(403, $response->statusCode());
        self::assertSame('tenant_context_required', $this->errorCode($response));
    }

    /** Contrat inchangé : missionId absent → 400 propre à la route, avant toute question de communauté. */
    #[DataProvider('missionRoutes')]
    public function testMissingMissionIdKeepsTheRouteError(string $controller, string $action): void
    {
        $response = $this->call($controller, $action, []);

        self::assertSame([], $this->reads);
        self::assertSame(400, $response->statusCode());
    }

    public function testQueryStringMissionIdIsScopedToo(): void
    {
        $this->asCommunity7('session');

        $_GET = ['missionId' => self::FOREIGN];
        $replay = $this->call('replay', 'mission', []);
        $_GET = ['mission_id' => self::FOREIGN];
        $operations = $this->call('operations', 'aar', []);

        self::assertSame([], $this->reads);
        self::assertSame(403, $replay->statusCode());
        self::assertSame(403, $operations->statusCode());
    }

    public function testNonScalarMissionIdIsRejectedInsteadOfReaching500(): void
    {
        $this->asCommunity7('session');

        $_GET = ['missionId' => [self::OWN]];
        $replay = $this->call('replay', 'events', []);
        $_GET = ['mission_id' => [self::OWN]];
        $operations = $this->call('operations', 'comms', []);

        self::assertSame([], $this->reads);
        self::assertSame(422, $replay->statusCode());
        self::assertSame(422, $operations->statusCode());
    }

    /**
     * Préalable : en production, le garde tactique protège bien ces chemins, mais une session web de la
     * communauté 7 le franchit quelle que soit la mission de l’URL. Le cloisonnement revient au contrôleur.
     */
    public function testTacticalGateLetsCommunity7ThroughWhateverTheMission(): void
    {
        $cfg = require dirname(__DIR__, 2) . '/config/tactical_api.php';
        // Sans communauté par défaut : un refus n’écrit aucun journal (pas de base en test).
        $env = $this->overrideEnv([
            'APP_ENV' => 'production',
            'TACTICAL_API_ALLOW_SESSION' => 'true',
            'ATAK_DEFAULT_TENANT_ID' => '',
            'APP_ATAK_DEFAULT_TENANT_ID' => '',
        ]);
        $started = new \ReflectionProperty(Session::class, 'started');
        $wasStarted = (bool) $started->getValue();
        $started->setValue(null, true);
        (new \ReflectionProperty(ComspecApiKeyAuth::class, 'tacticalConfigCache'))->setValue(null, $cfg);
        $this->asCommunity7('session');
        try {
            foreach (['/api/replay/mission/', '/api/replay/aar/', '/api/operations/medical/'] as $prefix) {
                self::assertTrue(ComspecApiKeyAuth::pathRequiresProtection($prefix . self::FOREIGN, $cfg));
                self::assertNull(ComspecApiKeyAuth::enforceForTacticalPath($prefix . self::FOREIGN));
            }
            $_SESSION = [];
            self::assertSame(401, ComspecApiKeyAuth::enforceForTacticalPath('/api/replay/mission/' . self::FOREIGN)?->statusCode());
        } finally {
            $started->setValue(null, $wasStarted);
            $this->restoreEnv($env);
        }
    }

    /**
     * Lien « Synthèse de mission (PDF) » du compte rendu (back-office) : il portait l’id du cycle (42), que la
     * route refuse désormais ; il pointe vers le bac de relecture de la mission liée, servi à sa communauté.
     */
    public function testAarReportPdfLinkTargetsTheMissionReplayBucket(): void
    {
        $url = (string) AdminAarReportsController::missionAarPdfUrl([
            'id' => 42,
            'replay_mission_id' => self::OWN,
            'window' => ['from' => '2026-10-01 20:00:00', 'to' => '2026-10-01 23:30:00'],
        ]);

        self::assertStringEndsWith('/api/operations/aar/mission_7_map_2/export.pdf?from=2026-10-01+20%3A00%3A00&to=2026-10-01+23%3A30%3A00', $url);
        self::assertNull(AdminAarReportsController::missionAarPdfUrl(null));
        self::assertNull(AdminAarReportsController::missionAarPdfUrl(['id' => 42, 'replay_mission_id' => '']));

        $this->asCommunity7('session');
        self::assertSame(1, preg_match('#/api/operations/aar/([^/?]+)/export\.pdf#', $url, $m));
        self::assertSame(200, $this->call('operations', 'aar', ['missionId' => rawurldecode($m[1])])->statusCode());
        self::assertSame(403, $this->call('operations', 'aar', ['missionId' => '42'])->statusCode());
    }

    /**
     * @param array<string, string> $values
     * @return array<string, array{0: bool, 1: mixed, 2: string|false}>
     */
    private function overrideEnv(array $values): array
    {
        $saved = [];
        foreach ($values as $key => $value) {
            $saved[$key] = [array_key_exists($key, $_ENV), $_ENV[$key] ?? null, getenv($key)];
            $_ENV[$key] = $value;
            putenv($key . '=' . $value);
        }

        return $saved;
    }

    /** @param array<string, array{0: bool, 1: mixed, 2: string|false}> $saved */
    private function restoreEnv(array $saved): void
    {
        foreach ($saved as $key => [$inEnv, $value, $getenv]) {
            if ($inEnv) {
                $_ENV[$key] = $value;
            } else {
                unset($_ENV[$key]);
            }
            putenv($getenv === false ? $key : $key . '=' . $getenv);
        }
    }

    /** Clé communauté (ou session jeu) de la communauté 7, ou navigateur connecté à la communauté 7. */
    private function asCommunity7(string $mode): void
    {
        if ($mode === 'cle') {
            (new \ReflectionProperty(ComspecApiKeyAuth::class, 'matchedTenantId'))->setValue(null, 7);

            return;
        }
        $_SESSION = ['user_id' => 70, 'tenant_id' => 7];
    }

    /** @param array<string, string> $params paramètres de route */
    private function call(string $controller, string $action, array $params): Response
    {
        $target = $controller === 'replay' ? new ReplayController($this->replayService()) : $this->operationsController();

        return $target->$action(new Request(), $params);
    }

    private function replayService(): ReplayService
    {
        $replay = $this->createMock(ReplayService::class);
        $replay->method('getTimeline')->willReturnCallback(
            fn (string $missionId, mixed ...$rest): array => $this->read('getTimeline', $missionId, ['missionId' => $missionId, 'timeline' => [['timestamp' => '2026-10-01 20:00:00', 'units' => []]]])
        );
        $replay->method('getEvents')->willReturnCallback(
            fn (string $missionId, mixed ...$rest): array => $this->read('getEvents', $missionId, ['missionId' => $missionId, 'events' => [['source' => 'ALPHA-1']]])
        );
        $replay->method('getSessions')->willReturnCallback(
            fn (string $missionId, mixed ...$rest): array => $this->read('getSessions', $missionId, ['missionId' => $missionId, 'sessions' => []])
        );
        $replay->method('buildAfterActionReview')->willReturnCallback(
            fn (string $missionId, mixed ...$rest): array => $this->read('buildAfterActionReview', $missionId, ['summary' => ['positions' => 1], 'errors' => []])
        );

        return $replay;
    }

    private function operationsController(): OperationsApiController
    {
        $logistics = $this->createMock(AssetLogisticsRepository::class);
        $logistics->method('listByMission')->willReturnCallback(
            fn (string $missionId): array => $this->read('listByMission', $missionId, [])
        );
        $atak = $this->createMock(AtakDataRepository::class);
        $atak->method('getMedicalAlertsFromChat')->willReturnCallback(
            fn (int $tenantId, int $mapId, mixed ...$rest): array => $this->read('getMedicalAlertsFromChat', $tenantId . ':' . $mapId, [])
        );
        $atak->method('getUnitsWithCriticalHealth')->willReturnCallback(
            fn (int $tenantId, int $mapId, mixed ...$rest): array => $this->read('getUnitsWithCriticalHealth', $tenantId . ':' . $mapId, [])
        );

        return new OperationsApiController(
            $this->replayService(),
            $this->createMock(IntelFusionService::class),
            $logistics,
            $this->createMock(AssetLogisticsEvaluator::class),
            $atak,
        );
    }

    /**
     * @param array<mixed> $result
     * @return array<mixed>
     */
    private function read(string $method, string $scope, array $result): array
    {
        $this->reads[] = [$method, $scope];

        return $result;
    }

    private function errorCode(Response $response): string
    {
        $payload = json_decode($response->body(), true);
        $error = is_array($payload) ? ($payload['error'] ?? null) : null;

        return (string) (is_array($error) ? ($error['code'] ?? '') : ($error ?? ''));
    }
}
