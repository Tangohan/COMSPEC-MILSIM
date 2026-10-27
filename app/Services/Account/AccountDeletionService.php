<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Core\Database;
use App\Repositories\PersonnelExtrasRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\RecruitmentPresetRepository;
use App\Repositories\UserLegalIdentityRepository;
use App\Repositories\UserProfileDisplaySettingsRepository;
use App\Repositories\UserProfileRepository;
use App\Repositories\UserRepository;
use App\Services\Community\CommunityHandoverService;
use PDO;

/**
 * Suppression de compte (annuaire plateforme + self-service RGPD après le délai).
 *
 * Une suppression est définitive ({@see self::eraseAccount}) :
 * 1. les communautés dont la personne est propriétaire passent au membre suivant, ou sont
 *    supprimées si elle y était seule ({@see CommunityHandoverService}) ;
 * 2. tout ce qui est rangé par e-mail ou par compte de jeu disparaît (compte de jeu Athena,
 *    rôles du site, invitations, candidatures : {@see AccountIdentityTraceEraser}) — c’est
 *    par là qu’un nouveau compte ouvert avec la même adresse récupérait l’ancien ;
 * 3. données personnelles et traces de jeu effacées, puis la fiche `users` elle-même : ce
 *    que d’autres membres partagent (messages de forum, comptes rendus…) passe au compte
 *    technique « Ancien membre » de la communauté ({@see AccountPurgeService}).
 */
final class AccountDeletionService
{
    public const GRACE_PERIOD_DAYS = 14;

    public const DELETED_DISPLAY_NAME = 'Compte supprimé';

    /** Libellé neutre pour l’historique rattaché au compte technique par communauté. */
    public const HISTORY_GHOST_DISPLAY_NAME = 'Ancien membre';

    /**
     * Compte technique d’archivage (une fiche par communauté) — invisible des annuaires.
     *
     * @param array<string, mixed>|null $user
     */
    public static function isTenantHistoryGhostUser(?array $user): bool
    {
        if (!is_array($user) || $user === []) {
            return false;
        }
        $email = strtolower(trim((string) ($user['email'] ?? '')));

        return preg_match('/^history\.\d+@internal\.local$/', $email) === 1;
    }

    /**
     * Compte déjà anonymisé (« Compte supprimé » / @deleted.invalid).
     *
     * @param array<string, mixed>|null $user
     */
    public static function isAnonymizedUser(?array $user): bool
    {
        if (!is_array($user) || $user === []) {
            return false;
        }
        $email = strtolower(trim((string) ($user['email'] ?? '')));
        if ($email !== '' && str_ends_with($email, '@deleted.invalid')) {
            return true;
        }
        $name = trim((string) ($user['display_name'] ?? ''));
        if ($name !== '' && strcasecmp($name, self::DELETED_DISPLAY_NAME) === 0) {
            return true;
        }
        if (!empty($user['deleted_at'])) {
            return true;
        }

        return false;
    }

    public function __construct(
        private UserRepository $users,
        private UserProfileRepository $userProfiles,
        private UserLegalIdentityRepository $userLegalIdentities,
        private PersonnelProfileRepository $personnelProfiles,
        private UserProfileDisplaySettingsRepository $displaySettings,
        private PersonnelExtrasRepository $personnelExtras,
        private RecruitmentPresetRepository $recruitmentPresets,
        private ?CommunityHandoverService $handover = null,
        private ?AccountIdentityTraceEraser $traceEraser = null,
        private ?AccountPurgeService $purge = null,
    ) {}

    private function handover(): CommunityHandoverService
    {
        return $this->handover ??= new CommunityHandoverService();
    }

    private function traceEraser(): AccountIdentityTraceEraser
    {
        return $this->traceEraser ??= new AccountIdentityTraceEraser();
    }

    private function purgeService(): AccountPurgeService
    {
        return $this->purge ??= new AccountPurgeService();
    }

    /**
     * Ce que deviendraient les communautés de la personne si son compte était supprimé.
     *
     * @return list<array{tenant_id: int, tenant_name: string, action: string, successor_id: ?int, successor_name: ?string}>
     */
    public function communityHandoverPlan(int $userId, int $tenantId): array
    {
        try {
            return $this->handover()->plan($this->siblingIds($userId, $tenantId));
        } catch (\Throwable) {
            return [];
        }
    }

    public function requestDeletion(int $userId, int $tenantId): bool
    {
        $now = date('Y-m-d H:i:s');
        $scheduled = date('Y-m-d H:i:s', strtotime('+' . self::GRACE_PERIOD_DAYS . ' days'));

        return $this->users->requestDeletion($userId, $tenantId, $now, $scheduled);
    }

    public function cancelDeletion(int $userId, int $tenantId): bool
    {
        return $this->users->cancelDeletion($userId, $tenantId);
    }

