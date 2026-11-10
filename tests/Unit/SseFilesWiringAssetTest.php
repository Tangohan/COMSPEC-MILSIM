<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\SseFileController;
use PHPUnit\Framework\TestCase;

/**
 * Passage aux fichiers : routes du portail SSE, étape de migration inscrite, liens depuis le registre, API du jeu
 * branchée, écrans sans libellé au-dessus des titres, mention SIMULATION et textes traduits.
 */
final class SseFilesWiringAssetTest extends TestCase
{
    private static function read(string $rel): string
    {
        $path = dirname(__DIR__, 2) . '/' . $rel;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }

    public function testRoutesAreInTheSsePortal(): void
    {
        $routes = self::read('routes/web.php');
        foreach ([
            "get('/atak/sse/fichiers', [\\App\\Controllers\\Web\\SseFileController::class, 'index'], \$mwSsePortal)",
            "get('/atak/sse/fichiers/requete', [\\App\\Controllers\\Web\\SseFileController::class, 'query'], \$mwSsePortal)",
            "get('/atak/sse/fichiers/journal', [\\App\\Controllers\\Web\\SseFileController::class, 'journal'], \$mwSsePortal)",
            "post('/atak/sse/fichiers/mentions', [\\App\\Controllers\\Web\\SseFileController::class, 'mentionStore'], \$mwSsePortal)",
            "post('/atak/sse/fichiers/mentions/{id}/lever', [\\App\\Controllers\\Web\\SseFileController::class, 'mentionLift'], \$mwSsePortal)",
            "post('/atak/sse/fichiers/documents/{id}/signalement', [\\App\\Controllers\\Web\\SseFileController::class, 'documentReport'], \$mwSsePortal)",
        ] as $route) {
            self::assertStringContainsString($route, $routes);
        }
    }

    public function testMigrationStepIsRegistered(): void
    {
        self::assertStringContainsString("'file' => '4330_sse_file_queries.php'", self::read('database/steps/registry.php'));
        self::assertStringContainsString('sse_file_queries_migration.php', self::read('database/steps/4330_sse_file_queries.php'));
        $migration = self::read('bootstrap/sse_file_queries_migration.php');
        foreach (['sse_file_queries', 'sse_file_mentions', 'sse_seek_records', 'uq_sse_file_queries_key'] as $needle) {
            self::assertStringContainsString($needle, $migration);
        }
    }

    public function testRegistryLinksToTheFiles(): void
    {
        self::assertStringContainsString("'/atak/sse/fichiers', 'Passage aux fichiers'", self::read('views/atak/sse/papers/_subnav.php'));
        self::assertStringContainsString("SseFileController::replayUrl('QW'", self::read('views/atak/sse/papers/identity_show.php'));
        self::assertStringContainsString("SseFileController::replayUrl('QA'", self::read('views/atak/sse/papers/document_show.php'));
    }

    public function testGameApiDispatchesTheNewKindsAndTheLookup(): void
    {
        $api = self::read('app/Controllers/Api/SseIdentityApiController.php');
        self::assertStringContainsString('SseFileQueryService::KINDS', $api);
        self::assertStringContainsString('->gameLookup($tenantId, $q', $api);
        self::assertStringContainsString('return Response::json($this->lookup->lookup($tenantId, $q));', $api, 'repli sur le contrôle N3 seul');
    }

    public function testScreensHaveNoEyebrowAndShowSimulation(): void
    {
        foreach (['index', 'journal', 'mentions', 'seek', '_result', '_nav'] as $view) {
            $src = self::read('views/atak/sse/files/' . $view . '.php');
            self::assertStringNotContainsString('overline', $src, $view);
            self::assertStringNotContainsString('eyebrow', $src, $view);
        }
        self::assertStringContainsString('SIMULATION', self::read('views/atak/sse/files/_nav.php'));
        self::assertStringContainsString('ARMÉ ET DANGEREUX', self::read('views/atak/sse/files/_result.php'));
    }

    public function testVisibleTextsAreTranslated(): void
    {
        $en = self::read('lang/en/site_auto.php');
        foreach (['Passage aux fichiers', 'Journal des consultations', 'Mentions aux fichiers', 'Armé et dangereux', 'Interroger les fichiers'] as $fr) {
            self::assertStringContainsString("'" . $fr . "' =>", $en, $fr);
        }
    }

    public function testReplayAndBackUrlsStayInTheFiles(): void
    {
        self::assertStringContainsString('fichiers/requete?type=QW&last=Nowak', SseFileController::replayUrl('QW', ['last' => 'Nowak', 'first' => '']));
        self::assertStringEndsWith('atak/sse/fichiers', SseFileController::safeBack('https://evil.example/x'));
        self::assertStringEndsWith('atak/sse/fichiers/mentions?etat=toutes', SseFileController::safeBack('atak/sse/fichiers/mentions?etat=toutes'));
        self::assertSame('S1', SseFileController::replayCriteria(['bio_key' => 'S1'])['number']);
        self::assertFalse(SseFileController::hasCriteria('QA', ['last' => 'x', 'first' => '', 'number' => '']));
    }
}
