<?php

declare(strict_types=1);

namespace App\Services\Community;

use App\Core\Database;
use App\Repositories\AthenaAccountRepository;
use App\Repositories\AtakAdminControlRepository;
use App\Repositories\AtakRealismRepository;
use App\Repositories\UserRepository;
use App\Services\Game\GameAuthService;
use App\Support\SteamId;
use PDO;
use Throwable;

/**
 * Fait suivre la liaison ATAK / Steam vers la communauté définitive :
 * Steam sur la fiche conservée, compte Athena rattaché, sessions des
 * communautés quittées fermées, terminal re-homé si possible.
 */
final class AtakCommunityMigrationService
{
    public function __construct(
        private UserRepository $users,
        private GameAuthService $gameAuth,
        private AthenaAccountRepository $accounts,
        private AtakAdminControlRepository $control,
        private AtakRealismRepository $realism,
    ) {}

    /**
     * @param list<array{tenant_id: int, user_id?: int}> $leftCandidates
     * @return array{ok: bool, steam_moved: bool, sessions_revoked: int, terminals_moved: int}
     */
    public function migrateToDefinitiveCommunity(int $keepUserId, int $keepTenantId, array $leftCandidates): array
    {
        $result = [
            'ok' => true,
            'steam_moved' => false,
            'sessions_revoked' => 0,
            'terminals_moved' => 0,
        ];
        if ($keepUserId < 1 || $keepTenantId < 1) {
            return $result;
        }

        $leftTenantIds = [];
        $relatedUserIds = [$keepUserId];
        foreach ($leftCandidates as $candidate) {
            $tid = (int) ($candidate['tenant_id'] ?? 0);
            $uid = (int) ($candidate['user_id'] ?? 0);
            if ($tid > 0 && $tid !== $keepTenantId) {
                $leftTenantIds[$tid] = true;
            }
            if ($uid > 0) {
                $relatedUserIds[] = $uid;
            }
        }
        $relatedUserIds = array_values(array_unique($relatedUserIds));
        $leftTenantIds = array_keys($leftTenantIds);

        $steam = $this->harvestSteamId($relatedUserIds);
        $keepUser = $this->users->findById($keepUserId, $keepTenantId)
            ?? $this->users->findById($keepUserId, null);
        if ($keepUser === null) {
            return $result;
        }

        try {
            $account = $this->gameAuth->ensureAccountForUser($keepUserId, $keepTenantId);
        } catch (Throwable) {
            $account = null;
        }

        if ($steam !== null && $account !== null) {
            try {
                $this->bindSteamToKeepUser($keepUser, (int) $account['id'], $steam, $keepTenantId);
                $result['steam_moved'] = true;
            } catch (Throwable $e) {
                error_log('[atak.community_migrate] steam : ' . $e->getMessage());
            }
        } elseif ($account !== null) {
            // Sans Steam : le rattachement Athena à la communauté conservée suffit.
        }

        foreach ($leftTenantIds as $leftTenantId) {
            $result['sessions_revoked'] += $this->detachUserFromTenantGame($keepUserId, $leftTenantId);
            foreach ($relatedUserIds as $uid) {
                if ($uid === $keepUserId) {
                    continue;
                }
                $result['sessions_revoked'] += $this->detachUserFromTenantGame($uid, $leftTenantId);
            }
        }

        // Sessions encore ouvertes sur d’autres tenants pour ce compte (hors liste candidats).
        $result['sessions_revoked'] += $this->deactivateForeignGameMemberships($keepUserId, $keepTenantId);

        try {
            $this->gameAuth->ensureAccountForUser($keepUserId, $keepTenantId);
        } catch (Throwable) {
        }

        $result['terminals_moved'] = $this->rehomeTerminals($keepUserId, $keepTenantId, $leftTenantIds, $keepUser);

        return $result;
    }

