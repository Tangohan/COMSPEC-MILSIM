<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Repositories\AuditLogRepository;
use App\Repositories\PersonnelAssignmentRepository;
use App\Repositories\PersonnelOrgHistoryRepository;
use App\Repositories\RoleAssignmentLogRepository;
use App\Repositories\SeniorityRepository;
use App\Repositories\UserRepository;

/**
 * Crée ou met à jour des périodes d’ancienneté dérivées du dossier (marqueur {@see inferenceMarker}),
 * uniquement lorsqu’aucune période « saisie » n’existe pour l’indicateur.
 */
final class SeniorityDossierInferenceSyncService
{
    /** @var list<string> */
    public const INFERENCE_CODES = [
        'tenure_unit_primary',
        'tenure_group_attachment',
        'tenure_role_community',
        'tenure_rank_current',
    ];

    public function __construct(
        private SeniorityRepository $seniorityRepository,
        private SeniorityTenantDefaultsService $tenantDefaultsService,
        private PersonnelAssignmentRepository $personnelAssignmentRepository,
        private RoleAssignmentLogRepository $roleAssignmentLogRepository,
        private PersonnelOrgHistoryRepository $personnelOrgHistoryRepository,
        private AuditLogRepository $auditLogRepository,
        private UserRepository $userRepository,
    ) {}

    public static function inferenceMarker(string $code): string
    {
        return 'system:dossier_inference:' . trim($code);
    }

    /**
     * @return array{
     *   members: int,
     *   inserted: int,
     *   updated: int,
     *   unchanged: int,
     *   skipped_manual: int,
     *   cleared: int,
     *   skipped_no_definition: int,
     *   insert_failed: int,
     *   skipped_schema: int
     * }
     */
    public function syncForAllActiveMembers(int $tenantId): array
    {
        $agg = $this->emptyStats();
        if (!$this->seniorityRepository->schemaReady() || $tenantId < 1) {
            $agg['skipped_schema'] = 1;

            return $agg;
        }
        $this->tenantDefaultsService->ensureStandardPack($tenantId);
        $ids = $this->userRepository->listActiveUserIdsForTenant($tenantId);
        $agg['members'] = count($ids);
        foreach ($ids as $uid) {
            $one = $this->syncForUser($tenantId, $uid, true);
            foreach ($one as $k => $v) {
                if ($k === 'members') {
                    continue;
                }
                $agg[$k] += $v;
            }
        }

        return $agg;
    }

    /**
     * @return array{
     *   members: int,
     *   inserted: int,
     *   updated: int,
     *   unchanged: int,
     *   skipped_manual: int,
     *   cleared: int,
     *   skipped_no_definition: int,
     *   insert_failed: int,
     *   skipped_schema: int
     * }
     */
    public function syncForUser(int $tenantId, int $userId, bool $tenantPackAlreadyEnsured = false): array
    {
        $stats = $this->emptyStats();
        $stats['members'] = 1;
        if (!$this->seniorityRepository->schemaReady() || $tenantId < 1 || $userId < 1) {
            $stats['skipped_schema'] = 1;

            return $stats;
        }
        if (!$tenantPackAlreadyEnsured) {
            $this->tenantDefaultsService->ensureStandardPack($tenantId);
        }
        foreach (self::INFERENCE_CODES as $code) {
            $this->syncOneIndicator($tenantId, $userId, $code, $stats);
        }

        return $stats;
    }

    /**
     * @param array<string, int> $stats
     */
    private function syncOneIndicator(int $tenantId, int $userId, string $code, array &$stats): void
    {
        $defId = $this->seniorityRepository->findDefinitionIdByTenantAndCode($tenantId, $code);
        if ($defId === null) {
            ++$stats['skipped_no_definition'];

            return;
        }
        $marker = self::inferenceMarker($code);
        if ($this->seniorityRepository->userHasBlockingPeriodOutsideInferenceMarker($userId, $defId, $marker)) {
            ++$stats['skipped_manual'];

            return;
        }
        $resolved = $this->resolveStartYmd($tenantId, $userId, $code);
        $existingId = $this->seniorityRepository->findPeriodIdByRelatedType($tenantId, $userId, $defId, $marker);
        if ($resolved === null) {
            if ($existingId !== null) {
                if ($this->seniorityRepository->deletePeriodById($existingId, $tenantId, $userId)) {
                    ++$stats['cleared'];
                }
            }

            return;
        }
        $meta = json_encode(['source' => 'dossier_inference', 'code' => $code], JSON_UNESCAPED_UNICODE);
        if ($existingId !== null) {
            $ok = $this->seniorityRepository->updatePeriodStartDate($existingId, $tenantId, $userId, $resolved);
            if ($ok) {
                ++$stats['updated'];
            } else {
                ++$stats['unchanged'];
            }

            return;
        }
        $newId = $this->seniorityRepository->insertPeriod(
            $tenantId,
            $userId,
            $defId,
            $resolved,
            $marker,
            null,
            'active',
            $meta !== false ? $meta : null,
        );
        if ($newId !== null) {
            ++$stats['inserted'];
        } else {
            ++$stats['insert_failed'];
        }
    }

    private function resolveStartYmd(int $tenantId, int $userId, string $code): ?string
    {
        return match ($code) {
            'tenure_unit_primary' => $this->personnelAssignmentRepository->inferCurrentAttachmentStartYmd($tenantId, $userId, false),
            'tenure_group_attachment' => $this->personnelAssignmentRepository->inferCurrentAttachmentStartYmd($tenantId, $userId, true),
            'tenure_role_community' => $this->resolveRoleCommunityStartYmd($tenantId, $userId),
            'tenure_rank_current' => $this->resolveRankStartYmd($tenantId, $userId),
            default => null,
        };
    }

    private function resolveRoleCommunityStartYmd(int $tenantId, int $userId): ?string
    {
        $roleIds = $this->userRepository->listOrganizationRoleIdsForUser($userId);
        if ($this->roleAssignmentLogRepository->isTableReady() && $roleIds !== []) {
            $fromLog = $this->roleAssignmentLogRepository->earliestAssignDateYmdForRoles($tenantId, $userId, $roleIds);
            if ($fromLog !== null) {
                return $fromLog;
            }
        }

        return $this->auditLogRepository->earliestRoleAssignedDateYmdForTargetUser($tenantId, $userId);
    }

    private function resolveRankStartYmd(int $tenantId, int $userId): ?string
    {
        if ($this->personnelOrgHistoryRepository->schemaReady()) {
            $g = $this->personnelOrgHistoryRepository->latestGradeChangeDateYmd($tenantId, $userId);
            if ($g !== null) {
                return $g;
            }
        }
        $row = $this->userRepository->findById($userId, $tenantId);

        return $this->normalizeDateYmd(isset($row['created_at']) ? (string) $row['created_at'] : null);
    }

    private function normalizeDateYmd(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $t = trim($raw);
        if ($t === '' || str_starts_with($t, '0000-00-00')) {
            return null;
        }
        try {
            if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $t, $m)) {
                return $m[1];
            }

            return (new \DateTimeImmutable($t))->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{
     *   members: int,
     *   inserted: int,
     *   updated: int,
     *   unchanged: int,
     *   skipped_manual: int,
     *   cleared: int,
     *   skipped_no_definition: int,
     *   insert_failed: int,
     *   skipped_schema: int
     * }
     */
    private function emptyStats(): array
    {
        return [
            'members' => 0,
            'inserted' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'skipped_manual' => 0,
            'cleared' => 0,
            'skipped_no_definition' => 0,
            'insert_failed' => 0,
            'skipped_schema' => 0,
        ];
    }
}
