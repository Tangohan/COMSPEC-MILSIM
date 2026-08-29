<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class UserAdvancedEditGrantRepository
{
    private const DURATION_HOURS = 24;

    private function pdo(): PDO
    {
        return Database::getPdo();
    }

    public function tableExists(): bool
    {
        try {
            $st = $this->pdo()->query(
                "SELECT 1 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_advanced_edit_grants' LIMIT 1"
            );

            return (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Grant actif pour un utilisateur (non révoqué, dans la fenêtre starts/ends).
     *
     * @return array<string, mixed>|null
     */
    public function findActiveForUser(int $tenantId, int $userId): ?array
    {
        if (!$this->tableExists() || $tenantId < 1 || $userId < 1) {
            return null;
        }
        $st = $this->pdo()->prepare(
            'SELECT g.*,
                    gu.display_name AS granter_display_name,
                    tu.display_name AS target_display_name,
                    tu.athena_identifier AS target_athena_identifier
             FROM user_advanced_edit_grants g
             LEFT JOIN users gu ON gu.id = g.granted_by
             LEFT JOIN users tu ON tu.id = g.user_id
             WHERE g.tenant_id = ?
               AND g.user_id = ?
               AND g.revoked_at IS NULL
               AND g.starts_at <= NOW()
               AND g.ends_at > NOW()
             ORDER BY g.ends_at DESC
             LIMIT 1'
        );
        $st->execute([$tenantId, $userId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function hasActiveGrant(int $tenantId, int $userId): bool
    {
        return $this->findActiveForUser($tenantId, $userId) !== null;
    }

    /**
     * Crée un grant 24 h. Révoque d’abord tout grant encore actif pour cet utilisateur.
     *
     * @return array{ok: bool, message: string, id?: int, ends_at?: string}
     */
    public function grant(int $tenantId, int $userId, int $grantedBy, ?string $reason = null): array
    {
        if (!$this->tableExists()) {
            return ['ok' => false, 'message' => 'Table des autorisations indisponible (migration manquante).'];
        }
        if ($tenantId < 1 || $userId < 1 || $grantedBy < 1) {
            return ['ok' => false, 'message' => 'Paramètres invalides.'];
        }

        $this->revokeActiveForUser($tenantId, $userId, $grantedBy, 'Remplacé par une nouvelle activation');

        $starts = date('Y-m-d H:i:s');
        $ends = date('Y-m-d H:i:s', time() + self::DURATION_HOURS * 3600);
        $reasonClean = $reason !== null ? mb_substr(trim($reason), 0, 500) : null;
        if ($reasonClean === '') {
            $reasonClean = null;
        }

        $st = $this->pdo()->prepare(
            'INSERT INTO user_advanced_edit_grants
                (tenant_id, user_id, granted_by, starts_at, ends_at, reason, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );
        $st->execute([$tenantId, $userId, $grantedBy, $starts, $ends, $reasonClean]);
        $id = (int) $this->pdo()->lastInsertId();

        return [
            'ok' => true,
            'message' => 'Mode édition avancée activé pour 24 heures.',
            'id' => $id,
            'ends_at' => $ends,
        ];
    }

    public function revoke(int $tenantId, int $grantId, int $revokedBy, ?string $note = null): bool
    {
        if (!$this->tableExists() || $grantId < 1) {
            return false;
        }
        $st = $this->pdo()->prepare(
            'UPDATE user_advanced_edit_grants
             SET revoked_at = NOW(), revoked_by = ?
             WHERE id = ? AND tenant_id = ? AND revoked_at IS NULL'
        );
        $st->execute([$revokedBy, $grantId, $tenantId]);

        return $st->rowCount() > 0;
    }

    public function revokeActiveForUser(int $tenantId, int $userId, int $revokedBy, ?string $note = null): int
    {
        if (!$this->tableExists()) {
            return 0;
        }
        $st = $this->pdo()->prepare(
            'UPDATE user_advanced_edit_grants
             SET revoked_at = NOW(), revoked_by = ?
             WHERE tenant_id = ? AND user_id = ? AND revoked_at IS NULL AND ends_at > NOW()'
        );
        $st->execute([$revokedBy, $tenantId, $userId]);

        return $st->rowCount();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listActiveForTenant(int $tenantId, int $limit = 100): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        $limit = max(1, min(200, $limit));
        $st = $this->pdo()->prepare(
            "SELECT g.*,
                    tu.display_name AS target_display_name,
                    tu.email AS target_email,
                    tu.athena_identifier AS target_athena_identifier,
                    gu.display_name AS granter_display_name
             FROM user_advanced_edit_grants g
             LEFT JOIN users tu ON tu.id = g.user_id
             LEFT JOIN users gu ON gu.id = g.granted_by
             WHERE g.tenant_id = ?
               AND g.revoked_at IS NULL
               AND g.starts_at <= NOW()
               AND g.ends_at > NOW()
             ORDER BY g.ends_at ASC
             LIMIT {$limit}"
        );
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Historique récent (actifs + expirés + révoqués).
     *
     * @return list<array<string, mixed>>
     */
    public function listRecentForTenant(int $tenantId, int $limit = 80): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        $limit = max(1, min(200, $limit));
        $st = $this->pdo()->prepare(
            "SELECT g.*,
                    tu.display_name AS target_display_name,
                    tu.email AS target_email,
                    tu.athena_identifier AS target_athena_identifier,
                    gu.display_name AS granter_display_name,
                    ru.display_name AS revoker_display_name
             FROM user_advanced_edit_grants g
             LEFT JOIN users tu ON tu.id = g.user_id
             LEFT JOIN users gu ON gu.id = g.granted_by
             LEFT JOIN users ru ON ru.id = g.revoked_by
             WHERE g.tenant_id = ?
             ORDER BY g.created_at DESC
             LIMIT {$limit}"
        );
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function durationHours(): int
    {
        return self::DURATION_HOURS;
    }
}
