<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class BlockedIndicatorRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
        require_once dirname(__DIR__, 2) . '/bootstrap/platform_unit_commander_migration.php';
        ensure_platform_unit_commander_schema($this->pdo);
        require_once dirname(__DIR__, 2) . '/bootstrap/moderation_granular_sanctions_migration.php';
        ensure_moderation_granular_sanctions_schema($this->pdo);
    }

    public static function hashEmail(string $email): string
    {
        return hash('sha256', strtolower(trim($email)));
    }

    public static function hashIp(string $ip): string
    {
        $ip = trim($ip);
        if ($ip === '') {
            return hash('sha256', '');
        }
        if (str_contains($ip, ',')) {
            $ip = trim(explode(',', $ip)[0]);
        }

        return hash('sha256', $ip);
    }

    public function isEmailBlockedForTenant(int $tenantId, string $email): bool
    {
        return $this->isEmailBlocked($tenantId, $email) || $this->isEmailBlockedGlobally($email);
    }

    public function isEmailBlocked(int $tenantId, string $email): bool
    {
        $h = self::hashEmail($email);

        return $this->rowActiveExists('email', $h, 'tenant', $tenantId);
    }

    public function isEmailBlockedGlobally(string $email): bool
    {
        $h = self::hashEmail($email);

        return $this->rowActiveExists('email', $h, 'global', null);
    }

    public function isIpBlockedForContext(?int $tenantId, string $ip): bool
    {
        if ($this->isIpBlockedGlobally($ip)) {
            return true;
        }
        if ($tenantId !== null && $tenantId > 0) {
            return $this->isIpBlocked($tenantId, $ip);
        }

        return false;
    }

    public function isIpBlocked(int $tenantId, string $ip): bool
    {
        $h = self::hashIp($ip);

        return $this->rowActiveExists('ip', $h, 'tenant', $tenantId);
    }

    public function isIpBlockedGlobally(string $ip): bool
    {
        $h = self::hashIp($ip);

        return $this->rowActiveExists('ip', $h, 'global', null);
    }

    private function rowActiveExists(string $indicatorType, string $valueHash, string $scope, ?int $tenantId): bool
    {
        if ($scope === 'global') {
            $st = $this->pdo->prepare(
                "SELECT 1 FROM blocked_indicators WHERE indicator_type = ? AND value_hash = ? AND scope = 'global'
                 AND revoked_at IS NULL AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1"
            );
            $st->execute([$indicatorType, $valueHash]);
        } else {
            $st = $this->pdo->prepare(
                "SELECT 1 FROM blocked_indicators WHERE indicator_type = ? AND value_hash = ? AND scope = 'tenant' AND tenant_id = ?
                 AND revoked_at IS NULL AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1"
            );
            $st->execute([$indicatorType, $valueHash, $tenantId]);
        }

        return (bool) $st->fetchColumn();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForTenant(int $tenantId, int $limit = 200): array
    {
        $lim = max(1, min(500, $limit));
        $st = $this->pdo->prepare(
            "SELECT * FROM blocked_indicators WHERE scope = 'tenant' AND tenant_id = ? ORDER BY id DESC LIMIT {$lim}"
        );
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Entrées encore actives (non révoquées, non expirées).
     *
     * @return list<array<string, mixed>>
     */
    public function listActiveForTenant(int $tenantId, int $limit = 200): array
    {
        $lim = max(1, min(500, $limit));
        $st = $this->pdo->prepare(
            "SELECT * FROM blocked_indicators WHERE scope = 'tenant' AND tenant_id = ?
             AND revoked_at IS NULL AND (expires_at IS NULL OR expires_at > NOW())
             ORDER BY id DESC LIMIT {$lim}"
        );
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Blocages tenant actifs liés au portail recrutement (motifs créés par la modération automatique ou l’équipe).
     *
     * @return list<array<string, mixed>>
     */
    public function listActiveTenantPortalRecruitmentRelated(int $tenantId, int $limit = 150): array
    {
        $lim = max(1, min(500, $limit));
        $st = $this->pdo->prepare(
            "SELECT * FROM blocked_indicators WHERE scope = 'tenant' AND tenant_id = ?
             AND revoked_at IS NULL AND (expires_at IS NULL OR expires_at > NOW())
             AND reason IS NOT NULL AND reason LIKE ?
             ORDER BY id DESC LIMIT {$lim}"
        );
        $st->execute([$tenantId, '%Portail recrutement%']);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Lève tous les blocages e-mail actifs pour une empreinte donnée sur une communauté.
     */
    public function revokeActiveTenantEmailHash(int $tenantId, string $emailHash): int
    {
        if ($tenantId < 1 || $emailHash === '') {
            return 0;
        }
        $st = $this->pdo->prepare(
            "UPDATE blocked_indicators SET revoked_at = NOW()
             WHERE scope = 'tenant' AND tenant_id = ? AND indicator_type = 'email'
             AND value_hash = ? AND revoked_at IS NULL"
        );
        $st->execute([$tenantId, $emailHash]);

        return $st->rowCount();
    }

    /**
     * Lève les blocages réseau actifs dont le motif indique un refus côté candidat sur le portail (assistance site).
     */
    public function revokeActiveTenantIpPortalCandidateViolations(int $tenantId): int
    {
        if ($tenantId < 1) {
            return 0;
        }
        $st = $this->pdo->prepare(
            "UPDATE blocked_indicators SET revoked_at = NOW()
             WHERE scope = 'tenant' AND tenant_id = ? AND indicator_type = 'ip'
             AND revoked_at IS NULL
             AND reason IS NOT NULL AND reason LIKE ? AND reason LIKE ?"
        );
        $st->execute([$tenantId, '%Portail recrutement%', '%(candidat)%']);

        return $st->rowCount();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listGlobal(int $limit = 200): array
    {
        $lim = max(1, min(500, $limit));
        $st = $this->pdo->query(
            "SELECT * FROM blocked_indicators WHERE scope = 'global' ORDER BY id DESC LIMIT {$lim}"
        );

        return $st ? ($st->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listActiveGlobal(int $limit = 200): array
    {
        $lim = max(1, min(500, $limit));
        $st = $this->pdo->query(
            "SELECT * FROM blocked_indicators WHERE scope = 'global'
             AND revoked_at IS NULL AND (expires_at IS NULL OR expires_at > NOW())
             ORDER BY id DESC LIMIT {$lim}"
        );

        return $st ? ($st->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    public function add(
        string $indicatorType,
        string $valueHash,
        string $scope,
        ?int $tenantId,
        ?string $reason,
        ?\DateTimeInterface $expiresAt,
        ?int $createdByUserId,
        ?int $moderationActionId
    ): int {
        $st = $this->pdo->prepare(
            'INSERT INTO blocked_indicators (tenant_id, indicator_type, value_hash, scope, reason, expires_at, created_at, revoked_at, created_by_user_id, moderation_action_id)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), NULL, ?, ?)'
        );
        $st->execute([
            $scope === 'global' ? null : $tenantId,
            $indicatorType,
            $valueHash,
            $scope,
            $reason,
            $expiresAt ? $expiresAt->format('Y-m-d H:i:s') : null,
            $createdByUserId,
            $moderationActionId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        $st = $this->pdo->prepare('SELECT * FROM blocked_indicators WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function revoke(int $id, ?int $tenantIdForScope): bool
    {
        if ($tenantIdForScope !== null) {
            $st = $this->pdo->prepare(
                'UPDATE blocked_indicators SET revoked_at = NOW() WHERE id = ? AND tenant_id = ? AND revoked_at IS NULL'
            );
            $st->execute([$id, $tenantIdForScope]);
        } else {
            $st = $this->pdo->prepare(
                "UPDATE blocked_indicators SET revoked_at = NOW() WHERE id = ? AND scope = 'global' AND revoked_at IS NULL"
            );
            $st->execute([$id]);
        }

        return $st->rowCount() > 0;
    }

    public function revokeByModerationActionId(int $moderationActionId): void
    {
        if ($moderationActionId < 1) {
            return;
        }
        $st = $this->pdo->prepare('UPDATE blocked_indicators SET revoked_at = NOW() WHERE moderation_action_id = ? AND revoked_at IS NULL');
        $st->execute([$moderationActionId]);
    }

    public function hasActiveEmailTenant(int $tenantId, string $emailHash): bool
    {
        return $this->rowActiveExists('email', $emailHash, 'tenant', $tenantId);
    }
}
