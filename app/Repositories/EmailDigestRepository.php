<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SilentSchemaMigration;
use PDO;

/**
 * Alertes d'exploitation en attente du récapitulatif e-mail (voir App\Services\Email\MailBudget).
 */
class EmailDigestRepository
{
    private static bool $schemaReady = false;

    private function pdo(): PDO
    {
        return Database::getPdo();
    }

    public function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }
        SilentSchemaMigration::run(base_path('bootstrap/email_digest_migration.php'), $this->pdo());
        self::$schemaReady = true;
    }

    public function add(string $recipient, string $eventCode, string $subject, string $bodyText, ?int $tenantId): void
    {
        $this->ensureSchema();
        $this->pdo()->prepare(
            'INSERT INTO email_digest_items (recipient, event_code, subject, body_text, tenant_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())'
        )->execute([
            mb_substr($recipient, 0, 255),
            mb_substr($eventCode, 0, 80),
            mb_substr($subject, 0, 500),
            mb_substr($bodyText, 0, 4000),
            $tenantId,
        ]);
    }

    /**
     * Destinataires dont la plus ancienne alerte en attente a au moins $minutes minutes.
     *
     * @return list<string>
     */
    public function recipientsDue(int $minutes): array
    {
        $this->ensureSchema();
        $st = $this->pdo()->prepare(
            'SELECT recipient FROM email_digest_items WHERE digested_at IS NULL
             GROUP BY recipient HAVING MIN(created_at) <= DATE_SUB(NOW(), INTERVAL ? MINUTE)'
        );
        $st->execute([max(0, $minutes)]);

        return array_values(array_map('strval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []));
    }

    /** @return list<array<string, mixed>> */
    public function pendingFor(string $recipient, int $limit = 500): array
    {
        $this->ensureSchema();
        $st = $this->pdo()->prepare(
            'SELECT id, event_code, subject, body_text, tenant_id, created_at FROM email_digest_items
             WHERE digested_at IS NULL AND recipient = ? ORDER BY id ASC LIMIT ' . max(1, min(2000, $limit))
        );
        $st->execute([$recipient]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @param list<int> $ids */
    public function markDigested(array $ids): void
    {
        $ids = array_values(array_filter(array_map('intval', $ids), static fn (int $i): bool => $i > 0));
        if ($ids === []) {
            return;
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $this->pdo()->prepare("UPDATE email_digest_items SET digested_at = NOW() WHERE id IN ($in)")->execute($ids);
    }

    public function countPending(): int
    {
        try {
            $this->ensureSchema();

            return (int) $this->pdo()->query('SELECT COUNT(*) FROM email_digest_items WHERE digested_at IS NULL')->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    /** Purge des alertes déjà envoyées depuis plus de 30 jours. */
    public function purgeOld(int $days = 30): void
    {
        $this->ensureSchema();
        $this->pdo()->prepare('DELETE FROM email_digest_items WHERE digested_at IS NOT NULL AND digested_at < DATE_SUB(NOW(), INTERVAL ? DAY)')
            ->execute([max(1, $days)]);
    }
}
