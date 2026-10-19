<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class DoctrineOfficialEditionsSeedTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function testEditionsSeedIsWiredAfterBundledDoctrines(): void
    {
        $migration = (string) file_get_contents($this->root() . '/bootstrap/doctrine_referential_migration.php');

        self::assertStringContainsString('doctrine_official_editions_seed', $migration);
        self::assertGreaterThan(
            strpos($migration, 'doctrine_ren_fiches_employment_seed'),
            strpos($migration, 'doctrine_official_editions_seed'),
        );
    }

    public function testEditionsAreBundledAndResetAcknowledgment(): void
    {
        $seed = (string) file_get_contents($this->root() . '/bootstrap/doctrine_official_editions_seed.php');

        self::assertStringContainsString('acknowledgment_reset', $seed);
        self::assertStringContainsString('acknowledgment_required = 1', $seed);
        foreach (['doctrine/ren-proc-2026-001-v1.1.pdf', 'doctrine/sic-atak-2026-001-v1.3.pdf'] as $relative) {
            self::assertStringContainsString($relative, $seed);
            $pdf = $this->root() . '/storage/documents/' . $relative;
            self::assertFileExists($pdf);
            self::assertSame('%PDF-', (string) file_get_contents($pdf, false, null, 0, 5));
        }
    }

    public function testOfficialPointerDetection(): void
    {
        require_once $this->root() . '/app/Support/SqlText.php';
        require_once $this->root() . '/bootstrap/doctrine_official_editions_seed.php';

        $prev = ['doctrine/sic-atak-2026-001.pdf', 'doctrine/sic-atak-2026-001.md'];
        self::assertTrue(doctrineEditionIsOfficialPointer('doctrine/sic-atak-2026-001.pdf', $prev));
        self::assertTrue(doctrineEditionIsOfficialPointer('12/34/v1.1.pdf', $prev));
        self::assertTrue(doctrineEditionIsOfficialPointer('', $prev));
        self::assertFalse(doctrineEditionIsOfficialPointer('12/34/doctrine-locale.pdf', $prev));
    }

    public function testDashboardSignaturesModalIsWired(): void
    {
        $partial = (string) file_get_contents($this->root() . '/views/partials/dashboard_doctrine_pending.php');
        $js = (string) file_get_contents($this->root() . '/public/assets/js/dashboard-signatures.js');

        self::assertStringContainsString('data-dash-sign-modal', $partial);
        self::assertStringContainsString('dashboard-signatures.js', $partial);
        self::assertStringContainsString('csrf_token', $js);
        self::assertStringContainsString('version_id', $js);
    }
}
