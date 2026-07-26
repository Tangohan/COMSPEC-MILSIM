<?php

namespace App\Repositories;

use App\Core\Database;

/**
 * Repository pour le calcul automatique de menace et notifications temps réel des zones
 */
class AtakZoneThreatRepository
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Enregistre un événement dans une zone
     */
    public function recordZoneEvent(int $zoneId, int $tenantId, int $contextId, array $eventData): int
    {
        // Calculer impact menace selon type et sévérité
        $threatImpact = $this->calculateThreatImpact($eventData['event_type'], $eventData['event_severity']);

        // Calculer expiration selon sévérité (événements expirent)
        $expiresAt = $this->calculateEventExpiration($eventData['event_severity']);

        $this->db->execute(
            "INSERT INTO atak_zone_events 
             (zone_id, tenant_id, context_id, event_type, event_severity, event_description,
              event_pos_x, event_pos_y, threat_impact, reported_by_user_id, reported_by_callsign,
              event_occurred_at, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $zoneId,
                $tenantId,
                $contextId,
                $eventData['event_type'],
                $eventData['event_severity'],
                $eventData['event_description'] ?? null,
                $eventData['event_pos_x'] ?? null,
                $eventData['event_pos_y'] ?? null,
                $threatImpact,
                $eventData['reported_by_user_id'] ?? null,
                $eventData['reported_by_callsign'] ?? null,
                $eventData['event_occurred_at'] ?? date('Y-m-d H:i:s'),
                $expiresAt
            ]
        );

        $eventId = $this->db->lastInsertId();

        // Déclencher recalcul menace zone
        $this->recalculateZoneThreat($zoneId);

        return $eventId;
    }

    /**
     * Calcule l'impact menace d'un événement
     */
    private function calculateThreatImpact(string $eventType, string $severity): float
    {
        // Impact de base selon type
        $baseImpact = [
            'CONTACT_ENEMY' => 25.0,
            'FIRE_TAKEN' => 30.0,
            'IED_EXPLOSION' => 35.0,
            'CASUALTY' => 20.0,
            'POI_DISCOVERED' => 15.0,
            'ACTIVITY_SUSPICIOUS' => 10.0,
            'UNIT_AMBUSHED' => 40.0,
            'INTEL_REPORT' => 12.0,
            'DRONE_DETECTION' => 18.0,
            'OTHER' => 10.0
        ];

        $impact = $baseImpact[$eventType] ?? 10.0;

        // Multiplicateur selon sévérité
        $multipliers = [
            'LOW' => 0.5,
            'MEDIUM' => 1.0,
            'HIGH' => 1.5,
            'CRITICAL' => 2.0
        ];

        return $impact * ($multipliers[$severity] ?? 1.0);
    }

    /**
     * Calcule la durée de validité d'un événement
     */
    private function calculateEventExpiration(string $severity): string
    {
        // Durée selon sévérité
        $durations = [
            'LOW' => '+30 minutes',
            'MEDIUM' => '+1 hour',
            'HIGH' => '+2 hours',
            'CRITICAL' => '+4 hours'
        ];

        $duration = $durations[$severity] ?? '+1 hour';
        return date('Y-m-d H:i:s', strtotime($duration));
    }

    /**
     * Recalcule le score de menace d'une zone
     */
    public function recalculateZoneThreat(int $zoneId): array
    {
        // Charger événements actifs non expirés
        $events = $this->db->query(
            "SELECT * FROM atak_zone_events 
             WHERE zone_id = ? 
               AND is_active = TRUE 
               AND (expires_at IS NULL OR expires_at > NOW())
             ORDER BY event_occurred_at DESC",
            [$zoneId]
        )->fetchAll();

        if (empty($events)) {
            // Pas d'événements : menace minimale
            $this->updateZoneThreat($zoneId, 0.0, [], 0);
            return ['threat_score' => 0.0, 'factors' => []];
        }

        // Calculer score avec decay temporel
        $totalThreat = 0.0;
        $factors = [];
        $now = time();

        foreach ($events as $event) {
            $eventTime = strtotime($event['event_occurred_at']);
            $ageHours = ($now - $eventTime) / 3600;

            // Decay exponentiel : événements récents pèsent plus
            $decayFactor = exp(-$ageHours / 2); // Demi-vie 2h
            $weightedThreat = $event['threat_impact'] * $decayFactor;

            $totalThreat += $weightedThreat;

            $factors[] = [
                'event_type' => $event['event_type'],
                'severity' => $event['event_severity'],
                'base_impact' => $event['threat_impact'],
                'weighted_impact' => round($weightedThreat, 2),
                'age_hours' => round($ageHours, 1)
            ];
        }

        // Normaliser score 0-100
        $threatScore = min(100, $totalThreat);

        // Compter menaces proches (dans rayon 500m)
        $nearbyThreats = $this->countNearbyThreats($zoneId);

        // Mettre à jour zone
        $this->updateZoneThreat($zoneId, $threatScore, $factors, $nearbyThreats);

        return [
            'threat_score' => round($threatScore, 2),
            'factors' => $factors,
            'nearby_threats' => $nearbyThreats
        ];
    }

    /**
     * Compte les menaces à proximité
     */
    private function countNearbyThreats(int $zoneId): int
    {
        // Charger position zone
        $zone = $this->db->query(
            "SELECT center_x, center_y FROM atak_tactical_zones WHERE id = ?",
            [$zoneId]
        )->fetch();

        if (!$zone) return 0;

        // Compter POI hostiles dans rayon 500m
        $poiCount = $this->db->query(
            "SELECT COUNT(*) as count FROM atak_poi 
             WHERE affiliation = 'ENEMY' 
               AND threat_level IN ('HIGH', 'CRITICAL')
               AND deleted_at IS NULL
               AND SQRT(POW(pos_x - ?, 2) + POW(pos_y - ?, 2)) <= 500",
            [$zone['center_x'], $zone['center_y']]
        )->fetch()['count'];

        return $poiCount;
    }

    /**
     * Met à jour le score menace dans la zone
     */
    private function updateZoneThreat(int $zoneId, float $score, array $factors, int $nearbyCount): void
    {
        // Déterminer niveau menace
        $threatLevel = 'NONE';
        if ($score >= 80) $threatLevel = 'CRITICAL';
        elseif ($score >= 60) $threatLevel = 'HIGH';
        elseif ($score >= 40) $threatLevel = 'MEDIUM';
        elseif ($score >= 20) $threatLevel = 'LOW';

        $this->db->execute(
            "UPDATE atak_tactical_zones 
             SET threat_score = ?, 
                 threat_last_updated = NOW(), 
                 threat_factors = ?,
                 nearby_threats_count = ?,
                 threat_level = ?
             WHERE id = ?",
            [
                $score,
                json_encode($factors),
                $nearbyCount,
                $threatLevel,
                $zoneId
            ]
        );

        // Si menace augmente significativement, créer notification
        $zone = $this->db->query("SELECT * FROM atak_tactical_zones WHERE id = ?", [$zoneId])->fetch();
        
        if ($score >= 60 && $zone['priority'] !== 'LOW') {
            $this->createThreatNotification($zone, $score, $factors);
        }
    }

    /**
     * Crée une notification de menace accrue
     */
    private function createThreatNotification(array $zone, float $score, array $factors): void
    {
        // Éviter notifications spam (max 1 par zone par 15min)
        $recentNotif = $this->db->query(
            "SELECT COUNT(*) as count FROM atak_realtime_notifications 
             WHERE source_entity_type = 'ZONE' 
               AND source_entity_id = ?
               AND notification_type = 'ZONE_THREAT_INCREASE'
               AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)",
            [$zone['id']]
        )->fetch();

        if ($recentNotif['count'] > 0) return;

        // Déterminer priorité notification
        $priority = $score >= 80 ? 'CRITICAL' : ($score >= 60 ? 'HIGH' : 'MEDIUM');

        // Message détaillé
        $eventTypes = array_unique(array_column($factors, 'event_type'));
        $eventSummary = implode(', ', array_map(function($type) {
            return str_replace('_', ' ', strtolower($type));
        }, $eventTypes));

        $message = sprintf(
            "Menace accrue détectée dans zone %s. Score : %.0f/100. Événements récents : %s.",
            $zone['zone_name'],
            $score,
            $eventSummary
        );

        $notificationRepo = new AtakNotificationRepository($this->db);
        $notificationRepo->create($zone['tenant_id'], $zone['context_id'], [
            'notification_type' => 'ZONE_THREAT_INCREASE',
            'priority' => $priority,
            'title' => "⚠️ Menace zone {$zone['zone_name']}",
            'message' => $message,
            'source_entity_type' => 'ZONE',
            'source_entity_id' => $zone['id'],
            'target_roles' => json_encode(['COMMAND', 'INTELLIGENCE']),
            'sound_alert' => $priority === 'CRITICAL' ? 'ALERT_CRITICAL' : 'ALERT_WARNING',
            'show_on_map' => true,
            'map_pos_x' => $zone['center_x'],
            'map_pos_y' => $zone['center_y'],
            'expires_at' => date('Y-m-d H:i:s', strtotime('+1 hour'))
        ]);
    }

    /**
     * Nettoie les événements expirés
     */
    public function cleanupExpiredEvents(int $tenantId, int $contextId): int
    {
        $stmt = $this->db->execute(
            "UPDATE atak_zone_events 
             SET is_active = FALSE 
             WHERE tenant_id = ? AND context_id = ?
               AND is_active = TRUE 
               AND expires_at IS NOT NULL 
               AND expires_at <= NOW()",
            [$tenantId, $contextId]
        );

        return $stmt->rowCount();
    }

    /**
     * Recalcule toutes les zones d'un contexte
     */
    public function recalculateAllZones(int $tenantId, int $contextId): array
    {
        $zones = $this->db->query(
            "SELECT id FROM atak_tactical_zones 
             WHERE tenant_id = ? AND context_id = ? AND deleted_at IS NULL",
            [$tenantId, $contextId]
        )->fetchAll();

        $results = [];
        foreach ($zones as $zone) {
            $results[$zone['id']] = $this->recalculateZoneThreat($zone['id']);
        }

        return $results;
    }

    /**
     * Liste les zones par niveau de menace
     */
    public function listByThreatLevel(int $tenantId, int $contextId, array $threatLevels = ['HIGH', 'CRITICAL']): array
    {
        $placeholders = implode(',', array_fill(0, count($threatLevels), '?'));

        return $this->db->query(
            "SELECT * FROM v_atak_zones_threat_assessed
             WHERE tenant_id = ? AND context_id = ?
               AND threat_level IN ($placeholders)
             ORDER BY threat_score DESC",
            array_merge([$tenantId, $contextId], $threatLevels)
        )->fetchAll();
    }
}
