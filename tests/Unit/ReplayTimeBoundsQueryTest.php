<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Api\ReplayController;
use App\Core\Request;
use App\Core\Response;
use App\Services\Replay\ReplayService;
use App\Support\ComspecApiKeyAuth;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * /api/replay/mission, /events, /aar et /aar/export.pdf : une borne from ou to envoyée en tableau (?from[]=…)
 * arrivait telle quelle dans ReplayService, qui attend une chaîne, et la TypeError finissait en erreur 500.
 * Elle est ignorée (relecture sans cette borne), comme une borne absente. Les bornes en chaîne arrivent au
 * service sans changement. Appelant : clé de la communauté 7, sur une mission de sa communauté.
 */
final class ReplayTimeBoundsQueryTest extends TestCase
{
    private const OWN = 'mission_7_map_2';

    private const STOP_BEFORE_PDF = 'arrêt avant le rendu du PDF';

    /** @var list<array{0: string, 1: list<mixed>}> Appels reçus par ReplayService : [méthode, arguments]. */
    private array $calls = [];

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
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/replay/mission/' . self::OWN;
        unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['HTTP_X_COMSPEC_KEY'], $_SERVER['HTTP_X_ATAK_TOKEN']);
        ComspecApiKeyAuth::resetForTests();
        (new \ReflectionProperty(ComspecApiKeyAuth::class, 'matchedTenantId'))->setValue(null, 7);
        $this->calls = [];
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
    public static function jsonRoutes(): array
    {
        return [
            'mission' => ['mission', 'getTimeline'],
            'events' => ['events', 'getEvents'],
            'aar' => ['aar', 'buildAfterActionReview'],
        ];
    }

    /** @return array<string, array{0: string, 1: string, 2: array<string, mixed>, 3: ?string, 4: ?string}> */
    public static function nonScalarBounds(): array
    {
        $cases = [];
        foreach (self::jsonRoutes() as $label => [$action, $method]) {
            $cases[$label . ', from[]'] = [$action, $method, ['from' => ['2026-10-01 20:00:00']], null, null];
            $cases[$label . ', to[]'] = [$action, $method, ['to' => ['2026-10-01 22:00:00']], null, null];
            $cases[$label . ', from[] et to en chaîne'] = [
                $action,
                $method,
                ['from' => ['x' => '1'], 'to' => '2026-10-01 22:00:00'],
                null,
                '2026-10-01 22:00:00',
            ];
        }

        return $cases;
    }

    /** @param array<string, mixed> $query */
    #[DataProvider('nonScalarBounds')]
    public function testNonScalarBoundIsIgnoredInsteadOfAServerError(
        string $action,
        string $method,
        array $query,
        ?string $expectedFrom,
        ?string $expectedTo
    ): void {
        $_GET = $query;

        $response = $this->call($action);

        self::assertSame(200, $response->statusCode());
        self::assertSame([[$method, [self::OWN, $expectedFrom, $expectedTo, '']]], $this->calls);
    }

    /** Export PDF : le rapport est construit sans la borne refusée (le rendu TCPDF n’est pas joué ici). */
    public function testNonScalarBoundOnPdfExportReachesTheReportWithoutIt(): void
    {
        $_GET = ['from' => ['2026-10-01 20:00:00'], 'to' => ['2026-10-01 22:00:00']];

        try {
            $this->call('aarExportPdf', true);
            self::fail('Le rapport aurait dû être demandé au service.');
        } catch (\RuntimeException $e) {
            self::assertSame(self::STOP_BEFORE_PDF, $e->getMessage());
        }
        self::assertSame([['buildAfterActionReview', [self::OWN, null, null, '']]], $this->calls);
    }

    /** @return array<string, array{0: string, 1: string, 2: array<string, mixed>, 3: ?string, 4: ?string}> */
    public static function stringBounds(): array
    {
        $cases = [];
        foreach (self::jsonRoutes() as $label => [$action, $method]) {
            $cases[$label . ', sans borne'] = [$action, $method, [], null, null];
            $cases[$label . ', from et to'] = [
                $action,
                $method,
                ['from' => '2026-10-01 20:00:00', 'to' => '2026-10-01 22:00:00'],
                '2026-10-01 20:00:00',
                '2026-10-01 22:00:00',
            ];
            $cases[$label . ', bornes vides'] = [$action, $method, ['from' => '', 'to' => ''], null, null];
        }

        return $cases;
    }

    /**
     * Les clients actuels (pages de relecture et d’AAR) ne changent pas de réponse.
     *
     * @param array<string, mixed> $query
     */
    #[DataProvider('stringBounds')]
    public function testStringBoundsReachTheServiceUnchanged(
        string $action,
        string $method,
        array $query,
        ?string $expectedFrom,
        ?string $expectedTo
    ): void {
        $_GET = $query;

        $response = $this->call($action);

        self::assertSame(200, $response->statusCode());
        self::assertSame([[$method, [self::OWN, $expectedFrom, $expectedTo, '']]], $this->calls);
    }

    private function call(string $action, bool $stopBeforePdf = false): Response
    {
        $replay = $this->createMock(ReplayService::class);
        foreach (['getTimeline', 'getEvents', 'buildAfterActionReview'] as $method) {
            $replay->method($method)->willReturnCallback(
                function (mixed ...$args) use ($method, $stopBeforePdf): array {
                    $this->calls[] = [$method, $args];
                    if ($stopBeforePdf) {
                        throw new \RuntimeException(self::STOP_BEFORE_PDF);
                    }

                    return ['missionId' => self::OWN, 'events' => []];
                }
            );
        }

        return (new ReplayController($replay))->$action(new Request(), ['missionId' => self::OWN]);
    }
}
