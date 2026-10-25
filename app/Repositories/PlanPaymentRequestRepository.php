<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Demandes de paiement PayPal (formules et soutien du cœur), activées à la main par l’administration du site.
 */
final class PlanPaymentRequestRepository
{
    public const STATUS_AWAITING = 'awaiting';
    public const STATUS_DECLARED = 'declared';
    public const STATUS_ACTIVATED = 'activated';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    public const KIND_PLAN = 'plan';
    public const KIND_HEART = 'heart';

    private static ?bool $ready = null;

    public function __construct(private ?PDO $pdo = null) {}

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    public function schemaReady(): bool
    {
        if ($this->pdo !== null) {
            return $this->probe();
        }
        return self::$ready ??= $this->probe();
    }

    private function probe(): bool
    {
        try {
            $this->pdo()->query('SELECT 1 FROM plan_payment_requests LIMIT 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param array{reference: string, kind: string, tenant_id: ?int, user_id: ?int, payer_email: ?string, plan_slug: ?string, billing_interval: ?string, amount_cents: int} $data
     */
    public function create(array $data): int
    {
        $st = $this->pdo()->prepare(
            'INSERT INTO plan_payment_requests
                (reference, kind, tenant_id, user_id, payer_email, plan_slug, billing_interval, amount_cents, currency, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            $data['reference'],
            $data['kind'],
            $data['tenant_id'],
            $data['user_id'],
            $data['payer_email'],
            $data['plan_slug'],
            $data['billing_interval'],
            $data['amount_cents'],
            'EUR',
            self::STATUS_AWAITING,
            gmdate('Y-m-d H:i:s'),
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    /** @return array<string, mixed>|null */
    public function findByReference(string $reference): ?array
    {
        $st = $this->pdo()->prepare('SELECT * FROM plan_payment_requests WHERE reference = ? LIMIT 1');
        $st->execute([strtoupper(trim($reference))]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $st = $this->pdo()->prepare('SELECT * FROM plan_payment_requests WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function referenceExists(string $reference): bool
    {
        return $this->findByReference($reference) !== null;
    }

    public function markDeclared(int $id, string $transactionRef, string $note): bool
    {
        $st = $this->pdo()->prepare(
            'UPDATE plan_payment_requests SET status = ?, transaction_ref = ?, payer_note = ?, declared_at = ?
             WHERE id = ? AND status IN (?, ?)'
        );
        $st->execute([
            self::STATUS_DECLARED,
            mb_substr($transactionRef, 0, 80),
            $note !== '' ? mb_substr($note, 0, 500) : null,
            gmdate('Y-m-d H:i:s'),
            $id,
            self::STATUS_AWAITING,
            self::STATUS_DECLARED,
        ]);

        return $st->rowCount() === 1;
    }

    public function markDecided(int $id, string $status, ?int $adminId, string $note, ?string $periodEndsAt = null): bool
    {
        $st = $this->pdo()->prepare(
            'UPDATE plan_payment_requests SET status = ?, admin_note = ?, decided_at = ?, decided_by = ?, period_ends_at = ?
             WHERE id = ? AND status IN (?, ?)'
        );
        $st->execute([
            $status,
            $note !== '' ? mb_substr($note, 0, 500) : null,
            gmdate('Y-m-d H:i:s'),
            $adminId,
            $periodEndsAt,
            $id,
            self::STATUS_AWAITING,
            self::STATUS_DECLARED,
        ]);

        return $st->rowCount() === 1;
    }

    public function cancel(int $id): bool
    {
        $st = $this->pdo()->prepare('UPDATE plan_payment_requests SET status = ? WHERE id = ? AND status = ?');
        $st->execute([self::STATUS_CANCELLED, $id, self::STATUS_AWAITING]);

        return $st->rowCount() === 1;
    }

    /**
     * Pour l’administration : déclarées d’abord, puis les plus récentes.
     *
     * @return list<array<string, mixed>>
     */
    public function listForAdmin(?string $status = null, int $limit = 200): array
    {
        $limit = max(1, min(500, $limit));
        $sql = 'SELECT r.*, t.name AS tenant_name, t.slug AS tenant_slug, u.display_name AS user_display_name, u.email AS user_email
                FROM plan_payment_requests r
                LEFT JOIN tenants t ON t.id = r.tenant_id
                LEFT JOIN users u ON u.id = r.user_id';
        $params = [];
        if ($status !== null && $status !== '') {
            $sql .= ' WHERE r.status = ?';
            $params[] = $status;
        }
        $sql .= " ORDER BY CASE WHEN r.status = 'declared' THEN 0 WHEN r.status = 'awaiting' THEN 1 ELSE 2 END, r.created_at DESC, r.id DESC LIMIT " . $limit;
        $st = $this->pdo()->prepare($sql);
        $st->execute($params);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string, int> */
    public function countByStatus(): array
    {
        $out = [];
        foreach ($this->pdo()->query('SELECT status, COUNT(*) AS n FROM plan_payment_requests GROUP BY status')?->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $out[(string) $row['status']] = (int) $row['n'];
        }

        return $out;
    }

    /** Dernière demande encore ouverte (à payer ou déclarée) d’une communauté. */
    public function latestOpenForTenant(int $tenantId): ?array
    {
        $st = $this->pdo()->prepare(
            'SELECT * FROM plan_payment_requests WHERE tenant_id = ? AND status IN (?, ?) ORDER BY created_at DESC, id DESC LIMIT 1'
        );
        $st->execute([$tenantId, self::STATUS_AWAITING, self::STATUS_DECLARED]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}
