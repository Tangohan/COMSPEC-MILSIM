<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Billing\DonorService;
use App\Services\Billing\PaymentAnalyticsService as Analytics;
use PHPUnit\Framework\TestCase;

/** Paiements et dons : paliers donateur, communautés soutien, indicateurs, mois, filtres, export. */
final class PaymentsLedgerTest extends TestCase
{
    private const NOW = 1761480000; // 2025-10-26 12:00 UTC

    /** @return list<array<string, mixed>> */
    private function ledger(): array
    {
        $d = static fn (int $days): string => gmdate('Y-m-d H:i:s', self::NOW - $days * 86400);

        return [
            ['source' => 'plan', 'status' => 'activated', 'amount_cents' => 1200, 'tenant_id' => 1, 'user_id' => 10, 'payer_email' => 'a@x.fr', 'payer_name' => 'Alpha', 'reference' => 'ATH-AAA', 'tenant_name' => 'Escadron Alpha', 'plan_slug' => 'pro', 'interval' => 'monthly', 'created_at' => $d(3), 'declared_at' => $d(3), 'decided_at' => gmdate('Y-m-d H:i:s', self::NOW - 3 * 86400 + 7200), 'paid_at' => $d(2)],
            ['source' => 'heart', 'status' => 'activated', 'amount_cents' => 200, 'tenant_id' => 2, 'user_id' => 20, 'payer_email' => 'B@x.fr', 'payer_name' => 'Bravo', 'reference' => 'ATH-BBB', 'created_at' => $d(40), 'paid_at' => $d(40)],
            ['source' => 'donation', 'status' => 'activated', 'amount_cents' => 2500, 'tenant_id' => 0, 'user_id' => 20, 'payer_email' => 'b@x.fr', 'payer_name' => '', 'reference' => 'DON-1', 'created_at' => $d(10), 'paid_at' => $d(10)],
            ['source' => 'stripe_donation', 'status' => 'activated', 'amount_cents' => 9000, 'tenant_id' => 3, 'user_id' => 30, 'payer_email' => 'c@x.fr', 'payer_name' => 'Charlie', 'reference' => 'STR-1', 'created_at' => $d(400), 'paid_at' => $d(400)],
            ['source' => 'stripe_donation', 'status' => 'activated', 'amount_cents' => 2000, 'tenant_id' => 3, 'user_id' => 30, 'payer_email' => 'c@x.fr', 'payer_name' => 'Charlie', 'reference' => 'STR-2', 'created_at' => $d(5), 'paid_at' => $d(5)],
            ['source' => 'plan', 'status' => 'declared', 'amount_cents' => 1200, 'tenant_id' => 4, 'user_id' => 40, 'payer_email' => 'd@x.fr', 'reference' => 'ATH-DDD', 'created_at' => $d(2), 'declared_at' => gmdate('Y-m-d H:i:s', self::NOW - 30 * 3600)],
            ['source' => 'plan', 'status' => 'awaiting', 'amount_cents' => 1200, 'tenant_id' => 5, 'user_id' => 50, 'payer_email' => 'e@x.fr', 'reference' => 'ATH-EEE', 'created_at' => $d(1)],
            ['source' => 'donation', 'status' => 'refunded', 'amount_cents' => 500, 'tenant_id' => 0, 'user_id' => 60, 'payer_email' => 'f@x.fr', 'reference' => 'DON-2', 'created_at' => $d(70), 'paid_at' => $d(70), 'refunded_at' => $d(1)],
            ['source' => 'donation', 'status' => 'activated', 'amount_cents' => 300, 'tenant_id' => 0, 'user_id' => 0, 'payer_email' => '', 'reference' => 'DON-3', 'created_at' => $d(4), 'paid_at' => $d(4)],
        ];
    }

    public function testTierThresholds(): void
    {
        self::assertSame(null, DonorService::tierFor(0));
        self::assertSame('soutien', DonorService::tierFor(200));
        self::assertSame('allie', DonorService::tierFor(2000));
        self::assertSame('pilier', DonorService::tierFor(10000));
        self::assertSame('Allié', DonorService::tierLabel('allie'));
    }

