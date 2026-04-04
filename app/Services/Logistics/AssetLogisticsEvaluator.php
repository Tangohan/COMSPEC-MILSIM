<?php

declare(strict_types=1);

namespace App\Services\Logistics;

class AssetLogisticsEvaluator
{
    public function evaluate(array $asset): array
    {
        $fuel = isset($asset['fuel_ratio']) ? (float) $asset['fuel_ratio'] : null;
        $damage = isset($asset['damage_ratio']) ? (float) $asset['damage_ratio'] : null;
        $crewCount = isset($asset['crew_count']) ? (int) $asset['crew_count'] : null;
        $ammo = $asset['ammo_state_json'] ?? [];
        $ammoCount = is_array($ammo) && isset($ammo['magazinesCount']) ? (int) $ammo['magazinesCount'] : null;
        $weaponsOnline = is_array($ammo) && isset($ammo['weaponsOnline']) ? (bool) $ammo['weaponsOnline'] : true;

        $statusFlags = [];
        if ($fuel !== null) {
            if ($fuel < 0.10) {
                $statusFlags[] = 'BINGO_FUEL';
            } elseif ($fuel < 0.20) {
                $statusFlags[] = 'LOW_FUEL';
            }
        }
        if ($ammoCount !== null && $ammoCount === 0 && !$weaponsOnline) {
            $statusFlags[] = 'WINCHESTER';
        }
        if ($damage !== null && $damage > 0.75) {
            $statusFlags[] = 'CRIPPLED';
        }
        if ($crewCount !== null && $crewCount === 0) {
            $statusFlags[] = 'ABANDONED';
        }

        $sustainability = 'FULL';
        if (in_array('CRIPPLED', $statusFlags) || in_array('ABANDONED', $statusFlags)) {
            $sustainability = 'NONE';
        } elseif (in_array('BINGO_FUEL', $statusFlags) || in_array('WINCHESTER', $statusFlags)) {
            $sustainability = 'CRITICAL';
        } elseif (in_array('LOW_FUEL', $statusFlags)) {
            $sustainability = 'LIMITED';
        }

        $recommendedAction = null;
        if (in_array('BINGO_FUEL', $statusFlags) || in_array('LOW_FUEL', $statusFlags)) {
            $recommendedAction = 'RESUPPLY';
        }
        if (in_array('WINCHESTER', $statusFlags)) {
            $recommendedAction = $recommendedAction ? $recommendedAction . ',REARM' : 'REARM';
        }
        if (in_array('CRIPPLED', $statusFlags)) {
            $recommendedAction = 'RECOVERY';
        }

        return [
            'assetId' => $asset['asset_id'] ?? null,
            'callsign' => $asset['callsign'] ?? '',
            'statusFlags' => $statusFlags,
            'sustainability' => $sustainability,
            'recommendedAction' => $recommendedAction,
        ];
    }
}
