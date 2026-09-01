<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class DocumentAuthoredAssetTest extends TestCase
{
    public function testCreateFormOffersWriteModeAndFieldManualCover(): void
    {
        $upload = (string) file_get_contents(dirname(__DIR__, 2) . '/views/admin/documents/upload.php');
        $paper = (string) file_get_contents(dirname(__DIR__, 2) . '/views/partials/document_fm_paper.php');
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/document-fm.css');
        $js = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/document-fm-preview.js');

        self::assertStringContainsString('Rédiger le document', $upload);
        self::assertStringContainsString('Joindre un fichier', $upload);
        self::assertStringContainsString('document_origin', $upload);
        self::assertStringContainsString('fm-page--cover', $paper);
        self::assertStringContainsString('Foreword', $paper);
        self::assertStringContainsString('fm-sigs', $paper);
        self::assertStringContainsString('.fm-page--cover', $css);
        self::assertStringContainsString('.fm-sig-script', $css);
        self::assertStringContainsString('refreshPreview', $js);
        self::assertFileExists(dirname(__DIR__, 2) . '/docs/utilisateur/documents.md');
    }
}
