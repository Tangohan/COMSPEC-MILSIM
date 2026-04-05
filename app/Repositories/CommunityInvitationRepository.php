<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class CommunityInvitationRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function create(
        int $tenantId,
        string $email,
        string $tokenHash,
        int $invitedByUserId,
        ?int $roleId,
        \DateTimeInterface $expiresAt
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO community_invitations (tenant_id, email, token_hash, role_id, invited_by_user_id, status, expires_at, created_at)
             VALUES (?, ?, ?, ?, ?, \'pending\', ?, NOW())'
        );
        $stmt->execute([
            $tenantId,
            strtolower(trim($email)),
            $tokenHash,
            $roleId,
            $invitedByUserId,
            $expiresAt->format('Y-m-d H:i:s'),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function findValidByTokenHash(string $tokenHash): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM community_invitations WHERE token_hash = ? AND status = 'pending' AND expires_at > NOW() LIMIT 1"
        );
        $stmt->execute([$tokenHash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function markAccepted(int $id, int $acceptedUserId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE community_invitations SET status = 'accepted', accepted_user_id = ?, accepted_at = NOW(), updated_at = NOW() WHERE id = ?"
        );
        $stmt->execute([$acceptedUserId, $id]);
    }

    public function findByIdForTenant(int $id, int $tenantId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM community_invitations WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$id, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function markRevoked(int $id, int $tenantId): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE community_invitations SET status = 'revoked', updated_at = NOW() WHERE id = ? AND tenant_id = ? AND status = 'pending'"
        );
        $stmt->execute([$id, $tenantId]);

        return $stmt->rowCount() > 0;
    }

    /** @return list<array<string, mixed>> */
    public function listForTenant(int $tenantId, ?string $status = null): array
    {
        $sql = 'SELECT ci.*, u.email AS inviter_email FROM community_invitations ci
             INNER JOIN users u ON u.id = ci.invited_by_user_id
             WHERE ci.tenant_id = ?';
        $params = [$tenantId];
        if ($status !== null && $status !== '') {
            $sql .= ' AND ci.status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY ci.created_at DESC LIMIT 200';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function expireStale(): int
    {
        return (int) $this->pdo->exec(
            "UPDATE community_invitations SET status = 'expired', updated_at = NOW() WHERE status = 'pending' AND expires_at <= NOW()"
        );
    }
}
