<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\SseTransmissionIndex;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SseTransmissionIndexTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    /** @return list<array<string,mixed>> */
    private function events(): array
    {
        return [
            ['id' => 1, 'event_time' => '2026-11-08 08:00:00', 'event_type_label' => 'Fiche de site', 'author_label' => 'Zoé'],
            ['id' => 2, 'event_time' => '2026-11-15 09:30:00', 'event_type_label' => 'Rapport reçu', 'author_label' => ''],
            ['id' => 3, 'event_time' => '2026-11-14 22:10:00', 'event_type_label' => 'Rapport reçu', 'author_label' => 'alice'],
            ['id' => 4, 'event_time' => '2026-11-15 09:30:00', 'event_type_label' => 'Contrôle', 'author_label' => 'Bob'],
        ];
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-15 15:10:00');
    }

    /** @param list<array<string,mixed>> $rows */
    private function ids(array $rows): array
    {
        return array_map(static fn (array $e): int => (int) $e['id'], $rows);
    }

    public function testSortNormalizesUnknownValuesToRecent(): void
    {
        self::assertSame('recent', SseTransmissionIndex::normalizeSort(''));
        self::assertSame('recent', SseTransmissionIndex::normalizeSort('nimporte'));
        self::assertSame('operateur', SseTransmissionIndex::normalizeSort(' OPERATEUR '));
    }

    public function testSortRecentAndOldest(): void
    {
        self::assertSame([4, 2, 3, 1], $this->ids(SseTransmissionIndex::sort($this->events(), 'recent')));
        self::assertSame([1, 3, 2, 4], $this->ids(SseTransmissionIndex::sort($this->events(), 'ancien')));
    }

    public function testSortByNatureThenRecent(): void
    {
        self::assertSame([4, 1, 2, 3], $this->ids(SseTransmissionIndex::sort($this->events(), 'nature')));
    }

    public function testSortByOperatorPutsMissingLast(): void
    {
        self::assertSame([3, 4, 1, 2], $this->ids(SseTransmissionIndex::sort($this->events(), 'operateur')));
    }

    public function testCountersByPeriodAndNature(): void
    {
        $c = SseTransmissionIndex::counters($this->events(), $this->now());
        self::assertSame(4, $c['total']);
        self::assertSame(2, $c['today']);
        self::assertSame(3, $c['week']);
        self::assertSame(['Rapport reçu' => 2, 'Fiche de site' => 1, 'Contrôle' => 1], $c['natures']);
        self::assertSame(['total' => 0, 'today' => 0, 'week' => 0, 'natures' => []], SseTransmissionIndex::counters([], $this->now()));
    }

    public function testPeriodsAreInclusiveDays(): void
    {
        self::assertSame(['today' => '2026-11-15', 'week' => '2026-11-09', 'month' => '2026-10-17'], SseTransmissionIndex::periods($this->now()));
    }

    public function testShortWhenAddsYearOnlyWhenNotCurrent(): void
    {
        self::assertSame('15/11 14:55', SseTransmissionIndex::shortWhen('2026-11-15 14:55:12', $this->now()));
        self::assertSame('03/02/2025 08:05', SseTransmissionIndex::shortWhen('2025-02-03 08:05:00', $this->now()));
        self::assertSame('bientôt', SseTransmissionIndex::shortWhen('bientôt', $this->now()));
    }

    public function testActiveFilterDetection(): void
    {
        self::assertFalse(SseTransmissionIndex::hasActiveFilter(['q' => '', 'event_type' => '', 'source' => 'TERRAIN', 'since' => '']));
        self::assertTrue(SseTransmissionIndex::hasActiveFilter(['q' => 'convoi']));
        self::assertTrue(SseTransmissionIndex::hasActiveFilter(['source' => 'ALL']));
        self::assertTrue(SseTransmissionIndex::hasActiveFilter(['since' => '2026-11-01']));
    }

    public function testPagesAreWiredWithTabsDrawerModalAndConfirm(): void
    {
        $index = (string) file_get_contents(self::ROOT . '/views/atak/sse/transmissions.php');
        foreach (['role="tablist"', 'data-txp-tab="relais"', 'id="txp-drawer"', 'id="txp-relay-add"', 'data-ui-confirm="1"', 'name="tri"', 'sse-transmissions.js', 'ui_confirm_modal.js', 'portal-ui-confirm'] as $needle) {
            self::assertStringContainsString($needle, $index);
        }
        self::assertStringNotContainsString('confirm(', $index);
        self::assertStringNotContainsString('onsubmit=', $index);
        $show = (string) file_get_contents(self::ROOT . '/views/atak/sse/transmission_show.php');
        foreach (['txp-panel-resume', 'data-txp-tab', 'role="tabpanel"', 'data-txp-fieldfilter'] as $needle) {
            self::assertStringContainsString($needle, $show);
        }
        $ctrl = (string) file_get_contents(self::ROOT . '/app/Controllers/Web/SsePortalController.php');
        self::assertStringContainsString("'counters' => \App\Support\SseTransmissionIndex::counters", $ctrl);
        self::assertStringContainsString("?onglet=relais", $ctrl);
    }

    public function testAssetsHaveNoEyebrowAndNoTinyText(): void
    {
        $css = (string) file_get_contents(self::ROOT . '/public/assets/css/sse-transmissions.css');
        self::assertStringNotContainsString('eyebrow', $css);
        self::assertSame(0, preg_match('/font-size:\s*(?:[0-9]|1[01])(?:\.\d+)?px/', $css));
        self::assertSame(0, preg_match('/font-size:\s*0?\.[0-7]\d*rem/', $css));
        $js = (string) file_get_contents(self::ROOT . '/public/assets/js/sse-transmissions.js');
        self::assertStringContainsString("'Escape'", $js);
        self::assertStringContainsString('showModal', $js);
        self::assertStringNotContainsString('confirm(', $js);
    }
}