    public function testDonorsMergeByEmailAndRespectOverrides(): void
    {
        $donors = DonorService::donorsFromLedger($this->ledger());
        self::assertSame(['c@x.fr', 'b@x.fr'], array_keys($donors), 'tri par total, plans et anonymes exclus, remboursé exclu');
        self::assertSame(2700, $donors['b@x.fr']['total_cents'], 'cœur + don fusionnés malgré la casse');
        self::assertSame('allie', $donors['b@x.fr']['tier']);
        self::assertSame('Bravo', $donors['b@x.fr']['name']);
        self::assertSame('pilier', $donors['c@x.fr']['tier']);
        self::assertSame([30], $donors['c@x.fr']['user_ids']);

        $o = DonorService::donorsFromLedger($this->ledger(), ['c@x.fr'], ['z@x.fr' => 'allie', 'b@x.fr' => 'soutien']);
        self::assertFalse(isset($o['c@x.fr']), 'révoqué');
        self::assertSame('allie', $o['z@x.fr']['tier']);
        self::assertTrue($o['z@x.fr']['manual']);
        self::assertSame('allie', $o['b@x.fr']['tier'], 'une attribution manuelle ne fait pas descendre le palier');
    }

    public function testSupporters(): void
    {
        $s = DonorService::supportersFromLedger($this->ledger(), [1, 9], self::NOW);
        self::assertSame('plan', $s[1]['kind']);
        self::assertSame('plan', $s[9]['kind']);
        self::assertSame('heart', $s[2]['kind']);
        self::assertSame('donation', $s[3]['kind']);
        self::assertFalse(isset($s[4]), 'paiement seulement déclaré');
        self::assertFalse(isset($s[5]));
    }

    public function testKpis(): void
    {
        $subs = [['plan_slug' => 'pro', 'interval' => 'monthly', 'days_left' => 5], ['plan_slug' => 'pro', 'interval' => 'yearly', 'days_left' => 200]];
        $k = Analytics::kpis($this->ledger(), $subs, ['pro' => ['monthly' => 1200, 'yearly' => 12000]], self::NOW);
        self::assertSame(1200 + 2500 + 2000 + 300, $k['month_cents']);
        self::assertSame(2200, $k['mrr_cents']);
        self::assertSame(26400, $k['arr_cents']);
        self::assertSame(1, $k['renewals_soon']);
        self::assertSame(1, $k['to_verify']);
        self::assertSame(30, $k['oldest_declared_hours']);
        self::assertSame(1, $k['unpaid']);
        self::assertSame(500, $k['refunds_90_cents']);
        self::assertSame(200 + 2500 + 2000 + 300, $k['donations_12_cents']);
        self::assertSame(3, $k['donors_12'], 'b, c et un anonyme');
        self::assertSame(2.0, $k['verify_median_hours']);
        self::assertSame(50, $k['conversion_pct'], '4 demandes en 90 j, 2 encaissées');
    }

    public function testMonthlyBucketsAndRefunds(): void
    {
        $m = Analytics::monthly($this->ledger(), 12, self::NOW);
        self::assertSame(12, count($m));
        $last = $m[11];
        self::assertSame('2025-10', $last['month']);
        self::assertSame('oct. 25', $last['label']);
        self::assertSame(1200, $last['plan']);
        self::assertSame(4800, $last['donation']);
        self::assertSame(-500, $last['refund']);
        self::assertSame(5500, $last['total']);
        self::assertSame(500, $m[9]['donation'], 'le don remboursé reste compté au mois du paiement (août)');
    }

    public function testFilter(): void
    {
        $l = $this->ledger();
        self::assertSame(6, count(Analytics::filter($l, 'donations', '', '', 0, self::NOW)));
        self::assertSame(3, count(Analytics::filter($l, 'plan', '', '', 0, self::NOW)));
        self::assertSame(1, count(Analytics::filter($l, '', 'declared', '', 0, self::NOW)));
        self::assertSame(['ATH-AAA'], array_column(Analytics::filter($l, '', '', 'escadron', 0, self::NOW), 'reference'));
        self::assertSame(6, count(Analytics::filter($l, '', '', '', 30, self::NOW)));
    }

    public function testCsvNeutralisesFormulas(): void
    {
        $csv = Analytics::csv([['reference' => '=HYPERLINK("x")', 'source' => 'donation', 'status' => 'activated', 'amount_cents' => 1234, 'payer_name' => 'Kodiak; "K"']]);
        self::assertTrue(str_starts_with($csv, "\xEF\xBB\xBF"));
        self::assertTrue(str_contains($csv, '"\'=HYPERLINK(""x"")"'));
        self::assertTrue(str_contains($csv, '"12,34"'));
        self::assertTrue(str_contains($csv, '"Kodiak; ""K"""'));
        self::assertTrue(str_contains($csv, '"Don";"Encaissé"'));
    }
}
