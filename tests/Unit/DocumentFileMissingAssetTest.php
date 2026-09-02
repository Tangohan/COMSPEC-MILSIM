<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class DocumentFileMissingAssetTest extends TestCase
{
    public function testMissingFilePageIsBrandedAndWired(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/documents/file_missing.php');
        $controller = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Web/DocumentsController.php');

        self::assertStringContainsString('Document indisponible', $view);
        self::assertStringContainsString('Retour au document', $view);
        self::assertStringContainsString('Voir le référentiel', $view);
        self::assertStringNotContainsString('Fichier absent', $view);
        self::assertStringContainsString('missingDocumentFilePage', $controller);
        self::assertStringContainsString('documents/file_missing', $controller);
        self::assertStringNotContainsString("setBody('Fichier absent')", $controller);
    }
}
