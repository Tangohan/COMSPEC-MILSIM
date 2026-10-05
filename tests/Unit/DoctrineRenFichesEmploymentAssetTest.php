<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\SseFieldNoteCatalog;
use PHPUnit\Framework\TestCase;

final class DoctrineRenFichesEmploymentAssetTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function testRenFichesDoctrineSeedIsWiredAndBundled(): void
    {
        $seed = (string) file_get_contents($this->root() . '/bootstrap/doctrine_ren_fiches_employment_seed.php');
        $migration = (string) file_get_contents($this->root() . '/bootstrap/doctrine_referential_migration.php');
        $pdf = $this->root() . '/storage/documents/doctrine/ren-proc-2026-001.pdf';

        self::assertStringContainsString('doctrine_ren_fiches_employment_seed', $migration);
        self::assertStringContainsString('REN/PROC/2026-001', $seed);
        self::assertStringContainsString('doctrine/ren-proc-2026-001.pdf', $seed);
        self::assertStringContainsString('FM ATHENA REN-01', $seed);
        self::assertStringContainsString('all_members', $seed);
        self::assertStringContainsString('mandatory', $seed);
        self::assertStringContainsString('reading_required', $seed);
        self::assertStringContainsString('acknowledgment_required', $seed);
        self::assertFileExists($pdf);
        self::assertFileExists($this->root() . '/storage/documents/doctrine/ren-proc-2026-001.md');
        self::assertGreaterThan(50000, (int) filesize($pdf));
        self::assertSame('%PDF-', (string) file_get_contents($pdf, false, null, 0, 5));
    }

    /**
     * La circulaire décrit le référentiel des fiches : elle doit suivre le catalogue réel.
     */
    public function testCircularSourceMatchesFieldNoteCatalog(): void
    {
        $html = (string) file_get_contents($this->root() . '/docs/doctrine/fm-athena-ren-01/fm-athena-ren-01.html');

        foreach (array_keys(SseFieldNoteCatalog::KINDS) as $kind) {
            self::assertStringContainsString('<td class="code">' . $kind . '</td>', $html, $kind);
        }
        foreach (SseFieldNoteCatalog::THEMES as $code => $def) {
            self::assertStringContainsString($code . '</td><td>' . $def['label'] . '</td>', $html, $code);
        }
        foreach (array_keys(SseFieldNoteCatalog::SOURCES) as $source) {
            self::assertStringContainsString('<td class="code">' . $source . '</td>', $html, $source);
        }
        foreach (SseFieldNoteCatalog::STATUSES as $label) {
            self::assertStringContainsString('<b>' . $label . '</b>', $html, $label);
        }
        self::assertSame(1000, SseFieldNoteCatalog::BODY_MAX_LENGTH);
        self::assertSame(4, SseFieldNoteCatalog::ATTACHMENTS_MAX);
        self::assertSame(4, SseFieldNoteCatalog::THEMES_MAX);
    }
}
