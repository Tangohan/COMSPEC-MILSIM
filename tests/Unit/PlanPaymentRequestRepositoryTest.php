<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\PlanPaymentRequestRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class PlanPaymentRequestRepositoryTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE tenants (id INTEGER PRIMARY KEY, name TEXT, slug TEXT)');
        $this->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, display_name TEXT, email TEXT)');
        $this->pdo->exec("CREATE TABLE plan_payment_requests (id INTEGER PRIMARY KEY AUTOINCREMENT, reference TEXT UNIQUE, kind TEXT, tenant_id INTEGER,
            user_id INTEGER, payer_email TEXT, plan_slug TEXT, billing_interval TEXT, amount_cents INTEGER, currency TEXT, status TEXT,
            transaction_ref TEXT, payer_note TEXT, admin_note TEXT, period_ends_at TEXT, created_at TEXT, declared_at TEXT, decided_at TEXT, decided_by INTEGER,
            admin_reminded_at TEXT, refunded_at TEXT)");
        $this->pdo->exec("INSERT INTO tenants (id, name, slug) VALUES (2, 'Alpha', 'alpha')");
    }

    private function create(PlanPaymentRequestRepository $repo, string $ref): int
    {
        return $repo->create([
            'reference' => $ref, 'kind' => 'plan', 'tenant_id' => 2, 'user_id' => 7, 'payer_email' => 'a@x.fr',
            'plan_slug' => 'pro', 'billing_interval' => 'monthly', 'amount_cents' => 999,
        ]);
    }

    public function testLifecycleDeclareThenActivate(): void
    {
        $repo = new PlanPaymentRequestRepository($this->pdo);
        self::assertTrue($repo->schemaReady());
        $id = $this->create($repo, 'ATH-AAAAAA');

        $row = $repo->findByReference('ath-aaaaaa');
        self::assertNotNull($row);
        self::assertSame('awaiting', $row['status']);
        self::assertSame($id, (int) $repo->latestOpenForTenant(2)['id']);

        self::assertTrue($repo->markDeclared($id, '5TY05013RG002845M', 'compte de Jean'));
        self::assertSame('declared', $repo->findById($id)['status']);

        self::assertTrue($repo->markDecided($id, 'activated', 1, '', '2026-11-25 10:00:00'));
        self::assertSame('activated', $repo->findById($id)['status']);
        // Une demande traitée ne se re-déclare ni ne se re-décide.
        self::assertFalse($repo->markDeclared($id, 'X', ''));
        self::assertFalse($repo->markDecided($id, 'rejected', 1, ''));
        self::assertNull($repo->latestOpenForTenant(2));
    }

    public function testAdminListPutsDeclaredFirst(): void
    {
        $repo = new PlanPaymentRequestRepository($this->pdo);
        $a = $this->create($repo, 'ATH-AAAAAA');
        $b = $this->create($repo, 'ATH-BBBBBB');
        $repo->markDeclared($a, '5TY05013RG002845M', '');

        $rows = $repo->listForAdmin();
        self::assertSame('ATH-AAAAAA', $rows[0]['reference']);
        self::assertSame('Alpha', $rows[0]['tenant_name']);
        self::assertSame(['awaiting' => 1, 'declared' => 1], $repo->countByStatus());
        self::assertCount(1, $repo->listForAdmin('awaiting'));
        self::assertSame($b, (int) $repo->listForAdmin('awaiting')[0]['id']);
    }

    public function testAdminCancelRefundAndReminders(): void
    {
        $repo = new PlanPaymentRequestRepository($this->pdo);
        $a = $this->create($repo, 'ATH-AAAAAA');
        $b = $this->create($repo, 'ATH-BBBBBB');

        // Annulation par l’admin : seulement avant activation.
        self::assertTrue($repo->cancelByAdmin($a, 1, 'doublon'));
        self::assertSame('cancelled', $repo->findById($a)['status']);
        self::assertSame(1, (int) $repo->findById($a)['decided_by']);
        self::assertFalse($repo->cancelByAdmin($a, 1, ''));

        // Remboursement : seulement un paiement activé.
        self::assertFalse($repo->markRefunded($b, 1, ''));
        $repo->markDeclared($b, '5TY05013RG002845M', '');
        $this->pdo->exec("UPDATE plan_payment_requests SET declared_at = '2026-10-20 10:00:00' WHERE id = " . $b);
        self::assertSame(1, $repo->declaredSummary()['count']);
        $due = $repo->listDeclaredAwaitingReminder(24, strtotime('2026-10-22 10:00:00 UTC'));
        self::assertCount(1, $due);
        $repo->markAdminReminded([$b]);
        self::assertCount(0, $repo->listDeclaredAwaitingReminder(24, strtotime('2026-10-22 10:00:00 UTC')));

        $repo->markDecided($b, 'activated', 1, '', '2026-11-25 10:00:00');
        self::assertTrue($repo->markRefunded($b, 1, 'geste commercial'));
        $row = $repo->findById($b);
        self::assertSame('refunded', $row['status']);
        self::assertNotNull($row['refunded_at']);
        self::assertSame(0, $repo->sumActivatedSince('2000-01-01 00:00:00'));
        self::assertSame($b, (int) ($repo->latestForTenantSince(2, '2000-01-01 00:00:00')['id'] ?? 0));
    }
}
