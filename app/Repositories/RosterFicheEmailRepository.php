<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Adresse e-mail rattachée à une fiche sans compte (table roster_fiche_emails, étape 5040). L'adresse n'est jamais dans
 * users.email tant que la personne n'a pas activé son compte : la fiche garde son adresse réservée.
 * Toute lecture et écriture filtre sur la communauté. Table absente (migration non lancée) : lectures vides, écriture refusée.
 */
class RosterFicheEmailRepository
{
    public function __construct(private ?PDO $pdo = null)
    {
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    public function available(): bool
    {
        try {
            $this->pdo()->query('SELECT 1 FROM roster_fiche_emails LIMIT 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function normalize(string $email): string
    {
        return strtolower(trim($email));
    }

    /** @return array<int, string> identifiant de fiche => adresse */
    public function mapForTenant(int $tenantId): array
    {
        try {
            $st = $this->pdo()->prepare('SELECT user_id, email FROM roster_fiche_emails WHERE tenant_id = ?');
            $st->execute([$tenantId]);
            $out = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $out[(int) $r['user_id']] = (string) $r['email'];
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    public function find(int $tenantId, int $userId): string
    {
        try {
            $st = $this->pdo()->prepare('SELECT email FROM roster_fiche_emails WHERE tenant_id = ? AND user_id = ? LIMIT 1');
            $st->execute([$tenantId, $userId]);
            $v = $st->fetchColumn();

            return is_string($v) ? $v : '';
        } catch (\Throwable) {
            return '';
        }
    }

    /** Identifiant de la fiche qui porte déjà cette adresse dans la communauté, ou 0. */
    public function ownerOf(int $tenantId, string $email): int
    {
        try {
            $st = $this->pdo()->prepare('SELECT user_id FROM roster_fiche_emails WHERE tenant_id = ? AND email = ? LIMIT 1');
            $st->execute([$tenantId, self::normalize($email)]);

            return (int) ($st->fetchColumn() ?: 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    public function set(int $tenantId, int $userId, string $email, ?int $actorId, string $now): bool
    {
        $email = self::normalize($email);
        if ($tenantId < 1 || $userId < 1 || $email === '') {
            return false;
        }
        try {
            $upd = $this->pdo()->prepare('UPDATE roster_fiche_emails SET email = ?, updated_by = ?, updated_at = ? WHERE tenant_id = ? AND user_id = ?');
            $upd->execute([$email, $actorId, $now, $tenantId, $userId]);
            if ($upd->rowCount() > 0 || $this->find($tenantId, $userId) !== '') {
                return true;
            }
            $ins = $this->pdo()->prepare('INSERT INTO roster_fiche_emails (user_id, tenant_id, email, updated_by, updated_at) VALUES (?, ?, ?, ?, ?)');
            $ins->execute([$userId, $tenantId, $email, $actorId, $now]);

            return true;
        } catch (\Throwable $e) {
            error_log('[roster-email] écriture impossible : ' . $e->getMessage());

            return false;
        }
    }

    public function remove(int $tenantId, int $userId): void
    {
        try {
            $this->pdo()->prepare('DELETE FROM roster_fiche_emails WHERE tenant_id = ? AND user_id = ?')->execute([$tenantId, $userId]);
        } catch (\Throwable) {
        }
    }
}
