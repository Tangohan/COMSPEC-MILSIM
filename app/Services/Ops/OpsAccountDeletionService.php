<?php

declare(strict_types=1);

namespace App\Services\Ops;

use App\Core\Database;
use App\Repositories\UserRepository;
use App\Services\Account\AccountDeletionService;
use App\Services\Rbac\PlatformAdminFlag;
use PDO;

/**
 * Suppressions de comptes décidées depuis la console d'exploitation, avec retour en arrière.
 *
 * Programmer : le compte (et ceux qui partagent son adresse) est mis en sommeil tout de suite
 * (statut « inactive », plus de connexion) et sa suppression est datée. Pendant le délai, « Annuler »
 * rend le statut d'avant. À l'échéance, la tâche automatique « Anonymisation des comptes supprimés »
 * applique la suppression du site (même effacement que l'annuaire plateforme). « Supprimer maintenant »
 * l'applique sans attendre ; la console prend une sauvegarde de la base juste avant.
 */
final class OpsAccountDeletionService
{
    public const TABLE = 'platform_ops_account_deletions';
    public const DEFAULT_DAYS = 7;
    public const MAX_DAYS = 30;

    private bool $ensured = false;

    public function __construct(
        private UserRepository $users,
        private AccountDeletionService $deletion,
        private ?PDO $pdo = null,
    ) {}

    public static function clampDays(int $days): int
    {
        return max(1, min(self::MAX_DAYS, $days));
    }

