<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Dernier rappel « créez votre compte » envoyé à chaque personne sans compte (roster_account_reminders).
 * Toute lecture et écriture filtre sur la communauté. Table absente (étape non lancée) : lectures vides, écriture ignorée.
 */
class RosterAccountReminderRepository
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

    /** @return array<int, array{last_reminded_at: string, reminder_count: int}> indexé par identifiant de personne */
    public function forTenant(int $tenantId): array
    {
        if ($tenantId < 1) {
            return [];
        }
        try {
            $st = $this->pdo()->prepare('SELECT user_id, last_reminded_at, reminder_count FROM roster_account_reminders WHERE tenant_id = ?');
            $st->execute([$tenantId]);
            $out = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $out[(int) $r['user_id']] = ['last_reminded_at' => (string) $r['last_reminded_at'], 'reminder_count' => (int) $r['reminder_count']];
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    public function record(int $tenantId, int $userId, ?int $actorId, string $channel, string $at): void
    {
        if ($tenantId < 1 || $userId < 1) {
            return;
        }
        $channel = mb_substr(trim($channel), 0, 16, 'UTF-8');
        $actor = $actorId !== null && $actorId > 0 ? $actorId : null;
        try {
            $up = $this->pdo()->prepare(
                'UPDATE roster_account_reminders SET last_reminded_at = ?, reminder_count = reminder_count + 1, last_channel = ?, last_actor_user_id = ?
                 WHERE tenant_id = ? AND user_id = ?'
            );
            $up->execute([$at, $channel, $actor, $tenantId, $userId]);
            if ($up->rowCount() > 0) {
                return;
            }
            $exists = $this->pdo()->prepare('SELECT 1 FROM roster_account_reminders WHERE tenant_id = ? AND user_id = ?');
            $exists->execute([$tenantId, $userId]);
            if ($exists->fetchColumn() !== false) {
                return;
            }
            $this->pdo()->prepare(
                'INSERT INTO roster_account_reminders (tenant_id, user_id, last_reminded_at, reminder_count, last_channel, last_actor_user_id) VALUES (?, ?, ?, 1, ?, ?)'
            )->execute([$tenantId, $userId, $at, $channel, $actor]);
        } catch (\Throwable $e) {
            error_log('[roster-account] rappel non enregistré : ' . $e->getMessage());
        }
    }
}
