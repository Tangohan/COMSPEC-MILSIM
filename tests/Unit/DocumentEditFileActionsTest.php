<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class DocumentEditFileActionsTest extends TestCase
{
    public function testEditPageOffersOpenDownloadAndDetach(): void
    {
        $root = dirname(__DIR__, 2);
        $edit = (string) file_get_contents($root . '/views/admin/documents/edit.php');
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $controller = (string) file_get_contents($root . '/app/Controllers/Admin/AdminDocumentsController.php');
        $web = (string) file_get_contents($root . '/app/Controllers/Web/DocumentsController.php');
        $service = (string) file_get_contents($root . '/app/Services/Documents/DocumentUploadService.php');

        self::assertStringContainsString('Ouvrir le fichier', $edit);
        self::assertStringContainsString('Télécharger', $edit);
        self::assertStringContainsString('Retirer le fichier', $edit);
        self::assertStringContainsString('Je confirme le retrait du fichier joint', $edit);
        self::assertStringContainsString("url('documents/' . \$document['id'] . '/file')", $edit);
        self::assertStringContainsString("url('documents/' . \$document['id'] . '/download')", $edit);
        self::assertStringNotContainsString('file_path', $edit);

        self::assertStringContainsString("/documents/gestion/{id}/retirer-fichier", $routes);
        self::assertStringContainsString("'detachFile'", $routes);

        self::assertStringContainsString('function detachFile', $controller);
        self::assertStringContainsString('confirm_detach', $controller);
        self::assertStringContainsString('detachCurrentFile', $service);
        self::assertStringContainsString('viewerMayManageAttachedFile', $web);
        self::assertStringContainsString('denyAttachedFileAccess', $web);
    }
}
