<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class TenantRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tenants WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tenants WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getDefaultTenant(): ?array
    {
        $stmt = $this->pdo->query('SELECT * FROM tenants ORDER BY id ASC LIMIT 1');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function slugExists(string $slug): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM tenants WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
        return (bool) $stmt->fetchColumn();
    }

    /** @return int id du tenant créé */
    public function create(string $name, string $slug, string $planSlug = 'free'): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO tenants (name, slug, plan_slug, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())');
        $stmt->execute([$name, $slug, $planSlug]);
        return (int) $this->pdo->lastInsertId();
    }

    public function setOwner(int $tenantId, int $ownerUserId): void
    {
        $stmt = $this->pdo->prepare('UPDATE tenants SET owner_user_id = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$ownerUserId, $tenantId]);
    }

    public function updateSubscriptionFromStripe(
        int $tenantId,
        ?string $stripeCustomerId,
        ?string $stripeSubscriptionId,
        string $status,
        ?string $planSlug,
        ?string $periodEndIso
    ): void {
        $periodEnd = null;
        if ($periodEndIso !== null && $periodEndIso !== '') {
            $periodEnd = date('Y-m-d H:i:s', (int) strtotime($periodEndIso));
        }
        $row = $this->findById($tenantId);
        if (!$row) {
            return;
        }
        $stmt = $this->pdo->prepare(
            'UPDATE tenants SET stripe_customer_id = ?, stripe_subscription_id = ?, subscription_status = ?, plan_slug = ?, subscription_current_period_end = ?, updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute([
            $stripeCustomerId ?? $row['stripe_customer_id'] ?? null,
            $stripeSubscriptionId ?? $row['stripe_subscription_id'] ?? null,
            $status,
            $planSlug ?? ($row['plan_slug'] ?? 'free'),
            $periodEnd ?? $row['subscription_current_period_end'] ?? null,
            $tenantId,
        ]);
    }

    /** Fusionne un objet JSON dans tenants.settings. */
    public function mergeSettings(int $tenantId, array $patch): void
    {
        $row = $this->findById($tenantId);
        if (!$row) {
            return;
        }
        $current = [];
        if (!empty($row['settings'])) {
            $decoded = json_decode((string) $row['settings'], true);
            if (is_array($decoded)) {
                $current = $decoded;
            }
        }
        $merged = array_merge($current, $patch);
        $stmt = $this->pdo->prepare('UPDATE tenants SET settings = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([json_encode($merged, JSON_THROW_ON_ERROR), $tenantId]);
    }
}
