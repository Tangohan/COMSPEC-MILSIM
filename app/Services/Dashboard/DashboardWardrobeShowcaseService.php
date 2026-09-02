<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Repositories\ArsenalWardrobeRepository;
use App\Repositories\TenantDashboardWardrobePinRepository;
use App\Support\EquipmentCoverStorage;

final class DashboardWardrobeShowcaseService
{
    public function __construct(
        private TenantDashboardWardrobePinRepository $pins,
        private ArsenalWardrobeRepository $wardrobes,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listForDashboard(int $tenantId): array
    {
        if ($tenantId < 1 || !$this->wardrobes->tablesReady()) {
            return [];
        }
        $out = [];
        foreach ($this->pins->listOrderedForTenant($tenantId) as $row) {
            $resolved = $this->resolveCard($row);
            if ($resolved !== null) {
                $out[] = $resolved;
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>|null
     */
    public function resolveCard(array $row): ?array
    {
        $id = (int) ($row['id'] ?? 0);
        $wardrobeId = (int) ($row['wardrobe_id'] ?? 0);
        if ($id < 1 || $wardrobeId < 1) {
            return null;
        }
        $name = trim((string) ($row['title'] ?? ''));
        if ($name === '') {
            $name = trim((string) ($row['wardrobe_name'] ?? ''));
        }
        if ($name === '') {
            $name = 'Tenue';
        }
        $badge = trim((string) ($row['badge_label'] ?? ''));
        if ($badge === '') {
            $badge = trim((string) ($row['collection_name'] ?? ''));
        }
        if ($badge === '') {
            $badge = 'Tenue';
        }
        $figure = EquipmentCoverStorage::publicUrl(isset($row['figure_path']) ? (string) $row['figure_path'] : null);
        $backdrop = EquipmentCoverStorage::publicUrl(isset($row['backdrop_path']) ? (string) $row['backdrop_path'] : null);
        $cover = EquipmentCoverStorage::publicUrl(isset($row['wardrobe_cover_path']) ? (string) $row['wardrobe_cover_path'] : null);
        $notes = trim((string) ($row['wardrobe_notes'] ?? ''));
        $hasFigure = $figure !== null && $figure !== '';

        return [
            'id' => $id,
            'wardrobe_id' => $wardrobeId,
            'title' => $name,
            'badge_label' => $badge,
            'collection_name' => trim((string) ($row['collection_name'] ?? '')),
            'notes' => $notes,
            'figure' => $figure,
            'backdrop' => $backdrop,
            'cover' => $cover,
            'thumb' => $hasFigure ? $figure : ($cover ?? ''),
            'has_figure' => $hasFigure,
            'href' => url('equipment/tenues/' . $wardrobeId),
        ];
    }
}
