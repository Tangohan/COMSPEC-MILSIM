<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Kits mission SSE (LOT 8) — libellés métier pour Athena / docs.
 * Les datasets runtime vivent dans le mod Arma (`comspec_sse_fnc_datasetFalcon`).
 */
final class SseMissionKitCatalog
{
    /**
     * @return list<array<string,mixed>>
     */
    public static function kits(): array
    {
        return [
            [
                'id' => 'falcon',
                'label' => 'FALCON — Cellule Irak 2012',
                'seed' => 'FALCON-IQ-2012-A',
                'era' => 'Irak 2010–2020',
                'region_label' => 'Irak',
                'summary' => 'Cellule d’entraînement : chef de secteur, technicien engins, courrier, finance, planque et bruit civil.',
                'roles' => [
                    ['id' => 'falcon_hvt', 'label' => 'Chef de secteur (HVT)', 'alias' => 'ABU KARIM'],
                    ['id' => 'falcon_ied', 'label' => 'Technicien IED', 'alias' => 'L INGENIEUR'],
                    ['id' => 'falcon_courier', 'label' => 'Courrier frontière', 'alias' => 'LE CHAUFFEUR'],
                    ['id' => 'falcon_finance', 'label' => 'Relais financier', 'alias' => 'LE CHANGEUR'],
                    ['id' => 'falcon_safehouse', 'label' => 'Gardien de planque', 'alias' => 'LE LOCATAIRE'],
                    ['id' => 'falcon_noise', 'label' => 'Civil bruit de fond', 'alias' => ''],
                ],
                'levels' => [
                    0 => 'Surface — couverture et alias',
                    1 => 'Tactique — réseau apparent',
                    2 => 'Terrain — finance et planque',
                    3 => 'Vérité complète — Zeus / debrief',
                ],
                'arma_pack' => 'FALCON',
                'arma_apply' => '["falcon", player, 50, 1] call comspec_sse_fnc_applyDataset',
            ],
        ];
    }

    public static function find(string $id): ?array
    {
        $id = strtolower(trim($id));
        foreach (self::kits() as $kit) {
            if (($kit['id'] ?? '') === $id) {
                return $kit;
            }
        }

        return null;
    }
}
