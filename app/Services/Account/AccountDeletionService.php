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
use PDO;

/**
 * Suppression de compte (admin soft-delete + self-service RGPD).
 *
 * L’historique métier (posts forum, documents, messages…) reste rattaché à l’ancien
 * users.id — une nouvelle inscription avec le même e-mail crée un nouvel id et ne
 * récupère jamais cet historique. Les traces personnelles sont scrubées et les
 * libellés publics passent par « Compte supprimé ».
 */
final class AccountDeletionService
{
    public const GRACE_PERIOD_DAYS = 14;

    public const DELETED_DISPLAY_NAME = 'Compte supprimé';

    public function __construct(
        private UserRepository $users,
        private UserProfileRepository $userProfiles,
        private UserLegalIdentityRepository $userLegalIdentities,
        private PersonnelProfileRepository $personnelProfiles,
        private UserProfileDisplaySettingsRepository $displaySettings,
        private PersonnelExtrasRepository $personnelExtras,
        private RecruitmentPresetRepository $recruitmentPresets,
    ) {}

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
     * Soft-delete admin (annuaire plateforme) : anonymise le compte et les comptes
     * partageant le même e-mail, puis scrub les tables personnelles liées.
     *
     * @return array{ok: bool, anonymized_user_ids: list<int>}
     */
    public function softDeleteAccount(int $userId, int $tenantId, int $actorUserId): array
    {
        $target = $this->users->findById($userId, $tenantId);
        if ($target === null) {
            return ['ok' => false, 'anonymized_user_ids' => []];
        }

        $originalEmail = strtolower(trim((string) ($target['email'] ?? '')));
        $relatedIds = $originalEmail !== '' && !str_ends_with($originalEmail, '@deleted.invalid')
            ? $this->users->listIdsByEmailNormalized($originalEmail)
            : [$userId];
        if ($relatedIds === []) {
            $relatedIds = [$userId];
        }
        if (!in_array($userId, $relatedIds, true)) {
            $relatedIds[] = $userId;
        }

        $ok = $this->users->softDeleteAccount($userId, $tenantId, $actorUserId);
        foreach ($relatedIds as $relatedId) {
            $this->scrubRelatedPersonalData((int) $relatedId);
        }

        return ['ok' => $ok, 'anonymized_user_ids' => array_values(array_map('intval', $relatedIds))];
    }

    /**
     * Libère une adresse encore portée par des comptes déjà marqués supprimés
     * (anonymisation incomplète) et scrub les traces personnelles restantes.
     */
    public function releaseEmailHeldByDeletedAccounts(string $email): int
    {
        $ids = $this->users->releaseEmailHeldByDeletedAccounts($email);
        foreach ($ids as $id) {
            $this->scrubRelatedPersonalData((int) $id);
        }

        return count($ids);
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
     * Anonymisation complète d’un compte (cron RGPD) : siblings e-mail + scrub + ligne users.
     */
    public function anonymizeAccountCompletely(int $userId, int $tenantId, int $actorUserId = 0): bool
    {
        $target = $this->users->findById($userId, $tenantId);
        if ($target === null) {
            return false;
        }

        $originalEmail = strtolower(trim((string) ($target['email'] ?? '')));
        $siblingIds = $originalEmail !== '' && !str_ends_with($originalEmail, '@deleted.invalid')
            ? $this->users->listIdsByEmailNormalized($originalEmail)
            : [$userId];
        if ($siblingIds === []) {
            $siblingIds = [$userId];
        }
        if (!in_array($userId, $siblingIds, true)) {
            $siblingIds[] = $userId;
        }

        $ok = true;
        foreach ($siblingIds as $sid) {
            $sid = (int) $sid;
            $this->scrubRelatedPersonalData($sid);
            if (!$this->users->anonymizeUserIdentity($sid, $actorUserId)) {
                $ok = false;
            }
        }

        return $ok;
    }

    /**
     * Efface / neutralise les tables personnelles liées (hors users).
     * Les contenus publics (forum, docs…) restent liés à users.id mais affichent
     * « Compte supprimé » via display_name + absence d’alias / profil.
     */
    public function scrubRelatedPersonalData(int $userId): void
    {
        if ($userId < 1) {
            return;
        }

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
            'user_forum_stats',
        ]);
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
