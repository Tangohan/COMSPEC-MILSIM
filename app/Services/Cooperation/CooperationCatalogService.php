<?php

declare(strict_types=1);

namespace App\Services\Cooperation;

use App\Repositories\CooperationCatalogRepository;
use App\Support\CooperationDictionary;

/**
 * Fusion catalogue plateforme (tenant_id = 0) et entrées de la communauté (surcharge par slug ou entrées locales).
 */
final class CooperationCatalogService
{
    public function __construct(
        private CooperationCatalogRepository $catalogRepository
    ) {}

    /** @return array<string, string> slug => libellé affiché */
    public function typologyChoicesForTenant(int $tenantId): array
    {
        if (!$this->catalogRepository->tableExists()) {
            return CooperationDictionary::typologyChoices();
        }
        $platform = $this->catalogRepository->listByTenantId(0);
        $local = $tenantId > 0 ? $this->catalogRepository->listByTenantId($tenantId) : [];
        $bySlug = [];
        foreach ($platform as $row) {
            if (empty($row['is_active'])) {
                continue;
            }
            $slug = (string) ($row['slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $bySlug[$slug] = [
                'label' => (string) ($row['label'] ?? $slug),
                'sort' => (int) ($row['sort_order'] ?? 0),
            ];
        }
        foreach ($local as $row) {
            if (empty($row['is_active'])) {
                continue;
            }
            $slug = (string) ($row['slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $bySlug[$slug] = [
                'label' => (string) ($row['label'] ?? $slug),
                'sort' => (int) ($row['sort_order'] ?? 0),
            ];
        }
        if ($bySlug === []) {
            return CooperationDictionary::typologyChoices();
        }
        uasort($bySlug, static fn (array $a, array $b): int => ($a['sort'] <=> $b['sort']) ?: strcmp($a['label'], $b['label']));
        $out = [];
        foreach ($bySlug as $slug => $meta) {
            $out[$slug] = $meta['label'];
        }

        return $out;
    }

    public function normalizeTypologyForTenant(?string $raw, int $tenantId): ?string
    {
        $t = trim((string) $raw);
        if ($t === '') {
            return null;
        }
        $choices = $this->typologyChoicesForTenant($tenantId);
        if (array_key_exists($t, $choices)) {
            return $t;
        }

        return CooperationDictionary::normalizeTypology($t);
    }

    public function typologyLabelForTenant(int $leadTenantId, string $slug): string
    {
        if ($slug === '') {
            return '';
        }
        $choices = $this->typologyChoicesForTenant($leadTenantId);
        if (isset($choices[$slug])) {
            return $choices[$slug];
        }
        $dict = CooperationDictionary::typologyChoices();

        return $dict[$slug] ?? $slug;
    }

    /** @return array<string, string> slug => description courte */
    public function descriptionsForTenant(int $tenantId): array
    {
        if (!$this->catalogRepository->tableExists()) {
            return [];
        }
        $platform = $this->catalogRepository->listByTenantId(0);
        $local = $tenantId > 0 ? $this->catalogRepository->listByTenantId($tenantId) : [];
        $desc = [];
        foreach ($platform as $row) {
            if (empty($row['is_active'])) {
                continue;
            }
            $slug = (string) ($row['slug'] ?? '');
            $d = trim((string) ($row['description'] ?? ''));
            if ($slug !== '' && $d !== '') {
                $desc[$slug] = $d;
            }
        }
        foreach ($local as $row) {
            if (empty($row['is_active'])) {
                continue;
            }
            $slug = (string) ($row['slug'] ?? '');
            $d = trim((string) ($row['description'] ?? ''));
            if ($slug !== '' && $d !== '') {
                $desc[$slug] = $d;
            }
        }

        return $desc;
    }
}
