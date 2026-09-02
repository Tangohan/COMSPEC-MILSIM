<?php

declare(strict_types=1);

namespace App\Services\Doctrine;

use App\Repositories\Doctrine\DocumentAudienceRepository;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;

/**
 * Détermine si un utilisateur est dans le public cible d'une doctrine.
 */
final class DocumentAudienceResolver
{
    public function __construct(
        private DocumentAudienceRepository $audienceRepository,
        private UnitRepository $unitRepository,
        private PersonnelProfileRepository $personnelProfileRepository,
        private PersonnelJobRoleRepository $personnelJobRoleRepository,
        private UserRepository $userRepository,
    ) {}

    /**
     * @param array<string, mixed> $doctrineRow Ligne document_doctrines + document
     */
    public function isUserInAudience(int $tenantId, int $userId, int $documentId, array $doctrineRow = []): bool
    {
        if ($tenantId < 1 || $userId < 1 || $documentId < 1) {
            return false;
        }

        if (!$this->audienceRepository->tableExists()) {
            return true;
        }

        $audiences = $this->audienceRepository->listForDocument($documentId, $tenantId);
        if ($audiences === []) {
            return ($doctrineRow['requirement_level'] ?? 'informative') === 'informative';
        }

        foreach ($audiences as $aud) {
            if ($this->matchesAudience($tenantId, $userId, $aud)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<int> User IDs concernés (résolution matérialisée pour admin/compliance).
     */
    public function resolveUserIds(int $tenantId, int $documentId): array
    {
        if ($tenantId < 1 || $documentId < 1) {
            return [];
        }
        $audiences = $this->audienceRepository->listForDocument($documentId, $tenantId);
        if ($audiences === []) {
            return $this->userRepository->listActiveUserIdsForTenant($tenantId);
        }

        $ids = [];
        foreach ($audiences as $aud) {
            $type = (string) ($aud['audience_type'] ?? '');
            $value = (string) ($aud['audience_value'] ?? '');
            $includeChildren = !empty($aud['include_children']);
            if ($type === 'all_members') {
                foreach ($this->userRepository->listActiveUserIdsForTenant($tenantId) as $uid) {
                    $ids[$uid] = true;
                }
            } elseif ($type === 'unit') {
                $unitId = (int) $value;
                if ($unitId < 1) {
                    continue;
                }
                $unitIds = $includeChildren
                    ? $this->unitRepository->expandUnitIdsWithDescendants($tenantId, [$unitId])
                    : [$unitId];
                foreach ($this->unitRepository->listActiveUserIdsForUnits($tenantId, $unitIds) as $uid) {
                    $ids[$uid] = true;
                }
            } elseif ($type === 'job_role') {
                $holders = $this->personnelJobRoleRepository->listHoldersByJobRoleIds($tenantId, [(int) $value]);
                foreach ($holders[(int) $value] ?? [] as $holder) {
                    $ids[(int) ($holder['user_id'] ?? 0)] = true;
                }
            } elseif ($type === 'grade') {
                foreach ($this->resolveUserIdsByGrade($tenantId, $value) as $uid) {
                    $ids[$uid] = true;
                }
            } elseif ($type === 'role') {
                foreach ($this->userRepository->listActiveUserIdsWithOrganizationRoleSlugs($tenantId, [$value]) as $uid) {
                    $ids[$uid] = true;
                }
            } elseif ($type === 'user') {
                $uid = (int) $value;
                if ($uid > 0) {
                    $ids[$uid] = true;
                }
            }
        }

        return array_map('intval', array_keys($ids));
    }

    /** @param array<string, mixed> $aud */
    private function matchesAudience(int $tenantId, int $userId, array $aud): bool
    {
        $type = (string) ($aud['audience_type'] ?? '');
        $value = (string) ($aud['audience_value'] ?? '');
        $includeChildren = !empty($aud['include_children']);

        if ($type === 'all_members') {
            return true;
        }
        if ($type === 'user') {
            return $userId === (int) $value;
        }
        if ($type === 'unit') {
            $unitId = (int) $value;
            if ($unitId < 1) {
                return false;
            }
            $userUnits = $this->unitRepository->unitIdsForUser($tenantId, $userId);
            if ($includeChildren) {
                $targetUnits = $this->unitRepository->expandUnitIdsWithDescendants($tenantId, [$unitId]);
                foreach ($userUnits as $uu) {
                    if (in_array($uu, $targetUnits, true)) {
                        return true;
                    }
                }

                return false;
            }

            return in_array($unitId, $userUnits, true);
        }
        if ($type === 'job_role') {
            return in_array($userId, array_map(
                static fn (array $h): int => (int) ($h['user_id'] ?? 0),
                $this->personnelJobRoleRepository->listHoldersByJobRoleIds($tenantId, [(int) $value])[(int) $value] ?? []
            ), true);
        }
        if ($type === 'grade') {
            return in_array($userId, $this->resolveUserIdsByGrade($tenantId, $value), true);
        }
        if ($type === 'role') {
            return in_array($userId, $this->userRepository->listActiveUserIdsWithOrganizationRoleSlugs($tenantId, [$value]), true);
        }

        return false;
    }

    /** @return list<int> */
    private function resolveUserIdsByGrade(int $tenantId, string $gradeSlug): array
    {
        $gradeSlug = trim($gradeSlug);
        if ($tenantId < 1 || $gradeSlug === '') {
            return [];
        }
        $pdo = \App\Core\Database::getPdo();
        $stmt = $pdo->prepare(
            'SELECT pp.user_id FROM personnel_profiles pp
             INNER JOIN users u ON u.id = pp.user_id AND u.tenant_id = ?
             WHERE u.tenant_id = ? AND u.status = \'active\'
               AND (pp.grade_slug = ? OR pp.rank = ?)'
        );
        $stmt->execute([$tenantId, $tenantId, $gradeSlug, $gradeSlug]);
        $ids = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $uid = (int) ($row['user_id'] ?? 0);
            if ($uid > 0) {
                $ids[] = $uid;
            }
        }

        return $ids;
    }
}
