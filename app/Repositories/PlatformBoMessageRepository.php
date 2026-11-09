<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Messages de la plateforme aux back-offices des communautés (platform_bo_messages) et leur suivi :
 * par communauté (vu, cliqué, fermé, acquitté : compteurs de l'administration) et par personne (masquage).
 * SQL portable (dates passées par PHP) pour être testé sur SQLite.
 */
final class PlatformBoMessageRepository
{
    private ?PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    private static function ts(?int $now = null): string
    {
        return date('Y-m-d H:i:s', $now ?? time());
    }

    /** @return list<array<string, mixed>> */
    public function listAll(): array
    {
        $st = $this->pdo()->query('SELECT * FROM platform_bo_messages ORDER BY id DESC');

        return $st ? ($st->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $st = $this->pdo()->prepare('SELECT * FROM platform_bo_messages WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** Messages actifs dont la fenêtre n'est pas terminée (compte pour la limite). */
    public function countActive(?int $exceptId = null, ?int $now = null): int
    {
        $st = $this->pdo()->prepare(
            'SELECT COUNT(*) FROM platform_bo_messages WHERE is_active = 1 AND id <> ? AND (ends_at IS NULL OR ends_at >= ?)'
        );
        $st->execute([(int) $exceptId, self::ts($now)]);

        return (int) $st->fetchColumn();
    }

    /** @param array<string, mixed> $d */
    public function create(array $d, ?int $actorId): int
    {
        $now = self::ts();
        $st = $this->pdo()->prepare(
            'INSERT INTO platform_bo_messages
            (level, title_fr, title_en, body_fr, body_en, cta_label_fr, cta_label_en, cta_url, starts_at, ends_at, is_active,
             target_mode, target_json, created_by, updated_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            $d['level'], $d['title_fr'], $d['title_en'], $d['body_fr'], $d['body_en'], $d['cta_label_fr'], $d['cta_label_en'],
            $d['cta_url'], $d['starts_at'], $d['ends_at'], $d['is_active'] ? 1 : 0, $d['target_mode'], $d['target_json'],
            $actorId, $actorId, $now, $now,
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    /** @param array<string, mixed> $d */
    public function update(int $id, array $d, ?int $actorId): void
    {
        $st = $this->pdo()->prepare(
            'UPDATE platform_bo_messages SET level = ?, title_fr = ?, title_en = ?, body_fr = ?, body_en = ?, cta_label_fr = ?,
             cta_label_en = ?, cta_url = ?, starts_at = ?, ends_at = ?, is_active = ?, target_mode = ?, target_json = ?,
             updated_by = ?, updated_at = ? WHERE id = ?'
        );
        $st->execute([
            $d['level'], $d['title_fr'], $d['title_en'], $d['body_fr'], $d['body_en'], $d['cta_label_fr'], $d['cta_label_en'],
            $d['cta_url'], $d['starts_at'], $d['ends_at'], $d['is_active'] ? 1 : 0, $d['target_mode'], $d['target_json'],
            $actorId, self::ts(), $id,
        ]);
    }

    public function setActive(int $id, bool $active, ?int $actorId): void
    {
        $st = $this->pdo()->prepare('UPDATE platform_bo_messages SET is_active = ?, updated_by = ?, updated_at = ? WHERE id = ?');
        $st->execute([$active ? 1 : 0, $actorId, self::ts(), $id]);
    }

    public function delete(int $id): void
    {
        foreach (['platform_bo_message_tenants', 'platform_bo_message_users', 'platform_bo_messages'] as $table) {
            $col = $table === 'platform_bo_messages' ? 'id' : 'message_id';
            $st = $this->pdo()->prepare('DELETE FROM ' . $table . ' WHERE ' . $col . ' = ?');
            $st->execute([$id]);
        }
    }

    /**
     * Messages diffusés maintenant (actifs, dans leur fenêtre). Le ciblage se vérifie ensuite avec
     * PlatformBoMessage::targets() : une communauté ne reçoit jamais un message qui ne la vise pas.
     *
     * @return list<array<string, mixed>>
     */
    public function listLive(?int $now = null): array
    {
        $ts = self::ts($now);
        $st = $this->pdo()->prepare(
            'SELECT * FROM platform_bo_messages WHERE is_active = 1
             AND (starts_at IS NULL OR starts_at <= ?) AND (ends_at IS NULL OR ends_at >= ?)
             ORDER BY id DESC'
        );
        $st->execute([$ts, $ts]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * États d'une communauté et d'une personne pour des messages.
     *
     * @param list<int> $ids
     * @return array<int, array{seen: bool, opened: bool, dismissed: bool, acked: bool}>
     */
    public function states(int $tenantId, int $userId, array $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            $out[$id] = ['seen' => false, 'opened' => false, 'dismissed' => false, 'acked' => false];
        }
        if ($ids === [] || $tenantId < 1 || $userId < 1) {
            return $out;
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $st = $this->pdo()->prepare(
            'SELECT message_id, seen_at, opened_at, dismissed_at FROM platform_bo_message_users WHERE tenant_id = ? AND user_id = ? AND message_id IN (' . $in . ')'
        );
        $st->execute(array_merge([$tenantId, $userId], $ids));
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $id = (int) $r['message_id'];
            $out[$id]['seen'] = !empty($r['seen_at']);
            $out[$id]['opened'] = !empty($r['opened_at']);
            $out[$id]['dismissed'] = !empty($r['dismissed_at']);
        }
        $st = $this->pdo()->prepare(
            'SELECT message_id, acked_at FROM platform_bo_message_tenants WHERE tenant_id = ? AND message_id IN (' . $in . ')'
        );
        $st->execute(array_merge([$tenantId], $ids));
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $out[(int) $r['message_id']]['acked'] = !empty($r['acked_at']);
        }

        return $out;
    }

    /** Vu (affiché dans le back-office) ; $opened : la personne a ouvert la page des messages. */
    public function markSeen(int $messageId, int $tenantId, int $userId, bool $opened): void
    {
        $now = self::ts();
        $this->touchTenant($messageId, $tenantId, 'seen_at', $now);
        $st = $this->pdo()->prepare('SELECT seen_at, opened_at FROM platform_bo_message_users WHERE message_id = ? AND user_id = ?');
        $st->execute([$messageId, $userId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $this->pdo()->prepare(
                'INSERT INTO platform_bo_message_users (message_id, user_id, tenant_id, seen_at, opened_at) VALUES (?, ?, ?, ?, ?)'
            )->execute([$messageId, $userId, $tenantId, $now, $opened ? $now : null]);

            return;
        }
        if (empty($row['seen_at']) || ($opened && empty($row['opened_at']))) {
            $this->pdo()->prepare(
                'UPDATE platform_bo_message_users SET seen_at = COALESCE(seen_at, ?), opened_at = COALESCE(opened_at, ?) WHERE message_id = ? AND user_id = ?'
            )->execute([$now, $opened ? $now : null, $messageId, $userId]);
        }
    }

    public function dismiss(int $messageId, int $tenantId, int $userId): void
    {
        $now = self::ts();
        $this->markSeen($messageId, $tenantId, $userId, false);
        $this->pdo()->prepare(
            'UPDATE platform_bo_message_users SET dismissed_at = COALESCE(dismissed_at, ?) WHERE message_id = ? AND user_id = ?'
        )->execute([$now, $messageId, $userId]);
        $this->touchTenant($messageId, $tenantId, 'dismissed_at', $now);
    }

    public function click(int $messageId, int $tenantId): void
    {
        $this->touchTenant($messageId, $tenantId, 'clicked_at', self::ts());
    }

    public function ack(int $messageId, int $tenantId, int $userId): void
    {
        $this->touchTenant($messageId, $tenantId, 'acked_at', self::ts());
        $this->pdo()->prepare(
            'UPDATE platform_bo_message_tenants SET acked_by = COALESCE(acked_by, ?) WHERE message_id = ? AND tenant_id = ?'
        )->execute([$userId, $messageId, $tenantId]);
    }

    /** Pose une date une seule fois (la première) sur la ligne message × communauté. */
    private function touchTenant(int $messageId, int $tenantId, string $column, string $now): void
    {
        if (!in_array($column, ['seen_at', 'clicked_at', 'dismissed_at', 'acked_at'], true)) {
            return;
        }
        $st = $this->pdo()->prepare('SELECT 1 FROM platform_bo_message_tenants WHERE message_id = ? AND tenant_id = ?');
        $st->execute([$messageId, $tenantId]);
        if (!$st->fetchColumn()) {
            $this->pdo()->prepare(
                'INSERT INTO platform_bo_message_tenants (message_id, tenant_id, ' . $column . ') VALUES (?, ?, ?)'
            )->execute([$messageId, $tenantId, $now]);

            return;
        }
        $this->pdo()->prepare(
            'UPDATE platform_bo_message_tenants SET ' . $column . ' = COALESCE(' . $column . ', ?) WHERE message_id = ? AND tenant_id = ?'
        )->execute([$now, $messageId, $tenantId]);
    }

    /**
     * Compteurs par message : nombre de communautés ayant vu, cliqué, fermé, acquitté. Rien de personnel.
     *
     * @return array<int, array{seen: int, clicked: int, dismissed: int, acked: int}>
     */
    public function stats(): array
    {
        $st = $this->pdo()->query(
            'SELECT message_id,
                SUM(CASE WHEN seen_at IS NOT NULL THEN 1 ELSE 0 END) AS seen,
                SUM(CASE WHEN clicked_at IS NOT NULL THEN 1 ELSE 0 END) AS clicked,
                SUM(CASE WHEN dismissed_at IS NOT NULL THEN 1 ELSE 0 END) AS dismissed,
                SUM(CASE WHEN acked_at IS NOT NULL THEN 1 ELSE 0 END) AS acked
             FROM platform_bo_message_tenants GROUP BY message_id'
        );
        $out = [];
        foreach ($st ? ($st->fetchAll(PDO::FETCH_ASSOC) ?: []) : [] as $r) {
            $out[(int) $r['message_id']] = [
                'seen' => (int) $r['seen'],
                'clicked' => (int) $r['clicked'],
                'dismissed' => (int) $r['dismissed'],
                'acked' => (int) $r['acked'],
            ];
        }

        return $out;
    }
}
