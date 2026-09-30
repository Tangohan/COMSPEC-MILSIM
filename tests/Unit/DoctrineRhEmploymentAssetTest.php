<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class DoctrineRhEmploymentAssetTest extends TestCase
{
    public function testRhEmploymentDoctrineSeedExists(): void
    {
        $seed = (string) file_get_contents(dirname(__DIR__, 2) . '/bootstrap/doctrine_rh_employment_seed.php');
        $pdf = dirname(__DIR__, 2) . '/storage/documents/doctrine/drh-pers-2026-001.pdf';
        $md = dirname(__DIR__, 2) . '/storage/documents/doctrine/drh-pers-2026-001.md';

        self::assertStringContainsString('DRH/PERS/2026-001', $seed);
        self::assertStringContainsString('Doctrine d’emploi RH — Recrutement et Avancement', $seed);
        self::assertStringContainsString('all_members', $seed);
        self::assertStringContainsString('mandatory', $seed);
        self::assertStringContainsString('reading_required', $seed);
        self::assertStringContainsString('acknowledgment_required', $seed);
        self::assertStringContainsString('ensureRhEmploymentMandatoryAudience', $seed);
        self::assertStringContainsString('upgradeRhEmploymentDoctrineIfDemoPlaceholder', $seed);
        self::assertStringContainsString('upgradeRhEmploymentDoctrineToOfficialPdf', $seed);
        self::assertStringContainsString('ensureRhEmploymentBundledFile', $seed);
        self::assertStringContainsString('application/pdf', $seed);
        self::assertStringContainsString('doctrine/drh-pers-2026-001.pdf', $seed);
        self::assertStringContainsString('FM_ATHENA_RH_Doctrine_v1.0_FR.pdf', $seed);
        self::assertFileExists($pdf);
        self::assertFileExists($md);
        self::assertGreaterThan(10000, (int) filesize($pdf));
        $head = (string) file_get_contents($pdf, false, null, 0, 5);
        self::assertSame('%PDF-', $head);
        self::assertStringContainsString('Recrutement', (string) file_get_contents($md));
        self::assertStringContainsString('Avancement', (string) file_get_contents($md));
        self::assertStringContainsString('campagne', mb_strtolower((string) file_get_contents($md)));
    }
}
