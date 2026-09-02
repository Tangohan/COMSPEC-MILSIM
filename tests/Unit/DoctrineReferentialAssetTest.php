<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class DoctrineReferentialAssetTest extends TestCase
{
    public function testDoctrineReferentialWiringExists(): void
    {
        $migration = (string) file_get_contents(dirname(__DIR__, 2) . '/migrations/20260902120000_doctrine_referential.sql');
        $indexView = (string) file_get_contents(dirname(__DIR__, 2) . '/views/documents/doctrine_index.php');
        $showView = (string) file_get_contents(dirname(__DIR__, 2) . '/views/documents/doctrine_show.php');
        $compliance = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/Doctrine/DocumentComplianceService.php');
        $routes = (string) file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');

        self::assertStringContainsString('document_doctrines', $migration);
        self::assertStringContainsString('document_acknowledgments', $migration);
        self::assertStringContainsString('doctrine-referential.css', $indexView);
        self::assertStringContainsString('data-doctrine-ack-form', $showView);
        self::assertStringContainsString('listPendingActionsForUser', $compliance);
        self::assertStringContainsString('SIC/ATAK/2026-001', (string) file_get_contents(dirname(__DIR__, 2) . '/bootstrap/doctrine_atak_employment_seed.php'));
        self::assertStringContainsString('doctrine_atak_employment_seed', (string) file_get_contents(dirname(__DIR__, 2) . '/bootstrap/doctrine_referential_migration.php'));
        self::assertStringContainsString('DoctrineDocumentAccessService', (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/Doctrine/DoctrineDocumentAccessService.php'));
        self::assertStringContainsString('DoctrineDocumentsController', $routes);
        self::assertStringContainsString('back-office/documents/nomenclature', $routes);
    }
}
