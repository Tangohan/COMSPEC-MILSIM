<?php

declare(strict_types=1);

namespace App\Services\Communications;

use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;

/**
 * Résout les destinataires à partir du JSON de groupe (unités, rôles, etc.).
 */
final class TenantEmailRecipientResolver
{
    public function __construct(
        private UnitRepository $unitRepository,
        private UserRepository $userRepository
    ) {}

    /**
     * @param array<string, mixed> $definition
     * @return list<int> Identifiants utilisateurs distincts, éligibles à un envoi
     */
    public function resolveUserIds(int $tenantId, array $definition): array
    {
        $def = array_merge([
            'all_members' => false,
            'unit_ids' => [],
            'include_descendants' => true,
            'role_slugs' => [],
            'extra_user_ids' => [],
        ], $definition);

        $bucket = [];

        if (!empty($def['all_members'])) {
            foreach ($this->userRepository->listActiveUserIdsEligibleForEmailBroadcast($tenantId) as $uid) {
                $bucket[$uid] = true;
            }
        }

        $unitIds = $this->normalizeIntList($def['unit_ids'] ?? []);
        if ($unitIds !== []) {
            $scopeIds = !empty($def['include_descendants'])
                ? $this->unitRepository->expandUnitIdsWithDescendants($tenantId, $unitIds)
                : $unitIds;
            foreach ($this->unitRepository->listActiveUserIdsForUnits($tenantId, $scopeIds) as $uid) {
                $bucket[$uid] = true;
            }
        }

        $roleSlugs = $this->normalizeStringList($def['role_slugs'] ?? []);
        if ($roleSlugs !== []) {
            foreach ($this->userRepository->listActiveUserIdsWithOrganizationRoleSlugs($tenantId, $roleSlugs) as $uid) {
                $bucket[$uid] = true;
            }
        }

        foreach ($this->normalizeIntList($def['extra_user_ids'] ?? []) as $uid) {
            $bucket[$uid] = true;
        }

        $eligible = array_flip($this->userRepository->listActiveUserIdsEligibleForEmailBroadcast($tenantId));
        $out = [];
        foreach (array_keys($bucket) as $uid) {
            $uid = (int) $uid;
            if ($uid > 0 && isset($eligible[$uid])) {
                $out[] = $uid;
            }
        }
        sort($out);

        return $out;
    }

    /** @param mixed $v */
    private function normalizeIntList($v): array
    {
        if (!is_array($v)) {
            return [];
        }
        $out = [];
        foreach ($v as $x) {
            $n = (int) $x;
            if ($n > 0) {
                $out[$n] = true;
            }
        }

        return array_map('intval', array_keys($out));
    }

    /** @param mixed $v */
    private function normalizeStringList($v): array
    {
        if (!is_array($v)) {
            return [];
        }
        $out = [];
        foreach ($v as $x) {
            $s = trim((string) $x);
            if ($s !== '') {
                $out[$s] = true;
            }
        }

        return array_keys($out);
    }
}
