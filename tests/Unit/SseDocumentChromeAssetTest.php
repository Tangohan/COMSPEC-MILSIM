<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\SseDocumentChromeCatalog;
use PHPUnit\Framework\TestCase;

final class SseDocumentChromeAssetTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function testCatalogExposesPrefabsAndPaperStyles(): void
    {
        $styles = SseDocumentChromeCatalog::paperStyles();
        self::assertArrayHasKey('clean', $styles);
        self::assertArrayHasKey('stained', $styles);
        self::assertArrayHasKey('crumpled', $styles);
        self::assertArrayHasKey('aged', $styles);

        $prefabs = SseDocumentChromeCatalog::builtInPrefabs();
        self::assertGreaterThanOrEqual(4, count($prefabs));
        $codes = array_column($prefabs, 'code');
        self::assertContains('standard_restreint', $codes);
        self::assertContains('terrain_tache', $codes);
        self::assertContains('brouillon_froisse', $codes);

        $def = SseDocumentChromeCatalog::defaultChrome();
        self::assertStringContainsString('preuve', (string) ($def['footer'] ?? ''));
        self::assertSame('clean', $def['paper_style'] ?? null);
    }

    public function testPresentationPageAndPaperPartialAreWired(): void
    {
        $root = $this->root();
        $view = (string) file_get_contents($root . '/views/atak/sse/document_presentation.php');
        $paper = (string) file_get_contents($root . '/views/atak/sse/partials/document_paper.php');
        $layout = (string) file_get_contents($root . '/views/atak/sse/_layout.php');
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $css = (string) file_get_contents($root . '/public/assets/css/sse_portal.css');
        $controller = (string) file_get_contents($root . '/app/Controllers/Web/SsePortalController.php');

        self::assertFileExists($root . '/app/Support/SseDocumentChromeCatalog.php');
        self::assertFileExists($root . '/app/Repositories/SseDocumentPrefabRepository.php');
        self::assertFileExists($root . '/bootstrap/sse_document_prefabs_migration.php');

        self::assertStringContainsString('/atak/sse/presentation', $routes);
        self::assertStringContainsString('documentPresentationIndex', $controller);
        self::assertStringContainsString('Présentation des documents', $view);
        self::assertStringContainsString('Pied de page', $view);
        self::assertStringContainsString('Aspect du papier', $view);
        self::assertStringContainsString('atak/sse/presentation', $layout);
        self::assertStringContainsString('sse-doc-paper-style--', $paper);
        self::assertStringContainsString('footerDisclaimer', $paper);
        self::assertStringContainsString('sse-doc-paper-style--stained', $css);
        self::assertStringContainsString('sse-doc-paper-style--crumpled', $css);
        self::assertStringNotContainsString('endpoint', strtolower($view));
        self::assertStringNotContainsString('json', strtolower($view));
        self::assertStringNotContainsString('slug', strtolower($view));
    }

    public function testArmaSheetChromeIsDynamic(): void
    {
        $root = $this->root();
        $fill = (string) file_get_contents($root . '/mod/@COMSPEC_SSE/addons/ui/functions/fn_fillResultDialog.sqf');
        $chrome = (string) file_get_contents($root . '/mod/@COMSPEC_SSE/addons/ui/functions/fn_getDocumentChrome.sqf');
        $dialog = (string) file_get_contents($root . '/mod/@COMSPEC_SSE/addons/ui/dialogs/resultDialog.hpp');
        $module = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/modules/module_sse.hpp');
        $zen = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_registerZenSseModules.sqf');

        self::assertStringContainsString('getDocumentChrome', $fill);
        self::assertStringContainsString('footer', $fill);
        self::assertStringContainsString('terrain_tache', $chrome);
        self::assertStringContainsString('idc = 93019', $dialog);
        self::assertStringContainsString('Présentation des documents SSE', $module);
        self::assertStringContainsString('Présentation des documents', $zen);
        self::assertFileExists($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_moduleSseDocChrome.sqf');
    }
}
