<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Sse\SseTriageInbox;
use App\Support\ShortCache;
use PHPUnit\Framework\TestCase;

/**
 * Boîte « À trier » du bureau SSE : agrégation des files, dédoublonnage, droits par source, communauté, cache de 30 s,
 * ton des files. Sources réelles du service, chargeurs fournis par le test (aucune simulation de classe).
 */
final class SseTriageInboxTest extends TestCase
{
    private int $calls = 0;

    protected function setUp(): void
    {
        ShortCache::setEnabled(false);
        $this->calls = 0;
    }

    protected function tearDown(): void
    {
        ShortCache::setEnabled(null);
    }

    /** @return list<array<string, mixed>> */
    private static function items(string $prefix, int $n, int $ageDays, int $priority = 0): array
    {
        $out = [];
        for ($i = 1; $i <= $n; $i++) {
            $out[] = ['key' => $prefix . ':' . $i, 'title' => $prefix . ' ' . $i, 'meta' => '', 'at' => gmdate('Y-m-d H:i:s', time() - $ageDays * 86400 - $i), 'href' => '/x/' . $i, 'priority' => $i === 1 ? $priority : 0];
        }

        return $out;
    }

    /** @param array<int, list<array<string, mixed>>> $byTenant */
    private function def(string $key, string $nav, string $path, array $byTenant): array
    {
        return ['key' => $key, 'nav' => $nav, 'label' => ucfirst($key), 'tab' => ucfirst($key), 'path' => $path, 'load' => function (int $t) use ($byTenant): array {
            $this->calls++;

            return $byTenant[$t] ?? [];
        }];
    }

    public function testAggregatesCountsPerSourceAndDistinctTotal(): void
    {
        $inbox = new SseTriageInbox([
            $this->def('fiches', 'fiches', 'atak/sse/fiches', [1 => self::items('note', 5, 1)]),
            $this->def('anomalies', 'anomalies', 'atak/sse/anomalies', [1 => self::items('signal', 3, 1)]),
        ], fn (): bool => true);
        $snap = $inbox->snapshot(1, 7, ['fiches', 'anomalies']);

        self::assertSame(8, $snap['total']);
        self::assertSame(5, $snap['sources']['fiches']['count']);
        self::assertSame(3, $snap['sources']['anomalies']['count']);
        self::assertSame('fiches', $snap['sources']['fiches']['nav']);
        self::assertCount(8, $snap['items']);
    }

    public function testAnItemInTwoQueuesIsCountedOnceInTheTotal(): void
    {
        $shared = ['key' => 'note:9', 'title' => 'Fiche partagée', 'meta' => '', 'at' => gmdate('Y-m-d H:i:s'), 'href' => '/n/9', 'priority' => 0];
        $inbox = new SseTriageInbox([
            $this->def('fiches', 'fiches', 'atak/sse/fiches', [1 => array_merge(self::items('note', 2, 1), [$shared])]),
            $this->def('frm', 'papiers-frm', 'atak/sse/papiers/tableau', [1 => [$shared]]),
        ], fn (): bool => true);
        $snap = $inbox->snapshot(1, 7, ['fiches', 'papiers-frm']);

        self::assertSame(3, $snap['total'], 'somme brute 4, éléments distincts 3');
        self::assertSame(3, $snap['sources']['fiches']['count']);
        self::assertSame(1, $snap['sources']['frm']['count']);
    }

    public function testAnUnreadableSourceIsNeitherCountedNorListedNorLoaded(): void
    {
        $inbox = new SseTriageInbox([
            $this->def('fiches', 'fiches', 'atak/sse/fiches', [1 => self::items('note', 2, 1)]),
            $this->def('controles', 'papiers-controles', 'atak/sse/papiers/controles', [1 => self::items('check', 4, 1)]),
        ], static fn (string $section, string $level): bool => $section !== 'papiers');
        $snap = $inbox->snapshot(1, 7, ['fiches', 'papiers-controles']);

        self::assertSame(2, $snap['total']);
        self::assertSame(['fiches'], array_keys($snap['sources']));
        foreach ($snap['items'] as $it) {
            self::assertSame('fiches', $it['source']);
        }
        self::assertSame(1, $this->calls, 'la file interdite n’est même pas interrogée');
    }

    public function testOnlyVisibleNavigationEntriesAreCounted(): void
    {
        $inbox = new SseTriageInbox([$this->def('fiches', 'fiches', 'atak/sse/fiches', [1 => self::items('note', 2, 1)])], fn (): bool => true);

        self::assertSame(0, $inbox->snapshot(1, 7, ['accueil'])['total']);
    }

    public function testTenantsStayApart(): void
    {
        $inbox = new SseTriageInbox([$this->def('fiches', 'fiches', 'atak/sse/fiches', [1 => self::items('note', 3, 1), 2 => self::items('other', 9, 1)])], fn (): bool => true);

        self::assertSame(3, $inbox->snapshot(1, 7, ['fiches'])['total']);
        self::assertSame(9, $inbox->snapshot(2, 7, ['fiches'])['total']);
        self::assertSame(0, $inbox->snapshot(0, 7, ['fiches'])['total'], 'sans communauté, rien');
    }

