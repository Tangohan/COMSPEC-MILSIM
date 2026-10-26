<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\DonorGamePerk;
use PHPUnit\Framework\TestCase;

/** Palier donateur envoyé au jeu (net_donor_tier) et à la Tacmap : palier seul, jamais de montant. */
final class DonorGamePerkTest extends TestCase
{
    public function testPayloadUsesNetKeyForCurrentDll(): void
    {
        $tiers = [7 => 'pilier', 8 => 'ALLIE ', 9 => 'gold'];
        $of = static fn (int $id): ?string => $tiers[$id] ?? null;

        self::assertSame(['net_donor_tier' => 'pilier', 'donor_tier' => 'pilier'], DonorGamePerk::payload(7, $of));
        self::assertSame('allie', DonorGamePerk::payload(8, $of)['donor_tier'], 'casse et espaces normalisés');
        self::assertSame('', DonorGamePerk::payload(9, $of)['net_donor_tier'], 'palier inconnu ignoré');
        self::assertSame('', DonorGamePerk::payload(null, $of)['donor_tier'], 'pas de session jeu');
        self::assertSame('', DonorGamePerk::payload(0, $of)['donor_tier']);
        self::assertSame(['net_donor_tier', 'donor_tier'], array_keys(DonorGamePerk::payload(7, $of)), 'aucune autre donnée exposée');
    }

    public function testResolverFailureIsSilent(): void
    {
        $boom = static function (int $id): ?string {
            throw new \RuntimeException('cache illisible');
        };
        self::assertSame('', DonorGamePerk::payload(3, $boom)['donor_tier']);
    }

    public function testTiersForKeepsOnlyDonors(): void
    {
        $of = static fn (int $id): ?string => [1 => 'soutien', 3 => 'pilier'][$id] ?? null;
        self::assertSame([1 => 'soutien', 3 => 'pilier'], DonorGamePerk::tiersFor([1, 2, 3, 3, 0, -4], $of));
    }
}
