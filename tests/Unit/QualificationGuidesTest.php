<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\DocumentationController;
use App\Core\Request;
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Support\QualificationCriteriaCatalog;
use PHPUnit\Framework\TestCase;

/**
 * Guides des qualifications (/documentation/qualifications) : trois guides en français et en anglais
 * (docs/guides/*.md), routés, servis dans la langue du lecteur, exhaustifs sur les critères mesurés et attestés, et
 * reliés depuis le référentiel, le catalogue de référence, l’assistant d’importation et le Studio Academy.
 */
final class QualificationGuidesTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    public function testEachGuideExistsInFrenchAndEnglishAndIsServedInTheReaderLanguage(): void
    {
        self::assertSame(['en-jeu', 'prerequis-automatiques', 'prerequis-attestes'], array_keys(DocumentationController::QUALIFICATION_GUIDES));
        foreach (DocumentationController::QUALIFICATION_GUIDES as $slug => $guide) {
            $fr = self::ROOT . '/docs/' . $guide['file'] . '.md';
            $en = self::ROOT . '/docs/' . $guide['file'] . '.en.md';
            self::assertFileExists($fr, $slug);
            self::assertFileExists($en, $slug);
            self::assertSame(file_get_contents($fr), DocumentationController::qualificationGuideMarkdown($slug, 'fr'));
            self::assertSame(file_get_contents($en), DocumentationController::qualificationGuideMarkdown($slug, 'en'));
            self::assertStringStartsWith('# ', ltrim((string) file_get_contents($en)));
            self::assertStringContainsString('<table', markdown_to_html((string) file_get_contents($fr)), $slug . ' : tableaux rendus');
        }
        self::assertNull(DocumentationController::qualificationGuideMarkdown('inconnu', 'fr'));
        $en = (string) DocumentationController::qualificationGuideMarkdown('en-jeu', 'en');
        self::assertStringContainsString('no real-world value', $en);
        self::assertStringNotContainsString('sans valeur réelle', $en);
    }

    public function testAutomaticGuideListsEveryMeasuredCriterionAndManualGuideEveryAttestation(): void
    {
        $auto = (string) DocumentationController::qualificationGuideMarkdown('prerequis-automatiques', 'fr');
        foreach (QualificationCriteriaCatalog::METRICS as $key => $metric) {
            self::assertStringContainsString('| ' . $metric['label'] . ' |', $auto, 'critère mesuré absent du tableau : ' . $key);
        }
        self::assertStringContainsString('150 m', $auto, 'seuil de détection des sauts du mod');
        self::assertStringContainsString('toutes les heures', $auto);
        $manual = (string) DocumentationController::qualificationGuideMarkdown('prerequis-attestes', 'fr');
        foreach (QualificationCriteriaCatalog::ATTESTATIONS as $key => $attestation) {
            self::assertStringContainsString($attestation['label'], $manual, 'attestation absente : ' . $key);
        }
        $inGame = (string) DocumentationController::qualificationGuideMarkdown('en-jeu', 'fr');
        foreach (['Brevet parachutiste', 'JTAC', 'AH-64', 'Tireur d’élite', 'Stand de tir', 'CQB', 'MEDEVAC', 'Renouvellement'] as $topic) {
            self::assertStringContainsString($topic, $inGame);
        }
    }

    public function testGuidePagesAreRoutedBehindLoginAndRenderTheirMarkdown(): void
    {
        $server = $_SERVER;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        $router = new Router(new Request());
        (require self::ROOT . '/routes/web.php')($router);
        $routes = (new \ReflectionProperty(Router::class, 'routes'))->getValue($router);
        $_SERVER = $server;
        $found = [];
        foreach (['/documentation/qualifications' => 'qualificationGuides', '/documentation/qualifications/prerequis-attestes' => 'qualificationGuide'] as $path => $method) {
            foreach ($routes as $route) {
                if ($route['method'] === 'GET' && preg_match($route['pattern'], $path)) {
                    self::assertSame([DocumentationController::class, $method], $route['handler'], $path);
                    self::assertContains(AuthMiddleware::class, $route['middleware'], $path);
                    $found[] = $path;
                    break;
                }
            }
        }
        self::assertCount(2, $found);

        $qualificationGuides = DocumentationController::QUALIFICATION_GUIDES;
        $guideSlug = 'prerequis-automatiques';
        $docBodyHtml = markdown_to_html((string) DocumentationController::qualificationGuideMarkdown($guideSlug, 'fr'));
        ob_start();
        require self::ROOT . '/views/documentation/qualifications/guide.php';
        $html = (string) ob_get_clean();
        self::assertStringContainsString('<h1>Prérequis automatiques possibles</h1>', $html);
        self::assertStringContainsString('Sauts en parachute', $html);
        self::assertStringContainsString('documentation/qualifications/prerequis-attestes', $html, 'navigation entre guides');
        self::assertStringContainsString('aria-current="page"', $html);
        ob_start();
        require self::ROOT . '/views/documentation/qualifications/index.php';
        $index = (string) ob_get_clean();
        foreach (array_keys($qualificationGuides) as $slug) {
            self::assertStringContainsString('documentation/qualifications/' . $slug, $index);
        }
    }

    /** « ? Comment ça marche » depuis le référentiel, le catalogue de référence, l’assistant d’importation et le Studio. */
    public function testHowItWorksLinksFromReferentialCatalogueImportWizardAndStudio(): void
    {
        foreach ([
            'views/admin/organization/qualifications/index.php',
            'views/admin/organization/qualifications/catalog.php',
            'views/academy/import_wizard.php',
            'views/academy/studio.php',
            'views/documentation/site/index.php',
        ] as $view) {
            $src = (string) file_get_contents(self::ROOT . '/' . $view);
            self::assertStringContainsString("url('documentation/qualifications')", $src, $view);
            self::assertStringContainsString('data-qualification-guides', $src, $view);
        }
        foreach (['fr' => 'Comment ça marche', 'en' => 'How it works'] as $lang => $text) {
            $dict = require self::ROOT . '/lang/' . $lang . '/academy.php';
            self::assertStringContainsString($text, (string) ($dict['studio_how'] ?? ''), $lang);
        }
    }
}
