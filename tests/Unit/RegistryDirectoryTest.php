<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Admin\System\SystemRegistryController;
use App\Repositories\PlatformSettingsRepository;
use App\Services\Community\RegistryDirectory;
use PHPUnit\Framework\TestCase;

final class RegistryDirectoryTest extends TestCase
{
    public function testNormalizePinClampsAndRejects(): void
    {
        self::assertNull(RegistryDirectory::normalizePin(['tenant_id' => 1]));
        self::assertNull(RegistryDirectory::normalizePin([]));
        $pin = RegistryDirectory::normalizePin(['tenant_id' => '5', 'position' => 99, 'until' => '2026-02-31', 'note' => "  a\n  b  "]);
        self::assertSame(5, $pin['tenant_id']);
        self::assertSame(RegistryDirectory::MAX_POSITION, $pin['position']);
        self::assertNull($pin['until'], 'date invalide ignorée');
        self::assertSame('a b', $pin['note']);
        self::assertSame(1, RegistryDirectory::normalizePin(['tenant_id' => 3, 'position' => -4])['position']);
    }

    public function testPinActiveUntilLastDayIncluded(): void
    {
        $now = new \DateTimeImmutable('2026-10-19 23:00:00');
        self::assertTrue(RegistryDirectory::isPinActive(['until' => null], $now));
        self::assertTrue(RegistryDirectory::isPinActive(['until' => '2026-10-19'], $now));
        self::assertFalse(RegistryDirectory::isPinActive(['until' => '2026-10-18'], $now));
    }

    public function testDecodePinsDeduplicatesAndSortsByPosition(): void
    {
        $pins = RegistryDirectory::decodePins((string) json_encode([
            ['tenant_id' => 7, 'position' => 3],
            ['tenant_id' => 4, 'position' => 1],
            ['tenant_id' => 7, 'position' => 2],
            'garbage',
            ['tenant_id' => 0],
        ]));
        self::assertSame([4, 7], array_column($pins, 'tenant_id'));
        self::assertSame(2, $pins[1]['position'], 'la dernière épingle d’une communauté gagne');
        self::assertSame([], RegistryDirectory::decodePins('not json'));
    }

    public function testTrendScoreAndRanking(): void
    {
        self::assertSame(10 + 12 + 3 + 6, RegistryDirectory::trendScore(['visitors' => 10, 'visitors_prev' => 4, 'recruits' => 2, 'active' => 3]));
        self::assertSame(5, RegistryDirectory::trendScore(['visitors' => 5, 'visitors_prev' => 50]), 'une baisse ne retire pas de points');

        $ranks = RegistryDirectory::rankTrending([
            2 => ['visitors' => 1, 'visitors_prev' => 1, 'recruits' => 0, 'active' => 1],   // 2 : sous le seuil
            3 => ['visitors' => 20, 'visitors_prev' => 20, 'recruits' => 0, 'active' => 0],  // 20
            4 => ['visitors' => 2, 'visitors_prev' => 2, 'recruits' => 3, 'active' => 0],    // 20, plus de recrues
            5 => ['visitors' => 40, 'visitors_prev' => 0, 'recruits' => 0, 'active' => 0],   // 80
        ], 2);
        self::assertSame([5 => 1, 4 => 2], $ranks);
    }

    public function testDecorateSortsPinnedThenFeaturedAndNeverAddsRows(): void
    {
        $rows = [
            ['id' => 2, 'name' => 'Alpha'],
            ['id' => 3, 'name' => 'Bravo', 'registry_featured' => true],
            ['id' => 4, 'name' => 'Charlie'],
            ['id' => 5, 'name' => 'Delta'],
        ];
        $pins = [
            5 => ['tenant_id' => 5, 'position' => 2],
            4 => ['tenant_id' => 4, 'position' => 1],
            9 => ['tenant_id' => 9, 'position' => 1], // communauté non listée : ignorée
        ];
        $signals = [2 => ['visitors' => 30, 'visitors_prev' => 0, 'recruits' => 0, 'active' => 0]];
        $out = RegistryDirectory::decorate($rows, $pins, $signals);

        self::assertSame([4, 5, 3, 2], array_map(static fn (array $r): int => $r['id'], $out));
        self::assertCount(4, $out);
        self::assertTrue($out[0]['registry_pinned']);
        self::assertFalse($out[2]['registry_pinned']);
        self::assertSame(1, $out[3]['registry_trending_rank']);
        self::assertSame(60, $out[3]['registry_trend']['score']);
    }

    public function testPinAndUnpinPersistThroughPlatformSettings(): void
    {
        $store = ['registry.pins' => '[]'];
        $settings = $this->createMock(PlatformSettingsRepository::class);
        $settings->method('get')->willReturnCallback(static function (string $k, string $d = '') use (&$store): string {
            return $store[$k] ?? $d;
        });
        $settings->method('setMany')->willReturnCallback(static function (array $pairs) use (&$store): void {
            $store = array_merge($store, $pairs);
        });
        $dir = new RegistryDirectory($settings);

        self::assertTrue($dir->pin(6, 2, null, 'Partenaire', 1));
        self::assertTrue($dir->pin(6, 1, '2099-01-01', '', 1));
        $pins = $dir->pins();
        self::assertCount(1, $pins);
        self::assertSame(1, $pins[0]['position']);
        self::assertSame('2099-01-01', $pins[0]['until']);
        self::assertArrayHasKey(6, $dir->activePinsByTenant());
        self::assertFalse($dir->pin(1, 1, null, '', 1), 'espace technique refusé');

        self::assertTrue($dir->unpin(6));
        self::assertFalse($dir->unpin(6));
        self::assertSame([], $dir->pins());
    }

    public function testUntilFromRequest(): void
    {
        $today = new \DateTimeImmutable('2026-10-19');
        self::assertNull(SystemRegistryController::untilFromRequest('', '', $today));
        self::assertSame('2026-10-26', SystemRegistryController::untilFromRequest('7', '', $today));
        self::assertSame('2026-12-24', SystemRegistryController::untilFromRequest('date', '2026-12-24', $today));
        self::assertNull(SystemRegistryController::untilFromRequest('date', '2026-10-01', $today), 'date passée refusée');
        self::assertNull(SystemRegistryController::untilFromRequest('date', 'demain', $today));
    }
}
