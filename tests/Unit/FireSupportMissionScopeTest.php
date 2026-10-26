<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Api\FireSupportController;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\FireUnitRepository;
use App\Services\FireSupport\BallisticCalculatorService;
use App\Support\ComspecApiKeyAuth;
use PHPUnit\Framework\TestCase;

/**
 * SEC-008 (balayage) : appui-feu (/api/fire-support/*) cloisonné comme les zones de danger. Un missionId
 * d’une autre communauté, vide ou numérique n’est jamais retenu : repli sur le bac de la communauté
 * appelante (le mod envoie encore mission_1_map_1 par défaut). Plus de repli sur la communauté 1.
 */
final class FireSupportMissionScopeTest extends TestCase
{
    /** @var list<string> Bacs de mission lus (listByMission / getByIdAndMission). */
    private array $reads = [];

    private bool $hadSession = false;

    private mixed $savedSession = null;

    /** @var array<string, mixed> */
    private array $savedGet = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->hadSession = isset($_SESSION);
        $this->savedSession = $_SESSION ?? null;
        $this->savedGet = $_GET;
        $_SESSION = [];
        $_GET = [];
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
        parent::tearDown();
    }

    public function testForeignMissionFallsBackToTheCallerBucket(): void
    {
        $this->communityKey(7);
        $_GET = ['missionId' => 'mission_3_map_1', 'mapId' => '2'];

        $response = $this->controller()->units(new Request(), []);

        self::assertSame(200, $response->statusCode());
        self::assertSame(['mission_7_map_2'], $this->reads);
    }

    /** Clé communauté sans session web ni missionId : bac de la communauté de la clé, plus celui de la communauté 1. */
    public function testDefaultBucketIsTheKeyCommunityNotCommunity1(): void
    {
        $this->communityKey(7);

        $this->controller()->units(new Request(), []);

        self::assertSame(['mission_7_map_1'], $this->reads);
    }

    public function testOwnMissionIsKept(): void
    {
        $_SESSION = ['user_id' => 70, 'tenant_id' => 7];
        $_GET = ['missionId' => 'mission_7_map_4'];

        $response = $this->controller()->units(new Request(), []);

        self::assertSame(200, $response->statusCode());
        self::assertSame(['mission_7_map_4'], $this->reads);
    }

    /** Calcul de tir : la batterie (fireUnitId) n’est cherchée que dans le bac de l’appelant. */
    public function testCalculateResolvesTheSameScopedBucket(): void
    {
        $this->communityKey(7);
        $method = new \ReflectionMethod(FireSupportController::class, 'missionId');

        self::assertSame('mission_7_map_1', $method->invoke($this->controller(), new Request(), ['missionId' => 'mission_3_map_1', 'fireUnitId' => 12]));
        self::assertSame('mission_7_map_1', $method->invoke($this->controller(), new Request(), ['missionId' => '42']));
    }

    /** Ni clé communauté ou session jeu, ni session web : refus au lieu du bac de la communauté 1. */
    public function testWithoutCommunityNothingIsReadOrComputed(): void
    {
        $_GET = ['missionId' => 'mission_3_map_1'];

        $units = $this->controller()->units(new Request(), []);
        $calculate = $this->controller()->calculate(new Request(), []);

        self::assertSame([], $this->reads);
        self::assertSame(403, $units->statusCode());
        self::assertSame(403, $calculate->statusCode());
        self::assertSame('tenant_context_required', json_decode($units->body(), true)['error'] ?? null);
    }

    public function testNonScalarMissionIdIsRejected(): void
    {
        $this->communityKey(7);
        $_GET = ['missionId' => ['mission_7_map_1']];

        $response = $this->controller()->units(new Request(), []);

        self::assertSame([], $this->reads);
        self::assertSame(422, $response->statusCode());
    }

    private function communityKey(int $tenantId): void
    {
        (new \ReflectionProperty(ComspecApiKeyAuth::class, 'matchedTenantId'))->setValue(null, $tenantId);
    }

    private function controller(): FireSupportController
    {
        $units = $this->createMock(FireUnitRepository::class);
        $units->method('listByMission')->willReturnCallback(function (string $missionId): array {
            $this->reads[] = $missionId;

            return [];
        });
        $units->method('getByIdAndMission')->willReturnCallback(function (int $id, string $missionId): ?array {
            $this->reads[] = $missionId;

            return null;
        });

        return new FireSupportController($units, $this->createMock(BallisticCalculatorService::class));
    }
}
