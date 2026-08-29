<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Repositories\AuditLogRepository;
use App\Repositories\PersonnelAssignmentRepository;
use App\Repositories\PersonnelOrgHistoryRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\PersonnelQualificationRepository;
use App\Repositories\RoleAssignmentLogRepository;
use App\Repositories\SeniorityRepository;
use App\Repositories\TrainingCertificateRepository;
use App\Repositories\UserRepository;

/**
 * Crée ou met à jour des périodes d’ancienneté dérivées du dossier (marqueur {@see inferenceMarker}),
 * uniquement lorsqu’aucune période « saisie » n’existe pour l’indicateur.
 * À l’acceptation, {@see seedMissingPackPeriodsAfterAcceptance} complète le lot catalogue restant.
 */
final class SeniorityDossierInferenceSyncService
{
    /** @var list<string> */
    public const INFERENCE_CODES = [
        'tenure_service',
        'tenure_unit_primary',
        'tenure_group_attachment',
        'tenure_role_community',
        'tenure_rank_current',
        'tenure_training_track',
        'tenure_qualification_hold',
        'tenure_garrison',
        'tenure_operational_commitment',
        'tenure_staff_assignment',
        'tenure_reserve_status',
    ];

    /**
     * Indicateurs épisodiques / personnalisés : pas d’inférence dossier fiable ;
     * à l’acceptation on pose une période active depuis la date d’enrôlement.
     *
     * @var list<string>
     */
    public const ACCEPTANCE_SEED_CODES = [
        'tenure_field_deployment',
        'tenure_instructor_capacity',
        'tenure_campaign_participation',
        'tenure_joint_interop',
        'tenure_custom_engagement',
        'tenure_tenant_wide_recognition',
    ];

    /** @var list<string> */
    private const STAFF_SLUG_NEEDLES = [
        'admin', 'owner', 'officer', 'cadre', 'staff', 'rh', 'command', 's1',
        'instruct', 'zeus', 'gm', 'moderat', 'recrut', 'ops',
    ];

    public function __construct(
        private SeniorityRepository $seniorityRepository,
        private SeniorityTenantDefaultsService $tenantDefaultsService,
        private PersonnelAssignmentRepository $personnelAssignmentRepository,
        private RoleAssignmentLogRepository $roleAssignmentLogRepository,
        private PersonnelOrgHistoryRepository $personnelOrgHistoryRepository,
        private AuditLogRepository $auditLogRepository,
        private UserRepository $userRepository,
        private PersonnelQualificationRepository $personnelQualificationRepository,
        private TrainingCertificateRepository $trainingCertificateRepository,
        private ?PersonnelProfileRepository $personnelProfileRepository = null,
    ) {}

    public static function inferenceMarker(string $code): string
    {
        return 'system:dossier_inference:' . trim($code);
    }

