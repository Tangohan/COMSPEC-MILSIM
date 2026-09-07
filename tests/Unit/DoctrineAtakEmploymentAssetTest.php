<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class DoctrineAtakEmploymentAssetTest extends TestCase
{
    public function testAtakEmploymentDoctrineSeedExists(): void
    {
        $seed = (string) file_get_contents(dirname(__DIR__, 2) . '/bootstrap/doctrine_atak_employment_seed.php');
        $pdf = dirname(__DIR__, 2) . '/storage/documents/doctrine/sic-atak-2026-001.pdf';

        self::assertStringContainsString('SIC/ATAK/2026-001', $seed);
        self::assertStringContainsString('Doctrine d’emploi d’ATAK / Overwatch Athena', $seed);
        self::assertStringContainsString('all_members', $seed);
        self::assertStringContainsString('mandatory', $seed);
        self::assertStringContainsString('upgradeAtakEmploymentDoctrineIfDemoPlaceholder', $seed);
        self::assertStringContainsString('upgradeAtakEmploymentDoctrineToOfficialPdf', $seed);
        self::assertStringContainsString('ensureAtakEmploymentBundledFile', $seed);
        self::assertStringContainsString('application/pdf', $seed);
        self::assertStringContainsString('doctrine/sic-atak-2026-001.pdf', $seed);
        self::assertFileExists($pdf);
        self::assertGreaterThan(10000, (int) filesize($pdf));
        $head = (string) file_get_contents($pdf, false, null, 0, 5);
        self::assertSame('%PDF-', $head);
    }
}
