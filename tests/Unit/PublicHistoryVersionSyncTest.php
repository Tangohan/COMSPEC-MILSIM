<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Platform\PublicReleaseHistoryService;
use PHPUnit\Framework\TestCase;

/** « Notre histoire » : les versions affichées ne doivent jamais dériver des sources réelles du dépôt. */
final class PublicHistoryVersionSyncTest extends TestCase
{
    private string $cache;

    protected function setUp(): void
    {
        $this->cache = sys_get_temp_dir() . '/comspec-sync-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->cache . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->cache);
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    /** @return array<string, string> */
    private function currentOnPage(): array
    {
        $svc = new PublicReleaseHistoryService($this->root(), $this->cache, static fn (): array => []);

        return array_column($svc->history(true)['current'], 'version', 'product');
    }

    public function testCurrentVersionsMatchRepositorySources(): void
    {
        $root = $this->root();
        $hpp = (string) file_get_contents($root . '/mod/COMSPEC_ATAK_Native/Sources/addons/main/script_component.hpp');
        self::assertSame(1, preg_match('/#define VERSION_STR "([\d.]+)"/', $hpp, $atak));
        self::assertSame(1, preg_match('/#define LINK_VERSION_STR "([\d.]+)"/', $hpp, $link));
        self::assertSame(1, preg_match('/versionStr\s*=\s*"([\d.]+)"/', (string) file_get_contents($root . '/' . PublicReleaseHistoryService::SOURCE_FILES['overwatch']), $ow));
        self::assertSame(1, preg_match('/versionStr\s*=\s*"([\d.]+)"/', (string) file_get_contents($root . '/' . PublicReleaseHistoryService::SOURCE_FILES['sse']), $sse));
        $portal = json_decode((string) file_get_contents($root . '/storage/app_version.json'), true);

        $cur = $this->currentOnPage();
        self::assertSame($atak[1], $cur['atak']);
        self::assertSame($link[1], $cur['link']);
        self::assertSame($ow[1], $cur['overwatch']);
        self::assertSame($sse[1], $cur['sse']);
        self::assertSame($portal['version'], $cur['portal']);
    }

    public function testLatestChangelogEntryIsTheModVersionAndTheNewestRelease(): void
    {
        $svc = new PublicReleaseHistoryService($this->root(), $this->cache, static fn (): array => []);
        $history = $svc->history(true);
        $atak = $this->currentOnPage()['atak'];
        self::assertSame($atak, $history['releases'][0]['tags'][0]['version'], 'La frise doit commencer par la version du mod.');
        self::assertSame($atak, $history['current'][0]['version']);
        self::assertNotNull($history['current'][0]['date']);
    }

    public function testEveryRecentAtakEntryHasAnExplicitDate(): void
    {
        $raw = str_replace("\r\n", "\n", (string) file_get_contents($this->root() . '/CHANGELOG-ATAK.md'));
        $missing = [];
        foreach (preg_split('/^## /m', $raw) ?: [] as $i => $part) {
            if ($i === 0) {
                continue;
            }
            $heading = (string) strtok($part, "\n");
            if (preg_match('/ATAK natif (\d+)\.\d+\.\d+/', $heading, $m) !== 1 || (int) $m[1] < 4) {
                continue;
            }
            if (preg_match('/20\d{2}-\d{2}-\d{2}/', $heading) !== 1 && preg_match('/^<!-- date: 20\d{2}-\d{2}-\d{2} -->$/m', $part) !== 1) {
                $missing[] = $heading;
            }
        }
        self::assertSame([], $missing, 'Chaque entrée ATAK 4.x+ porte une ligne <!-- date: AAAA-MM-JJ --> (sinon la frise lui prête une date plus ancienne).');
    }

    public function testReleaseDatesNeverGoBackwardsWithVersion(): void
    {
        $svc = new PublicReleaseHistoryService($this->root(), $this->cache, static fn (): array => []);
        $atak = array_values(array_filter($svc->history(true)['releases'], static fn (array $r): bool => $r['product'] === 'atak' && $r['date_precision'] === 'day'));
        $prev = null;
        foreach ($atak as $r) {
            $v = PublicReleaseHistoryService::versionKey($r['tags'][0]['version']);
            if ($prev !== null && version_compare($v, $prev['v']) > 0 && strcmp($r['date'], $prev['d']) < 0) {
                self::fail("ATAK {$v} ({$r['date']}) est plus récent que {$prev['v']} ({$prev['d']}) mais affiché après.");
            }
            if ($prev === null || version_compare($v, $prev['v']) < 0) {
                $prev = ['v' => $v, 'd' => $r['date']];
            }
            if (version_compare($v, '4.0.0') < 0) {
                break;
            }
        }
        self::assertTrue(true);
    }
}
