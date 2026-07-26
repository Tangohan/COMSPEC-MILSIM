<?php

namespace App\Repositories;

use App\Core\Database;

/**
 * Repository pour le scoring d'urgence MEDEVAC et prédiction ETA intelligente
 */
class AtakMedevacIntelligenceRepository
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Calcule le score d'urgence d'une demande MEDEVAC
     */
    public function calculateUrgencyScore(int $medevacId): array
    {
        $medevac = $this->db->query(
            "SELECT * FROM atak_medevac_requests WHERE id = ?",
            [$medevacId]
        )->fetch();

        if (!$medevac) {
            return ['error' => 'MEDEVAC not found'];
        }

        $factors = [];
        $totalScore = 0.0;

        // Facteur 1 : Triage patients (40 points max)
        $triageScore = 0.0;
        if ($medevac['patients_t1_urgent'] > 0) {
            $triageScore += $medevac['patients_t1_urgent'] * 15; // T1 = 15pts chacun
            $factors[] = ['factor' => 'T1_patients', 'value' => $medevac['patients_t1_urgent'], 'score' => $medevac['patients_t1_urgent'] * 15];
        }
        if ($medevac['patients_t2_urgent'] > 0) {
            $triageScore += $medevac['patients_t2_urgent'] * 8; // T2 = 8pts chacun
            $factors[] = ['factor' => 'T2_patients', 'value' => $medevac['patients_t2_urgent'], 'score' => $medevac['patients_t2_urgent'] * 8];
        }
        $triageScore = min(40, $triageScore);
        $totalScore += $triageScore;

        // Facteur 2 : Golden hour (25 points max)
        if ($medevac['golden_hour_expires_at']) {
            $minutesRemaining = (strtotime($medevac['golden_hour_expires_at']) - time()) / 60;
            
            if ($minutesRemaining < 0) {
                $goldenScore = 25; // Expiré = max urgence
                $factors[] = ['factor' => 'golden_hour_expired', 'value' => true, 'score' => 25];
            } elseif ($minutesRemaining <= 15) {
                $goldenScore = 20; // Critique
                $factors[] = ['factor' => 'golden_hour_critical', 'value' => round($minutesRemaining, 1), 'score' => 20];
            } elseif ($minutesRemaining <= 30) {
                $goldenScore = 12; // Warning
                $factors[] = ['factor' => 'golden_hour_warning', 'value' => round($minutesRemaining, 1), 'score' => 12];
            } else {
                $goldenScore = 5; // OK mais présent
                $factors[] = ['factor' => 'golden_hour_ok', 'value' => round($minutesRemaining, 1), 'score' => 5];
            }
            $totalScore += $goldenScore;
        }

        // Facteur 3 : Sécurité zone pickup (15 points max)
        $securityScores = [
            'NO_ENEMY' => 0,
            'POSSIBLE_ENEMY' => 5,
            'ENEMY_IN_AREA' => 10,
            'ENEMY_TROOPS' => 12,
            'HOT_LZ' => 15
        ];
        $securityScore = $securityScores[$medevac['security_status']] ?? 0;
        $totalScore += $securityScore;
        $factors[] = ['factor' => 'lz_security', 'value' => $medevac['security_status'], 'score' => $securityScore];

        // Facteur 4 : Temps d'attente (10 points max)
        $waitMinutes = (time() - strtotime($medevac['requested_at'])) / 60;
        $waitScore = min(10, $waitMinutes / 6); // 1pt par 6min d'attente
        $totalScore += $waitScore;
        $factors[] = ['factor' => 'wait_time_minutes', 'value' => round($waitMinutes, 1), 'score' => round($waitScore, 2)];

        // Facteur 5 : Priorité demande (10 points max)
        $priorityScores = [
            'CONVENIENCE' => 0,
            'ROUTINE' => 3,
            'PRIORITY' => 6,
            'URGENT' => 10
        ];
        $priorityScore = $priorityScores[$medevac['priority']] ?? 5;
        $totalScore += $priorityScore;
        $factors[] = ['factor' => 'request_priority', 'value' => $medevac['priority'], 'score' => $priorityScore];

        // Normaliser score 0-100
        $urgencyScore = min(100, $totalScore);

        // Mettre à jour dans BDD
        $this->db->execute(
            "UPDATE atak_medevac_requests 
             SET urgency_score = ?, urgency_factors = ?
             WHERE id = ?",
            [$urgencyScore, json_encode($factors), $medevacId]
        );

        return [
            'urgency_score' => round($urgencyScore, 2),
            'factors' => $factors,
            'assessment' => $this->getUrgencyAssessment($urgencyScore)
        ];
    }

    /**
     * Trouve l'asset médical optimal
     */
    public function findOptimalAsset(int $medevacId): ?array
    {
        $medevac = $this->db->query(
            "SELECT * FROM atak_medevac_requests WHERE id = ?",
            [$medevacId]
        )->fetch();

        if (!$medevac) return null;

        // Charger assets disponibles
        $assets = $this->db->query(
            "SELECT * FROM atak_medical_assets 
             WHERE tenant_id = ? AND context_id = ?
               AND status IN ('AVAILABLE', 'RTB')
               AND (max_litter_patients >= ? OR max_ambulatory_patients >= ?)",
            [
                $medevac['tenant_id'],
                $medevac['context_id'],
                $medevac['patients_litter'],
                $medevac['patients_ambulatory']
            ]
        )->fetchAll();

        if (empty($assets)) return null;

        $bestAsset = null;
        $bestScore = -1;

        foreach ($assets as $asset) {
            $score = $this->scoreAsset($asset, $medevac);
            
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestAsset = $asset;
            }
        }

        return $bestAsset;
    }

    /**
     * Score un asset médical pour une MEDEVAC
     */
    private function scoreAsset(array $asset, array $medevac): float
    {
        $score = 0.0;

        // Distance (50 points max - plus proche = mieux)
        if ($asset['current_pos_x'] && $asset['current_pos_y']) {
            $distance = sqrt(
                pow($asset['current_pos_x'] - $medevac['pickup_pos_x'], 2) +
                pow($asset['current_pos_y'] - $medevac['pickup_pos_y'], 2)
            );
            
            // Score inversement proportionnel à distance
            $distanceScore = max(0, 50 - ($distance / 100)); // -0.5pt par 100m
            $score += $distanceScore;
        }

        // Capacité (30 points)
        $capacityScore = 0;
        if ($asset['max_litter_patients'] >= $medevac['patients_litter']) {
            $capacityScore += 15;
        }
        if ($asset['max_ambulatory_patients'] >= $medevac['patients_ambulatory']) {
            $capacityScore += 10;
        }
        if ($asset['has_advanced_equipment']) {
            $capacityScore += 5;
        }
        $score += $capacityScore;

        // Statut (20 points)
        $statusScores = [
            'AVAILABLE' => 20,
            'RTB' => 10,
            'ASSIGNED' => 0
        ];
        $score += $statusScores[$asset['status']] ?? 0;

        return $score;
    }

    /**
     * Calcule ETA basé sur distance et vitesse
     */
    public function calculateETA(int $medevacId, string $assetCallsign): ?string
    {
        $medevac = $this->db->query(
            "SELECT * FROM atak_medevac_requests WHERE id = ?",
            [$medevacId]
        )->fetch();

        $asset = $this->db->query(
            "SELECT * FROM atak_medical_assets 
             WHERE asset_callsign = ? AND tenant_id = ? AND context_id = ?",
            [$assetCallsign, $medevac['tenant_id'], $medevac['context_id']]
        )->fetch();

        if (!$asset || !$asset['current_pos_x']) return null;

        // Distance en mètres
        $distance = sqrt(
            pow($asset['current_pos_x'] - $medevac['pickup_pos_x'], 2) +
            pow($asset['current_pos_y'] - $medevac['pickup_pos_y'], 2)
        );

        // Conversion vitesse km/h -> m/min
        $speedMetersPerMinute = ($asset['cruise_speed_kph'] ?? 200) * 1000 / 60;

        // Temps de vol brut
        $flightTimeMinutes = $distance / $speedMetersPerMinute;

        // Ajout marges : préparation (2min) + approche prudente (+20%)
        $totalTimeMinutes = 2 + ($flightTimeMinutes * 1.2);

        // Ajustements conditions
        if ($medevac['weather_impact'] === 'MODERATE') {
            $totalTimeMinutes *= 1.15;
        } elseif ($medevac['weather_impact'] === 'SEVERE') {
            $totalTimeMinutes *= 1.3;
        }

        if (in_array($medevac['security_status'], ['ENEMY_TROOPS', 'HOT_LZ'])) {
            $totalTimeMinutes += 3; // Temps manœuvres évasives
        }

        $eta = date('Y-m-d H:i:s', time() + ($totalTimeMinutes * 60));

        // Mettre à jour MEDEVAC
        $this->db->execute(
            "UPDATE atak_medevac_requests 
             SET eta = ?, 
                 estimated_response_time_minutes = ?,
                 nearest_available_asset = ?
             WHERE id = ?",
            [$eta, round($totalTimeMinutes), $assetCallsign, $medevacId]
        );

        return $eta;
    }

    /**
     * Évalue la menace de la zone pickup
     */
    public function assessPickupZoneThreat(int $medevacId): array
    {
        $medevac = $this->db->query(
            "SELECT * FROM atak_medevac_requests WHERE id = ?",
            [$medevacId]
        )->fetch();

        // Trouver zone contenant position pickup
        $zoneRepo = new AtakTacticalZoneRepository($this->db);
        $zones = $zoneRepo->findZonesContainingPosition(
            $medevac['tenant_id'],
            $medevac['context_id'],
            $medevac['pickup_pos_x'],
            $medevac['pickup_pos_y']
        );

        $maxThreat = 'NONE';
        $threatFactors = [];

        foreach ($zones as $zone) {
            if ($zone['threat_score'] > 0) {
                $threatFactors[] = [
                    'zone_name' => $zone['zone_name'],
                    'threat_score' => $zone['threat_score'],
                    'threat_level' => $zone['threat_level']
                ];

                // Prendre niveau max
                $threatOrder = ['NONE', 'LOW', 'MEDIUM', 'HIGH', 'CRITICAL'];
                $current = array_search($maxThreat, $threatOrder);
                $new = array_search($zone['threat_level'], $threatOrder);
                if ($new > $current) {
                    $maxThreat = $zone['threat_level'];
                }
            }
        }

        // Mettre à jour MEDEVAC
        $this->db->execute(
            "UPDATE atak_medevac_requests 
             SET pickup_zone_threat_level = ?
             WHERE id = ?",
            [$maxThreat, $medevacId]
        );

        return [
            'threat_level' => $maxThreat,
            'threat_factors' => $threatFactors
        ];
    }

    /**
     * Libellé assessment urgence
     */
    private function getUrgencyAssessment(float $score): string
    {
        if ($score >= 90) return 'EXTREME';
        if ($score >= 75) return 'CRITICAL';
        if ($score >= 50) return 'HIGH';
        if ($score >= 25) return 'MEDIUM';
        return 'LOW';
    }

    /**
     * Liste MEDEVAC par urgence décroissante
     */
    public function listByUrgency(int $tenantId, int $contextId, array $statuses = ['REQUESTED', 'ACKNOWLEDGED']): array
    {
        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        
        return $this->db->query(
            "SELECT * FROM v_atak_medevac_optimized
             WHERE tenant_id = ? AND context_id = ?
               AND status IN ($placeholders)
             ORDER BY urgency_score DESC, requested_at ASC",
            array_merge([$tenantId, $contextId], $statuses)
        )->fetchAll();
    }

    /**
     * Recalcule tous les scores d'urgence
     */
    public function recalculateAllScores(int $tenantId, int $contextId): array
    {
        $medevacs = $this->db->query(
            "SELECT id FROM atak_medevac_requests 
             WHERE tenant_id = ? AND context_id = ?
               AND status IN ('REQUESTED', 'ACKNOWLEDGED', 'ASSIGNED', 'INBOUND')",
            [$tenantId, $contextId]
        )->fetchAll();

        $results = [];
        foreach ($medevacs as $medevac) {
            $results[$medevac['id']] = $this->calculateUrgencyScore($medevac['id']);
        }

        return $results;
    }
}
