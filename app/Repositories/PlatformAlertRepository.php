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

    private function hasDismissibleColumn(): bool
    {
        static $ok = null;
        if ($ok !== null) {
            return $ok;
        }
        try {
            $st = $this->pdo->query("SHOW COLUMNS FROM platform_alerts LIKE 'dismissible'");
            $ok = $st && (bool) $st->fetchColumn();
        } catch (\Throwable) {
            $ok = false;
        }

        return $ok;
    }

    private function hasDisplayStyleColumn(): bool
    {
        static $ok = null;
        if ($ok !== null) {
            return $ok;
        }
        try {
            $st = $this->pdo->query("SHOW COLUMNS FROM platform_alerts LIKE 'display_style'");
            $ok = $st && (bool) $st->fetchColumn();
        } catch (\Throwable) {
            $ok = false;
        }

        return $ok;
    }

    /** @return list<array<string, mixed>> */
    public function allOrdered(): array
    {
        try {
            $stmt = $this->pdo->query('SELECT * FROM platform_alerts ORDER BY sort_order ASC, id DESC');
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

            return array_map([$this, 'normalizeRowDefaults'], $rows ?: []);
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

        return $row ? $this->normalizeRowDefaults($row) : null;
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

            return array_map([$this, 'normalizeRowDefaults'], $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
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
        $displayStyle = \App\Support\AlertDisplayStyle::sanitizePlatform(
            isset($data['display_style']) ? (string) $data['display_style'] : null
        );
        $cols = ['kind'];
        $vals = [$data['kind'] ?? 'info'];
        if ($this->hasDisplayStyleColumn()) {
            $cols[] = 'display_style';
            $vals[] = $displayStyle;
        }
        $cols = array_merge($cols, [
            'title', 'body', 'cta_label', 'cta_url', 'coupon_code',
            'starts_at', 'ends_at', 'sort_order', 'is_active',
        ]);
        $vals = array_merge($vals, [
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
        if ($this->hasDismissibleColumn()) {
            $cols[] = 'dismissible';
            $vals[] = !empty($data['dismissible']) ? 1 : 0;
        }
        $cols[] = 'audience_json';
        $vals[] = $this->encodeAudience($data['audience_json'] ?? null);
        $placeholders = implode(', ', array_fill(0, count($cols), '?'));
        $stmt = $this->pdo->prepare(
            'INSERT INTO platform_alerts (' . implode(', ', $cols) . ', created_at) VALUES (' . $placeholders . ', NOW())'
        );
        $stmt->execute($vals);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $displayStyle = \App\Support\AlertDisplayStyle::sanitizePlatform(
            isset($data['display_style']) ? (string) $data['display_style'] : null
        );
        $sets = ['kind = ?'];
        $vals = [$data['kind'] ?? 'info'];
        if ($this->hasDisplayStyleColumn()) {
            $sets[] = 'display_style = ?';
            $vals[] = $displayStyle;
        }
        $sets = array_merge($sets, [
            'title = ?', 'body = ?', 'cta_label = ?', 'cta_url = ?', 'coupon_code = ?',
            'starts_at = ?', 'ends_at = ?', 'sort_order = ?', 'is_active = ?',
        ]);
        $vals = array_merge($vals, [
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
        if ($this->hasDismissibleColumn()) {
            $sets[] = 'dismissible = ?';
            $vals[] = !empty($data['dismissible']) ? 1 : 0;
        }
        $sets[] = 'audience_json = ?';
        $vals[] = $this->encodeAudience($data['audience_json'] ?? null);
        $sets[] = 'updated_at = NOW()';
        $vals[] = $id;
        $stmt = $this->pdo->prepare(
            'UPDATE platform_alerts SET ' . implode(', ', $sets) . ' WHERE id = ?'
        );
        $stmt->execute($vals);
    }

    public function markEmailBroadcast(int $id, int $sentCount): void
    {
        if (!$this->hasDismissibleColumn()) {
            return;
        }
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE platform_alerts SET email_last_sent_at = NOW(), email_last_sent_count = ?, updated_at = NOW() WHERE id = ?'
            );
            $stmt->execute([max(0, $sentCount), $id]);
        } catch (\Throwable) {
        }
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM platform_alerts WHERE id = ?');
        $stmt->execute([$id]);
    }

    /** @param array<string, mixed> $row */
    private function normalizeRowDefaults(array $row): array
    {
        if (!array_key_exists('dismissible', $row)) {
            $row['dismissible'] = 1;
        }
        if (!array_key_exists('display_style', $row) || trim((string) ($row['display_style'] ?? '')) === '') {
            $row['display_style'] = \App\Support\AlertDisplayStyle::CLASSIC;
        } else {
            $row['display_style'] = \App\Support\AlertDisplayStyle::sanitizePlatform((string) $row['display_style']);
        }

        return $row;
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
