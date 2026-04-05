<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class PlatformAlertRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    /** @return list<array<string, mixed>> */
    public function allOrdered(): array
    {
        try {
            $stmt = $this->pdo->query('SELECT * FROM platform_alerts ORDER BY sort_order ASC, id DESC');
            return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (\PDOException $e) {
            if ($e->getCode() === '42S02' || str_contains($e->getMessage(), "doesn't exist")) {
                return [];
            }
            throw $e;
        }
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM platform_alerts WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Alertes actives dans la fenêtre de dates (pour affichage public).
     *
     * @return list<array<string, mixed>>
     */
    public function listActiveForDisplay(): array
    {
        try {
            $now = date('Y-m-d H:i:s');
            $sql = 'SELECT * FROM platform_alerts WHERE is_active = 1
                AND (starts_at IS NULL OR starts_at <= ?)
                AND (ends_at IS NULL OR ends_at >= ?)
                ORDER BY sort_order ASC, id ASC';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$now, $now]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            if ($e->getCode() === '42S02' || str_contains($e->getMessage(), "doesn't exist")) {
                return [];
            }
            throw $e;
        }
    }

    /** @param array<string, mixed> $data */
    public function insert(array $data): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO platform_alerts (
            kind, title, body, cta_label, cta_url, coupon_code,
            starts_at, ends_at, sort_order, is_active, audience_json, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
        $stmt->execute([
            $data['kind'] ?? 'info',
            $data['title'] ?? '',
            $data['body'] ?? null,
            $data['cta_label'] ?? null,
            $data['cta_url'] ?? null,
            $data['coupon_code'] ?? null,
            $data['starts_at'] ?? null,
            $data['ends_at'] ?? null,
            (int) ($data['sort_order'] ?? 0),
            ! empty($data['is_active']) ? 1 : 0,
            $this->encodeAudience($data['audience_json'] ?? null),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $stmt = $this->pdo->prepare('UPDATE platform_alerts SET
            kind = ?, title = ?, body = ?, cta_label = ?, cta_url = ?, coupon_code = ?,
            starts_at = ?, ends_at = ?, sort_order = ?, is_active = ?, audience_json = ?, updated_at = NOW()
            WHERE id = ?');
        $stmt->execute([
            $data['kind'] ?? 'info',
            $data['title'] ?? '',
            $data['body'] ?? null,
            $data['cta_label'] ?? null,
            $data['cta_url'] ?? null,
            $data['coupon_code'] ?? null,
            $data['starts_at'] ?? null,
            $data['ends_at'] ?? null,
            (int) ($data['sort_order'] ?? 0),
            ! empty($data['is_active']) ? 1 : 0,
            $this->encodeAudience($data['audience_json'] ?? null),
            $id,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM platform_alerts WHERE id = ?');
        $stmt->execute([$id]);
    }

    private function encodeAudience(mixed $audience): ?string
    {
        if ($audience === null || $audience === '') {
            return json_encode([
                'guest' => true,
                'authenticated' => true,
                'free' => true,
                'paid' => true,
            ], JSON_UNESCAPED_UNICODE);
        }
        if (is_string($audience)) {
            $d = json_decode($audience, true);

            return is_array($d) ? json_encode($d, JSON_UNESCAPED_UNICODE) : null;
        }
        if (is_array($audience)) {
            return json_encode($audience, JSON_UNESCAPED_UNICODE);
        }

        return null;
    }
}
