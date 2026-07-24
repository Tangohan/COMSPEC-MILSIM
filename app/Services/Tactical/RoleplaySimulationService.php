<?php

declare(strict_types=1);

namespace App\Services\Tactical;

use App\Repositories\TenantAtakConfigRepository;

/**
 * Service de simulation roleplay pour ATAK.
 * Gère les effets de dégradation réseau, défauts capteurs, et autres dysfonctionnements simulés.
 */
class RoleplaySimulationService
{
    private const SESSION_KEY_DISCONNECT_NEXT = 'roleplay_disconnect_next_time';
    private const SESSION_KEY_DISCONNECT_UNTIL = 'roleplay_disconnect_until';

    public function __construct(
        private TenantAtakConfigRepository $configRepo
    ) {
    }

    /**
     * Applique une latence artificielle selon la configuration roleplay.
     * À appeler au début des endpoints sensibles au timing.
     */
    public function applyNetworkLatency(int $tenantId): void
    {
        $config = $this->configRepo->getRoleplayConfig($tenantId);
        
        if (!$config['network_enabled']) {
            return;
        }

        $minMs = max(0, $config['latency_min_ms']);
        $maxMs = max($minMs, $config['latency_max_ms']);
        
        if ($maxMs > 0) {
            $delayMs = $minMs === $maxMs ? $minMs : random_int($minMs, $maxMs);
            if ($delayMs > 0) {
                usleep($delayMs * 1000);
            }
        }
    }

    /**
     * Vérifie si la connexion doit être simulée comme "déconnectée".
     * Retourne true si l'endpoint doit retourner une erreur de connexion.
     */
    public function shouldSimulateDisconnection(int $tenantId): bool
    {
        $config = $this->configRepo->getRoleplayConfig($tenantId);
        
        if (!$config['network_enabled'] || !$config['disconnect_enabled']) {
            return false;
        }

        $now = time();
        
        // Vérifier si on est en période de déconnexion
        $disconnectUntil = $_SESSION[self::SESSION_KEY_DISCONNECT_UNTIL] ?? 0;
        if ($disconnectUntil > $now) {
            return true;
        }

        // Vérifier si c'est le moment de déclencher une nouvelle déconnexion
        $nextDisconnectTime = $_SESSION[self::SESSION_KEY_DISCONNECT_NEXT] ?? 0;
        if ($nextDisconnectTime === 0) {
            // Première fois : planifier la prochaine déconnexion
            $interval = max(60, $config['disconnect_interval_sec']);
            $_SESSION[self::SESSION_KEY_DISCONNECT_NEXT] = $now + $interval;
            return false;
        }

        if ($now >= $nextDisconnectTime) {
            // Déclencher une déconnexion
            $minDuration = max(1, $config['disconnect_min_sec']);
            $maxDuration = max($minDuration, $config['disconnect_max_sec']);
            $duration = $minDuration === $maxDuration ? $minDuration : random_int($minDuration, $maxDuration);
            
            $_SESSION[self::SESSION_KEY_DISCONNECT_UNTIL] = $now + $duration;
            
            // Planifier la prochaine déconnexion
            $interval = max(60, $config['disconnect_interval_sec']);
            $_SESSION[self::SESSION_KEY_DISCONNECT_NEXT] = $now + $duration + $interval;
            
            return true;
        }

        return false;
    }

    /**
     * Vérifie si un paquet doit être "perdu" selon le taux configuré.
     */
    public function shouldSimulatePacketLoss(int $tenantId): bool
    {
        $config = $this->configRepo->getRoleplayConfig($tenantId);
        
        if (!$config['network_enabled']) {
            return false;
        }

        $lossPercent = max(0.0, min(100.0, $config['packet_loss_percent']));
        
        if ($lossPercent <= 0) {
            return false;
        }

        // Utilisation de random_int pour précision (0-10000 = 0.00% à 100.00%)
        $roll = random_int(0, 10000);
        return $roll < ($lossPercent * 100);
    }