    /**
     * Suppression depuis l’annuaire plateforme : le compte et toutes les fiches partageant
     * son adresse sont supprimés définitivement ({@see self::eraseAccount}).
     *
     * @return array{ok: bool, anonymized_user_ids: list<int>, handover?: list<array<string, mixed>>}
     */
    public function softDeleteAccount(int $userId, int $tenantId, int $actorUserId): array
    {
        $erased = $this->eraseAccount($userId, $tenantId, $actorUserId);

        return [
            'ok' => $erased['ok'],
            'anonymized_user_ids' => $erased['user_ids'],
            'handover' => $erased['handover'],
        ];
    }

    /**
     * Soft-delete limité à une communauté : n’anonymise que la fiche user_id/tenant_id.
     * Les autres appartenance (même e-mail) restent intactes.
     *
     * @return array{ok: bool, anonymized_user_ids: list<int>}
     */
    public function softDeleteMembership(int $userId, int $tenantId, int $actorUserId): array
    {
        $target = $this->users->findById($userId, $tenantId);
        if ($target === null) {
            return ['ok' => false, 'anonymized_user_ids' => []];
        }

        // Propriétaire de cette communauté : le rôle passe au membre suivant avant le départ.
        $handover = [];
        try {
            $handover = $this->handover()->handOver([$userId], $tenantId);
        } catch (\Throwable $e) {
            error_log('[account_deletion] reprise de la communauté #' . $tenantId . ' : ' . $e->getMessage());
        }

        $this->users->communityMemberships()->leave($userId, $tenantId);
        $this->users->communityMemberships()->upsertProfile($userId, $tenantId, [
            'status' => 'inactive',
            'display_name' => self::DELETED_DISPLAY_NAME,
            'callsign' => null,
        ]);
        $this->revokeAthenaAccess($userId, $tenantId);
        // Le joueur quitte la communauté : ses traces de jeu y disparaissent (carte, registre, statistiques).
        $this->purgeGameTraces($userId, $tenantId);

        return ['ok' => true, 'anonymized_user_ids' => [], 'handover' => $handover];
    }

    /**
     * Libère une adresse encore portée par des comptes déjà marqués supprimés
     * (anonymisation incomplète) et scrub les traces personnelles restantes.
     */
    public function releaseEmailHeldByDeletedAccounts(string $email): int
    {
        // Délai de rétractation dépassé sans passage de la tâche planifiée : la suppression
        // est faite maintenant, avant que l’adresse serve à un nouveau compte.
        $this->eraseOverdueForEmail($email);

        $ids = $this->users->releaseEmailHeldByDeletedAccounts($email);
        foreach ($ids as $id) {
            $this->scrubRelatedPersonalData((int) $id);
            $this->purgeGameTraces((int) $id, null);
        }
        try {
            // Suppressions faites avant la purge définitive : leur compte de jeu est resté.
            $this->traceEraser()->eraseOrphanGameAccount($email);
        } catch (\Throwable $e) {
            error_log('[account_deletion] compte de jeu orphelin : ' . $e->getMessage());
        }

        return count($ids);
    }

