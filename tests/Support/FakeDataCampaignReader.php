<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Services\DataCampaign\DataCampaignRecordReader;
use App\Support\DataCampaignBlocks;
use App\Support\DataCampaignSnapshot;

/**
 * Lecteur de fiche à valeurs fixes : les tests changent une valeur pour simuler une donnée modifiée après coup.
 */
final class FakeDataCampaignReader extends DataCampaignRecordReader
{
    /** @var array<int, array<string, array<string, string>>> membre => bloc => libellé => valeur */
    public array $data = [];

    public function setValue(int $userId, string $block, string $label, string $value): void
    {
        $this->data[$userId][$block][$label] = $value;
    }

    public function read(int $tenantId, int $userId, array $blocks): array
    {
        $out = [];
        foreach (DataCampaignBlocks::normalize($blocks) as $key) {
            $rows = [];
            foreach ($this->data[$userId][$key] ?? ['Valeur' => 'défaut ' . $key] as $label => $value) {
                $rows[] = ['label' => (string) $label, 'value' => (string) $value];
            }
            $out[$key] = ['rows' => $rows, 'portrait' => null, 'notice' => '', 'links' => []];
        }

        return $out;
    }

    public function hashes(int $tenantId, int $userId, array $blocks): array
    {
        $out = [];
        foreach ($this->read($tenantId, $userId, array_values(array_filter(DataCampaignBlocks::normalize($blocks), static fn (string $k): bool => !DataCampaignBlocks::isVolatile($k)))) as $key => $block) {
            $out[$key] = DataCampaignSnapshot::hash($block['rows']);
        }

        return $out;
    }
}
