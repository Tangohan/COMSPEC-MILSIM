<?php

declare(strict_types=1);

namespace App\Services\Tactical;

use App\Repositories\AtakRealismConfigRepository;

/**
 * Service helper pour appliquer les effets météo sur les communications radio.
 * Utilisé côté serveur pour calculer la portée effective des relais selon conditions météo.
 */
final class AtakWeatherEffectsService
{
    public function __construct(
        private ?AtakRealismConfigRepository $configRepo = null,
    ) {
        $this->configRepo ??= new AtakRealismConfigRepository();
    }

    /**
     * Calcule la portée effective d'un relais selon les conditions météo.
     *
     * @param int $tenantId ID du tenant
     * @param float $baseRange Portée nominale du relais en mètres
     * @param array $weather Conditions météo : ['rain' => 0-1, 'fog' => 0-1, 'overcast' => 0-1, 'wind_kmh' => float]
     * @return array{effective_range: float, weather_multiplier: float, wind_multiplier: float, total_multiplier: float}
     */
    public function calculateEffectiveRange(int $tenantId, float $baseRange, array $weather): array
    {
        $config = $this->configRepo->getActiveConfig($tenantId);
        if ($config === null) {
            return [
                'effective_range' => $baseRange,
                'weather_multiplier' => 1.0,
                'wind_multiplier' => 1.0,
                'total_multiplier' => 1.0,
            ];
        }

        $configJson = json_decode($config['config_json'], true);
        $radioConfig = $configJson['radio_relays'] ?? [];

        if (empty($radioConfig['weather_effects_enabled'])) {
            return [
                'effective_range' => $baseRange,
                'weather_multiplier' => 1.0,
                'wind_multiplier' => 1.0,
                'total_multiplier' => 1.0,
            ];
        }

        // Calculer multiplicateur météo
        $weatherMult = $this->calculateWeatherMultiplier($radioConfig, $weather);
        
        // Calculer multiplicateur vent
        $windMult = $this->calculateWindMultiplier($radioConfig, $weather['wind_kmh'] ?? 0);
        
        // Portée effective
        $totalMult = $weatherMult * $windMult;
        $effectiveRange = $baseRange * $totalMult;

        return [
            'effective_range' => $effectiveRange,
            'weather_multiplier' => $weatherMult,
            'wind_multiplier' => $windMult,
            'total_multiplier' => $totalMult,
        ];
    }

    /**
     * Calcule le multiplicateur météo (pluie, brouillard, orage).
     *
     * @param array $radioConfig Configuration radio_relays
     * @param array $weather Conditions météo
     * @return float Multiplicateur (0-1)
     */
    private function calculateWeatherMultiplier(array $radioConfig, array $weather): float
    {
        $rain = (float) ($weather['rain'] ?? 0);
        $fog = (float) ($weather['fog'] ?? 0);
        $overcast = (float) ($weather['overcast'] ?? 0);

        // Détecter orage (pluie + couverture nuageuse élevée)
        $isStorm = ($rain > 0.5) && ($overcast > 0.7);

        if ($isStorm) {
            return (float) ($radioConfig['storm_range_multiplier'] ?? 0.60);
        }

        $mult = 1.0;

        // Pluie
        if ($rain > 0.3) {
            $mult = (float) ($radioConfig['rain_range_multiplier'] ?? 0.85);
        }

        // Brouillard (prend le minimum si les deux sont actifs)
        if ($fog > 0.5) {
            $fogMult = (float) ($radioConfig['fog_range_multiplier'] ?? 0.70);
            $mult = min($mult, $fogMult);
        }

        return $mult;
    }

    /**
     * Calcule le multiplicateur vent.
     *
     * @param array $radioConfig Configuration radio_relays
     * @param float $windKmh Vitesse du vent en km/h
     * @return float Multiplicateur (0.5-1.0)
     */
    private function calculateWindMultiplier(array $radioConfig, float $windKmh): float
    {
        $threshold = (float) ($radioConfig['wind_threshold_kmh'] ?? 50);
        $penaltyPer10 = (float) ($radioConfig['wind_range_penalty_per_10kmh'] ?? 0.05);

        if ($windKmh <= $threshold) {
            return 1.0;
        }

        $windOver = $windKmh - $threshold;
        $penalties = floor($windOver / 10);
        $mult = 1.0 - ($penalties * $penaltyPer10);

        // Cap à -50% maximum
        return max(0.5, $mult);
    }

    /**
     * Retourne une description textuelle de l'effet météo.
     *
     * @param array $weather Conditions météo
     * @param float $weatherMultiplier Multiplicateur météo calculé
     * @param float $windMultiplier Multiplicateur vent calculé
     * @return string Description lisible
     */
    public function getWeatherEffectDescription(
        array $weather,
        float $weatherMultiplier,
        float $windMultiplier
    ): string {
        $rain = (float) ($weather['rain'] ?? 0);
        $fog = (float) ($weather['fog'] ?? 0);
        $overcast = (float) ($weather['overcast'] ?? 0);
        $windKmh = (float) ($weather['wind_kmh'] ?? 0);

        $isStorm = ($rain > 0.5) && ($overcast > 0.7);

        $parts = [];

        if ($isStorm) {
            $parts[] = sprintf('Orage (%d%%)', round($weatherMultiplier * 100));
        } elseif ($rain > 0.3) {
            $parts[] = sprintf('Pluie (%d%%)', round($weatherMultiplier * 100));
        } elseif ($fog > 0.5) {
            $parts[] = sprintf('Brouillard (%d%%)', round($weatherMultiplier * 100));
        } else {
            $parts[] = 'Temps clair';
        }

        if ($windMultiplier < 1.0) {
            $parts[] = sprintf('Vent %d km/h (%d%%)', round($windKmh), round($windMultiplier * 100));
        }

        return implode(' × ', $parts);
    }

    /**
     * Applique les effets météo à tous les relais d'un tenant.
     * Retourne la liste des relais avec leur portée effective mise à jour.
     *
     * @param int $tenantId ID du tenant
     * @param array $relays Liste des relais : [['id' => int, 'base_range' => float], ...]
     * @param array $weather Conditions météo actuelles
     * @return array Liste des relais avec effective_range ajouté
     */
    public function applyWeatherToRelays(int $tenantId, array $relays, array $weather): array
    {
        $result = [];

        foreach ($relays as $relay) {
            $baseRange = (float) ($relay['base_range'] ?? $relay['range_m'] ?? 2000);
            $effects = $this->calculateEffectiveRange($tenantId, $baseRange, $weather);

            $relay['effective_range'] = $effects['effective_range'];
            $relay['weather_multiplier'] = $effects['weather_multiplier'];
            $relay['wind_multiplier'] = $effects['wind_multiplier'];
            $relay['weather_description'] = $this->getWeatherEffectDescription(
                $weather,
                $effects['weather_multiplier'],
                $effects['wind_multiplier']
            );

            $result[] = $relay;
        }

        return $result;
    }
}
