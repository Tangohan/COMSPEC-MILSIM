<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class DocumentPdfJsLocalAssetTest extends TestCase
{
    public function testDocumentViewerLoadsPdfJsFromAthenaNotACdn(): void
    {
        $show = (string) file_get_contents(dirname(__DIR__, 2) . '/views/documents/show.php');
        $htaccess = (string) file_get_contents(dirname(__DIR__, 2) . '/public/.htaccess');
        $sw = (string) file_get_contents(dirname(__DIR__, 2) . '/public/sw.js');

        self::assertStringNotContainsString('cdn.jsdelivr.net/npm/pdfjs-dist', $show);
        self::assertStringContainsString('assets/vendor/pdfjs/pdf.mjs', $show);
        self::assertStringContainsString('assets/vendor/pdfjs/pdf.worker.min.mjs', $show);
        self::assertFileExists(dirname(__DIR__, 2) . '/public/assets/vendor/pdfjs/pdf.mjs');
        self::assertFileExists(dirname(__DIR__, 2) . '/public/assets/vendor/pdfjs/pdf.worker.min.mjs');
        self::assertStringContainsString('AddType application/javascript .js .mjs', $htaccess);
        self::assertStringContainsString("new URL(url).origin !== self.location.origin", $sw);
        self::assertStringContainsString("athena-shell-v8", $sw);
        self::assertFileExists(dirname(__DIR__, 2) . '/docs/bugs/2026-09-01-documents-pdfjs-csp.md');
    }
}
