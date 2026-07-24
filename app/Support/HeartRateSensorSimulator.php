<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\Tactical\RoleplaySimulationService;

/**
 * Helper pour appliquer la simulation du capteur cardiaque aux données d'unités.
 * À utiliser dans les contrôleurs/services qui manipulent les données médicales.
 */
class HeartRateSensorSimulator
{
    public function __construct(
        private RoleplaySimulationService $roleplaySim
    ) {
    }

    /**
     * Applique la simulation de défaut capteur cardiaque à un tableau d'unités.
     * Modifie le tableau en place.
     * 
     * @param int $tenantId
     * @param array &$units Tableau d'unités avec champ 'extra' potentiellement contenant 'heart_rate'
     */
    public function applyToUnits(int $tenantId, array &$units): void
    {
        foreach ($units as &$unit) {
            if (!isset($unit['extra'])) {
                continue;
            }

            $extra = is_string($unit['extra']) ? json_decode($unit['extra'], true) : $unit['extra'];
            
            if (!is_array($extra) || !isset($extra['heart_rate']) || !is_numeric($extra['heart_rate'])) {
                continue;
            }

            $originalHr = (int) $extra['heart_rate'];
            $simulatedHr = $this->roleplaySim->applyHeartRateSensorFailure($tenantId, $originalHr);
            
            if ($simulatedHr === null) {
                // Capteur en panne - valeur manquante
                $extra['heart_rate'] = null;
                $extra['sensor_status'] = 'missing';
                $extra['sensor_message'] = 'Capteur non disponible — Données manquantes';
            } elseif ($simulatedHr === 0 && $originalHr > 0) {
                // Défaillance capteur (erreur matérielle)
                $extra['heart_rate'] = 0;
                $extra['sensor_status'] = 'failure';
                $extra['sensor_message'] = 'Défaut matériel capteur — Données non fiables';
            } elseif (abs($simulatedHr - $originalHr) > ($originalHr * 0.25)) {
                // Valeur erronée (± 25%)
                $extra['heart_rate'] = $simulatedHr;
                $extra['sensor_status'] = 'error';
                $extra['sensor_message'] = 'Valeur capteur erronée — Vérification requise';
            } else {
                // Valeur normale ou légèrement modifiée
                $extra['heart_rate'] = $simulatedHr;
                if ($simulatedHr !== $originalHr) {
                    $extra['sensor_status'] = 'imprecise';
                }
            }
            
            $unit['extra'] = is_string($unit['extra']) ? json_encode($extra) : $extra;
        }
        unset($unit);
    }

    /**
     * Applique la simulation à une seule unité.
     * 
     * @param int $tenantId
     * @param array &$unit Unité avec champ 'extra' potentiellement contenant 'heart_rate'
     */
    public function applyToUnit(int $tenantId, array &$unit): void
    {
        $units = [$unit];
        $this->applyToUnits($tenantId, $units);
        $unit = $units[0];
    }
}