    public static function acceptanceSeedMarker(string $code): string
    {
        return 'system:acceptance_seed:' . trim($code);
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
     * Complète le lot standard : toute définition encore sans période reçoit une ligne active
     * datée à l’enrôlement (ou à aujourd’hui en dernier recours). Idempotent.
     *
     * @return array{
     *   members: int,
     *   inserted: int,
     *   skipped_existing: int,
     *   skipped_no_definition: int,
     *   skipped_no_date: int,
     *   insert_failed: int,
     *   skipped_schema: int
     * }
     */
    public function seedMissingPackPeriodsAfterAcceptance(
        int $tenantId,
        int $userId,
        bool $tenantPackAlreadyEnsured = false
    ): array {
        $stats = [
            'members' => 1,
            'inserted' => 0,
            'skipped_existing' => 0,
            'skipped_no_definition' => 0,
            'skipped_no_date' => 0,
            'insert_failed' => 0,
            'skipped_schema' => 0,
        ];
        if (!$this->seniorityRepository->schemaReady() || $tenantId < 1 || $userId < 1) {
            $stats['skipped_schema'] = 1;

            return $stats;
        }
        if (!$tenantPackAlreadyEnsured) {
            $this->tenantDefaultsService->ensureStandardPack($tenantId);
        }
        $startYmd = $this->resolveEnlistmentOrServiceStartYmd($tenantId, $userId);
        if ($startYmd === null) {
            $startYmd = (new \DateTimeImmutable('today'))->format('Y-m-d');
        }

        $seedCodes = array_values(array_unique(array_merge(
            self::INFERENCE_CODES,
            self::ACCEPTANCE_SEED_CODES,
            ['tenure_community'],
        )));
        /* Couvrir aussi tout code catalogue ajouté au pack sans être listé ci-dessus. */
        foreach (SeniorityTenantDefaultsService::listStandardPackCodes() as $packCode) {
            if (!in_array($packCode, $seedCodes, true)) {
                $seedCodes[] = $packCode;
            }
        }

        foreach ($seedCodes as $code) {
            $defId = $this->seniorityRepository->findDefinitionIdByTenantAndCode($tenantId, $code);
            if ($defId === null) {
                ++$stats['skipped_no_definition'];
                continue;
            }
            $existing = $this->seniorityRepository->listPeriodsForUserAndDefinition($userId, $defId);
            if ($existing !== []) {
                ++$stats['skipped_existing'];
                continue;
            }
            $marker = in_array($code, self::INFERENCE_CODES, true)
                ? self::inferenceMarker($code)
                : ($code === 'tenure_community'
                    ? SeniorityEnrollmentBootstrapService::BOOTSTRAP_RELATED_TYPE
                    : self::acceptanceSeedMarker($code));
            $meta = json_encode(
                ['source' => 'acceptance_seed', 'code' => $code],
                JSON_UNESCAPED_UNICODE
            );
            $newId = $this->seniorityRepository->insertPeriod(
                $tenantId,
                $userId,
                $defId,
                $startYmd,
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
        $specific = match ($code) {
            'tenure_service' => $this->resolveServiceStartYmd($tenantId, $userId),
            'tenure_unit_primary' => $this->personnelAssignmentRepository->inferCurrentAttachmentStartYmd($tenantId, $userId, false),
            'tenure_group_attachment' => $this->personnelAssignmentRepository->inferCurrentAttachmentStartYmd($tenantId, $userId, true),
            'tenure_role_community' => $this->resolveRoleCommunityStartYmd($tenantId, $userId),
            'tenure_rank_current' => $this->resolveRankStartYmd($tenantId, $userId),
            'tenure_training_track' => $this->resolveTrainingTrackStartYmd($tenantId, $userId),
            'tenure_qualification_hold' => $this->resolveQualificationHoldStartYmd($tenantId, $userId),
            'tenure_garrison' => $this->resolveGarrisonStartYmd($tenantId, $userId),
            'tenure_operational_commitment' => $this->resolveServiceStartYmd($tenantId, $userId),
            'tenure_staff_assignment' => $this->resolveStaffAssignmentStartYmd($tenantId, $userId),
            'tenure_reserve_status' => $this->resolveReserveStatusStartYmd($tenantId, $userId),
            default => null,
        };
        if ($specific !== null) {
            return $specific;
        }

        /* Complétion autonome : sans signal métier, bascule sur la date d’enrôlement / compte. */
        return $this->resolveEnlistmentOrServiceStartYmd($tenantId, $userId);
    }

    private function resolveGarrisonStartYmd(int $tenantId, int $userId): ?string
    {
        $unit = $this->personnelAssignmentRepository->inferCurrentAttachmentStartYmd($tenantId, $userId, false);
        if ($unit !== null) {
            return $unit;
        }

        return $this->resolveEnlistmentOrServiceStartYmd($tenantId, $userId);
    }

    private function resolveStaffAssignmentStartYmd(int $tenantId, int $userId): ?string
    {
        $slug = strtolower(trim((string) ($this->userRepository->getRoleSlugForUser($userId) ?? '')));
        if ($slug === '' || in_array($slug, ['member', 'guest', 'invite', 'applicant', 'candidate'], true)) {
            return null;
        }
        if (!$this->slugLooksLikeStaff($slug)) {
            return null;
        }

        return $this->resolveRoleCommunityStartYmd($tenantId, $userId)
            ?? $this->resolveEnlistmentOrServiceStartYmd($tenantId, $userId);
    }

    private function resolveReserveStatusStartYmd(int $tenantId, int $userId): ?string
    {
        if ($this->personnelProfileRepository === null) {
            return null;
        }
        $pp = $this->personnelProfileRepository->getByUserId($userId) ?? [];
        $hay = strtolower(trim(
            (string) ($pp['operator_status'] ?? '') . ' ' .
            (string) ($pp['service_status'] ?? '') . ' ' .
            (string) ($pp['gendarmerie_status'] ?? '') . ' ' .
            (string) ($pp['administrative_position'] ?? '')
        ));
        if ($hay === '' || !preg_match('/reserv|réserv|irr[eé]gulier|dispo\b|disponib/', $hay)) {
            return null;
        }

        return $this->resolveEnlistmentOrServiceStartYmd($tenantId, $userId);
    }

    private function resolveEnlistmentOrServiceStartYmd(int $tenantId, int $userId): ?string
    {
        if ($this->personnelProfileRepository !== null) {
            $pp = $this->personnelProfileRepository->getByUserId($userId) ?? [];
            $enlist = $this->normalizeDateYmd(isset($pp['enlistment_date']) ? (string) $pp['enlistment_date'] : null);
            if ($enlist !== null) {
                return $enlist;
            }
        }

        return $this->resolveServiceStartYmd($tenantId, $userId);
    }

    private function slugLooksLikeStaff(string $slug): bool
    {
        foreach (self::STAFF_SLUG_NEEDLES as $needle) {
            if (str_contains($slug, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function resolveServiceStartYmd(int $tenantId, int $userId): ?string
    {
        $candidates = [
            $this->personnelAssignmentRepository->inferCurrentAttachmentStartYmd($tenantId, $userId, false),
            $this->resolveRoleCommunityStartYmd($tenantId, $userId),
            $this->resolveRankStartYmd($tenantId, $userId),
        ];
        $row = $this->userRepository->findById($userId, $tenantId);
        $candidates[] = $this->normalizeDateYmd(isset($row['created_at']) ? (string) $row['created_at'] : null);

        return $this->pickEarliestDate($candidates);
    }

    private function resolveTrainingTrackStartYmd(int $tenantId, int $userId): ?string
    {
        $qualificationDate = $this->resolveEarliestQualificationDateYmd($userId);
        $certificateDate = $this->resolveEarliestCertificateDateYmd($tenantId, $userId);

        return $this->pickEarliestDate([$qualificationDate, $certificateDate]);
    }

    private function resolveQualificationHoldStartYmd(int $tenantId, int $userId): ?string
    {
        $activeQualificationDate = null;
        foreach ($this->personnelQualificationRepository->listForUser($userId) as $row) {
            $status = strtolower(trim((string) ($row['status'] ?? '')));
            if (in_array($status, ['revoked', 'expired', 'inactive', 'invalid'], true)) {
                continue;
            }
            $candidate = $this->normalizeDateYmd(isset($row['obtained_at']) ? (string) $row['obtained_at'] : null)
                ?? $this->normalizeDateYmd(isset($row['created_at']) ? (string) $row['created_at'] : null);
            if ($candidate !== null && ($activeQualificationDate === null || $candidate < $activeQualificationDate)) {
                $activeQualificationDate = $candidate;
            }
        }

        $certificateDate = $this->resolveEarliestCertificateDateYmd($tenantId, $userId);

        return $this->pickEarliestDate([$activeQualificationDate, $certificateDate]);
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

    private function resolveEarliestQualificationDateYmd(int $userId): ?string
    {
        $earliest = null;
        foreach ($this->personnelQualificationRepository->listForUser($userId) as $row) {
            $candidate = $this->normalizeDateYmd(isset($row['obtained_at']) ? (string) $row['obtained_at'] : null)
                ?? $this->normalizeDateYmd(isset($row['created_at']) ? (string) $row['created_at'] : null);
            if ($candidate !== null && ($earliest === null || $candidate < $earliest)) {
                $earliest = $candidate;
            }
        }

        return $earliest;
    }

    private function resolveEarliestCertificateDateYmd(int $tenantId, int $userId): ?string
    {
        $earliest = null;
        foreach ($this->trainingCertificateRepository->listByUserId($userId, $tenantId) as $row) {
            $status = strtolower(trim((string) ($row['status'] ?? '')));
            if (in_array($status, ['revoked', 'invalid'], true)) {
                continue;
            }
            $candidate = $this->normalizeDateYmd(isset($row['issued_at']) ? (string) $row['issued_at'] : null)
                ?? $this->normalizeDateYmd(isset($row['completed_at']) ? (string) $row['completed_at'] : null);
            if ($candidate !== null && ($earliest === null || $candidate < $earliest)) {
                $earliest = $candidate;
            }
        }

        return $earliest;
    }

    /**
     * @param list<?string> $dates
     */
    private function pickEarliestDate(array $dates): ?string
    {
        $earliest = null;
        foreach ($dates as $date) {
            if ($date === null) {
                continue;
            }
            if ($earliest === null || $date < $earliest) {
                $earliest = $date;
            }
        }

        return $earliest;
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
