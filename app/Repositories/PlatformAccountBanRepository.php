<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Bannissements décidés par l’administration du site (une ligne par décision).
 */
final class PlatformAccountBanRepository
{
    private ?PDO $pdo;

    private ?bool $ready = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    /** Crée la table au premier usage (le passage des migrations n’est pas automatique au déploiement). */
    public function ready(): bool
    {
        if ($this->ready !== null) {
            return $this->ready;
        }
        try {
            require_once dirname(__DIR__, 2) . '/bootstrap/platform_account_bans_migration.php';
            ob_start();
            run_platform_account_bans_migration($this->pdo());
            ob_end_clean();
            $st = $this->pdo()->query(
                "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'platform_account_bans' LIMIT 1"
            );
            $this->ready = (bool) ($st && $st->fetchColumn());
        } catch (\Throwable) {
            $this->ready = false;
        }

        return $this->ready;
    }

    /**
     * @param list<int> $indicatorIds
     * @param array<int, string> $statusSnapshot user_id => statut avant bannissement
     */
    public function create(
        string $email,
        ?int $userId,
        string $displayLabel,
        string $reason,
        array $indicatorIds,
        array $statusSnapshot,
        ?int $actorId
    ): int {
        if (!$this->ready()) {
            return 0;
        }
        $st = $this->pdo()->prepare(
            'INSERT INTO platform_account_bans
                (email_norm, user_id, display_label, reason, indicator_ids, status_snapshot, banned_by_user_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $st->execute([
            self::normalizeEmail($email),
            $userId,
            mb_substr($displayLabel, 0, 255),
            $reason,
            json_encode(array_values(array_map('intval', $indicatorIds))),
            json_encode($statusSnapshot, JSON_UNESCAPED_UNICODE),
            $actorId,
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    /** @return array<string, mixed>|null */
    public function findActiveForEmail(string $email): ?array
    {
        $email = self::normalizeEmail($email);
        if ($email === '' || !$this->ready()) {
            return null;
        }
        $st = $this->pdo()->prepare(
            'SELECT * FROM platform_account_bans WHERE email_norm = ? AND lifted_at IS NULL ORDER BY id DESC LIMIT 1'
        );
        $st->execute([$email]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Bannissements en cours indexés par e-mail normalisé.
     *
     * @param list<string> $emails
     * @return array<string, array<string, mixed>>
     */
    public function activeByEmails(array $emails): array
    {
        $emails = array_values(array_unique(array_filter(array_map([self::class, 'normalizeEmail'], $emails))));
        if ($emails === [] || !$this->ready()) {
            return [];
        }
        $in = implode(',', array_fill(0, count($emails), '?'));
        $st = $this->pdo()->prepare(
            "SELECT * FROM platform_account_bans WHERE lifted_at IS NULL AND email_norm IN ({$in}) ORDER BY id ASC"
        );
        $st->execute($emails);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $out[(string) $row['email_norm']] = $row;
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    public function listActive(int $limit = 200): array
    {
        if (!$this->ready()) {
            return [];
        }
        $limit = max(1, min(500, $limit));
        $st = $this->pdo()->query(
            'SELECT * FROM platform_account_bans WHERE lifted_at IS NULL ORDER BY created_at DESC, id DESC LIMIT ' . $limit
        );

        return $st ? ($st->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    public function countActive(): int
    {
        if (!$this->ready()) {
            return 0;
        }
        $st = $this->pdo()->query('SELECT COUNT(*) FROM platform_account_bans WHERE lifted_at IS NULL');

        return $st ? (int) $st->fetchColumn() : 0;
    }

    public function lift(int $id, ?int $actorId): bool
    {
        if ($id < 1 || !$this->ready()) {
            return false;
        }
        $st = $this->pdo()->prepare(
            'UPDATE platform_account_bans SET lifted_at = NOW(), lifted_by_user_id = ? WHERE id = ? AND lifted_at IS NULL'
        );
        $st->execute([$actorId, $id]);

        return $st->rowCount() > 0;
    }

    /** Prédicat SQL « e-mail actuellement banni » pour l’annuaire (null si la table n’existe pas). */
    public function sqlActiveBanExists(string $emailColumn): ?string
    {
        if (!$this->ready()) {
            return null;
        }

        return 'EXISTS (SELECT 1 FROM platform_account_bans __pab WHERE __pab.lifted_at IS NULL AND __pab.email_norm = LOWER(TRIM('
            . $emailColumn . ')))';
    }

    public static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }
}
