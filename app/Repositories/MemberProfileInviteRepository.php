<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;
use Throwable;

/**
 * Liens « compléter son profil » (table member_profile_invites, étape database/steps/3260_member_profile_invites.php).
 *
 * Toutes les lectures et écritures sont bornées à une communauté. Les dates sont fournies par l'appelant
 * (format Y-m-d H:i:s, UTC du serveur) : SQL portable, testable sans horloge.
 */
class MemberProfileInviteRepository
{
    private const TABLE = 'member_profile_invites';

    private ?bool $exists = null;

    public function __construct(private ?PDO $pdo = null)
    {
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    public function tableExists(): bool
    {
        if ($this->exists !== null) {
            return $this->exists;
        }
        try {
            $this->pdo()->query('SELECT 1 FROM ' . self::TABLE . ' LIMIT 1');
            $this->exists = true;
        } catch (Throwable) {
            $this->exists = false;
        }

        return $this->exists;
    }

    /**
     * @param array{tenant_id:int, user_id:int, token_hash:string, token_cipher:?string, duration_hours:int, single_use:bool, created_by:?int, created_at:string, expires_at:string} $row
     */
    public function create(array $row): int
    {
        $st = $this->pdo()->prepare(
            'INSERT INTO ' . self::TABLE . ' (tenant_id, user_id, token_hash, token_cipher, duration_hours, single_use, created_by, created_at, expires_at, open_count, sent_count)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0)'
        );
        $st->execute([
            $row['tenant_id'],
            $row['user_id'],
            $row['token_hash'],
            $row['token_cipher'],
            $row['duration_hours'],
            $row['single_use'] ? 1 : 0,
            $row['created_by'],
            $row['created_at'],
            $row['expires_at'],
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    /** @return array<string, mixed>|null */
    public function findByTokenHash(string $hash): ?array
    {
        if (preg_match('/^[a-f0-9]{64}$/', $hash) !== 1) {
            return null;
        }
        $st = $this->pdo()->prepare('SELECT * FROM ' . self::TABLE . ' WHERE token_hash = ? LIMIT 1');
        $st->execute([$hash]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function find(int $tenantId, int $id): ?array
    {
        $st = $this->pdo()->prepare('SELECT * FROM ' . self::TABLE . ' WHERE id = ? AND tenant_id = ? LIMIT 1');
        $st->execute([$id, $tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * Liens d'un membre, avec le nom de l'auteur.
     *
     * @return list<array<string, mixed>>
     */
    public function listForUser(int $tenantId, int $userId, int $limit = 20): array
    {
        return $this->listWhere('i.tenant_id = ? AND i.user_id = ?', [$tenantId, $userId], max(1, min(100, $limit)));
    }

    /**
     * Liens de la communauté, avec le nom du destinataire et de l'auteur.
     *
     * @return list<array<string, mixed>>
     */
    public function listForTenant(int $tenantId, int $limit = 200): array
    {
        return $this->listWhere('i.tenant_id = ?', [$tenantId], max(1, min(500, $limit)));
    }

    /**
     * @param list<int> $params
     * @return list<array<string, mixed>>
     */
    private function listWhere(string $where, array $params, int $limit): array
    {
        $st = $this->pdo()->prepare(
            'SELECT i.*, u.display_name AS member_name, u.callsign AS member_callsign,
                    c.display_name AS creator_name, c.callsign AS creator_callsign
             FROM ' . self::TABLE . ' i
             LEFT JOIN users u ON u.id = i.user_id
             LEFT JOIN users c ON c.id = i.created_by
             WHERE ' . $where . '
             ORDER BY i.created_at DESC, i.id DESC
             LIMIT ' . $limit
        );
        $st->execute($params);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function recordOpen(int $id, string $now): void
    {
        $this->pdo()->prepare(
            'UPDATE ' . self::TABLE . ' SET first_opened_at = COALESCE(first_opened_at, ?), last_opened_at = ?, open_count = open_count + 1 WHERE id = ?'
        )->execute([$now, $now, $id]);
    }

    public function markAccountReady(int $id, string $now, bool $consume): void
    {
        $this->pdo()->prepare(
            'UPDATE ' . self::TABLE . ' SET account_ready_at = COALESCE(account_ready_at, ?)' . ($consume ? ', used_at = COALESCE(used_at, ?)' : '') . ' WHERE id = ?'
        )->execute($consume ? [$now, $now, $id] : [$now, $id]);
    }

    public function markCompleted(int $id, string $now): void
    {
        $this->pdo()->prepare('UPDATE ' . self::TABLE . ' SET completed_at = COALESCE(completed_at, ?) WHERE id = ?')->execute([$now, $id]);
    }

    public function extend(int $tenantId, int $id, string $expiresAt): bool
    {
        $st = $this->pdo()->prepare('UPDATE ' . self::TABLE . ' SET expires_at = ? WHERE id = ? AND tenant_id = ? AND revoked_at IS NULL');
        $st->execute([$expiresAt, $id, $tenantId]);

        return $st->rowCount() > 0;
    }

    public function revoke(int $tenantId, int $id, ?int $actorId, string $now): bool
    {
        $st = $this->pdo()->prepare('UPDATE ' . self::TABLE . ' SET revoked_at = ?, revoked_by = ? WHERE id = ? AND tenant_id = ? AND revoked_at IS NULL');
        $st->execute([$now, $actorId, $id, $tenantId]);

        return $st->rowCount() > 0;
    }

    /** Ferme les liens encore ouverts d'un membre (un seul lien vivant à la fois). */
    public function revokeOpenForUser(int $tenantId, int $userId, ?int $actorId, string $now): int
    {
        $st = $this->pdo()->prepare(
            'UPDATE ' . self::TABLE . ' SET revoked_at = ?, revoked_by = ? WHERE tenant_id = ? AND user_id = ? AND revoked_at IS NULL AND completed_at IS NULL AND expires_at > ?'
        );
        $st->execute([$now, $actorId, $tenantId, $userId, $now]);

        return $st->rowCount();
    }

    public function recordSent(int $id, string $channel, string $now): void
    {
        $this->pdo()->prepare(
            'UPDATE ' . self::TABLE . ' SET last_sent_at = ?, last_sent_channel = ?, sent_count = sent_count + 1 WHERE id = ?'
        )->execute([$now, substr($channel, 0, 16), $id]);
    }
}
