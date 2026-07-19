<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class TenantAlertRepository
{
    private PDO $pdo;

    private static ?bool $hasVisualColumns = null;
    private static ?bool $hasDisplayStyleColumn = null;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    private function hasVisualColumns(): bool
    {
        if (self::$hasVisualColumns !== null) {
            return self::$hasVisualColumns;
        }
        try {
            $stmt = $this->pdo->query(
                "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenant_alerts' AND COLUMN_NAME = 'accent_color' LIMIT 1"
            );
            self::$hasVisualColumns = (bool) ($stmt && $stmt->fetchColumn());
        } catch (\Throwable) {
            self::$hasVisualColumns = false;
        }

        return self::$hasVisualColumns;
    }

    private function hasDisplayStyleColumn(): bool
    {
        if (self::$hasDisplayStyleColumn !== null) {
            return self::$hasDisplayStyleColumn;
        }
        try {
            $stmt = $this->pdo->query(
                "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenant_alerts' AND COLUMN_NAME = 'display_style' LIMIT 1"
            );
            self::$hasDisplayStyleColumn = (bool) ($stmt && $stmt->fetchColumn());
        } catch (\Throwable) {
            self::$hasDisplayStyleColumn = false;
        }

        return self::$hasDisplayStyleColumn;
    }

    /** @return list<array<string, mixed>> */
    public function allForTenantOrdered(int $tenantId): array
    {
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM tenant_alerts WHERE tenant_id = ? ORDER BY sort_order ASC, id DESC');
            $stmt->execute([$tenantId]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            if ($e->getCode() === '42S02' || str_contains($e->getMessage(), "doesn't exist")) {
                return [];
            }
            throw $e;
        }
    }

    /** @return array<string, mixed>|null */
    public function findByIdForTenant(int $id, int $tenantId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tenant_alerts WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$id, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listActiveForTenantDisplay(int $tenantId): array
    {
        try {
            $now = date('Y-m-d H:i:s');
            $sql = 'SELECT * FROM tenant_alerts WHERE tenant_id = ? AND is_active = 1
                AND (starts_at IS NULL OR starts_at <= ?)
                AND (ends_at IS NULL OR ends_at >= ?)
                ORDER BY sort_order ASC, id ASC';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$tenantId, $now, $now]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            if ($e->getCode() === '42S02' || str_contains($e->getMessage(), "doesn't exist")) {
                return [];
            }
            throw $e;
        }
    }

    /**
     * Annonces communautaires dont la fenêtre de diffusion est terminée.
     *
     * @return list<array<string, mixed>>
     */
    public function listRecentlyEndedForTenant(int $tenantId, int $limit = 40): array
    {
        $limit = max(1, min(100, $limit));
        try {
            $now = date('Y-m-d H:i:s');
            $sql = 'SELECT * FROM tenant_alerts WHERE tenant_id = ?
                AND ends_at IS NOT NULL AND ends_at < ?
                AND (starts_at IS NULL OR starts_at <= ends_at)
                ORDER BY ends_at DESC, id DESC
                LIMIT ' . $limit;
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$tenantId, $now]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            if ($e->getCode() === '42S02' || str_contains($e->getMessage(), "doesn't exist")) {
                return [];
            }
            throw $e;
        }
    }

    /** @param array<string, mixed> $data */
    public function insert(int $tenantId, array $data): int
    {
        $displayStyle = \App\Support\AlertDisplayStyle::sanitizeTenant(
            isset($data['display_style']) ? (string) $data['display_style'] : null
        );
        if ($this->hasVisualColumns() && $this->hasDisplayStyleColumn()) {
            $stmt = $this->pdo->prepare('INSERT INTO tenant_alerts (
                tenant_id, kind, display_style, title, body, cta_label, cta_url, coupon_code,
                accent_color, icon_key, image_path, banner_path,
                starts_at, ends_at, sort_order, is_active, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
            $stmt->execute([
                $tenantId,
                $data['kind'] ?? 'info',
                $displayStyle,
                $data['title'] ?? '',
                $data['body'] ?? null,
                $data['cta_label'] ?? null,
                $data['cta_url'] ?? null,
                $data['coupon_code'] ?? null,
                $data['accent_color'] ?? null,
                $data['icon_key'] ?? null,
                $data['image_path'] ?? null,
                $data['banner_path'] ?? null,
                $data['starts_at'] ?? null,
                $data['ends_at'] ?? null,
                (int) ($data['sort_order'] ?? 0),
                !empty($data['is_active']) ? 1 : 0,
            ]);
        } elseif ($this->hasVisualColumns()) {
            $stmt = $this->pdo->prepare('INSERT INTO tenant_alerts (
                tenant_id, kind, title, body, cta_label, cta_url, coupon_code,
                accent_color, icon_key, image_path, banner_path,
                starts_at, ends_at, sort_order, is_active, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
            $stmt->execute([
                $tenantId,
                $data['kind'] ?? 'info',
                $data['title'] ?? '',
                $data['body'] ?? null,
                $data['cta_label'] ?? null,
                $data['cta_url'] ?? null,
                $data['coupon_code'] ?? null,
                $data['accent_color'] ?? null,
                $data['icon_key'] ?? null,
                $data['image_path'] ?? null,
                $data['banner_path'] ?? null,
                $data['starts_at'] ?? null,
                $data['ends_at'] ?? null,
                (int) ($data['sort_order'] ?? 0),
                !empty($data['is_active']) ? 1 : 0,
            ]);
        } elseif ($this->hasDisplayStyleColumn()) {
            $stmt = $this->pdo->prepare('INSERT INTO tenant_alerts (
                tenant_id, kind, display_style, title, body, cta_label, cta_url, coupon_code,
                starts_at, ends_at, sort_order, is_active, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
            $stmt->execute([
                $tenantId,
                $data['kind'] ?? 'info',
                $displayStyle,
                $data['title'] ?? '',
                $data['body'] ?? null,
                $data['cta_label'] ?? null,
                $data['cta_url'] ?? null,
                $data['coupon_code'] ?? null,
                $data['starts_at'] ?? null,
                $data['ends_at'] ?? null,
                (int) ($data['sort_order'] ?? 0),
                !empty($data['is_active']) ? 1 : 0,
            ]);
        } else {
            $stmt = $this->pdo->prepare('INSERT INTO tenant_alerts (
                tenant_id, kind, title, body, cta_label, cta_url, coupon_code,
                starts_at, ends_at, sort_order, is_active, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
            $stmt->execute([
                $tenantId,
                $data['kind'] ?? 'info',
                $data['title'] ?? '',
                $data['body'] ?? null,
                $data['cta_label'] ?? null,
                $data['cta_url'] ?? null,
                $data['coupon_code'] ?? null,
                $data['starts_at'] ?? null,
                $data['ends_at'] ?? null,
                (int) ($data['sort_order'] ?? 0),
                !empty($data['is_active']) ? 1 : 0,
            ]);
        }

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, int $tenantId, array $data): void
    {
        $displayStyle = \App\Support\AlertDisplayStyle::sanitizeTenant(
            isset($data['display_style']) ? (string) $data['display_style'] : null
        );
        if ($this->hasVisualColumns() && $this->hasDisplayStyleColumn()) {
            $stmt = $this->pdo->prepare('UPDATE tenant_alerts SET
                kind = ?, display_style = ?, title = ?, body = ?, cta_label = ?, cta_url = ?, coupon_code = ?,
                accent_color = ?, icon_key = ?, image_path = ?, banner_path = ?,
                starts_at = ?, ends_at = ?, sort_order = ?, is_active = ?, updated_at = NOW()
                WHERE id = ? AND tenant_id = ?');
            $stmt->execute([
                $data['kind'] ?? 'info',
                $displayStyle,
                $data['title'] ?? '',
                $data['body'] ?? null,
                $data['cta_label'] ?? null,
                $data['cta_url'] ?? null,
                $data['coupon_code'] ?? null,
                $data['accent_color'] ?? null,
                $data['icon_key'] ?? null,
                $data['image_path'] ?? null,
                $data['banner_path'] ?? null,
                $data['starts_at'] ?? null,
                $data['ends_at'] ?? null,
                (int) ($data['sort_order'] ?? 0),
                !empty($data['is_active']) ? 1 : 0,
                $id,
                $tenantId,
            ]);

            return;
        }

        if ($this->hasVisualColumns()) {
            $stmt = $this->pdo->prepare('UPDATE tenant_alerts SET
                kind = ?, title = ?, body = ?, cta_label = ?, cta_url = ?, coupon_code = ?,
                accent_color = ?, icon_key = ?, image_path = ?, banner_path = ?,
                starts_at = ?, ends_at = ?, sort_order = ?, is_active = ?, updated_at = NOW()
                WHERE id = ? AND tenant_id = ?');
            $stmt->execute([
                $data['kind'] ?? 'info',
                $data['title'] ?? '',
                $data['body'] ?? null,
                $data['cta_label'] ?? null,
                $data['cta_url'] ?? null,
                $data['coupon_code'] ?? null,
                $data['accent_color'] ?? null,
                $data['icon_key'] ?? null,
                $data['image_path'] ?? null,
                $data['banner_path'] ?? null,
                $data['starts_at'] ?? null,
                $data['ends_at'] ?? null,
                (int) ($data['sort_order'] ?? 0),
                !empty($data['is_active']) ? 1 : 0,
                $id,
                $tenantId,
            ]);

            return;
        }

        if ($this->hasDisplayStyleColumn()) {
            $stmt = $this->pdo->prepare('UPDATE tenant_alerts SET
                kind = ?, display_style = ?, title = ?, body = ?, cta_label = ?, cta_url = ?, coupon_code = ?,
                starts_at = ?, ends_at = ?, sort_order = ?, is_active = ?, updated_at = NOW()
                WHERE id = ? AND tenant_id = ?');
            $stmt->execute([
                $data['kind'] ?? 'info',
                $displayStyle,
                $data['title'] ?? '',
                $data['body'] ?? null,
                $data['cta_label'] ?? null,
                $data['cta_url'] ?? null,
                $data['coupon_code'] ?? null,
                $data['starts_at'] ?? null,
                $data['ends_at'] ?? null,
                (int) ($data['sort_order'] ?? 0),
                !empty($data['is_active']) ? 1 : 0,
                $id,
                $tenantId,
            ]);

            return;
        }

        $stmt = $this->pdo->prepare('UPDATE tenant_alerts SET
            kind = ?, title = ?, body = ?, cta_label = ?, cta_url = ?, coupon_code = ?,
            starts_at = ?, ends_at = ?, sort_order = ?, is_active = ?, updated_at = NOW()
            WHERE id = ? AND tenant_id = ?');
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
            !empty($data['is_active']) ? 1 : 0,
            $id,
            $tenantId,
        ]);
    }

    public function delete(int $id, int $tenantId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM tenant_alerts WHERE id = ? AND tenant_id = ?');
        $stmt->execute([$id, $tenantId]);
    }
}
