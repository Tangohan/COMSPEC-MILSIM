<?php

namespace App\Repositories;

use App\Core\Database;

/**
 * Repository pour l'intelligence QRF, véhicules et POI
 * Regroupe les fonctionnalités avancées pour ces 3 systèmes
 */
class AtakAdvancedIntelligenceRepository
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    // ============================================
    // QRF : Optimisation route et coordination
    // ============================================

    /**
     * Calcule route optimale pour QRF
     */
    public function calculateOptimalQrfRoute(int $qrfId): array
    {
        $qrf = $this->db->query(
            "SELECT * FROM atak_qrf_requests WHERE id = ?",
            [$qrfId]
        )->fetch();

        if (!$qrf || !$qrf['qrf_current_pos_x']) {
            return ['error' => 'QRF position unavailable'];
        }

        // Position départ et arrivée
        $start = ['x' => $qrf['qrf_current_pos_x'], 'y' => $qrf['qrf_current_pos_y']];
        $end = ['x' => $qrf['contact_pos_x'], 'y' => $qrf['contact_pos_y']];

        // Distance directe
        $directDistance = sqrt(pow($end['x'] - $start['x'], 2) + pow($end['y'] - $start['y'], 2));

        // Vérifier dangers sur route directe
        $hazards = $this->findHazardsAlongRoute($qrf['tenant_id'], $qrf['context_id'], $start, $end);

        // Générer waypoints (simplifié - ligne droite avec évitement dangers)
        $waypoints = $this->generateWaypoints($start, $end, $hazards);

        // Calculer distance totale et temps
        $routeDistance = $this->calculateRouteDistance($waypoints);
        $estimatedSpeed = 60; // km/h moyen véhicule tactique
        $estimatedTimeMinutes = ($routeDistance / 1000) / $estimatedSpeed * 60;

        // Sauvegarder route
        $this->db->execute(
            "UPDATE atak_qrf_requests 
             SET optimal_route_calculated = TRUE,
                 route_waypoints = ?,
                 route_distance_meters = ?,
                 route_estimated_time_minutes = ?,
                 route_hazards = ?
             WHERE id = ?",
            [
                json_encode($waypoints),
                $routeDistance,
                round($estimatedTimeMinutes),
                json_encode($hazards),
                $qrfId
            ]
        );

        return [
            'waypoints' => $waypoints,
            'distance_meters' => round($routeDistance, 2),
            'estimated_time_minutes' => round($estimatedTimeMinutes, 1),
            'hazards_count' => count($hazards),
            'hazards' => $hazards
        ];
    }

    /**
     * Trouve dangers le long d'une route
     */
    private function findHazardsAlongRoute(int $tenantId, int $contextId, array $start, array $end): array
    {
        // Buffer 200m de chaque côté de la ligne
        $buffer = 200;

        // POI hostiles proches de la route
        $hazards = $this->db->query(
            "SELECT poi_name, category, threat_level, pos_x, pos_y
             FROM atak_poi
             WHERE tenant_id = ? AND context_id = ?
               AND affiliation = 'ENEMY'
               AND deleted_at IS NULL
               AND ST_Distance_Sphere(
                   POINT(pos_x, pos_y),
                   LineString(POINT(?, ?), POINT(?, ?))
               ) <= ?",
            [$tenantId, $contextId, $start['x'], $start['y'], $end['x'], $end['y'], $buffer]
        )->fetchAll();

        // Zones danger
        $dangerZones = $this->db->query(
            "SELECT zone_name, zone_type, threat_score, center_x, center_y, radius
             FROM atak_tactical_zones
             WHERE tenant_id = ? AND context_id = ?
               AND zone_type IN ('DANGER_ZONE', 'NO_GO_AREA')
               AND status = 'ACTIVE'
               AND deleted_at IS NULL",
            [$tenantId, $contextId]
        )->fetchAll();

        return array_merge(
            array_map(fn($h) => ['type' => 'POI', 'data' => $h], $hazards),
            array_map(fn($z) => ['type' => 'ZONE', 'data' => $z], $dangerZones)
        );
    }

    /**
     * Génère waypoints avec évitement basique
     */
    private function generateWaypoints(array $start, array $end, array $hazards): array
    {
        // Simplifié : route directe avec waypoints intermédiaires tous les 1000m
        $distance = sqrt(pow($end['x'] - $start['x'], 2) + pow($end['y'] - $start['y'], 2));
        $waypoints = [['x' => $start['x'], 'y' => $start['y'], 'type' => 'START']];

        $numWaypoints = max(1, floor($distance / 1000));
        for ($i = 1; $i <= $numWaypoints; $i++) {
            $ratio = $i / ($numWaypoints + 1);
            $waypoints[] = [
                'x' => $start['x'] + ($end['x'] - $start['x']) * $ratio,
                'y' => $start['y'] + ($end['y'] - $start['y']) * $ratio,
                'type' => 'INTERMEDIATE'
            ];
        }

        $waypoints[] = ['x' => $end['x'], 'y' => $end['y'], 'type' => 'END'];

        return $waypoints;
    }

    /**
     * Calcule distance totale route
     */
    private function calculateRouteDistance(array $waypoints): float
    {
        $totalDistance = 0;
        for ($i = 0; $i < count($waypoints) - 1; $i++) {
            $totalDistance += sqrt(
                pow($waypoints[$i+1]['x'] - $waypoints[$i]['x'], 2) +
                pow($waypoints[$i+1]['y'] - $waypoints[$i]['y'], 2)
            );
        }
        return $totalDistance;
    }

    /**
     * Crée coordination multi-QRF
     */
    public function createQrfCoordination(int $tenantId, int $contextId, array $data): int
    {
        $this->db->execute(
            "INSERT INTO atak_qrf_coordination 
             (tenant_id, context_id, coordination_name, primary_qrf_id, secondary_qrf_ids,
              coordination_type, coordination_plan, synchronize_arrival, target_arrival_time,
              common_frequency, command_callsign, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $tenantId,
                $contextId,
                $data['coordination_name'],
                $data['primary_qrf_id'],
                json_encode($data['secondary_qrf_ids'] ?? []),
                $data['coordination_type'],
                $data['coordination_plan'] ?? null,
                $data['synchronize_arrival'] ?? true,
                $data['target_arrival_time'] ?? null,
                $data['common_frequency'] ?? null,
                $data['command_callsign'] ?? null,
                'PLANNED'
            ]
        );

        return $this->db->lastInsertId();
    }

    // ============================================
    // VÉHICULES : Prédiction panne et maintenance
    // ============================================

    /**
     * Calcule score maintenance véhicule
     */
    public function calculateVehicleMaintenanceScore(int $vehicleId): array
    {
        $vehicle = $this->db->query(
            "SELECT * FROM atak_vehicle_tracking WHERE id = ?",
            [$vehicleId]
        )->fetch();

        if (!$vehicle) {
            return ['error' => 'Vehicle not found'];
        }

        $factors = [];
        $totalScore = 100.0; // Score santé démarre à 100

        // Facteur 1 : Santé composants (max -40 points)
        $componentHealth = ($vehicle['engine_health'] + $vehicle['hull_health'] + 
                           $vehicle['tracks_wheels_health'] + $vehicle['turret_health']) / 4;
        $healthPenalty = (100 - $componentHealth) * 0.4;
        $totalScore -= $healthPenalty;
        $factors[] = ['factor' => 'component_health', 'value' => round($componentHealth, 1), 'impact' => -round($healthPenalty, 2)];

        // Facteur 2 : Distance parcourue (max -20 points)
        $distanceKm = $vehicle['total_distance_traveled'] ?? 0;
        if ($distanceKm > 500) {
            $distancePenalty = min(20, ($distanceKm - 500) / 50); // -1pt tous les 50km après 500km
            $totalScore -= $distancePenalty;
            $factors[] = ['factor' => 'distance_traveled', 'value' => $distanceKm, 'impact' => -round($distancePenalty, 2)];
        }

        // Facteur 3 : Heures opération (max -20 points)
        $hours = $vehicle['total_operating_hours'] ?? 0;
        if ($hours > 100) {
            $hoursPenalty = min(20, ($hours - 100) / 10); // -1pt toutes les 10h après 100h
            $totalScore -= $hoursPenalty;
            $factors[] = ['factor' => 'operating_hours', 'value' => $hours, 'impact' => -round($hoursPenalty, 2)];
        }

        // Facteur 4 : Temps depuis maintenance (max -20 points)
        if ($vehicle['last_maintenance_at']) {
            $daysSinceMaintenance = (time() - strtotime($vehicle['last_maintenance_at'])) / 86400;
            if ($daysSinceMaintenance > 7) {
                $maintenancePenalty = min(20, ($daysSinceMaintenance - 7) / 2); // -1pt tous les 2 jours après 7 jours
                $totalScore -= $maintenancePenalty;
                $factors[] = ['factor' => 'days_since_maintenance', 'value' => round($daysSinceMaintenance, 1), 'impact' => -round($maintenancePenalty, 2)];
            }
        }

        $maintenanceScore = max(0, min(100, $totalScore));

        // Déterminer risque panne
        $failureRisk = 'NONE';
        if ($maintenanceScore < 20) $failureRisk = 'CRITICAL';
        elseif ($maintenanceScore < 40) $failureRisk = 'HIGH';
        elseif ($maintenanceScore < 60) $failureRisk = 'MEDIUM';
        elseif ($maintenanceScore < 80) $failureRisk = 'LOW';

        // Recommandations maintenance
        $recommendations = $this->generateMaintenanceRecommendations($vehicle, $maintenanceScore, $factors);

        // Prédire temps avant panne potentielle
        $predictedFailureTime = $this->predictFailureTime($maintenanceScore);

        // Mettre à jour
        $this->db->execute(
            "UPDATE atak_vehicle_tracking 
             SET maintenance_score = ?,
                 failure_risk = ?,
                 recommended_maintenance = ?,
                 predicted_failure_time = ?
             WHERE id = ?",
            [
                $maintenanceScore,
                $failureRisk,
                implode('; ', $recommendations),
                $predictedFailureTime,
                $vehicleId
            ]
        );

        return [
            'maintenance_score' => round($maintenanceScore, 2),
            'failure_risk' => $failureRisk,
            'factors' => $factors,
            'recommendations' => $recommendations,
            'predicted_failure_time' => $predictedFailureTime
        ];
    }

    /**
     * Génère recommandations maintenance
     */
    private function generateMaintenanceRecommendations(array $vehicle, float $score, array $factors): array
    {
        $recommendations = [];

        if ($vehicle['engine_health'] < 60) {
            $recommendations[] = "Inspection moteur urgente";
        }
        if ($vehicle['hull_health'] < 70) {
            $recommendations[] = "Réparation blindage recommandée";
        }
        if ($vehicle['tracks_wheels_health'] < 70) {
            $recommendations[] = "Vérification chenilles/roues nécessaire";
        }

        $daysSinceMaintenance = $vehicle['last_maintenance_at'] ? 
            (time() - strtotime($vehicle['last_maintenance_at'])) / 86400 : 999;

        if ($daysSinceMaintenance > 14) {
            $recommendations[] = "Maintenance générale en retard";
        }

        if ($score < 40) {
            $recommendations[] = "⚠️ PRIORITÉ HAUTE : Maintenance immédiate recommandée";
        }

        if (empty($recommendations)) {
            $recommendations[] = "État satisfaisant, maintenance préventive dans 7 jours";
        }

        return $recommendations;
    }

    /**
     * Prédit temps avant panne
     */
    private function predictFailureTime(float $score): ?string
    {
        if ($score >= 80) return null; // Aucune panne prévue

        // Modèle simplifié : heures avant panne selon score
        $hoursUntilFailure = ($score / 100) * 200; // 0% = 0h, 50% = 100h, 80% = 160h

        if ($hoursUntilFailure < 168) { // < 7 jours
            return date('Y-m-d H:i:s', time() + ($hoursUntilFailure * 3600));
        }

        return null;
    }

    /**
     * Enregistre maintenance effectuée
     */
    public function logVehicleMaintenance(int $vehicleId, array $maintenanceData): int
    {
        // Calculer amélioration santé
        $vehicle = $this->db->query("SELECT * FROM atak_vehicle_tracking WHERE id = ?", [$vehicleId])->fetch();
        $healthBefore = ($vehicle['engine_health'] + $vehicle['hull_health']) / 2;

        $this->db->execute(
            "INSERT INTO atak_vehicle_maintenance_log 
             (vehicle_tracking_id, maintenance_type, maintenance_category, description,
              parts_replaced, work_performed, performed_by_callsign, performed_by_user_id,
              maintenance_duration_minutes, condition_before, health_improvement)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $vehicleId,
                $maintenanceData['maintenance_type'],
                $maintenanceData['maintenance_category'],
                $maintenanceData['description'],
                json_encode($maintenanceData['parts_replaced'] ?? []),
                $maintenanceData['work_performed'] ?? null,
                $maintenanceData['performed_by_callsign'] ?? null,
                $maintenanceData['performed_by_user_id'] ?? null,
                $maintenanceData['maintenance_duration_minutes'] ?? null,
                $vehicle['status'],
                $maintenanceData['health_improvement'] ?? 0
            ]
        );

        // Mettre à jour véhicule
        $this->db->execute(
            "UPDATE atak_vehicle_tracking 
             SET last_maintenance_at = NOW(),
                 next_maintenance_due_at = DATE_ADD(NOW(), INTERVAL 7 DAY)
             WHERE id = ?",
            [$vehicleId]
        );

        // Recalculer score
        $this->calculateVehicleMaintenanceScore($vehicleId);

        return $this->db->lastInsertId();
    }

    // ============================================
    // POI : Corrélation et scoring confiance
    // ============================================

    /**
     * Détecte corrélations entre POI
     */
    public function detectPoiCorrelations(int $tenantId, int $contextId): array
    {
        $pois = $this->db->query(
            "SELECT * FROM atak_poi 
             WHERE tenant_id = ? AND context_id = ? AND deleted_at IS NULL",
            [$tenantId, $contextId]
        )->fetchAll();

        $correlations = [];

        for ($i = 0; $i < count($pois); $i++) {
            for ($j = $i + 1; $j < count($pois); $j++) {
                $correlation = $this->analyzePoiPair($pois[$i], $pois[$j]);
                
                if ($correlation && $correlation['strength'] >= 30) { // Seuil 30%
                    $correlations[] = $correlation;
                    $this->saveCorrelation($tenantId, $contextId, $correlation);
                }
            }
        }

        return $correlations;
    }

    /**
     * Analyse corrélation entre deux POI
     */
    private function analyzePoiPair(array $poi1, array $poi2): ?array
    {
        $strength = 0.0;
        $type = null;
        $explanation = [];

        // Corrélation proximité (max 40 points)
        $distance = sqrt(
            pow($poi2['pos_x'] - $poi1['pos_x'], 2) +
            pow($poi2['pos_y'] - $poi1['pos_y'], 2)
        );

        if ($distance < 500) {
            $proximityScore = 40 * (1 - ($distance / 500));
            $strength += $proximityScore;
            $type = 'PROXIMITY';
            $explanation[] = sprintf("Proximité %.0fm", $distance);
        }

        // Corrélation temporelle (max 30 points)
        $timeDiff = abs(strtotime($poi1['created_at']) - strtotime($poi2['created_at'])) / 3600;
        if ($timeDiff < 24) { // Moins de 24h
            $temporalScore = 30 * (1 - ($timeDiff / 24));
            $strength += $temporalScore;
            if (!$type) $type = 'TEMPORAL';
            $explanation[] = sprintf("Créés à %.1fh d'intervalle", $timeDiff);
        }

        // Corrélation activité pattern (max 30 points)
        if ($poi1['category'] === $poi2['category'] && $poi1['affiliation'] === $poi2['affiliation']) {
            $strength += 20;
            if (!$type) $type = 'ACTIVITY_PATTERN';
            $explanation[] = "Même type et affiliation";
        }

        if ($strength < 30) return null;

        return [
            'poi_id_1' => $poi1['id'],
            'poi_id_2' => $poi2['id'],
            'type' => $type,
            'strength' => round($strength, 2),
            'explanation' => implode('; ', $explanation),
            'intel_value' => $strength >= 70 ? 'HIGH' : ($strength >= 50 ? 'MEDIUM' : 'LOW')
        ];
    }

    /**
     * Sauvegarde une corrélation
     */
    private function saveCorrelation(int $tenantId, int $contextId, array $correlation): void
    {
        // Vérifier si existe déjà
        $existing = $this->db->query(
            "SELECT id FROM atak_poi_correlations 
             WHERE poi_id_1 = ? AND poi_id_2 = ? AND correlation_type = ?",
            [$correlation['poi_id_1'], $correlation['poi_id_2'], $correlation['type']]
        )->fetch();

        if ($existing) return;

        $this->db->execute(
            "INSERT INTO atak_poi_correlations 
             (tenant_id, context_id, poi_id_1, poi_id_2, correlation_type,
              correlation_strength, correlation_explanation, intel_value, detected_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $tenantId,
                $contextId,
                $correlation['poi_id_1'],
                $correlation['poi_id_2'],
                $correlation['type'],
                $correlation['strength'],
                $correlation['explanation'],
                $correlation['intel_value'],
                'AUTOMATIC'
            ]
        );

        // Mettre à jour compteurs POI
        $this->db->execute(
            "UPDATE atak_poi 
             SET correlation_count = correlation_count + 1 
             WHERE id IN (?, ?)",
            [$correlation['poi_id_1'], $correlation['poi_id_2']]
        );
    }

    /**
     * Calcule score confiance POI
     */
    public function calculatePoiConfidence(int $poiId): array
    {
        $poi = $this->db->query("SELECT * FROM atak_poi WHERE id = ?", [$poiId])->fetch();
        
        if (!$poi) return ['error' => 'POI not found'];

        $factors = [];
        $confidence = 50.0; // Base 50%

        // Source fiabilité (+/- 20 points)
        $reliabilityScores = [
            'COMPLETELY_RELIABLE' => 20,
            'USUALLY_RELIABLE' => 15,
            'FAIRLY_RELIABLE' => 10,
            'NOT_USUALLY_RELIABLE' => -5,
            'UNRELIABLE' => -10,
            'CANNOT_BE_JUDGED' => 0
        ];
        $confidence += $reliabilityScores[$poi['source_reliability']] ?? 0;
        $factors[] = ['factor' => 'source_reliability', 'value' => $poi['source_reliability'], 'impact' => $reliabilityScores[$poi['source_reliability']] ?? 0];

        // Observations multiples (+30 points max)
        $observationCount = $poi['observation_count'] ?? 0;
        $observationBonus = min(30, $observationCount * 10);
        $confidence += $observationBonus;
        $factors[] = ['factor' => 'observations', 'value' => $observationCount, 'impact' => $observationBonus];

        // Photos (+10 points)
        $photoCount = $poi['photo_count'] ?? 0;
        if ($photoCount > 0) {
            $confidence += min(10, $photoCount * 5);
            $factors[] = ['factor' => 'photos', 'value' => $photoCount, 'impact' => min(10, $photoCount * 5)];
        }

        // Corrélations (+20 points max)
        $correlationBonus = min(20, $poi['correlation_count'] * 5);
        $confidence += $correlationBonus;
        $factors[] = ['factor' => 'correlations', 'value' => $poi['correlation_count'], 'impact' => $correlationBonus];

        // Ancienneté (-10 points max si > 30 jours)
        $ageDay = (time() - strtotime($poi['created_at'])) / 86400;
        if ($ageHours > 30) {
            $agePenalty = -min(10, ($ageDays - 30) / 10);
            $confidence += $agePenalty;
            $factors[] = ['factor' => 'age_days', 'value' => round($ageDays, 1), 'impact' => round($agePenalty, 2)];
        }

        $confidence = max(0, min(100, $confidence));

        // Intel quality
        $intelQuality = 'UNVERIFIED';
        if ($confidence >= 90) $intelQuality = 'CONFIRMED';
        elseif ($confidence >= 70) $intelQuality = 'HIGH';
        elseif ($confidence >= 50) $intelQuality = 'MEDIUM';
        elseif ($confidence >= 30) $intelQuality = 'LOW';

        // Mettre à jour
        $this->db->execute(
            "UPDATE atak_poi 
             SET confidence_score = ?,
                 last_updated_confidence = NOW(),
                 intel_quality = ?
             WHERE id = ?",
            [$confidence, $intelQuality, $poiId]
        );

        return [
            'confidence_score' => round($confidence, 2),
            'intel_quality' => $intelQuality,
            'factors' => $factors
        ];
    }

    /**
     * Recalcule tous les scores confiance
     */
    public function recalculateAllPoiConfidence(int $tenantId, int $contextId): array
    {
        $pois = $this->db->query(
            "SELECT id FROM atak_poi 
             WHERE tenant_id = ? AND context_id = ? AND deleted_at IS NULL",
            [$tenantId, $contextId]
        )->fetchAll();

        $results = [];
        foreach ($pois as $poi) {
            $results[$poi['id']] = $this->calculatePoiConfidence($poi['id']);
        }

        return $results;
    }
}
