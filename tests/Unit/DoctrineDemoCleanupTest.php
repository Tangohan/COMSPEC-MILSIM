<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Doctrine\DoctrineDemoCatalog;
use PHPUnit\Framework\TestCase;

final class DoctrineDemoCleanupTest extends TestCase
{
    public function testCatalogStopsSeedingAndKeepsOnlyAtak(): void
    {
        $path = dirname(__DIR__, 2) . '/bootstrap/doctrine_demo_seed.php';
        $catalog = require $path;

        self::assertIsArray($catalog);
        self::assertFalse(is_callable($catalog));
        self::assertCount(8, $catalog['remove']);
        self::assertCount(1, $catalog['keep']);
        self::assertSame('SIC/ATAK/2026-001', $catalog['keep'][0]['reference']);
        self::assertSame('Doctrine d’emploi d’ATAK / Overwatch Athena', $catalog['keep'][0]['title']);

        $removeRefs = array_column($catalog['remove'], 'reference');
        self::assertContains('EM/DOCTR/2026-001', $removeRefs);
        self::assertContains('OPS/SEC/2026-014', $removeRefs);
        self::assertContains('OPS/SIC/2026-018', $removeRefs);
        self::assertContains('DRH/PERS/2026-004', $removeRefs);
        self::assertContains('FORM/INST/2026-021', $removeRefs);
        self::assertContains('LOG/MAT/2026-009', $removeRefs);
        self::assertContains('MED/SAN/2026-006', $removeRefs);
        self::assertContains('REN/PROC/2026-011', $removeRefs);
        self::assertNotContains('SIC/ATAK/2026-001', $removeRefs);
    }

    public function testCleanupTargetsOnlyKnownDemoPairs(): void
    {
        self::assertTrue(DoctrineDemoCatalog::isRemoveTarget(
            'EM/DOCTR/2026-001',
            'Doctrine générale d’emploi de l’unité',
            'em-doctr-2026-001'
        ));
        self::assertTrue(DoctrineDemoCatalog::isRemoveTarget(
            'MED/SAN/2026-006',
            "Conduite à tenir en cas de blessé au combat",
            ''
        ));
        self::assertTrue(DoctrineDemoCatalog::isRemoveTarget(
            'EM/DOCTR/2026-001',
            "Doctrine générale d'emploi de l'unité",
            ''
        ));
        self::assertTrue(DoctrineDemoCatalog::isRemoveTarget(
            'OPS/SEC/2026-014',
            'Titre modifié par un admin',
            'ops-sec-2026-014'
        ));

        self::assertFalse(DoctrineDemoCatalog::isRemoveTarget(
            'SIC/ATAK/2026-001',
            'Doctrine d’emploi d’ATAK / Overwatch Athena',
            'sic-atak-2026-001'
        ));
        self::assertFalse(DoctrineDemoCatalog::isRemoveTarget(
            'EM/DOCTR/2026-001',
            'Notre doctrine maison',
            'notre-doctrine-maison'
        ));
        self::assertFalse(DoctrineDemoCatalog::isRemoveTarget(
            'OPS/CUSTOM/2026-099',
            'Mesures de sûreté applicables aux opérations extérieures',
            'ops-sec-2026-014'
        ));
        self::assertFalse(DoctrineDemoCatalog::isRemoveTarget(
            '',
            'JTAC - CAS Librairie',
            'jtac-cas-librairie'
        ));
        self::assertFalse(DoctrineDemoCatalog::isRemoveTarget(
            'FORM/JTAC/2026-001',
            'JTAC - CAS Librairie',
            'jtac-cas-librairie'
        ));
        self::assertTrue(DoctrineDemoCatalog::isKeptReference('SIC/ATAK/2026-001'));
    }

    public function testCleanupBootstrapIsWiredAndDoesNotInsert(): void
    {
        $cleanup = (string) file_get_contents(dirname(__DIR__, 2) . '/bootstrap/doctrine_demo_cleanup.php');
        $migration = (string) file_get_contents(dirname(__DIR__, 2) . '/bootstrap/doctrine_referential_migration.php');
        $atak = (string) file_get_contents(dirname(__DIR__, 2) . '/bootstrap/doctrine_atak_employment_seed.php');

        self::assertStringContainsString('DoctrineDemoCatalog::isRemoveTarget', $cleanup);
        self::assertStringContainsString("doctrine_status = 'archived'", $cleanup);
        self::assertStringNotContainsString('INSERT INTO documents', $cleanup);
        self::assertStringContainsString('doctrine_demo_cleanup', $migration);
        self::assertStringContainsString('doctrine_atak_employment_seed', $migration);
        self::assertStringNotContainsString('seedTenantDemo', $migration);
        self::assertStringContainsString('SIC/ATAK/2026-001', $atak);
        self::assertStringContainsString('upgradeAtakEmploymentDoctrineIfDemoPlaceholder', $atak);
    }

    public function testDemoPlaceholderFingerprint(): void
    {
        self::assertTrue(DoctrineDemoCatalog::looksLikeDemoPlaceholder(
            'Document de démonstration — Doctrine générale d’emploi de l’unité',
            'storage/documents/demo/em-doctr-2026-001.pdf'
        ));
        self::assertFalse(DoctrineDemoCatalog::looksLikeDemoPlaceholder(
            'Fixe les règles d’emploi du terminal tactique Overwatch',
            'doctrine/sic-atak-2026-001.md'
        ));
    }
}
