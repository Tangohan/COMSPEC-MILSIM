<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SilentSchemaMigration;
use PDO;

/**
 * Formules offertes (plan_gifts) et fenêtres à montrer aux gestionnaires (plan_gift_notices).
 */
final class PlanGiftRepository
{
    private ?PDO $pdo = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    /** Crée les tables si besoin (administration uniquement : la fenêtre se contente d'ignorer une table absente). */
    public function ensureSchema(): void
    {
        SilentSchemaMigration::run(base_path('bootstrap/plan_gift_migration.php'), $this->pdo());
    }

    public function create(
        int $tenantId,
        string $planSlug,
        string $planName,
        ?string $endsAt,
        ?string $messageFr,
        ?string $messageEn,
        ?int $grantedBy,
    ): int {
        $st = $this->pdo()->prepare(
            'INSERT INTO plan_gifts (tenant_id, plan_slug, plan_name, starts_at, ends_at, message_fr, message_en, granted_by_user_id, created_at)
             VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, NOW())'
        );
        $st->execute([$tenantId, $planSlug, $planName, $endsAt, $messageFr, $messageEn, $grantedBy]);

        return (int) $this->pdo()->lastInsertId();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $st = $this->pdo()->prepare('SELECT * FROM plan_gifts WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** Clôt les offres encore ouvertes d'une communauté (nouvelle offre ou retrait). */
    public function revokeOpenForTenant(int $tenantId, ?int $actorId): int
    {
        $st = $this->pdo()->prepare(
            'UPDATE plan_gifts SET revoked_at = NOW(), revoked_by_user_id = ?
             WHERE tenant_id = ? AND revoked_at IS NULL AND (ends_at IS NULL OR ends_at > NOW())'
        );
        $st->execute([$actorId, $tenantId]);

        return $st->rowCount();
    }

    public function revoke(int $id, ?int $actorId): bool
    {
        $st = $this->pdo()->prepare('UPDATE plan_gifts SET revoked_at = NOW(), revoked_by_user_id = ? WHERE id = ? AND revoked_at IS NULL');
        $st->execute([$actorId, $id]);

        return $st->rowCount() > 0;
    }

    /** @return list<array<string, mixed>> */
    public function listRecent(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        $sql = "SELECT g.*, t.name AS tenant_name,
                    COALESCE(NULLIF(u.callsign, ''), NULLIF(u.display_name, ''), u.email) AS granted_by_name,
                    (SELECT COUNT(*) FROM plan_gift_notices n WHERE n.gift_id = g.id) AS recipient_count,
                    (SELECT COUNT(*) FROM plan_gift_notices n WHERE n.gift_id = g.id AND n.email_sent_at IS NOT NULL) AS email_count,
                    (SELECT COUNT(*) FROM plan_gift_notices n WHERE n.gift_id = g.id AND n.dismissed_at IS NOT NULL) AS seen_count
                FROM plan_gifts g
                LEFT JOIN tenants t ON t.id = g.tenant_id
                LEFT JOIN users u ON u.id = g.granted_by_user_id
                ORDER BY g.created_at DESC, g.id DESC
                LIMIT {$limit}";

        return $this->pdo()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function addNotice(int $giftId, int $tenantId, int $userId, string $email): void
    {
        $st = $this->pdo()->prepare(
            'INSERT IGNORE INTO plan_gift_notices (gift_id, tenant_id, user_id, email, created_at) VALUES (?, ?, ?, ?, NOW())'
        );
        $st->execute([$giftId, $tenantId, $userId, $email]);
    }

    public function markEmailSent(int $giftId, int $userId): void
    {
        $st = $this->pdo()->prepare('UPDATE plan_gift_notices SET email_sent_at = NOW() WHERE gift_id = ? AND user_id = ?');
        $st->execute([$giftId, $userId]);
    }

    /**
     * Prochaine fenêtre à montrer à ce compte : offre ni retirée ni expirée, pas encore fermée.
     *
     * @return array<string, mixed>|null
     */
    public function pendingNoticeForUser(int $userId): ?array
    {
        $st = $this->pdo()->prepare(
            'SELECT n.id AS notice_id, g.*, t.name AS tenant_name
             FROM plan_gift_notices n
             INNER JOIN plan_gifts g ON g.id = n.gift_id
             LEFT JOIN tenants t ON t.id = g.tenant_id
             WHERE n.user_id = ? AND n.dismissed_at IS NULL AND g.revoked_at IS NULL
               AND (g.ends_at IS NULL OR g.ends_at > NOW())
             ORDER BY g.created_at DESC
             LIMIT 1'
        );
        $st->execute([$userId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function dismissNotice(int $noticeId, int $userId): bool
    {
        $st = $this->pdo()->prepare('UPDATE plan_gift_notices SET dismissed_at = NOW() WHERE id = ? AND user_id = ? AND dismissed_at IS NULL');
        $st->execute([$noticeId, $userId]);

        return $st->rowCount() > 0;
    }
}
