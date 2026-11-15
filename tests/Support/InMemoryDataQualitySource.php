<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Services\DataQuality\DataQualitySource;

/**
 * Source de données en mémoire pour les tableaux de la qualité des données : une vraie implémentation, bornée à la
 * communauté comme la version MySQL, qui garde la trace des communautés interrogées.
 */
final class InMemoryDataQualitySource implements DataQualitySource
{
    /** @var array<int, list<array<string, mixed>>> communauté => lignes de membres */
    public array $rows = [];

    /** @var array<int, array<int, array{bad_range: int, future: int}>> */
    public array $qualifications = [];

    /** @var array<int, array<int, string>> */
    public array $confirmations = [];

    /** @var array<int, array<int, string>> */
    public array $units = [];

    /** @var array<int, array{campaign: array<string, mixed>, members: list<array<string, mixed>>}> */
    public array $campaigns = [];

    /** @var list<int> */
    public array $asked = [];

    public function memberRows(int $tenantId, int $limit): array
    {
        $this->asked[] = $tenantId;

        return array_slice($this->rows[$tenantId] ?? [], 0, $limit);
    }

    public function qualificationDateIssues(int $tenantId): array
    {
        return $this->qualifications[$tenantId] ?? [];
    }

    public function lastConfirmations(int $tenantId): array
    {
        return $this->confirmations[$tenantId] ?? [];
    }

    public function unitNames(int $tenantId): array
    {
        return $this->units[$tenantId] ?? [];
    }

    public function campaign(int $tenantId): ?array
    {
        return $this->campaigns[$tenantId] ?? null;
    }
}
