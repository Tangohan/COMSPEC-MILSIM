<?php

declare(strict_types=1);

namespace App\Support;

use App\Repositories\UnitStructTypeRepository;

/** Types d'entité d'une communauté : types de base (config units.types) puis types qu'elle a créés. */
final class UnitStructTypes
{
    public const CUSTOM_PREFIX = 'x-';
    public const MAX_SLUG = 48;

    /** @return list<array{id: string, label: string, builtin: bool}> */
    public static function forTenant(int $tenantId, ?UnitStructTypeRepository $repo = null): array
    {
        $out = [];
        foreach ((array) config('units.types', []) as $id => $meta) {
            if (is_array($meta)) {
                $out[] = ['id' => (string) $id, 'label' => (string) ($meta['label'] ?? $id), 'builtin' => true];
            }
        }
        if ($tenantId > 0) {
            try {
                $repo ??= new UnitStructTypeRepository();
                foreach ($repo->listForTenant($tenantId) as $row) {
                    $out[] = ['id' => $row['slug'], 'label' => $row['label'], 'builtin' => false];
                }
            } catch (\Throwable) {
                // Table absente ou base indisponible : on garde les types de base.
            }
        }

        return $out;
    }

    /** @return list<string> */
    public static function allowedIds(int $tenantId): array
    {
        return array_column(self::forTenant($tenantId), 'id');
    }

    public static function slugFromLabel(string $label): string
    {
        $base = OrbatChartDisplay::slugFromLabel($label);

        return rtrim(substr(self::CUSTOM_PREFIX . $base, 0, self::MAX_SLUG), '-');
    }
}