    public function ensureTable(): void
    {
        if ($this->ensured) {
            return;
        }
        $this->pdo()->exec(
            'CREATE TABLE IF NOT EXISTS ' . self::TABLE . ' (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT UNSIGNED NOT NULL,
                tenant_id INT UNSIGNED NOT NULL,
                email VARCHAR(255) NOT NULL DEFAULT \'\',
                display_name VARCHAR(190) NOT NULL DEFAULT \'\',
                previous_json TEXT NULL,
                reason VARCHAR(500) NOT NULL DEFAULT \'\',
                scheduled_by INT UNSIGNED NULL,
                scheduled_at DATETIME NOT NULL,
                execute_after DATETIME NOT NULL,
                cancelled_at DATETIME NULL,
                cancelled_by INT UNSIGNED NULL,
                executed_at DATETIME NULL,
                executed_by INT UNSIGNED NULL,
                snapshot_id VARCHAR(80) NULL,
                PRIMARY KEY (id),
                KEY idx_pod_open (cancelled_at, executed_at),
                KEY idx_pod_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        $this->ensured = true;
    }

    /**
     * Comptes trouvés par adresse, nom, indicatif ou numéro (comptes supprimés exclus).
     *
     * @return list<array<string, mixed>>
     */
    public function search(string $query, int $limit = 20): array
    {
        $query = trim($query);
        if ($query === '' || mb_strlen($query) < 2) {
            return [];
        }
        $like = '%' . strtolower(str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query)) . '%';
        $idMatch = ctype_digit($query) ? (int) $query : 0;
        $lim = max(1, min(50, $limit));
        $st = $this->pdo()->prepare(
            "SELECT u.id, u.tenant_id, u.email, u.display_name, u.status, u.created_at, u.last_login_at,
                    u.deletion_scheduled_at, u.is_platform_admin, t.name AS tenant_name
             FROM users u LEFT JOIN tenants t ON t.id = u.tenant_id
             WHERE (u.id = ? OR LOWER(u.email) LIKE ? OR LOWER(u.display_name) LIKE ?)
               AND LOWER(u.email) NOT LIKE '%@deleted.invalid'
             ORDER BY u.last_login_at IS NULL, u.last_login_at DESC
             LIMIT {$lim}"
        );
        try {
            $st->execute([$idMatch, $like, $like]);
        } catch (\PDOException) {
            // Anciennes bases sans last_login_at / is_platform_admin.
            $st = $this->pdo()->prepare(
                "SELECT u.id, u.tenant_id, u.email, u.display_name, u.status, u.created_at, t.name AS tenant_name
                 FROM users u LEFT JOIN tenants t ON t.id = u.tenant_id
                 WHERE (u.id = ? OR LOWER(u.email) LIKE ? OR LOWER(u.display_name) LIKE ?)
                   AND LOWER(u.email) NOT LIKE '%@deleted.invalid'
                 ORDER BY u.id DESC LIMIT {$lim}"
            );
            $st->execute([$idMatch, $like, $like]);
        }

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array{ok: bool, message: string, id?: int}
     */
    public function schedule(int $userId, int $tenantId, int $actorId, int $days, string $reason = ''): array
    {
        $this->ensureTable();
        $target = $this->users->findById($userId, $tenantId);
        $refusal = $this->refusal($target, $actorId);
        if ($refusal !== null) {
            return ['ok' => false, 'message' => $refusal];
        }
        if ($this->openFor($userId) !== null) {
            return ['ok' => false, 'message' => 'Une suppression est déjà programmée pour ce compte.'];
        }
        $days = self::clampDays($days);
        $now = date('Y-m-d H:i:s');
        $due = date('Y-m-d H:i:s', time() + $days * 86400);

        $previous = [];
        foreach ($this->siblings($target) as $row) {
            $previous[] = [
                'id' => (int) $row['id'],
                'tenant_id' => (int) $row['tenant_id'],
                'status' => (string) ($row['status'] ?? 'active'),
                'deletion_requested_at' => $row['deletion_requested_at'] ?? null,
                'deletion_scheduled_at' => $row['deletion_scheduled_at'] ?? null,
            ];
            $this->users->update((int) $row['id'], (int) $row['tenant_id'], ['status' => 'inactive']);
            $this->users->requestDeletion((int) $row['id'], (int) $row['tenant_id'], $now, $due);
        }

        $st = $this->pdo()->prepare(
            'INSERT INTO ' . self::TABLE . ' (user_id, tenant_id, email, display_name, previous_json, reason, scheduled_by, scheduled_at, execute_after)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            $userId, $tenantId,
            mb_substr((string) ($target['email'] ?? ''), 0, 255),
            mb_substr((string) ($target['display_name'] ?? ''), 0, 190),
            json_encode($previous),
            mb_substr(trim($reason), 0, 500),
            $actorId > 0 ? $actorId : null,
            $now, $due,
        ]);

        return ['ok' => true, 'message' => 'Suppression programmée le ' . date('d/m/Y à H:i', strtotime($due)) . '.', 'id' => (int) $this->pdo()->lastInsertId()];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function cancel(int $id, int $actorId): array
    {
        $row = $this->find($id);
        if ($row === null || $row['cancelled_at'] !== null || $row['executed_at'] !== null) {
            return ['ok' => false, 'message' => 'Cette suppression n’est plus en attente.'];
        }
        $user = $this->users->findById((int) $row['user_id'], null);
        if ($user === null || AccountDeletionService::isAnonymizedUser($user)) {
            $this->markExecuted($id, 0, null);

            return ['ok' => false, 'message' => 'Trop tard : le compte a déjà été supprimé. Seule une restauration de sauvegarde peut le ramener.'];
        }
        $previous = json_decode((string) ($row['previous_json'] ?? '[]'), true);
        foreach (is_array($previous) ? $previous : [] as $p) {
            $pid = (int) ($p['id'] ?? 0);
            $ptid = (int) ($p['tenant_id'] ?? 0);
            if ($pid < 1 || $ptid < 1) {
                continue;
            }
            $this->users->update($pid, $ptid, ['status' => (string) ($p['status'] ?? 'active')]);
            if (!empty($p['deletion_scheduled_at'])) {
                // Une demande de la personne elle-même existait déjà : on la remet telle quelle.
                $this->users->requestDeletion($pid, $ptid, (string) $p['deletion_requested_at'], (string) $p['deletion_scheduled_at']);
            } else {
                $this->users->cancelDeletion($pid, $ptid);
            }
        }
        $st = $this->pdo()->prepare('UPDATE ' . self::TABLE . ' SET cancelled_at = NOW(), cancelled_by = ? WHERE id = ?');
        $st->execute([$actorId > 0 ? $actorId : null, $id]);

        return ['ok' => true, 'message' => 'Suppression annulée : le compte est rétabli comme avant.'];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function executeNow(int $id, int $actorId, ?string $snapshotId = null): array
    {
        $row = $this->find($id);
        if ($row === null || $row['cancelled_at'] !== null || $row['executed_at'] !== null) {
            return ['ok' => false, 'message' => 'Cette suppression n’est plus en attente.'];
        }
        $result = $this->deletion->softDeleteAccount((int) $row['user_id'], (int) $row['tenant_id'], $actorId);
        if (!$result['ok']) {
            return ['ok' => false, 'message' => 'La suppression a échoué : le compte n’a pas été touché.'];
        }
        $this->markExecuted($id, $actorId, $snapshotId);

        return ['ok' => true, 'message' => 'Compte supprimé (' . count($result['anonymized_user_ids']) . ' fiche(s)).'];
    }

    /**
     * Suppressions en attente, puis les 15 dernières traitées. Les comptes déjà effacés par la tâche
     * automatique passent en « supprimé » au passage.
     *
     * @return array{open: list<array<string, mixed>>, done: list<array<string, mixed>>}
     */
    public function overview(): array
    {
        $this->ensureTable();
        $open = $this->pdo()->query(
            'SELECT * FROM ' . self::TABLE . ' WHERE cancelled_at IS NULL AND executed_at IS NULL ORDER BY execute_after ASC LIMIT 100'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $still = [];
        foreach ($open as $row) {
            $user = $this->users->findById((int) $row['user_id'], null);
            if ($user === null || AccountDeletionService::isAnonymizedUser($user)) {
                $this->markExecuted((int) $row['id'], 0, null);
                continue;
            }
            $still[] = $row;
        }
        $done = $this->pdo()->query(
            'SELECT * FROM ' . self::TABLE . ' WHERE cancelled_at IS NOT NULL OR executed_at IS NOT NULL ORDER BY id DESC LIMIT 15'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return ['open' => $still, 'done' => $done];
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $this->ensureTable();
        $st = $this->pdo()->prepare('SELECT * FROM ' . self::TABLE . ' WHERE id = ?');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * Pourquoi ce compte ne peut pas être supprimé depuis la console (null = possible).
     *
     * @param array<string, mixed>|null $target
     */
    public function refusal(?array $target, int $actorId): ?string
    {
        if ($target === null) {
            return 'Compte introuvable.';
        }
        if ((int) ($target['id'] ?? 0) === $actorId) {
            return 'Vous ne pouvez pas supprimer votre propre compte d’ici.';
        }
        if (PlatformAdminFlag::isEnabled($target)) {
            return 'C’est un administrateur du site : retirez-lui d’abord ce rôle (Administrateurs du site).';
        }
        if (!empty($target['is_service_account'])) {
            return 'Compte technique : il ne se supprime pas d’ici.';
        }
        if (AccountDeletionService::isAnonymizedUser($target)) {
            return 'Ce compte est déjà supprimé.';
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    private function openFor(int $userId): ?array
    {
        $st = $this->pdo()->prepare('SELECT * FROM ' . self::TABLE . ' WHERE user_id = ? AND cancelled_at IS NULL AND executed_at IS NULL LIMIT 1');
        $st->execute([$userId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $target
     * @return list<array<string, mixed>>
     */
    private function siblings(array $target): array
    {
        $email = strtolower(trim((string) ($target['email'] ?? '')));
        $ids = $email !== '' ? $this->users->listIdsByEmailNormalized($email) : [];
        if (!in_array((int) $target['id'], $ids, true)) {
            $ids[] = (int) $target['id'];
        }
        $rows = [];
        foreach ($ids as $id) {
            $row = (int) $id === (int) $target['id'] ? $target : $this->users->findById((int) $id, null);
            if (is_array($row) && (int) ($row['tenant_id'] ?? 0) > 0) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private function markExecuted(int $id, int $actorId, ?string $snapshotId): void
    {
        $st = $this->pdo()->prepare('UPDATE ' . self::TABLE . ' SET executed_at = NOW(), executed_by = ?, snapshot_id = ? WHERE id = ? AND executed_at IS NULL');
        $st->execute([$actorId > 0 ? $actorId : null, $snapshotId, $id]);
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }
}
