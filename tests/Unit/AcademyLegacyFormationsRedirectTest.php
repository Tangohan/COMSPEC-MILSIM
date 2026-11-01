<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\TrainingCompetencyController;
use App\Controllers\Web\TrainingController;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\TrainingModuleSanctionMiddleware;
use PHPUnit\Framework\TestCase;

/**
 * L'ancien LMS « Formations » est servi par l'Academy : chaque page /formations/… redirige en 301
 * vers son équivalent (lien profond et paramètres conservés), les formulaires gardent leurs adresses.
 */
final class AcademyLegacyFormationsRedirectTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    /** @var list<array{method:string,path:string,pattern:string,handler:mixed,middleware:array}> */
    private static array $routes = [];

    private array $server;

    protected function setUp(): void
    {
        $this->server = $_SERVER;
        $_GET = $_POST = [];
        if (self::$routes === []) {
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SERVER['REQUEST_URI'] = '/';
            $router = new Router(new Request());
            (require self::ROOT . '/routes/web.php')($router);
            self::$routes = (new \ReflectionProperty(Router::class, 'routes'))->getValue($router);
        }
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        $_GET = [];
    }

    /** Première route qui répond, comme Router::dispatch(). */
    private function match(string $method, string $path): array
    {
        foreach (self::$routes as $route) {
            if ($route['method'] === $method && preg_match($route['pattern'], $path, $m)) {
                return [$route, array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY)];
            }
        }
        self::fail($method . ' ' . $path . ' : aucune route');
    }

    /** @return list<array{0:string,1:array<string,string>,2:string}> */
    public static function redirects(): array
    {
        return [
            ['/formations', [], '/academy/catalogue'],
            ['/formations', ['category' => 'tir'], '/academy/catalogue?category=tir'],
            ['/formations/mes-formations', ['tab' => 'done'], '/academy/courses?tab=done'],
            ['/formations/creer', [], '/academy/studio'],
            ['/formations/sessions', [], '/academy/formations/sessions'],
            ['/formations/competences', [], '/academy/formations/competences'],
            ['/formations/code-acces', ['code' => 'ABC'], '/academy/formations/code-acces?code=ABC'],
            ['/formations/page/guide-radio', [], '/academy/formations/page/guide-radio'],
            ['/formations/page/guide-radio/pdf', [], '/academy/formations/page/guide-radio/pdf'],
            ['/formations/bilan-module', ['course' => '4'], '/academy/formations/bilan-module?course=4'],
            ['/formations/lesson/42', [], '/academy/formations/lesson/42'],
            ['/formations/quiz/7', [], '/academy/formations/quiz/7'],
            ['/formations/attestation/a1b2c3', [], '/academy/formations/attestation/a1b2c3'],
            ['/formations/certificate/9', [], '/academy/formations/certificate/9'],
            ['/formations/tir-de-precision/echanges', [], '/academy/formations/tir-de-precision/echanges'],
            ['/formations/tir-de-precision', ['lesson' => '3'], '/academy/formations/tir-de-precision?lesson=3'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('redirects')]
    public function testOldAddressRedirectsPermanentlyToItsAcademyEquivalent(string $path, array $query, string $target): void
    {
        [$route, $params] = $this->match('GET', $path);
        self::assertInstanceOf(\Closure::class, $route['handler'], $path);
        $_GET = $query;
        $_SERVER['REQUEST_URI'] = $path;
        $response = ($route['handler'])(new Request(), $params);
        self::assertInstanceOf(Response::class, $response);
        self::assertSame(301, $response->statusCode(), $path);
        self::assertStringEndsWith($target, (string) $response->headerValue('Location'), $path);
    }

    public function testAcademyServesTheLegacyPagesWithTheSameControllersAndRights(): void
    {
        $expected = [
            '/academy/formations/tir-de-precision' => [TrainingController::class, 'showBySlug'],
            '/academy/formations/lesson/42' => [TrainingController::class, 'lesson'],
            '/academy/formations/quiz/7' => [TrainingController::class, 'quiz'],
            '/academy/formations/certificate/9' => [TrainingController::class, 'certificate'],
            '/academy/formations/competences' => [TrainingCompetencyController::class, 'userJourney'],
            '/academy/formations/page/guide-radio/pdf' => [TrainingController::class, 'formationCustomPageExportPdf'],
        ];
        foreach ($expected as $path => $handler) {
            [$route] = $this->match('GET', $path);
            self::assertSame($handler, $route['handler'], $path);
            self::assertSame([AuthMiddleware::class, TrainingModuleSanctionMiddleware::class], $route['middleware'], $path);
        }
        // Vérification publique des attestations (QR code imprimé sur les PDF) : toujours sans connexion.
        [$route] = $this->match('GET', '/academy/formations/attestation/a1b2c3');
        self::assertSame([TrainingController::class, 'certificatePublic'], $route['handler']);
        self::assertSame([TrainingModuleSanctionMiddleware::class], $route['middleware']);
    }

    public function testLegacyFormsStillPostToTheirExistingAddresses(): void
    {
        foreach (['/formations/enroll' => 'postEnroll', '/formations/quiz/start' => 'startQuiz', '/formations/code-acces' => 'accessCodeSubmit', '/formations/review' => 'postReview'] as $path => $method) {
            [$route] = $this->match('POST', $path);
            self::assertSame([TrainingController::class, $method], $route['handler'], $path);
        }
    }

    public function testAcademyPathsUseTheTrainingModuleRules(): void
    {
        self::assertSame('training', \App\Services\Community\TenantTypeConfig::moduleForUri('academy/formations/lesson/3'));
        self::assertSame('training', detect_current_module('/academy/formations/tir'));
        self::assertNull(\App\Services\Community\TenantTypeConfig::moduleForUri('academy'));
    }
}
