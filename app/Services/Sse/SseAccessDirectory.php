<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;
use App\Services\Organization\OrgVisibilityService;
use App\Support\OrgVisibilityCapabilities;
use App\Support\VisibilityLevel;

/**
 * Membres que la personne qui règle les droits SSE a le droit de voir, avec les mêmes règles de visibilité que l'annuaire
 * des profils (OrgVisibilityService::filterPersonnelRow) : un membre masqué n'existe pas pour elle, un membre anonymisé
 * n'est jamais nommé (il n'est ni cherchable ni ouvrable ; il n'est que compté).
 */
class SseAccessDirectory
{
    public function __construct(
        private ?UserRepository $users = null,
        private ?UnitRepository $units = null,
        private ?OrgVisibilityCapabilities $caps = null,
    ) {
    }

    private function users(): UserRepository
    {
        return $this->users ??= new UserRepository();
    }

    private function units(): UnitRepository
    {
        return $this->units ??= new UnitRepository();
    }

    private function caps(): OrgVisibilityCapabilities
    {
        return $this->caps ??= OrgVisibilityCapabilities::fromGate();
    }

    /**
     * Membres actifs visibles dont le nom, l'indicatif ou le matricule contient la recherche.
     *
     * @return list<array{user_id: int, label: string, unit: string}>
     */
    public function search(int $tenantId, string $query, int $limit = 30): array
    {
        $out = [];
        foreach ($this->visible($tenantId, $query, 300)['named'] as $m) {
            $out[] = $m;
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /**
     * Annuaire visible : membres nommés et nombre de membres anonymisés (jamais nommés).
     *
     * @return array{named: list<array{user_id: int, label: string, unit: string}>, anonymized: int, anonymized_ids: list<int>}
     */
    public function visible(int $tenantId, string $query = '', int $limit = 300): array
    {
        $named = [];
        $anon = [];
        if ($tenantId < 1) {
            return ['named' => [], 'anonymized' => 0, 'anonymized_ids' => []];
        }
        try {
            $rows = $this->users()->listPersonnelDirectoryRich($tenantId, trim($query), $limit);
        } catch (\Throwable) {
            $rows = [];
        }
        $ids = [];
        foreach ($rows as $row) {
            $uid = (int) ($row['id'] ?? 0);
            if ($uid > 0) {
                $ids[] = $uid;
            }
        }
        $meta = $ids !== [] ? $this->visibilityMeta($tenantId, $ids) : [];
        $unitVis = $this->unitVisibility($tenantId);
        foreach ($rows as $row) {
            $uid = (int) ($row['id'] ?? 0);
            if ($uid < 1) {
                continue;
            }
            $m = $meta[$uid] ?? ['visibility_level' => VisibilityLevel::NORMAL, 'assignment_visibility' => VisibilityLevel::NORMAL, 'anonymized_label' => ''];
            $unitId = (int) ($row['primary_unit_id'] ?? 0);
            $filtered = OrgVisibilityService::filterPersonnelRow(
                [
                    'user_id' => $uid,
                    'visibility_level' => $m['visibility_level'],
                    'assignment_visibility' => $m['assignment_visibility'],
                    'anonymized_label' => $m['anonymized_label'],
                    'label' => (string) ($row['display_name'] ?? ''),
                ],
                $this->caps(),
                $unitVis[$unitId] ?? VisibilityLevel::NORMAL
            );
            if ($filtered === null) {
                continue;
            }
            if (!empty($filtered['anonymized'])) {
                $anon[] = $uid;
                continue;
            }
            $label = trim((string) ($row['display_name'] ?? ''));
            $named[] = [
                'user_id' => $uid,
                'label' => $label !== '' ? $label : 'Membre #' . $uid,
                'unit' => !empty($filtered['assignment_redacted']) ? '' : trim((string) ($row['unit_name'] ?? '')),
            ];
        }

        return ['named' => $named, 'anonymized' => count($anon), 'anonymized_ids' => $anon];
    }

    /**
     * Un membre de la communauté, seulement si l'annuaire le montre nommément à la personne ; sinon null (inconnu,
     * d'une autre communauté, masqué ou anonymisé : la réponse est la même).
     *
     * @return array{user_id: int, label: string, unit: string}|null
     */
    public function member(int $tenantId, int $userId): ?array
    {
        if ($tenantId < 1 || $userId < 1) {
            return null;
        }
        try {
            $row = $this->users()->findById($userId, $tenantId);
        } catch (\Throwable) {
            $row = null;
        }
        if (!is_array($row)) {
            return null;
        }
        $row = $this->users()->overlayCommunityProfile($row, $tenantId);
        $name = trim((string) ($row['display_name'] ?? ''));
        if ($name === '' || $name === 'Compte supprimé' || !empty($row['deleted_at'])) {
            return null;
        }
        $meta = $this->visibilityMeta($tenantId, [$userId])[$userId] ?? ['visibility_level' => VisibilityLevel::NORMAL, 'assignment_visibility' => VisibilityLevel::NORMAL, 'anonymized_label' => ''];
        $filtered = OrgVisibilityService::filterPersonnelRow(
            [
                'user_id' => $userId,
                'visibility_level' => $meta['visibility_level'],
                'assignment_visibility' => $meta['assignment_visibility'],
                'anonymized_label' => $meta['anonymized_label'],
                'label' => $name,
            ],
            $this->caps(),
            VisibilityLevel::NORMAL
        );
        if ($filtered === null || !empty($filtered['anonymized'])) {
            return null;
        }

        return ['user_id' => $userId, 'label' => $name, 'unit' => ''];
    }

    /**
     * @param list<int> $ids
     * @return array<int, array{visibility_level: string, assignment_visibility: string, anonymized_label: string}>
     */
    private function visibilityMeta(int $tenantId, array $ids): array
    {
        try {
            return $this->units()->personnelVisibilityByUserIdsForTenant($tenantId, $ids);
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<int, string> niveau de visibilité de chaque unité de la communauté */
    private function unitVisibility(int $tenantId): array
    {
        $out = [];
        try {
            foreach ($this->units()->allForTenant($tenantId) as $u) {
                $id = (int) ($u['id'] ?? 0);
                if ($id < 1) {
                    continue;
                }
                $out[$id] = array_key_exists('visibility_level', $u)
                    ? VisibilityLevel::normalize((string) ($u['visibility_level'] ?? ''))
                    : VisibilityLevel::fromOrbatMaskMode((string) ($u['orbat_mask_mode'] ?? ''));
            }
        } catch (\Throwable) {
        }

        return $out;
    }
}
