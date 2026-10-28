<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Request;
use PHPUnit\Framework\TestCase;

/**
 * Lecteur d'images de reconnaissance : GET /recon/images/{id} (page), …/file reste le fichier brut sauf ouverture
 * dans le navigateur (redirigée vers le lecteur), mêmes droits que l'API, et vignettes branchées sur le lecteur.
 */
final class ReconImageViewerTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $server = [];

    protected function setUp(): void
    {
        $this->server = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
    }

    private function read(string $rel): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $rel);
    }

    /** @param array<string, string> $headers */
    private function request(array $headers): Request
    {
        $_SERVER = ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/recon/images/98/file'] + $headers;

        return new Request();
    }

    public function testBrowserNavigationDetection(): void
    {
        self::assertTrue($this->request(['HTTP_SEC_FETCH_DEST' => 'document'])->isBrowserNavigation());
        self::assertFalse($this->request(['HTTP_SEC_FETCH_DEST' => 'image', 'HTTP_ACCEPT' => 'text/html'])->isBrowserNavigation());
        self::assertFalse($this->request(['HTTP_SEC_FETCH_DEST' => 'empty'])->isBrowserNavigation());
        // Navigateur sans Sec-Fetch : Accept décide.
        self::assertTrue($this->request(['HTTP_ACCEPT' => 'text/html,application/xhtml+xml,*/*;q=0.8'])->isBrowserNavigation());
        self::assertFalse($this->request(['HTTP_ACCEPT' => 'image/avif,image/webp,*/*'])->isBrowserNavigation());
        // Jeu, curl, Discord : ni l'un ni l'autre.
        self::assertFalse($this->request([])->isBrowserNavigation());
        self::assertSame('document', $this->request(['HTTP_SEC_FETCH_DEST' => 'document'])->header('Sec-Fetch-Dest'));
        self::assertNull($this->request([])->header('Sec-Fetch-Dest'));
    }

    /** @param array<string, string> $headers */
    private function linkId(string $uri, array $headers, string $method = 'GET'): ?int
    {
        $_SERVER = ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri] + $headers;
        $request = new Request();

        return \App\Middleware\ComspecTacticalApiMiddleware::reconImageBrowserLinkId($request, $request->path());
    }

    public function testKeylessBrowserLinkGoesToViewerInsteadOfKeyError(): void
    {
        $doc = ['HTTP_SEC_FETCH_DEST' => 'document'];
        self::assertSame(98, $this->linkId('/api/recon/images/98/file', $doc));
        self::assertSame(98, $this->linkId('/api/recon/images/98/file?raw=1', $doc));
        self::assertSame(98, $this->linkId('/api/recon/images/98', $doc));
        // Navigateur intégré sans Sec-Fetch-Dest : Accept HTML n'importe où dans la liste.
        self::assertSame(98, $this->linkId('/api/recon/images/98/file', ['HTTP_ACCEPT' => 'application/xhtml+xml,text/html;q=0.9']));
        // <img>, fetch, jeu, POST et autres routes : le 401 de la clé reste.
        self::assertNull($this->linkId('/api/recon/images/98/file', ['HTTP_SEC_FETCH_DEST' => 'image', 'HTTP_ACCEPT' => 'text/html']));
        self::assertNull($this->linkId('/api/recon/images/98/file', []));
        self::assertNull($this->linkId('/api/recon/images/98/file', $doc, 'POST'));
        self::assertNull($this->linkId('/api/recon/images/98/ops', $doc));
        self::assertNull($this->linkId('/api/recon/images', $doc));
        self::assertNull($this->linkId('/api/recon/images/known', $doc));

        $mw = $this->read('app/Middleware/ComspecTacticalApiMiddleware.php');
        self::assertStringContainsString("Response::redirect(url('recon/images/' . \$reconId))", $mw);
        // Lecteur sans session : connexion puis retour sur le cliché.
        self::assertMatchesRegularExpression('/function reconImagesViewer[\s\S]*?LoginIntendedDestination::rememberFromRequest\(\$request\)[\s\S]*?redirect\(url\(\'login\'\)\)/', $this->read('app/Controllers/Api/AtakApiController.php'));
    }

    public function testRoutesAndControllerRules(): void
    {
        $routes = $this->read('routes/web.php');
        self::assertStringContainsString("\$router->get('/recon/images/{id}', [AtakApiController::class, 'reconImagesViewer']);", $routes);
        self::assertStringContainsString("\$router->get('/api/recon/images/{id}/file', [AtakApiController::class, 'reconImagesFile']);", $routes);

        $ctrl = $this->read('app/Controllers/Api/AtakApiController.php');
        // …/file : redirection vers le lecteur seulement pour une navigation, ?raw=1 et ?download=1 gardent le fichier.
        self::assertStringContainsString("!\$download && \$request->query('raw') === null && \$request->isBrowserNavigation()", $ctrl);
        self::assertStringContainsString("(\$download ? 'attachment' : 'inline')", $ctrl);
        // Le lecteur applique la même présentation que la liste : confidentiels masqués sans habilitation.
        self::assertMatchesRegularExpression('/function reconImagesViewer[\s\S]*?presentReconRows\(\$tenantId, \$album\)/', $ctrl);
        self::assertMatchesRegularExpression('/function presentReconRows[\s\S]*?presentReconPhotoMeta\(\$tenantId, \$rows\)/', $ctrl);
        self::assertMatchesRegularExpression("/function reconImagesViewer[\\s\\S]*?'Cache-Control', 'no-store, private, max-age=0'/", $ctrl);
    }

    public function testViewerAssetsAreWired(): void
    {
        $js = $this->read('public/assets/js/recon-viewer.js');
        foreach (['ReconViewer', 'requestFullscreen', 'wheel', 'pointerdown', 'ArrowLeft', 'Escape', 'aria-modal', 'download'] as $needle) {
            self::assertStringContainsString($needle, $js);
        }
        self::assertFileExists(dirname(__DIR__, 2) . '/public/assets/css/recon-viewer.css');
        self::assertStringContainsString('recon-viewer.js', $this->read('views/atak.php'));
        self::assertStringContainsString('recon-viewer.js', $this->read('views/tacmap.php'));
        self::assertStringContainsString('recon-viewer.js', $this->read('views/atak-overwatch-beta.php'));
        self::assertStringContainsString('data-recon-viewer="rmt-att"', $this->read('views/admin/intel/intake_show.php'));
        self::assertStringContainsString('ReconViewer.open', $this->read('public/assets/js/atak-cams.js'));
        self::assertStringContainsString('ReconViewer.open', $this->read('public/assets/js/tacmap-recon.js'));
        self::assertStringContainsString('ReconViewer.open', $this->read('public/assets/js/atak-overwatch-beta.js'));
    }
}
