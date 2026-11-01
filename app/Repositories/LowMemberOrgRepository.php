<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Journal des rappels aux communautés restées à un ou deux membres (org_low_member_reminders, un envoi par palier)
 * et suivi de l'administration (org_low_member_watch : liste à supprimer, alertes, communauté gardée).
 * Dates écrites en UTC (gmdate).
 */
class LowMemberOrgRepository
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

    public function schemaReady(): bool
    {
        if ($this->ready !== null) {
            return $this->ready;
        }
        try {
            $this->pdo()->query('SELECT 1 FROM org_low_member_reminders WHERE 1 = 0');
            $this->pdo()->query('SELECT 1 FROM org_low_member_watch WHERE 1 = 0');
            $this->ready = true;
        } catch (\Throwable) {
            $this->ready = false;
        }

        return $this->ready;
    }

    /**
     * @return array<int, array<string, string>> tenant_id => [palier => sent_at]
     */
    public function remindersByTenant(): array
    {
        $out = [];
        $stmt = $this->pdo()->query('SELECT tenant_id, stage, sent_at FROM org_low_member_reminders ORDER BY sent_at ASC');
        foreach (($stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : []) as $row) {
            $out[(int) $row['tenant_id']][(string) $row['stage']] = (string) $row['sent_at'];
        }

        return $out;
    }

    /**
     * Réserve le palier avant tout envoi : la clé unique (tenant_id, stage) empêche un second envoi, même si deux
     * passages se chevauchent. false = palier déjà réservé.
     */
    public function claimReminder(int $tenantId, string $stage, int $memberCount, int $now): bool
    {
        try {
            $stmt = $this->pdo()->prepare(
                'INSERT INTO org_low_member_reminders (tenant_id, stage, member_count, recipients, emails, sent_at) VALUES (?, ?, ?, 0, 0, ?)'
            );

            return $stmt->execute([$tenantId, $stage, max(0, $memberCount), gmdate('Y-m-d H:i:s', $now)]);
        } catch (\PDOException) {
            return false;
        }
    }

    public function completeReminder(int $tenantId, string $stage, int $recipients, int $emails): void
    {
        $stmt = $this->pdo()->prepare('UPDATE org_low_member_reminders SET recipients = ?, emails = ? WHERE tenant_id = ? AND stage = ?');
        $stmt->execute([max(0, $recipients), max(0, $emails), $tenantId, $stage]);
    }

    /**
     * @return array<int, array<string, mixed>> tenant_id => ligne de suivi
     */
    public function watchByTenant(): array
    {
        $out = [];
        $stmt = $this->pdo()->query('SELECT tenant_id, listed_at, admin_notified_at, admin_emailed_at, kept_at, kept_by FROM org_low_member_watch');
        foreach (($stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : []) as $row) {
            $out[(int) $row['tenant_id']] = $row;
        }

        return $out;
    }

    private function ensureWatchRow(int $tenantId): void
    {
        $stmt = $this->pdo()->prepare('SELECT 1 FROM org_low_member_watch WHERE tenant_id = ?');
        $stmt->execute([$tenantId]);
        if ($stmt->fetchColumn() !== false) {
            return;
        }
        try {
            $this->pdo()->prepare('INSERT INTO org_low_member_watch (tenant_id) VALUES (?)')->execute([$tenantId]);
        } catch (\PDOException) {
            // Ligne créée entre-temps par un autre passage.
        }
    }

    /** Première entrée dans la liste à supprimer (la date n'est jamais réécrite). */
    public function markListed(int $tenantId, int $now): void
    {
        $this->ensureWatchRow($tenantId);
        $this->pdo()->prepare('UPDATE org_low_member_watch SET listed_at = ? WHERE tenant_id = ? AND listed_at IS NULL')
            ->execute([gmdate('Y-m-d H:i:s', $now), $tenantId]);
    }

    /**
     * Sortie de la liste (membres arrivés, communauté gardée) : une nouvelle entrée sera de nouveau signalée.
     * admin_emailed_at est gardé : il sert à espacer les résumés e-mail d'au moins une semaine.
     */
    public function clearListed(int $tenantId): void
    {
        $this->pdo()->prepare('UPDATE org_low_member_watch SET listed_at = NULL, admin_notified_at = NULL WHERE tenant_id = ?')
            ->execute([$tenantId]);
    }

    /** @param list<int> $tenantIds */
    public function markAdminNotified(array $tenantIds, int $now): void
    {
        $this->stamp('admin_notified_at', $tenantIds, $now);
    }

    /** @param list<int> $tenantIds */
    public function markAdminEmailed(array $tenantIds, int $now): void
    {
        $this->stamp('admin_emailed_at', $tenantIds, $now);
    }

    /** @param list<int> $tenantIds */
    private function stamp(string $column, array $tenantIds, int $now): void
    {
        $stmt = $this->pdo()->prepare('UPDATE org_low_member_watch SET ' . $column . ' = ? WHERE tenant_id = ?');
        foreach (array_unique(array_map('intval', $tenantIds)) as $id) {
            $stmt->execute([gmdate('Y-m-d H:i:s', $now), $id]);
        }
    }

    /** Dernier résumé e-mail envoyé à l'administration (UTC), toutes communautés confondues. */
    public function lastAdminEmailAt(): ?string
    {
        $stmt = $this->pdo()->query('SELECT MAX(admin_emailed_at) FROM org_low_member_watch');
        $v = $stmt ? $stmt->fetchColumn() : null;

        return is_string($v) && $v !== '' ? $v : null;
    }

    /**
     * Aperçu de la liste à supprimer, tel que posé par le dernier passage planifié (lecture légère pour /admin).
     *
     * @return array{count: int, names: list<string>}
     */
    public function listedSummary(int $limit = 3): array
    {
        $stmt = $this->pdo()->query(
            'SELECT t.name FROM org_low_member_watch w INNER JOIN tenants t ON t.id = w.tenant_id
             WHERE w.listed_at IS NOT NULL AND w.kept_at IS NULL ORDER BY w.listed_at ASC'
        );
        $names = $stmt ? array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN)) : [];

        return ['count' => count($names), 'names' => array_slice($names, 0, max(0, $limit))];
    }

    public function keep(int $tenantId, ?int $userId, int $now): void
    {
        $this->ensureWatchRow($tenantId);
        $this->pdo()->prepare('UPDATE org_low_member_watch SET kept_at = ?, kept_by = ? WHERE tenant_id = ?')
            ->execute([gmdate('Y-m-d H:i:s', $now), $userId !== null && $userId > 0 ? $userId : null, $tenantId]);
    }

    public function unkeep(int $tenantId): void
    {
        $this->pdo()->prepare('UPDATE org_low_member_watch SET kept_at = NULL, kept_by = NULL WHERE tenant_id = ?')
            ->execute([$tenantId]);
    }

    /**
     * Administrateurs de la plateforme (notification du site) : id et communauté de rattachement.
     *
     * @return list<array{id: int, tenant_id: int}>
     */
    public function platformAdminAccounts(): array
    {
        try {
            $stmt = $this->pdo()->query(
                "SELECT id, tenant_id FROM users WHERE is_platform_admin = 1 AND (status IS NULL OR status = 'active')
                 AND email NOT LIKE '%@deleted.invalid' AND email NOT LIKE '%@merged.invalid'"
            );
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach (($stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : []) as $row) {
            if ((int) $row['id'] > 0 && (int) $row['tenant_id'] > 0) {
                $out[] = ['id' => (int) $row['id'], 'tenant_id' => (int) $row['tenant_id']];
            }
        }

        return $out;
    }
}
