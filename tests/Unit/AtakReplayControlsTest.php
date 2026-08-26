<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakReplayControlsTest extends TestCase
{
    public function testReplayOffersEventFilteringAndTemporalWindow(): void
    {
        $view = file_get_contents(__DIR__ . '/../../views/atak.php');
        $script = file_get_contents(__DIR__ . '/../../public/assets/js/atak-replay.js');

        self::assertIsString($view);
        self::assertIsString($script);
        self::assertStringContainsString('id="atak-replay-event-filter"', $view);
        self::assertStringContainsString('id="atak-replay-zoom"', $view);
        self::assertStringContainsString("eventFilter = String(filter.value || 'all')", $script);
        self::assertStringContainsString('eventWindowSeconds * 1000', $script);
        self::assertStringContainsString('function escapeHtml(value)', $script);
        self::assertStringContainsString('class="atak-replay-event-target"', $script);
        self::assertStringContainsString('Number.isFinite(x) && Number.isFinite(y)', $script);
    }
}
