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
        $docx = dirname(__DIR__, 2) . '/storage/documents/doctrine/sic-atak-2026-001.docx';

        self::assertStringContainsString('SIC/ATAK/2026-001', $seed);
        self::assertStringContainsString('Doctrine d’emploi d’ATAK / Overwatch Athena', $seed);
        self::assertStringContainsString('all_members', $seed);
        self::assertStringContainsString('mandatory', $seed);
        self::assertStringContainsString('application/pdf', $seed);
        self::assertStringContainsString('sic-atak-2026-001.pdf', $seed);
        self::assertStringContainsString('v1.1', $seed);
        self::assertFileExists($pdf);
        self::assertGreaterThan(10000, (int) filesize($pdf));
        self::assertFileExists($docx);
    }
}