    /**
     * Comptes de cette adresse dont le délai de rétractation est dépassé : supprimés tout de
     * suite (à la connexion et à l’inscription), sans attendre la tâche planifiée.
     *
     * @return int comptes supprimés
     */
    public function eraseOverdueForEmail(string $email): int
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return 0;
        }
        $count = 0;
        try {
            foreach ($this->users->listDueForDeletionAnonymization() as $row) {
                $user = $this->users->findById((int) ($row['id'] ?? 0), null);
                if ($user === null || strtolower(trim((string) ($user['email'] ?? ''))) !== $email) {
                    continue;
                }
                if ($this->eraseAccount((int) $row['id'], (int) $row['tenant_id'], 0)['ok']) {
                    $count++;
                }
            }
        } catch (\Throwable $e) {
            error_log('[account_deletion] suppression en retard : ' . $e->getMessage());
        }

        return $count;
    }

    /** @return array{ok: bool, summary: string, details: array<string, mixed>} */
    public function anonymizeDueAccounts(): array
    {
        $due = $this->users->listDueForDeletionAnonymization();
        $count = 0;
        $failed = 0;
        foreach ($due as $row) {
            $userId = (int) ($row['id'] ?? 0);
            $tenantId = (int) ($row['tenant_id'] ?? 0);
            if ($userId < 1 || $tenantId < 1) {
                continue;
            }
            try {
                $this->anonymizeAccountCompletely($userId, $tenantId, 0);
                $count++;
            } catch (\Throwable $e) {
                $failed++;
                error_log('[account_deletion_anonymize] Échec anonymisation user #' . $userId . ' : ' . $e->getMessage());
            }
        }

        return [
            'ok' => $failed === 0,
            'summary' => "Comptes anonymisés : {$count}" . ($failed > 0 ? " · Échecs : {$failed}" : ''),
            'details' => ['anonymized' => $count, 'failed' => $failed],
        ];
    }

    /**
     * Fin du délai de rétractation (tâche planifiée) : suppression définitive.
     */
    public function anonymizeAccountCompletely(int $userId, int $tenantId, int $actorUserId = 0): bool
    {
        return $this->eraseAccount($userId, $tenantId, $actorUserId)['ok'];
    }

    /**
     * Suppression définitive d’une personne : toutes ses fiches (même adresse), ses
     * communautés transmises ou supprimées, ses traces par e-mail et par compte de jeu.
     * Une nouvelle inscription avec la même adresse repart de zéro.
     *
     * @return array{ok: bool, user_ids: list<int>, handover: list<array<string, mixed>>, traces: array<string, int>, errors: list<string>}
     */
    public function eraseAccount(int $userId, int $tenantId, int $actorUserId = 0): array
    {
        $result = ['ok' => false, 'user_ids' => [], 'handover' => [], 'traces' => [], 'errors' => []];
        $target = $this->users->findById($userId, $tenantId) ?? $this->users->findById($userId, null);
        if ($target === null) {
            $result['errors'][] = 'Compte introuvable.';

            return $result;
        }

        $email = strtolower(trim((string) ($target['email'] ?? '')));
        $ids = $this->siblingIds($userId, $tenantId);
        $result['user_ids'] = $ids;

        try {
            $result['handover'] = $this->handover()->handOver($ids);
        } catch (\Throwable $e) {
            $result['errors'][] = 'Reprise des communautés : ' . $e->getMessage();
        }
        foreach ($result['handover'] as $h) {
            if (empty($h['ok'])) {
                $result['errors'][] = 'Communauté « ' . ($h['tenant_name'] ?? '') . ' » : ' . ($h['error'] ?? 'échec');
            }
        }

        // Traces de jeu d’abord : elles sont retrouvées par le Steam et l’indicatif de la fiche.
        foreach ($ids as $id) {
            $this->purgeGameTraces($id, null);
        }
        try {
            $result['traces'] = $this->traceEraser()->erase($email, $ids);
        } catch (\Throwable $e) {
            $result['errors'][] = 'Traces par adresse : ' . $e->getMessage();
        }

        $ok = true;
        foreach ($ids as $id) {
            $this->scrubRelatedPersonalData($id);
            if (!$this->users->anonymizeUserIdentity($id, $actorUserId)) {
                $ok = false;
                continue;
            }
            $report = $this->purgeService()->purgeFromTenantPreservingHistory($id);
            if (!$report['ok'] && $report['purged_user_ids'] === []) {
                // La fiche reste anonymisée (adresse libérée) : rien n’est récupérable par e-mail.
                foreach ($report['errors'] as $error) {
                    $result['errors'][] = '#' . $id . ' : ' . $error;
                }
            }
        }
        foreach ($result['errors'] as $error) {
            error_log('[account_erase] user #' . $userId . ' : ' . $error);
        }
        $result['ok'] = $ok;

        return $result;
    }

    /**
     * Avant une suppression définitive sans conservation de l’historique
     * ({@see AccountPurgeService::purge}) : communautés transmises, traces par e-mail effacées.
     *
     * @param list<int> $userIds
     * @return list<array<string, mixed>> reprise des communautés
     */
    public function releaseIdentityBeforePurge(array $userIds, string $email, ?int $onlyTenantId = null): array
    {
        $handover = [];
        try {
            $handover = $this->handover()->handOver($userIds, $onlyTenantId);
        } catch (\Throwable $e) {
            error_log('[account_purge] reprise des communautés : ' . $e->getMessage());
        }
        if ($onlyTenantId === null) {
            try {
                $this->traceEraser()->erase($email, $userIds);
            } catch (\Throwable $e) {
                error_log('[account_purge] traces par adresse : ' . $e->getMessage());
            }
        }

        return $handover;
    }

    /**
     * Résumé lisible de la reprise des communautés, pour les messages de confirmation.
     *
     * @param list<array<string, mixed>> $handover
     */
    public static function describeHandover(array $handover): string
    {
        $parts = [];
        foreach ($handover as $h) {
            $name = (string) ($h['tenant_name'] ?? '');
            $parts[] = match ((string) ($h['action'] ?? '')) {
                'delegate' => '« ' . $name . ' » confiée à ' . ($h['successor_name'] ?? 'un membre'),
                'delete' => !empty($h['ok']) ? '« ' . $name . ' » supprimée (aucun autre membre)' : '« ' . $name . ' » n’a pas pu être supprimée',
                default => '',
            };
        }
        $parts = array_values(array_filter($parts));

        return $parts === [] ? '' : 'Communautés : ' . implode(' ; ', $parts) . '.';
    }

    /**
     * La fiche et toutes celles qui partagent son adresse (une par communauté).
     *
     * @return list<int>
     */
    private function siblingIds(int $userId, int $tenantId): array
    {
        $target = $this->users->findById($userId, $tenantId) ?? $this->users->findById($userId, null);
        $email = strtolower(trim((string) ($target['email'] ?? '')));
        $ids = $email !== '' && !str_ends_with($email, '@deleted.invalid')
            ? $this->users->listIdsByEmailNormalized($email)
            : [];
        $ids[] = $userId;

        return array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
    }

    /**
     * Efface / neutralise les tables personnelles liées (hors users).
     * Les contenus publics (documents…) restent liés à users.id mais affichent
     * « Compte supprimé » via display_name + absence d’alias / profil.
     */
    public function scrubRelatedPersonalData(int $userId): void
    {
        if ($userId < 1) {
            return;
        }

        $this->revokeAthenaAccess($userId, null);

        try {
            $this->userProfiles->deleteByUserId($userId);
        } catch (\Throwable) {
        }
        try {
            $this->userLegalIdentities->deleteByUserId($userId);
        } catch (\Throwable) {
        }
        try {
            $this->personnelProfiles->deleteByUserId($userId);
        } catch (\Throwable) {
        }
        try {
            $this->displaySettings->deleteByUserId($userId);
        } catch (\Throwable) {
        }
        try {
            $this->personnelExtras->deleteByUserId($userId);
        } catch (\Throwable) {
        }
        try {
            $this->recruitmentPresets->deleteAllForUser($userId);
        } catch (\Throwable) {
        }

        $this->deleteOptionalRowsByUserId($userId, [
            'personnel_media',
            'personnel_admin_data',
        ]);
    }

    /**
     * Coupe également la session persistée dans la DLL Overwatch. Effacer la
     * seule colonne users.steam_id ne suffit pas : son refresh token pouvait
     * continuer à réafficher l'identité supprimée pendant trente jours.
     */
    /**
     * Traces de jeu (carte, registre opérateur, mods, statistiques, terminaux…) : les tables ATAK sont
     * indexées par Steam ou indicatif, que l'anonymisation de la fiche ne touchait pas.
     */
    private function purgeGameTraces(int $userId, ?int $tenantId): void
    {
        try {
            (new \App\Services\Admin\PlayerTracePurgeService())->purgeUser($userId, $tenantId);
        } catch (\Throwable $e) {
            error_log('[account_deletion] purge des traces de jeu #' . $userId . ' : ' . $e->getMessage());
        }
    }

    private function revokeAthenaAccess(int $userId, ?int $tenantId): void
    {
        $pdo = Database::getPdo();
        foreach (['account_tenant_memberships', 'game_sessions'] as $table) {
            if (!$this->tableExists($pdo, $table)) {
                return;
            }
        }

        $tenantSql = $tenantId !== null ? ' AND tenant_id = ?' : '';
        $params = $tenantId !== null ? [$userId, $tenantId] : [$userId];
        try {
            $accounts = $pdo->prepare('SELECT account_id FROM account_tenant_memberships WHERE user_id = ?' . $tenantSql);
            $accounts->execute($params);
            $accountIds = array_values(array_unique(array_map('intval', $accounts->fetchAll(PDO::FETCH_COLUMN) ?: [])));

            $pdo->prepare("UPDATE account_tenant_memberships SET status = 'inactive' WHERE user_id = ?" . $tenantSql)
                ->execute($params);
            $pdo->prepare('UPDATE game_sessions SET revoked_at = NOW() WHERE user_id = ?' . $tenantSql . ' AND revoked_at IS NULL')
                ->execute($params);

            if ($accountIds !== [] && $this->tableExists($pdo, 'game_device_pairings')) {
                $marks = implode(',', array_fill(0, count($accountIds), '?'));
                $pdo->prepare("UPDATE game_device_pairings SET revoked_at = NOW() WHERE account_id IN ({$marks}) AND revoked_at IS NULL")
                    ->execute($accountIds);
            }
        } catch (\Throwable) {
            // Les anciennes installations sans schéma Game Auth restent supprimables.
        }
    }

    /**
     * @param list<string> $tables
     */
    private function deleteOptionalRowsByUserId(int $userId, array $tables): void
    {
        $pdo = Database::getPdo();
        foreach ($tables as $table) {
            if (!$this->tableExists($pdo, $table)) {
                continue;
            }
            try {
                $pdo->prepare('DELETE FROM `' . $table . '` WHERE user_id = ?')->execute([$userId]);
            } catch (\Throwable) {
            }
        }
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        static $cache = [];
        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }
        $stmt = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table]);
        $cache[$table] = (bool) $stmt->fetchColumn();

        return $cache[$table];
    }
}
