<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class PlatformBanAppealRepository
{
    private ?bool $ready = null;

    public function __construct(private ?PDO $pdo = null)
    {
    }

    private function db(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    public function ready(): bool
    {
        if ($this->ready === null) {
            try {
                $this->ready = $this->db()->query('SELECT ban_id FROM platform_ban_appeals WHERE 1 = 0') !== false
                    && $this->db()->query('SELECT token_hash FROM platform_ban_appeal_links WHERE 1 = 0') !== false;
            } catch (\PDOException) {
                $this->ready = false;
            }
        }

        return $this->ready;
    }

    public function storeLink(int $banId, string $hash, int $expires): void
    {
        $this->db()->prepare('DELETE FROM platform_ban_appeal_links WHERE expires_at <= ?')->execute([gmdate('Y-m-d H:i:s')]);
        $this->db()->prepare('INSERT INTO platform_ban_appeal_links (token_hash, ban_id, expires_at) VALUES (?, ?, ?)')
            ->execute([$hash, $banId, gmdate('Y-m-d H:i:s', $expires)]);
    }

    /** @return array{ban_id: int, expires_at: string}|null */
    public function findValidLink(string $hash): ?array
    {
        $stmt = $this->db()->prepare('SELECT ban_id, expires_at FROM platform_ban_appeal_links WHERE token_hash = ? AND expires_at > ?');
        $stmt->execute([$hash, gmdate('Y-m-d H:i:s')]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function submit(int $banId, string $statement): bool
    {
        try {
            $stmt = $this->db()->prepare('INSERT INTO platform_ban_appeals (ban_id, statement, created_at) VALUES (?, ?, ?)');
            $stmt->execute([$banId, $statement, gmdate('Y-m-d H:i:s')]);

            return true;
        } catch (\PDOException $e) {
            // Un formulaire soumis deux fois garde la première contestation.
            if ((string) $e->getCode() === '23000' && $this->find($banId) !== null) {
                return false;
            }

            throw $e;
        }
    }

    /** @return array<string, mixed>|null */
    public function find(int $banId): ?array
    {
        $stmt = $this->db()->prepare('SELECT * FROM platform_ban_appeals WHERE ban_id = ?');
        $stmt->execute([$banId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function countPending(): int
    {
        return (int) $this->db()->query('SELECT COUNT(*) FROM platform_ban_appeals WHERE reviewed_at IS NULL')->fetchColumn();
    }

    /** @return list<array<string, mixed>> */
    public function listForReview(bool $reviewed = false, int $limit = 50, int $focus = 0): array
    {
        $condition = $reviewed ? 'IS NOT NULL' : 'IS NULL';
        $order = $reviewed ? 'a.reviewed_at DESC, a.ban_id DESC' : 'a.created_at ASC, a.ban_id ASC';
        if ($focus > 0) {
            $order = '(a.ban_id = ' . $focus . ') DESC, ' . $order;
        }
        $limit = max(1, min(100, $limit));
        $stmt = $this->db()->query(
            "SELECT a.*, b.email_norm, b.display_label, b.reason, b.rule_reference, b.public_reason, b.ends_at, b.lifted_at
             FROM platform_ban_appeals a JOIN platform_account_bans b ON b.id = a.ban_id
             WHERE a.reviewed_at {$condition} ORDER BY {$order} LIMIT {$limit}"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function decide(int $banId, int $actor, string $decision, string $reply): bool
    {
        $stmt = $this->db()->prepare(
            'UPDATE platform_ban_appeals SET decision = ?, public_reply = ?, reviewed_at = ?, reviewed_by = ?
             WHERE ban_id = ? AND reviewed_at IS NULL'
        );
        $stmt->execute([$decision, $reply, gmdate('Y-m-d H:i:s'), $actor, $banId]);

        return $stmt->rowCount() === 1;
    }
}