    /**
     * Départ volontaire d’une seule communauté : coupe l’accès jeu sur ce tenant.
     */
    public function detachFromCommunity(int $userId, int $tenantId): int
    {
        if ($userId < 1 || $tenantId < 1) {
            return 0;
        }

        return $this->detachUserFromTenantGame($userId, $tenantId);
    }

    /**
     * @param list<int> $userIds
     */
    private function harvestSteamId(array $userIds): ?string
    {
        $found = null;
        foreach ($userIds as $userId) {
            if ($userId < 1) {
                continue;
            }
            $row = $this->users->findById($userId, null);
            if ($row === null) {
                continue;
            }
            $steam = SteamId::normalize((string) ($row['steam_id'] ?? ''));
            if ($steam === null) {
                continue;
            }
            // Premier Steam non vide (fiche conservée en tête de liste).
            if ($found === null) {
                $found = $steam;
            }
        }

        return $found;
    }

    /**
     * @param array<string, mixed> $keepUser
     */
    private function bindSteamToKeepUser(array $keepUser, int $accountId, string $steam, int $keepTenantId): void
    {
        $keepUserId = (int) ($keepUser['id'] ?? 0);
        if ($keepUserId < 1 || $accountId < 1 || $steam === '') {
            return;
        }
        $pdo = Database::getPdo();

        if ($this->tableExists($pdo, 'users')) {
            $st = $pdo->prepare('SELECT id FROM users WHERE steam_id = ? AND id <> ?');
            $st->execute([$steam, $keepUserId]);
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) ?: [] as $otherId) {
                $oid = (int) $otherId;
                $pdo->prepare('UPDATE users SET steam_id = NULL WHERE id = ? AND steam_id = ?')
                    ->execute([$oid, $steam]);
                $this->control->revokeGameSessions(0, $oid);
            }
        }

        if ($this->tableExists($pdo, 'athena_accounts')) {
            $st = $pdo->prepare('SELECT id FROM athena_accounts WHERE steam_id = ? AND id <> ?');
            $st->execute([$steam, $accountId]);
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) ?: [] as $otherAcc) {
                $aid = (int) $otherAcc;
                $pdo->prepare('UPDATE athena_accounts SET steam_id = NULL WHERE id = ?')->execute([$aid]);
                if ($this->tableExists($pdo, 'game_sessions')) {
                    $pdo->prepare('UPDATE game_sessions SET revoked_at = NOW() WHERE account_id = ? AND revoked_at IS NULL')
                        ->execute([$aid]);
                }
            }
        }

        $current = SteamId::normalize((string) ($keepUser['steam_id'] ?? ''));
        if ($current !== $steam) {
            $this->users->update($keepUserId, $keepTenantId, ['steam_id' => $steam]);
        }
        $this->accounts->replaceSteamId($accountId, $steam);
    }

    private function detachUserFromTenantGame(int $userId, int $tenantId): int
    {
        $revoked = 0;
        $pdo = Database::getPdo();
        if (!$this->tableExists($pdo, 'account_tenant_memberships') || !$this->tableExists($pdo, 'game_sessions')) {
            return $this->control->revokeGameSessions($tenantId, $userId);
        }

        try {
            $pdo->prepare(
                "UPDATE account_tenant_memberships SET status = 'inactive'
                 WHERE user_id = ? AND tenant_id = ?"
            )->execute([$userId, $tenantId]);
        } catch (Throwable) {
        }

        $revoked += $this->control->revokeGameSessions($tenantId, $userId);

        try {
            if ($this->tableExists($pdo, 'game_device_pairings')) {
                $st = $pdo->prepare(
                    'SELECT DISTINCT account_id FROM account_tenant_memberships WHERE user_id = ? AND tenant_id = ?'
                );
                $st->execute([$userId, $tenantId]);
                $accountIds = array_values(array_unique(array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: [])));
                if ($accountIds !== []) {
                    // Ne révoque les pairings appareil que si le compte n’a plus d’autre communauté active.
                    foreach ($accountIds as $accountId) {
                        if ($accountId < 1) {
                            continue;
                        }
                        $active = $pdo->prepare(
                            "SELECT 1 FROM account_tenant_memberships
                             WHERE account_id = ? AND status = 'active' LIMIT 1"
                        );
                        $active->execute([$accountId]);
                        if ($active->fetchColumn()) {
                            continue;
                        }
                        $pdo->prepare(
                            'UPDATE game_device_pairings SET revoked_at = NOW()
                             WHERE account_id = ? AND revoked_at IS NULL'
                        )->execute([$accountId]);
                    }
                }
            }
        } catch (Throwable) {
        }

        return $revoked;
    }

    private function deactivateForeignGameMemberships(int $keepUserId, int $keepTenantId): int
    {
        $pdo = Database::getPdo();
        if (!$this->tableExists($pdo, 'account_tenant_memberships')) {
            return 0;
        }
        $revoked = 0;
        try {
            $st = $pdo->prepare(
                "SELECT tenant_id FROM account_tenant_memberships
                 WHERE user_id = ? AND status = 'active' AND tenant_id <> ?"
            );
            $st->execute([$keepUserId, $keepTenantId]);
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) ?: [] as $tid) {
                $revoked += $this->detachUserFromTenantGame($keepUserId, (int) $tid);
            }
        } catch (Throwable) {
        }

        return $revoked;
    }

    /**
     * @param list<int> $leftTenantIds
     * @param array<string, mixed> $keepUser
     */
    private function rehomeTerminals(int $keepUserId, int $keepTenantId, array $leftTenantIds, array $keepUser): int
    {
        if ($leftTenantIds === [] || !$this->tableExists(Database::getPdo(), 'atak_terminals')) {
            return 0;
        }
        $pdo = Database::getPdo();
        $moved = 0;
        try {
            $marks = implode(',', array_fill(0, count($leftTenantIds), '?'));
            $st = $pdo->prepare(
                "SELECT id, tenant_id, terminal_uid, terminal_label, terminal_type, platform_label,
                        operator_callsign, status
                 FROM atak_terminals
                 WHERE user_id = ? AND tenant_id IN ({$marks})
                   AND (status IS NULL OR status NOT IN ('revoked', 'lost', 'inactive'))"
            );
            $st->execute(array_merge([$keepUserId], $leftTenantIds));
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return 0;
        }

        $callsign = trim((string) ($keepUser['callsign'] ?? ''));
        foreach ($rows as $row) {
            $uid = trim((string) ($row['terminal_uid'] ?? ''));
            if ($uid === '') {
                continue;
            }
            try {
                $this->realism->upsertTerminal($keepTenantId, [
                    'terminal_uid' => $uid,
                    'user_id' => $keepUserId,
                    'status' => 'active',
                    'terminal_label' => (string) ($row['terminal_label'] ?? ('ATAK ' . ($callsign !== '' ? $callsign : '#' . $keepUserId))),
                    'terminal_type' => (string) ($row['terminal_type'] ?? 'phone'),
                    'platform_label' => $row['platform_label'] ?? null,
                    'operator_callsign' => $callsign !== '' ? $callsign : ($row['operator_callsign'] ?? null),
                ]);
                $pdo->prepare(
                    "UPDATE atak_terminals
                     SET user_id = NULL, status = 'inactive', updated_at = UTC_TIMESTAMP()
                     WHERE id = ? AND tenant_id = ?"
                )->execute([(int) $row['id'], (int) $row['tenant_id']]);
                $moved++;
            } catch (Throwable $e) {
                error_log('[atak.community_migrate] terminal ' . $uid . ' : ' . $e->getMessage());
            }
        }

        return $moved;
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        try {
            $st = $pdo->query(
                "SELECT 1 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = " . $pdo->quote($table) . " LIMIT 1"
            );

            return (bool) ($st && $st->fetchColumn());
        } catch (Throwable) {
            return false;
        }
    }
}
