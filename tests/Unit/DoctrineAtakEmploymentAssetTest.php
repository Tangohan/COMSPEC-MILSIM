<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class DoctrineAtakEmploymentAssetTest extends TestCase
{
    public function testAtakEmploymentDoctrineSeedExists(): void
    {
        $seed = (string) file_get_contents(dirname(__DIR__, 2) . '/bootstrap/doctrine_atak_employment_seed.php');
        $markdown = (string) file_get_contents(dirname(__DIR__, 2) . '/storage/documents/doctrine/sic-atak-2026-001.md');

        self::assertStringContainsString('SIC/ATAK/2026-001', $seed);
        self::assertStringContainsString('Doctrine d’emploi d’ATAK / Overwatch Athena', $seed);
        self::assertStringContainsString('all_members', $seed);
        self::assertStringContainsString('mandatory', $seed);
        self::assertStringContainsString('COMSPEC Overwatch', $markdown);
        self::assertStringContainsString('Prise en compte obligatoire', $markdown);
    }
}
