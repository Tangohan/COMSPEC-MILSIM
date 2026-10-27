<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Core\Database;
use PDO;
use Throwable;

/**
 * Traces d’un compte rangées par adresse e-mail ou par compte de jeu, que la suppression
 * de la fiche `users` ne voyait pas.
 *
 * C’est par elles qu’un nouveau compte ouvert avec la même adresse récupérait l’ancien :
 * le compte de jeu Athena (`athena_accounts`, clé unique sur l’e-mail) était retrouvé à la
 * première liaison du jeu avec son mot de passe, ses appareils de confiance et ses
 * rattachements ; les rôles du site (`site_role_assignments`) sont attribués par adresse ;
 * les invitations et candidatures en attente aussi.
 *
 * Gardé exprès : les bannissements par adresse (`platform_account_bans`, une protection,
 * pas une donnée à rendre) et les paiements (`plan_payment_requests`, pièces comptables).
 */
final class AccountIdentityTraceEraser
{
    /**
     * Tables effacées par adresse : [table, colonne e-mail, condition supplémentaire].
     *
     * @var list<array{0: string, 1: string, 2: string}>
     */
    private const EMAIL_TABLES = [
        ['site_role_assignments', 'email_normalized', ''],
        ['community_invitations', 'email', "status = 'pending'"],
        ['enlistments', 'email', ''],
        ['user_identity_merges', 'email', ''],
        ['game_auth_otps', 'email', ''],
        ['login_attempts', 'email', ''],
        ['newsletter_subscribers', 'email', ''],
        ['plan_gift_notices', 'email', ''],
    ];

    /** Tables rattachées au compte de jeu Athena (`account_id`). */
    private const ACCOUNT_TABLES = [
        'game_sessions',
        'game_device_pairings',
        'atak_trusted_devices',
        'game_atak_pair_challenges',
        'account_tenant_memberships',
    ];

    /** @var array<string, bool> */
    private array $tables = [];

    public function __construct(private ?PDO $pdo = null) {}

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    /**
     * Efface tout ce qui est rangé sous cette adresse et sous ces fiches.
     *
     * @param list<int> $userIds fiches de la personne (pour les comptes de jeu rattachés par user_id)
     * @return array<string, int> lignes supprimées par table
     */
    public function erase(string $email, array $userIds = []): array
    {
        $email = strtolower(trim($email));
        $report = [];
        if ($email !== '' && !str_ends_with($email, '@deleted.invalid')) {
            foreach (self::EMAIL_TABLES as [$table, $column, $extra]) {
                $n = $this->exec(
                    'DELETE FROM `' . $table . '` WHERE LOWER(TRIM(`' . $column . '`)) = ?' . ($extra !== '' ? ' AND ' . $extra : ''),
                    [$email],
                    $table
                );
                if ($n > 0) {
                    $report[$table] = $n;
                }
            }
        }

        foreach ($this->gameAccountIds($email, $userIds) as $accountId) {
            foreach ($this->eraseGameAccount($accountId) as $table => $n) {
                $report[$table] = ($report[$table] ?? 0) + $n;
            }
        }

        return $report;
    }

    /**
     * À l’inscription : un compte de jeu resté sous cette adresse sans aucune fiche vivante
     * (suppression antérieure à ce service) est effacé avant d’ouvrir le nouveau compte.
     *
     * @return int comptes de jeu effacés
     */
    public function eraseOrphanGameAccount(string $email): int
    {
        $email = strtolower(trim($email));
        if ($email === '' || !$this->hasTable('athena_accounts')) {
            return 0;
        }
        $erased = 0;
        foreach ($this->rows('SELECT id FROM athena_accounts WHERE LOWER(TRIM(email)) = ?', [$email]) as $row) {
            $accountId = (int) $row['id'];
            if ($this->hasLiveMember($accountId, $email)) {
                continue;
            }
            $this->eraseGameAccount($accountId);
            $erased++;
        }

        return $erased;
    }

    /**
     * @param list<int> $userIds
     * @return list<int>
     */
    private function gameAccountIds(string $email, array $userIds): array
    {
        if (!$this->hasTable('athena_accounts')) {
            return [];
        }
        $ids = [];
        if ($email !== '' && !str_ends_with($email, '@deleted.invalid')) {
            foreach ($this->rows('SELECT id FROM athena_accounts WHERE LOWER(TRIM(email)) = ?', [$email]) as $r) {
                $ids[] = (int) $r['id'];
            }
        }
        $userIds = array_values(array_filter(array_map('intval', $userIds), static fn (int $id): bool => $id > 0));
        if ($userIds !== [] && $this->hasTable('account_tenant_memberships')) {
            $in = implode(',', array_fill(0, count($userIds), '?'));
            foreach ($this->rows("SELECT DISTINCT account_id FROM account_tenant_memberships WHERE user_id IN ({$in})", $userIds) as $r) {
                $accountId = (int) $r['account_id'];
                // Un compte de jeu partagé avec une autre fiche vivante (autre adresse) reste en place.
                if (!$this->hasLiveMember($accountId, $email, $userIds)) {
                    $ids[] = $accountId;
                }
            }
        }

        return array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
    }

    /**
     * @param list<int> $excludeUserIds
     */
    private function hasLiveMember(int $accountId, string $email, array $excludeUserIds = []): bool
    {
        if (!$this->hasTable('account_tenant_memberships')) {
            return false;
        }
        foreach ($this->rows(
            'SELECT u.* FROM account_tenant_memberships m INNER JOIN users u ON u.id = m.user_id WHERE m.account_id = ?',
            [$accountId]
        ) as $u) {
            if (in_array((int) $u['id'], $excludeUserIds, true)) {
                continue;
            }
            $userEmail = strtolower(trim((string) ($u['email'] ?? '')));
            if ($userEmail === '' || str_ends_with($userEmail, '@deleted.invalid') || !empty($u['deleted_at'])) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * @return array<string, int>
     */
    private function eraseGameAccount(int $accountId): array
    {
        $report = [];
        foreach (self::ACCOUNT_TABLES as $table) {
            $n = $this->exec('DELETE FROM `' . $table . '` WHERE account_id = ?', [$accountId], $table);
            if ($n > 0) {
                $report[$table] = $n;
            }
        }
        $n = $this->exec('DELETE FROM athena_accounts WHERE id = ?', [$accountId], 'athena_accounts');
        if ($n > 0) {
            $report['athena_accounts'] = $n;
        }

        return $report;
    }

    /**
     * @param list<mixed> $params
     */
    private function exec(string $sql, array $params, string $table): int
    {
        if (!$this->hasTable($table)) {
            return 0;
        }
        try {
            $st = $this->pdo()->prepare($sql);
            $st->execute($params);

            return $st->rowCount();
        } catch (Throwable $e) {
            error_log('[account_erase] ' . $table . ' : ' . $e->getMessage());

            return 0;
        }
    }

    /**
     * @param list<mixed> $params
     * @return list<array<string, mixed>>
     */
    private function rows(string $sql, array $params = []): array
    {
        try {
            $st = $this->pdo()->prepare($sql);
            $st->execute($params);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    private function hasTable(string $table): bool
    {
        if (!array_key_exists($table, $this->tables)) {
            try {
                $this->pdo()->query('SELECT 1 FROM `' . $table . '` LIMIT 1');
                $this->tables[$table] = true;
            } catch (Throwable) {
                $this->tables[$table] = false;
            }
        }

        return $this->tables[$table];
    }
}