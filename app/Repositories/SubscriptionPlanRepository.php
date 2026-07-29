<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class SubscriptionPlanRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM subscription_plans WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return list<array<string, mixed>> */
    public function allOrdered(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM subscription_plans ORDER BY sort_order ASC, id ASC');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    public function tableExists(): bool
    {
        try {
            $st = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscription_plans' LIMIT 1");

            return $st && (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM subscription_plans WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @param array{
     *   name: string,
     *   sort_order: int,
     *   features_json: ?string,
     *   limits_json: ?string,
     *   stripe_price_id_monthly: ?string,
     *   stripe_price_id_yearly: ?string,
     *   paypal_plan_id_monthly?: ?string,
     *   paypal_plan_id_yearly?: ?string
     * } $data
     */
    public function update(int $id, array $data): bool
    {
        if ($id < 1) {
            return false;
        }
        $hasPaypal = $this->hasPayPalPlanColumns();
        if ($hasPaypal) {
            $stmt = $this->pdo->prepare(
                'UPDATE subscription_plans SET
                    name = ?,
                    sort_order = ?,
                    features_json = ?,
                    limits_json = ?,
                    stripe_price_id_monthly = ?,
                    stripe_price_id_yearly = ?,
                    paypal_plan_id_monthly = ?,
                    paypal_plan_id_yearly = ?
                 WHERE id = ?'
            );

            return $stmt->execute([
                $data['name'],
                $data['sort_order'],
                $data['features_json'],
                $data['limits_json'],
                $data['stripe_price_id_monthly'],
                $data['stripe_price_id_yearly'],
                $data['paypal_plan_id_monthly'] ?? null,
                $data['paypal_plan_id_yearly'] ?? null,
                $id,
            ]);
        }

        $stmt = $this->pdo->prepare(
            'UPDATE subscription_plans SET
                name = ?,
                sort_order = ?,
                features_json = ?,
                limits_json = ?,
                stripe_price_id_monthly = ?,
                stripe_price_id_yearly = ?
             WHERE id = ?'
        );

        return $stmt->execute([
            $data['name'],
            $data['sort_order'],
            $data['features_json'],
            $data['limits_json'],
            $data['stripe_price_id_monthly'],
            $data['stripe_price_id_yearly'],
            $id,
        ]);
    }

    public function hasPayPalPlanColumns(): bool
    {
        try {
            $st = $this->pdo->query(
                "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscription_plans' AND COLUMN_NAME = 'paypal_plan_id_monthly' LIMIT 1"
            );

            return $st && (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }
}
