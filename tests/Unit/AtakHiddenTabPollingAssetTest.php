<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Sondages des pages web ATAK / Overwatch : aucune requête quand l'onglet est caché (document.hidden), une lecture au
 * retour (visibilitychange), et pour les calques quasi statiques du champ de vision une attente qui double quand rien
 * ne change (8 s, puis jusqu'à 60 s).
 */
final class AtakHiddenTabPollingAssetTest extends TestCase
{
    /** @return array<string, array{0: string}> */
    public static function pollers(): array
    {
        $files = [
            'public/assets/js/atak-overwatch-commands.js',
            'public/assets/js/atak-intel-timeline.js',
            'public/assets/js/atak-mission-plan.js',
            'public/assets/js/atak-viewshed.js',
            'public/assets/js/atak-overwatch-tracks.js',
            'public/assets/js/atak-sse-persons.js',
            'public/assets/js/atak-intel-view.js',
            'public/assets/js/atak-overwatch-support-auto.js',
            'public/assets/js/atak-overwatch-intel.js',
            'public/assets/js/atak-overwatch-ops.js',
            'public/assets/js/comspec-operational-map.js',
            'public/assets/js/atak-briefing-plan.js',
            'public/assets/js/atak-fire-teams.js',
            'public/assets/js/atak-iff.js',
            'public/assets/js/atak-medevac.js',
            'public/assets/js/atak-sse-layers.js',
            'public/assets/js/atak-terrain.js',
            'public/assets/js/atak-terminals.js',
            'public/assets/js/atak-ops-status.js',
        ];
        $out = [];
        foreach ($files as $f) {
            $out[basename($f)] = [$f];
        }

        return $out;
    }

    #[DataProvider('pollers')]
    public function testPollerSkipsHiddenTab(string $file): void
    {
        $js = (string) file_get_contents(dirname(__DIR__, 2) . '/' . $file);
        self::assertStringContainsString('document.hidden', $js, $file . ' : pause quand l’onglet est caché');
    }

    public function testFormerlyUnguardedIntervalsAreWrapped(): void
    {
        $root = dirname(__DIR__, 2) . '/public/assets/js/';
        $checks = [
            'atak-intel-timeline.js' => 'timer = setInterval(poll, 5000);',
            'atak-mission-plan.js' => 'timer = setInterval(fetchSnap, 8000);',
            'atak-overwatch-tracks.js' => 'pollTimer = setInterval(refresh, 8000);',
            'atak-sse-persons.js' => 'timer = window.setInterval(refresh, POLL_MS);',
            'atak-intel-view.js' => 'setInterval(poll, POLL_MS);',
            'atak-overwatch-ops.js' => 'window.setInterval(loadTraffic, 10000);',
            'atak-medevac.js' => 'setInterval(fetchList, 15000);',
            'atak-ops-status.js' => 'setInterval(refresh, 12000);',
        ];
        foreach ($checks as $file => $old) {
            self::assertStringNotContainsString($old, (string) file_get_contents($root . $file), $file);
        }
        $page = (string) file_get_contents(dirname(__DIR__, 2) . '/views/atak.php');
        self::assertStringContainsString('if (!document.hidden) refreshStatus(token);', $page);
    }

    public function testViewshedBacksOffWhenNothingChanges(): void
    {
        $js = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-viewshed.js');
        self::assertStringContainsString('var BASE_DELAY_MS = 8000;', $js);
        self::assertStringContainsString('var MAX_DELAY_MS = 60000;', $js);
        self::assertStringContainsString('delayMs = Math.min(MAX_DELAY_MS, delayMs * 2);', $js);
        self::assertStringContainsString('timer = setTimeout(loop, delayMs);', $js);
        self::assertStringContainsString("document.addEventListener('visibilitychange'", $js);
        self::assertStringNotContainsString('setInterval(fetchOverlays', $js);
    }
}
