<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Repositories\EnlistmentRepository;
use App\Repositories\PersonnelExtrasRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\SeniorityRepository;
use App\Repositories\UserRepository;
use DateTimeImmutable;

/**
 * À la connexion : assure une période d’ancienneté « communauté » alignée sur la date d’enrôlement du dossier,
 * lorsque le référentiel seniority est actif et qu’aucune saisie manuelle n’occupe encore cet indicateur.
 */
final class SeniorityEnrollmentBootstrapService
{
    /** Marqueur de période créée automatiquement (référent technique, non affiché au métier). */
    public const BOOTSTRAP_RELATED_TYPE = 'system:enrollment_bootstrap';

    public function __construct(
        private SeniorityRepository $seniorityRepository,
        private SeniorityTenantDefaultsService $tenantDefaultsService,
        private PersonnelProfileRepository $personnelProfileRepository,
        private PersonnelExtrasRepository $personnelExtrasRepository,
        private EnlistmentRepository $enlistmentRepository,
        private UserRepository $userRepository,
    ) {}

    /**
     * Parcours tous les membres actifs : aligne l’indicateur « ancienneté dans la communauté » sur les dates de dossier connues.
     *
     * @return array{
     *   members: int,
     *   inserted: int,
     *   updated: int,
     *   unchanged: int,
     *   skipped_manual: int,
     *   skipped_no_date: int,
     *   skipped_no_definition: int,
     *   insert_failed: int,
     *   skipped_schema: int
     * }
     */
    public function syncTenureCommunityForAllActiveMembers(int $tenantId): array
    {
        $stats = [
            'members' => 0,
            'inserted' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'skipped_manual' => 0,
            'skipped_no_date' => 0,
            'skipped_no_definition' => 0,
            'insert_failed' => 0,
            'skipped_schema' => 0,
        ];
        if (!$this->seniorityRepository->schemaReady() || $tenantId < 1) {
            return $stats;
        }
        $this->tenantDefaultsService->ensureStandardPack($tenantId);
        $defId = $this->seniorityRepository->findDefinitionIdByTenantAndCode($tenantId, 'tenure_community');
        if ($defId === null) {
            return $stats;
        }
        foreach ($this->userRepository->listActiveUserIdsForTenant($tenantId) as $uid) {
            ++$stats['members'];
            $code = $this->syncTenureCommunityFromEnrollment($tenantId, $uid, null, true);
            match ($code) {
                'inserted' => ++$stats['inserted'],
                'updated' => ++$stats['updated'],
                'unchanged' => ++$stats['unchanged'],
                'skipped_manual' => ++$stats['skipped_manual'],
                'skipped_no_date' => ++$stats['skipped_no_date'],
                'skipped_no_definition' => ++$stats['skipped_no_definition'],
                'insert_failed' => ++$stats['insert_failed'],
                'skipped_schema' => ++$stats['skipped_schema'],
                default => null,
            };
        }

        return $stats;
    }

    /**
     * Idempotent : crée ou met à jour uniquement la ligne « bootstrap » pour l’indicateur {@code tenure_community}.
     *
     * @param array<string, mixed>|null $userRow ligne {@see users} (ex. {@code created_at}) si déjà chargée
     * @return non-empty-string code interne pour agrégation (inserted|updated|unchanged|skipped_manual|skipped_no_date|skipped_no_definition|skipped_schema)
     */
    public function syncTenureCommunityFromEnrollment(int $tenantId, int $userId, ?array $userRow = null, bool $tenantPackAlreadyEnsured = false): string
    {
        if (!$this->seniorityRepository->schemaReady() || $tenantId < 1 || $userId < 1) {
            return 'skipped_schema';
        }
        if (!$tenantPackAlreadyEnsured) {
            $this->tenantDefaultsService->ensureStandardPack($tenantId);
        }
        $defId = $this->seniorityRepository->findDefinitionIdByTenantAndCode($tenantId, 'tenure_community');
        if ($defId === null) {
            return 'skipped_no_definition';
        }
        $resolved = $this->resolveEnrollmentStartDate($tenantId, $userId, $userRow);
        if ($resolved === null) {
            return 'skipped_no_date';
        }
        $periods = $this->seniorityRepository->listPeriodsForUserAndDefinition($userId, $defId);
        $bootstrapId = $this->seniorityRepository->findPeriodIdByRelatedType($tenantId, $userId, $defId, self::BOOTSTRAP_RELATED_TYPE);
        if ($bootstrapId !== null) {
            $ok = $this->seniorityRepository->updatePeriodStartDate($bootstrapId, $tenantId, $userId, $resolved);

            return $ok ? 'updated' : 'unchanged';
        }
        if ($periods !== []) {
            return 'skipped_manual';
        }
        $newId = $this->seniorityRepository->insertPeriod(
            $tenantId,
            $userId,
            $defId,
            $resolved,
            self::BOOTSTRAP_RELATED_TYPE,
            null,
            'active',
            json_encode(['source' => 'enrollment_sync'], JSON_UNESCAPED_UNICODE),
        );

        return $newId !== null ? 'inserted' : 'insert_failed';
    }