    public function testToneIsNeutralAmberOrRed(): void
    {
        $now = time();
        self::assertSame('neutral', SseTriageInbox::tone(self::items('a', 3, 6), $now));
        self::assertSame('warn', SseTriageInbox::tone(self::items('a', 3, 8), $now), 'plus de 7 jours : ambre');
        self::assertSame('bad', SseTriageInbox::tone(self::items('a', 3, 1, 2), $now), 'priorité critique : rouge');
        self::assertSame('bad', SseTriageInbox::tone(self::items('a', 3, 30, 2), $now), 'le rouge l’emporte sur l’ambre');
        self::assertSame('neutral', SseTriageInbox::tone([], $now));
    }

    public function testListIsSortedByPriorityThenOldestFirst(): void
    {
        $inbox = new SseTriageInbox([
            $this->def('fiches', 'fiches', 'atak/sse/fiches', [1 => self::items('note', 2, 2)]),
            $this->def('avis', 'avis-recherche', 'atak/sse/avis', [1 => self::items('notice', 1, 1, 2)]),
            $this->def('anomalies', 'anomalies', 'atak/sse/anomalies', [1 => self::items('signal', 1, 9)]),
        ], fn (): bool => true);
        $keys = array_map(static fn (array $i): string => $i['key'], $inbox->snapshot(1, 7, ['fiches', 'avis-recherche', 'anomalies'])['items']);

        self::assertSame('notice:1', $keys[0], 'le critique d’abord');
        self::assertSame('signal:1', $keys[1], 'puis le plus ancien');
    }

    public function testASourceIsBoundedToOneHundredItems(): void
    {
        $inbox = new SseTriageInbox([$this->def('controles', 'papiers-controles', 'atak/sse/papiers/controles', [1 => self::items('check', 250, 1)])], fn (): bool => true);

        self::assertSame(100, $inbox->snapshot(1, 7, ['papiers-controles'])['sources']['controles']['count']);
    }

    public function testAFailingSourceDoesNotBreakTheOthers(): void
    {
        $bad = ['key' => 'bad', 'nav' => 'anomalies', 'label' => 'Bad', 'tab' => 'Bad', 'path' => 'atak/sse/anomalies', 'load' => static function (int $t): array {
            throw new \RuntimeException('table absente');
        }];
        $inbox = new SseTriageInbox([$bad, $this->def('fiches', 'fiches', 'atak/sse/fiches', [1 => self::items('note', 2, 1)])], fn (): bool => true);
        $snap = $inbox->snapshot(1, 7, ['fiches', 'anomalies']);

        self::assertSame(2, $snap['total']);
        self::assertSame(0, $snap['sources']['bad']['count']);
    }

    public function testResultIsCachedThenInvalidatedByAWrite(): void
    {
        ShortCache::setEnabled(true);
        $inbox = new SseTriageInbox([$this->def('fiches', 'fiches', 'atak/sse/fiches', [41 => self::items('note', 2, 1)])], fn (): bool => true);
        $inbox->snapshot(41, 7, ['fiches']);
        $inbox->snapshot(41, 7, ['fiches']);
        self::assertSame(1, $this->calls, 'seconde lecture servie par le cache');

        SseTriageInbox::invalidate();
        $inbox->snapshot(41, 7, ['fiches']);
        self::assertSame(2, $this->calls, 'une écriture périme le cache');
        self::assertSame(30, SseTriageInbox::TTL);
    }

    public function testCacheIsNotSharedBetweenAccounts(): void
    {
        ShortCache::setEnabled(true);
        SseTriageInbox::invalidate();
        $inbox = new SseTriageInbox([$this->def('fiches', 'fiches', 'atak/sse/fiches', [42 => self::items('note', 2, 1)])], fn (): bool => true);
        $inbox->snapshot(42, 7, ['fiches']);
        $inbox->snapshot(42, 8, ['fiches']);

        self::assertSame(2, $this->calls);
    }

    public function testRealSourcesCoverTheQueuesOfTheOffice(): void
    {
        $keys = array_map(static fn (array $d): string => $d['nav'], SseTriageInbox::defaultDefinitions(1));
        foreach (['fiches', 'papiers-frm', 'papiers-controles', 'avis-detections', 'rapprochements', 'anomalies', 'interet', 'validation', 'avis-recherche', 'fichiers-acces-images'] as $nav) {
            self::assertContains($nav, $keys);
        }
    }

    public function testEveryRealSourceMapsToAnExistingNavigationEntry(): void
    {
        $ids = [];
        foreach (\App\Support\SsePortalNav::sections(true, true, false, true, true) as $s) {
            foreach ($s['items'] as $i) {
                $ids[] = $i['id'];
            }
        }
        foreach (SseTriageInbox::defaultDefinitions(1) as $d) {
            self::assertContains($d['nav'], $ids, $d['key']);
        }
    }

    public function testWritesInvalidateButReadsDoNot(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Core/Application.php');
        self::assertStringContainsString('SseTriageInbox::invalidateAfter', $src);
        $inboxSrc = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/Sse/SseTriageInbox.php');
        self::assertStringContainsString("['GET', 'HEAD', 'OPTIONS']", $inboxSrc);
    }
}
