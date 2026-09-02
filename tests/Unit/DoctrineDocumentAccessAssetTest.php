<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class DoctrineDocumentAccessAssetTest extends TestCase
{
    public function testDoctrineAccessUsesAudienceResolver(): void
    {
        $service = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/Doctrine/DoctrineDocumentAccessService.php');
        $controller = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Web/DoctrineDocumentsController.php');
        $documentsController = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Web/DocumentsController.php');

        self::assertStringContainsString('isUserInAudience', $service);
        self::assertStringContainsString('passesClassification', $service);
        self::assertStringContainsString('doctrineDocumentAccessService->canMemberView', $controller);
        self::assertStringContainsString('canReadDocumentOrDoctrine', $documentsController);
    }
}
