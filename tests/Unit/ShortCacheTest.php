<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Tactical\RoleplaySimulationService;
use App\Support\ShortCache;
use PHPUnit\Framework\TestCase;

final class ShortCacheTest extends TestCase
{
    private string $ns = '';

    protected function setUp(): void
    {
        $this->ns = 'test-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        ShortCache::setEnabled(null);
    }

    public function testDisabledInCliByDefault(): void
    {
        $calls = 0;
        $fn = static function () use (&$calls): int {
            return ++$calls;
        };
        self::assertFalse(ShortCache::enabled());
        ShortCache::remember($this->ns, 5, $fn);
        ShortCache::remember($this->ns, 5, $fn);
        self::assertSame(2, $calls, 'tests, cron, worker : toujours recalculé');
    }

    public function testSecondReadIsServedFromCacheUntilTheGenerationChanges(): void
    {
        ShortCache::setEnabled(true);
        $calls = 0;
        $read = function () use (&$calls): array {
            return ShortCache::remember(
                'rows:' . $this->ns . ':' . ShortCache::gen($this->ns),
                5,
                static function () use (&$calls): array {
                    $calls++;

                    return [['id' => $calls, 'empty' => [], 'zero' => 0]];
                }
            );
        };

        $first = $read();
        self::assertSame($first, $read(), 'même liste, pas de nouvelle lecture');
        self::assertSame(1, $calls);

        ShortCache::bump($this->ns);
        $after = $read();
        self::assertSame(2, $calls, 'écriture : relu tout de suite');
        self::assertSame(2, $after[0]['id']);
    }

    public function testEmptyListsAndFalsyValuesAreCachedToo(): void
    {
        ShortCache::setEnabled(true);
        $calls = 0;
        $fn = static function () use (&$calls): array {
            $calls++;

            return [];
        };
        self::assertSame([], ShortCache::remember('empty:' . $this->ns, 5, $fn));
        self::assertSame([], ShortCache::remember('empty:' . $this->ns, 5, $fn));
        self::assertSame(1, $calls);
    }

    public function testEntryExpiresAfterItsTtl(): void
    {
        ShortCache::setEnabled(true);
        $calls = 0;
        $fn = static function () use (&$calls): int {
            return ++$calls;
        };
        self::assertSame(1, ShortCache::remember('ttl:' . $this->ns, 1, $fn));
        usleep(1_100_000);
        self::assertSame(2, ShortCache::remember('ttl:' . $this->ns, 1, $fn));
    }

    /** Drapeau gardé entre les requêtes (canaux système du tchat déjà semés) ; rien en CLI par défaut. */
    public function testGetAndPutKeepAFlag(): void
    {
        self::assertNull(ShortCache::get('flag:' . $this->ns));
        ShortCache::put('flag:' . $this->ns, true, 60);
        self::assertNull(ShortCache::get('flag:' . $this->ns), 'désactivé en CLI');

        ShortCache::setEnabled(true);
        self::assertNull(ShortCache::get('flag:' . $this->ns));
        ShortCache::put('flag:' . $this->ns, true, 60);
        self::assertTrue(ShortCache::get('flag:' . $this->ns));
        ShortCache::put('null:' . $this->ns, null, 60);
        self::assertNull(ShortCache::get('null:' . $this->ns));
    }

    public function testExceptionIsNotCached(): void
    {
        ShortCache::setEnabled(true);
        try {
            ShortCache::remember('err:' . $this->ns, 5, static function (): never {
                throw new \RuntimeException('base indisponible');
            });
            self::fail('exception attendue');
        } catch (\RuntimeException) {
        }
        self::assertSame('ok', ShortCache::remember('err:' . $this->ns, 5, static fn (): string => 'ok'));
    }

    /** Coupures roleplay sans état en session (les sondages ne la verrouillent plus). */
    public function testRoleplayDisconnectScheduleIsStableAndBounded(): void
    {
        $interval = 120;
        $min = 5;
        $max = 20;
        $cycle = $interval + $max;
        $down = 0;
        $t0 = 1_800_000_000;
        for ($t = $t0; $t < $t0 + $cycle * 10; $t++) {
            $a = RoleplaySimulationService::disconnectedAt($t, 'viewer-a', $interval, $min, $max);
            self::assertSame($a, RoleplaySimulationService::disconnectedAt($t, 'viewer-a', $interval, $min, $max));
            $down += $a ? 1 : 0;
        }
        // 10 cycles : entre 10 × 5 s et 10 × 20 s de coupure (à un cycle près, selon le décalage du demandeur).
        self::assertGreaterThanOrEqual(9 * $min, $down);
        self::assertLessThanOrEqual(11 * $max, $down);
    }
}