    /**
     * Calcule la perte de paquets simulée en pourcentage.
     */
    public function getSimulatedPacketLoss(int $tenantId): float
    {
        $config = $this->configRepo->getRoleplayConfig($tenantId);
        
        if (!$config['network_enabled']) {
            return 0.0;
        }

        return max(0.0, min(100.0, $config['packet_loss_percent']));
    }

    /**
     * Applique des dysfonctionnements au rythme cardiaque selon la configuration.
     * Retourne le rythme cardiaque modifié ou null si le capteur est en panne.
     * 
     * @param int $heartRate Rythme cardiaque réel
     * @return int|null Rythme cardiaque simulé ou null si capteur en panne
     */
    public function applyHeartRateSensorFailure(int $tenantId, int $heartRate): ?int
    {
        $config = $this->configRepo->getRoleplayConfig($tenantId);
        
        if (!$config['sensor_enabled']) {
            return $heartRate;
        }

        // Panne complète (valeur manquante)
        $missingPercent = max(0.0, min(100.0, $config['sensor_missing_percent']));
        if ($missingPercent > 0) {
            $roll = random_int(0, 10000);
            if ($roll < ($missingPercent * 100)) {
                return null;
            }
        }

        // Défaillance (valeur à 0 ou erreur critique)
        $failurePercent = max(0.0, min(100.0, $config['sensor_failure_percent']));
        if ($failurePercent > 0) {
            $roll = random_int(0, 10000);
            if ($roll < ($failurePercent * 100)) {
                return 0;
            }
        }

        // Valeur erronée (± 30% à 200% du réel)
        $errorPercent = max(0.0, min(100.0, $config['sensor_error_percent']));
        if ($errorPercent > 0) {
            $roll = random_int(0, 10000);
            if ($roll < ($errorPercent * 100)) {
                $multiplier = random_int(30, 200) / 100.0;
                return (int) round($heartRate * $multiplier);
            }
        }

        return $heartRate;
    }

    /**
     * Retourne un message d'erreur contextualisé selon le type de dysfonctionnement.
     */
    public function getDisconnectionMessage(int $tenantId): string
    {
        $config = $this->configRepo->getRoleplayConfig($tenantId);
        
        $messages = [
            'normal' => 'Liaison temporairement indisponible',
            'hostile' => 'Interférences détectées — liaison interrompue',
            'degraded' => 'Conditions réseau dégradées — connexion perdue',
            'equipment' => 'Défaut matériel — reconnexion en cours',
        ];

        $mode = $config['network_mode'] ?? 'normal';
        return $messages[$mode] ?? $messages['normal'];
    }

    /**
     * Génère des statistiques réseau pour l'affichage UI.
     */
    public function getNetworkStats(int $tenantId): array
    {
        $config = $this->configRepo->getRoleplayConfig($tenantId);
        
        if (!$config['network_enabled']) {
            return [
                'enabled' => false,
                'latency_range' => null,
                'packet_loss' => 0.0,
                'disconnect_risk' => false,
            ];
        }

        return [
            'enabled' => true,
            'latency_range' => [
                'min' => $config['latency_min_ms'],
                'max' => $config['latency_max_ms'],
            ],
            'packet_loss' => $config['packet_loss_percent'],
            'disconnect_risk' => $config['disconnect_enabled'],
            'mode' => $config['network_mode'],
        ];
    }

    /**
     * Génère des statistiques capteurs pour l'affichage UI.
     */
    public function getSensorStats(int $tenantId): array
    {
        $config = $this->configRepo->getRoleplayConfig($tenantId);
        
        if (!$config['sensor_enabled']) {
            return [
                'enabled' => false,
                'failure_rate' => 0.0,
                'error_rate' => 0.0,
                'missing_rate' => 0.0,
            ];
        }

        return [
            'enabled' => true,
            'failure_rate' => $config['sensor_failure_percent'],
            'error_rate' => $config['sensor_error_percent'],
            'missing_rate' => $config['sensor_missing_percent'],
        ];
    }
}
