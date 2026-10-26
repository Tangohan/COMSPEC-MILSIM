<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Api\AtakApiController;
use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\MapShapeRepository;
use App\Services\Platform\FeatureGateService;
use App\Support\ComspecApiKeyAuth;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * GET /api/map-shapes : un paramètre mission_id (ou missionId) envoyé en tableau (?mission_id[]=…) atteignait
 * MapShapeRepository::list(), qui attend une chaîne, et la TypeError finissait en erreur 500. Il est refusé (422,
 * même enveloppe que les autres routes C2) avant toute lecture. Un since en tableau est ignoré (liste complète,
 * comme pour les ordres) au lieu de la même TypeError. Les valeurs en chaîne arrivent au dépôt sans changement.
 */
final class AtakMapShapesQueryTest extends TestCase
{
    private const CALLER = 7;

    /** Communauté par défaut lue par resolveTenantId() en dernier recours : absente pendant le test. */
    private const ENV_KEYS = ['ATAK_DEFAULT_TENANT_ID', 'APP_ATAK_DEFAULT_TENANT_ID'];

    /** @var list<list<mixed>> Appels reçus par MapShapeRepository::list(). */
    private array $listCalls = [];

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

    protected function setUp(): void
    {
        parent::setUp();
        $this->hadSession = isset($_SESSION);
        $this->savedSession = $_SESSION ?? null;
        $this->savedGet = $_GET;
        $this->savedServer = $_SERVER;
        $_SESSION = [];
        $_GET = [];
        unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['HTTP_X_COMSPEC_KEY'], $_SERVER['HTTP_X_ATAK_TOKEN']);
        ComspecApiKeyAuth::resetForTests();

        foreach (self::ENV_KEYS as $key) {
            $this->savedEnv[$key] = [
                'in_env' => array_key_exists($key, $_ENV),
                'env' => $_ENV[$key] ?? null,
                'getenv' => getenv($key),
            ];
            unset($_ENV[$key]);
            putenv($key);
        }

        // Formule : la carte est ouverte à la communauté de l’appelant.
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
    }

    protected function tearDown(): void
    {
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
        parent::tearDown();
    }

    /** @return array<string, array{0: array<string, mixed>}> */
    public static function nonScalarMissionIds(): array
    {
        return [
            'mission_id[]=…' => [['mission_id' => ['mission_7_map_1']]],
            'missionId[]=…' => [['missionId' => ['mission_7_map_1']]],
            'mission_id[a]=…' => [['mission_id' => ['a' => 'mission_7_map_1']]],
            'mission_id[]=… avec since' => [['mission_id' => ['mission_7_map_1'], 'since' => '2026-10-01 00:00:00']],
        ];
    }

    /** @param array<string, mixed> $query */
    #[DataProvider('nonScalarMissionIds')]
    public function testNonScalarMissionIdIsRefusedInsteadOfAServerError(array $query): void
    {
        $this->actAsCaller();

        $response = $this->index($query);

        self::assertSame(422, $response->statusCode());
        self::assertSame(
            ['error' => 'invalid_mission_id', 'message' => 'Identifiant de mission invalide.'],
            self::json($response)
        );
        self::assertSame([], $this->listCalls);
    }

    public function testNonScalarSinceIsIgnoredInsteadOfAServerError(): void
    {
        $this->actAsCaller();

        $response = $this->index(['mapId' => '2', 'mission_id' => 'mission_7_map_2', 'since' => ['2026-10-01']]);

        self::assertSame(200, $response->statusCode());
        self::assertSame([[self::CALLER, 2, 'mission_7_map_2', null]], $this->listCalls);
        self::assertSame([['id' => 5, 'shape_uid' => 'shape_a']], self::json($response));
    }

    /** @return array<string, array{0: array<string, mixed>, 1: list<mixed>}> */
    public static function stringQueries(): array
    {
        return [
            'sans paramètre' => [[], [self::CALLER, 1, null, null]],
            'mapId, mission_id et since' => [
                ['mapId' => '2', 'mission_id' => 'mission_7_map_2', 'since' => '2026-10-01 00:00:00'],
                [self::CALLER, 2, 'mission_7_map_2', '2026-10-01 00:00:00'],
            ],
            'alias missionId' => [['missionId' => 'mission_7_map_3'], [self::CALLER, 1, 'mission_7_map_3', null]],
            'chaînes vides' => [['mission_id' => '', 'since' => ''], [self::CALLER, 1, '', '']],
        ];
    }

    /**
     * Les appelants actuels (DLL GetMapShapes : mapId et since ; pages web : mapId) ne changent pas de réponse.
     *
     * @param array<string, mixed> $query
     * @param list<mixed> $expectedCall
     */
    #[DataProvider('stringQueries')]
    public function testStringParametersReachTheRepositoryUnchanged(array $query, array $expectedCall): void
    {
        $this->actAsCaller();

        $response = $this->index($query);

        self::assertSame(200, $response->statusCode());
        self::assertSame([$expectedCall], $this->listCalls);
        self::assertSame([['id' => 5, 'shape_uid' => 'shape_a']], self::json($response));
    }

    /** Sans communauté identifiée, le refus 403 reste le premier contrôle, quel que soit le paramètre. */
    public function testCallerWithoutCommunityIsStillRefusedFirst(): void
    {
        $response = $this->index(['mission_id' => ['mission_7_map_1']]);

        self::assertSame(403, $response->statusCode());
        self::assertSame('tenant_context_required', self::json($response)['error'] ?? null);
        self::assertSame([], $this->listCalls);
    }

    private function actAsCaller(): void
    {
        (new \ReflectionProperty(ComspecApiKeyAuth::class, 'matchedTenantId'))->setValue(null, self::CALLER);
    }

    /** @param array<string, mixed> $query */
    private function index(array $query): Response
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/map-shapes';
        $_GET = $query;
        $controller = (new \ReflectionClass(AtakApiController::class))->newInstanceWithoutConstructor();
        $repo = $this->createMock(MapShapeRepository::class);
        $repo->method('list')->willReturnCallback(function (mixed ...$args): array {
            $this->listCalls[] = $args;

            return [['id' => 5, 'shape_uid' => 'shape_a']];
        });
        (new \ReflectionProperty(AtakApiController::class, 'mapShapeRepo'))->setValue($controller, $repo);
        (new \ReflectionProperty(AtakApiController::class, 'jsonBodyCache'))->setValue($controller, []);

        return $controller->mapShapesIndex(new Request(), []);
    }

    /** @return array<mixed> */
    private static function json(Response $response): array
    {
        $decoded = json_decode($response->body(), true);

        return is_array($decoded) ? $decoded : [];
    }
}