    /**
     * Saisie RH (Effectifs) : aligne l’ancienneté communauté sur la date d’arrivée, y compris si une période existe déjà.
     *
     * @return non-empty-string
     */
    public function alignTenureCommunityFromStaffEdit(int $tenantId, int $userId): string
    {
        if (!$this->seniorityRepository->schemaReady() || $tenantId < 1 || $userId < 1) {
            return 'skipped_schema';
        }
        $this->tenantDefaultsService->ensureStandardPack($tenantId);
        $defId = $this->seniorityRepository->findDefinitionIdByTenantAndCode($tenantId, 'tenure_community');
        if ($defId === null) {
            return 'skipped_no_definition';
        }
        $resolved = $this->resolveEnrollmentStartDate($tenantId, $userId, null);
        if ($resolved === null) {
            return 'skipped_no_date';
        }
        $bootstrapId = $this->seniorityRepository->findPeriodIdByRelatedType(
            $tenantId,
            $userId,
            $defId,
            self::BOOTSTRAP_RELATED_TYPE
        );
        if ($bootstrapId !== null) {
            $ok = $this->seniorityRepository->updatePeriodStartDate($bootstrapId, $tenantId, $userId, $resolved);

            return $ok ? 'updated' : 'unchanged';
        }
        $periods = $this->seniorityRepository->listPeriodsForUserAndDefinition($userId, $defId);
        if ($periods !== []) {
            $firstId = (int) ($periods[0]['id'] ?? 0);
            if ($firstId > 0) {
                $ok = $this->seniorityRepository->updatePeriodStartDate($firstId, $tenantId, $userId, $resolved);

                return $ok ? 'updated' : 'unchanged';
            }
        }

        return $this->syncTenureCommunityFromEnrollment($tenantId, $userId, null, true);
    }

    /**
     * Même logique d’affichage que la fiche personnel (profil dossier, puis extra, puis candidature acceptée, puis compte).
     */
    private function resolveEnrollmentStartDate(int $tenantId, int $userId, ?array $userRow): ?string
    {
        $profile = $this->personnelProfileRepository->getByUserId($userId);
        $extras = $this->personnelExtrasRepository->getByUserId($userId);
        $fromProfile = $this->normalizeDateString(isset($profile['enlistment_date']) ? (string) $profile['enlistment_date'] : null);
        if ($fromProfile !== null) {
            return $fromProfile;
        }
        $fromExtras = $this->normalizeDateString(isset($extras['date_of_enlistment']) ? (string) $extras['date_of_enlistment'] : null);
        if ($fromExtras !== null) {
            return $fromExtras;
        }
        $enr = $this->enlistmentRepository->findLatestBySubmitter($tenantId, $userId);
        if ($enr !== null && (string) ($enr['status'] ?? '') === 'reviewed') {
            $rev = $this->normalizeDateString(isset($enr['reviewed_at']) ? (string) $enr['reviewed_at'] : null)
                ?? $this->normalizeDateString(isset($enr['updated_at']) ? (string) $enr['updated_at'] : null)
                ?? $this->normalizeDateString(isset($enr['created_at']) ? (string) $enr['created_at'] : null);
            if ($rev !== null) {
                return $rev;
            }
        }
        if ($userRow !== null) {
            return $this->normalizeDateString(isset($userRow['created_at']) ? (string) $userRow['created_at'] : null);
        }

        return null;
    }

    private function normalizeDateString(?string $raw): ?string
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
                $d = new DateTimeImmutable($m[1]);

                return $d->format('Y-m-d');
            }
            $d = new DateTimeImmutable($t);

            return $d->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
