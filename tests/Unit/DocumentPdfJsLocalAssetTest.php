<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class DocumentPdfJsLocalAssetTest extends TestCase
{
    public function testDocumentViewerLoadsPdfJsFromAthenaNotACdn(): void
    {
        $root = dirname(__DIR__, 2);
        $show = (string) file_get_contents($root . '/views/documents/show.php');
        $htaccess = (string) file_get_contents($root . '/public/.htaccess');
        $index = (string) file_get_contents($root . '/public/index.php');
        $nginx = (string) file_get_contents($root . '/docs/nginx.example.conf');
        $sw = (string) file_get_contents($root . '/public/sw.js');

        self::assertStringNotContainsString('cdn.jsdelivr.net/npm/pdfjs-dist', $show);
        self::assertStringContainsString('assets/vendor/pdfjs/pdf.js', $show);
        self::assertStringContainsString('assets/vendor/pdfjs/pdf.worker.min.js', $show);
        self::assertStringContainsString('Le document n’a pas pu s’afficher. Téléchargez-le.', $show);
        self::assertFileExists($root . '/public/assets/vendor/pdfjs/pdf.js');
        self::assertFileExists($root . '/public/assets/vendor/pdfjs/pdf.mjs');
        self::assertFileExists($root . '/public/assets/vendor/pdfjs/pdf.worker.min.js');
        self::assertFileExists($root . '/public/assets/vendor/pdfjs/pdf.worker.min.mjs');
        self::assertFileExists($root . '/docs/bugs/2026-09-01-documents-pdfjs-csp.md');
        self::assertFileExists($root . '/docs/bugs/2026-09-02-pdf-mjs-mime.md');
        self::assertStringContainsString("athena-shell-v9", $sw);
        self::assertStringContainsString('/assets/vendor/pdfjs/', $sw);
        self::assertStringContainsString('/documents/', $sw);
        self::assertStringContainsString("new URL(url).origin !== self.location.origin", $sw);
    }

    public function testMjsIsDeclaredAsJavascriptInHtaccessRouterAndNginx(): void
    {
        $root = dirname(__DIR__, 2);
        $htaccess = (string) file_get_contents($root . '/public/.htaccess');
        $index = (string) file_get_contents($root . '/public/index.php');
        $nginx = (string) file_get_contents($root . '/docs/nginx.example.conf');

        self::assertStringContainsString('AddType application/javascript .js .mjs', $htaccess);
        self::assertStringContainsString('AddType application/wasm .wasm', $htaccess);
        self::assertMatchesRegularExpression(
            "/'mjs'\\s*=>\\s*'application\\/javascript/",
            $index
        );
        self::assertMatchesRegularExpression(
            "/'wasm'\\s*=>\\s*'application\\/wasm'/",
            $index
        );
        self::assertStringContainsString('location ~* \.mjs$', $nginx);
        self::assertStringContainsString('default_type application/javascript', $nginx);
        self::assertStringContainsString('location ~* \.wasm$', $nginx);
        self::assertStringContainsString('default_type application/wasm', $nginx);
    }
}
