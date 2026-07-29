<?php

declare(strict_types=1);

namespace App\Services\Community;

use App\Repositories\MilitaryUnitRepository;

/**
 * Façade métier du référentiel militaire (source de vérité = BDD).
 */
final class MilitaryReferentialService
{
    public function __construct(
        private MilitaryUnitRepository $repo
    ) {}

    public function isReady(): bool
    {
        return $this->repo->tablesReady();
    }

    /** @return array<string, string> */
    public function countryLabels(): array
    {
        $out = [];
        foreach ($this->repo->listCountries(true) as $c) {
            $out[(string) $c['iso2']] = (string) $c['name_fr'];
        }

        return $out;
    }

    /** @return list<string> */
    public function allowedCountryCodes(): array
    {
        return array_keys($this->countryLabels());
    }

    /**
     * Payload compatible assistant création / paramètres communauté.
     *
     * @return array{countries: array<string, string>, units: array<string, list<array<string, mixed>>>}
     */
    public function frontendPayload(): array
    {
        $units = [];
        foreach ($this->allowedCountryCodes() as $code) {
            $units[$code] = $this->repo->catalogRowsForCountry($code);
        }

        return [
            'countries' => $this->countryLabels(),
            'units' => $units,
        ];
    }

    /** @return list<array{id: string, name: string, tier: string, tier_order: int, indent: int}> */
    public function unitsForCountry(string $countryCode): array
    {
        return $this->repo->catalogRowsForCountry($countryCode);
    }

    /** @return array{id: string, name: string, tier: string, tier_order: int, indent: int}|null */
    public function findUnit(string $countryCode, string $unitId): ?array
    {
        foreach ($this->unitsForCountry($countryCode) as $u) {
            if (($u['id'] ?? '') === trim($unitId)) {
                return $u;
            }
        }

        return null;
    }

    /**
     * @param list<string> $unitIds
     * @return list<array{id: string, name: string}>
     */
    public function resolveSelectedUnits(string $countryCode, array $unitIds): array
    {
        return $this->repo->resolveSelectedByCodes($countryCode, $unitIds);
    }

    /** @return list<string> */
    public function hierarchyBreadcrumbLabels(int $unitId): array
    {
        $unit = $this->repo->findById($unitId);
        if ($unit === null) {
            return [];
        }
        $labels = [];
        if (!empty($unit['country_name_fr'])) {
            $labels[] = (string) $unit['country_name_fr'];
        }
        if (!empty($unit['service_name'])) {
            $labels[] = (string) $unit['service_name'];
        }
        foreach ($this->repo->getAncestors($unitId) as $a) {
            $labels[] = (string) ($a['display_name'] ?? $a['official_name'] ?? $a['code']);
        }
        $labels[] = (string) ($unit['display_name'] ?? $unit['official_name'] ?? $unit['code']);

        return $labels;
    }

    public function syncTenantAffiliationsFromCodes(int $tenantId, array $unitCodes): void
    {
        if (!$this->repo->tablesReady()) {
            return;
        }
        $this->repo->replaceTenantAffiliations($tenantId, $unitCodes);
    }
}
