<?php

declare(strict_types=1);

namespace App\Services\Doctrine;

use App\Repositories\Doctrine\DocumentAudienceRepository;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;
use App\Support\SqlText;

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
     * Audiences préchargées (primeAudiences) : documentId => audiences, pour un tenant donné.
     *
     * @var array{tenant_id: int, by_document: array<int, list<array<string, mixed>>>}|null
     */
    private ?array $primedAudiences = null;

    /** @var array<string, list<int>> unités résolues pendant une passe préchargée (vidé par releasePrimedAudiences) */
    private array $primedUnitCache = [];

    /**
     * Précharge en une requête les audiences d’une liste de documents (tableau de bord).
     * À libérer avec releasePrimedAudiences() une fois la passe terminée.
     *
     * @param list<int> $documentIds
     */
    public function primeAudiences(int $tenantId, array $documentIds): void
    {
        if ($tenantId < 1 || !$this->audienceRepository->tableExists()) {
            $this->primedAudiences = null;

            return;
        }
        $this->primedAudiences = [
            'tenant_id' => $tenantId,
            'by_document' => $this->audienceRepository->listForDocuments($documentIds, $tenantId),
        ];
    }

    public function releasePrimedAudiences(): void
    {
        $this->primedAudiences = null;
        $this->primedUnitCache = [];
    }

    /**
     * @param array<string, mixed> $doctrineRow Ligne document_doctrines + document
     */
    public function isUserInAudience(int $tenantId, int $userId, int $documentId, array $doctrineRow = []): bool
    {
        if ($tenantId < 1 || $userId < 1 || $documentId < 1) {
            return false;
        }

        $primed = $this->primedAudiences;
        if ($primed !== null && $primed['tenant_id'] === $tenantId && array_key_exists($documentId, $primed['by_document'])) {
            $audiences = $primed['by_document'][$documentId];
        } else {
            if (!$this->audienceRepository->tableExists()) {
                return true;
            }
            $audiences = $this->audienceRepository->listForDocument($documentId, $tenantId);
        }
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
            $userUnits = $this->primedUnits('user:' . $tenantId . ':' . $userId, fn (): array => $this->unitRepository->unitIdsForUser($tenantId, $userId));
            if ($includeChildren) {
                $targetUnits = $this->primedUnits('tree:' . $tenantId . ':' . $unitId, fn (): array => $this->unitRepository->expandUnitIdsWithDescendants($tenantId, [$unitId]));
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

    /**
     * Mémoïse une résolution d’unités pendant une passe préchargée uniquement (hors passe : appel direct).
     *
     * @param callable(): list<int> $load
     * @return list<int>
     */
    private function primedUnits(string $key, callable $load): array
    {
        if ($this->primedAudiences === null) {
            return $load();
        }
        if (!array_key_exists($key, $this->primedUnitCache)) {
            $this->primedUnitCache[$key] = $load();
        }

        return $this->primedUnitCache[$key];
    }

    /** @return list<int> */
    private function resolveUserIdsByGrade(int $tenantId, string $gradeSlug): array
    {
        $gradeSlug = trim($gradeSlug);
        if ($tenantId < 1 || $gradeSlug === '') {
            return [];
        }
        $pdo = \App\Core\Database::getPdo();
        $statusActive = SqlText::equalsLiteral($pdo, 'u.status', 'active');
        $gradeEq = SqlText::equals($pdo, 'pp.grade_slug');
        $rankEq = SqlText::equals($pdo, 'pp.rank');
        $stmt = $pdo->prepare(
            'SELECT pp.user_id FROM personnel_profiles pp
             INNER JOIN users u ON u.id = pp.user_id AND u.tenant_id = ?
             WHERE u.tenant_id = ? AND ' . $statusActive . '
               AND (' . $gradeEq . ' OR ' . $rankEq . ')'
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
