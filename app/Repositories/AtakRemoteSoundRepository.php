<?php
/**
 * Repository pour gestion sons à distance (Troll + Réaliste)
 * 
 * Permet au commandement de déclencher des sons sur les joueurs in-game
 * Gère deux modes : troll (fun) et réaliste (immersion)
 */

class AtakRemoteSoundRepository
{
    private PDO $db;
    
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }
    
    /**
     * Déclencher un son à distance
     * 
     * @param int $tenantId
     * @param int $contextId
     * @param string $soundType 'troll' ou 'realistic'
     * @param string $soundId Identifiant du son
     * @param string $targetType 'player', 'unit', 'group', 'all', 'position'
     * @param string|null $targetIdentifier Callsign, Steam ID, ou nom groupe
     * @param array|null $position [x, y, z] pour sons 3D
     * @param float $volume 0.0 à 1.0
     * @param int $distanceAudible Distance en mètres (pour 3D)
     * @param int|null $triggeredByUserId Qui déclenche le son
     * @param string|null $reason Raison / contexte
     * @param int $expirationSeconds Expiration en secondes (défaut 300 = 5min)
     * @return int|false ID du son créé ou false en cas d'erreur
     */
    public function triggerSound(
        int $tenantId,
        int $contextId,
        string $soundType,
        string $soundId,
        string $targetType = 'player',
        ?string $targetIdentifier = null,
        ?array $position = null,
        float $volume = 1.0,
        int $distanceAudible = 100,
        ?int $triggeredByUserId = null,
        ?string $reason = null,
        int $expirationSeconds = 300
    ): int|false {
        // Validation
        if (!in_array($soundType, ['troll', 'realistic'])) {
            return false;
        }
        
        if (!in_array($targetType, ['player', 'unit', 'group', 'all', 'position'])) {
            return false;
        }
        
        if ($volume < 0.0 || $volume > 1.0) {
            $volume = 1.0;
        }
        
        // Vérifier permissions / cooldown si mode troll
        if ($soundType === 'troll') {
            $config = $this->getTrollConfig($tenantId);
            
            if (!$config['troll_mode_enabled']) {
                error_log("[ATAK Remote Sound] Mode troll désactivé pour tenant {$tenantId}");
                return false;
            }
            
            // Vérifier cooldown
            if (!$this->checkTrollCooldown($tenantId, $contextId, $targetIdentifier, $config['troll_cooldown_seconds'])) {
                error_log("[ATAK Remote Sound] Cooldown troll actif");
                return false;
            }
            
            // Vérifier limite horaire
            if (!$this->checkTrollHourlyLimit($tenantId, $contextId, $config['troll_max_per_hour'])) {
                error_log("[ATAK Remote Sound] Limite horaire troll atteinte");
                return false;
            }
        }
        
        // Préparer expiration
        $expiresAt = date('Y-m-d H:i:s', time() + $expirationSeconds);
        
        // Insérer son
        $sql = "
            INSERT INTO atak_remote_sounds (
                tenant_id, context_id, sound_type, sound_id,
                target_type, target_identifier,
                position_x, position_y, position_z,
                volume, distance_audible,
                triggered_by_user_id, reason,
                expires_at, status
            ) VALUES (
                :tenant_id, :context_id, :sound_type, :sound_id,
                :target_type, :target_identifier,
                :position_x, :position_y, :position_z,
                :volume, :distance_audible,
                :triggered_by_user_id, :reason,
                :expires_at, 'pending'
            )
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'tenant_id' => $tenantId,
            'context_id' => $contextId,
            'sound_type' => $soundType,
            'sound_id' => $soundId,
            'target_type' => $targetType,
            'target_identifier' => $targetIdentifier,
            'position_x' => $position[0] ?? null,
            'position_y' => $position[1] ?? null,
            'position_z' => $position[2] ?? 0,
            'volume' => $volume,
            'distance_audible' => $distanceAudible,
            'triggered_by_user_id' => $triggeredByUserId,
            'reason' => $reason,
            'expires_at' => $expiresAt
        ]);
        
        return (int)$this->db->lastInsertId();
    }
    
    /**
     * Récupérer sons en attente pour un joueur
     * 
     * @param int $tenantId
     * @param int $contextId
     * @param string|null $callsign
     * @param string|null $steamId
     * @return array Liste sons à jouer
     */
    public function getPendingSounds(
        int $tenantId,
        int $contextId,
        ?string $callsign = null,
        ?string $steamId = null
    ): array {
        $sql = "
            SELECT 
                id, sound_type, sound_id,
                position_x, position_y, position_z,
                volume, distance_audible,
                triggered_by_user_id, triggered_at, reason
            FROM atak_remote_sounds
            WHERE tenant_id = :tenant_id
              AND context_id = :context_id
              AND status = 'pending'
              AND expires_at > NOW()
              AND (
                  target_type = 'all'
                  OR (target_type = 'player' AND (
                      target_identifier = :callsign 
                      OR target_identifier = :steam_id
                  ))
              )
            ORDER BY triggered_at ASC
            LIMIT 10
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'tenant_id' => $tenantId,
            'context_id' => $contextId,
            'callsign' => $callsign,
            'steam_id' => $steamId
        ]);
        
        $sounds = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Formater position en array si présente
        foreach ($sounds as &$sound) {
            if ($sound['position_x'] !== null) {
                $sound['position'] = [
                    (float)$sound['position_x'],
                    (float)$sound['position_y'],
                    (float)$sound['position_z']
                ];
            } else {
                $sound['position'] = null;
            }
            unset($sound['position_x'], $sound['position_y'], $sound['position_z']);
            
            $sound['volume'] = (float)$sound['volume'];
            $sound['distance_audible'] = (int)$sound['distance_audible'];
        }
        
        return $sounds;
    }
    
    /**
     * Marquer un son comme livré (acknowledge)
     * 
     * @param int $soundId
     * @return bool Succès
     */
    public function acknowledgeSoundPlayed(int $soundId): bool
    {
        $sql = "
            UPDATE atak_remote_sounds
            SET status = 'delivered',
                delivered_at = NOW(),
                acknowledged_at = NOW()
            WHERE id = :id
              AND status = 'pending'
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $soundId]);
        
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Obtenir configuration mode troll
     */
    private function getTrollConfig(int $tenantId): array
    {
        $sql = "
            SELECT troll_mode_enabled, troll_cooldown_seconds, troll_max_per_hour
            FROM atak_sound_config
            WHERE tenant_id = :tenant_id
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['tenant_id' => $tenantId]);
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$config) {
            // Config par défaut
            return [
                'troll_mode_enabled' => false,
                'troll_cooldown_seconds' => 60,
                'troll_max_per_hour' => 10
            ];
        }
        
        return [
            'troll_mode_enabled' => (bool)$config['troll_mode_enabled'],
            'troll_cooldown_seconds' => (int)$config['troll_cooldown_seconds'],
            'troll_max_per_hour' => (int)$config['troll_max_per_hour']
        ];
    }
    
    /**
     * Vérifier cooldown entre sons troll
     */
    private function checkTrollCooldown(
        int $tenantId,
        int $contextId,
        ?string $targetIdentifier,
        int $cooldownSeconds
    ): bool {
        $sql = "
            SELECT COUNT(*) as count
            FROM atak_remote_sounds
            WHERE tenant_id = :tenant_id
              AND context_id = :context_id
              AND sound_type = 'troll'
              AND target_identifier = :target_identifier
              AND triggered_at > DATE_SUB(NOW(), INTERVAL :cooldown SECOND)
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'tenant_id' => $tenantId,
            'context_id' => $contextId,
            'target_identifier' => $targetIdentifier,
            'cooldown' => $cooldownSeconds
        ]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] == 0;
    }
    
    /**
     * Vérifier limite horaire sons troll
     */
    private function checkTrollHourlyLimit(
        int $tenantId,
        int $contextId,
        int $maxPerHour
    ): bool {
        $sql = "
            SELECT COUNT(*) as count
            FROM atak_remote_sounds
            WHERE tenant_id = :tenant_id
              AND context_id = :context_id
              AND sound_type = 'troll'
              AND triggered_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'tenant_id' => $tenantId,
            'context_id' => $contextId
        ]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] < $maxPerHour;
    }
    
    /**
     * Lister sons récents (pour admin/debug)
     */
    public function listRecentSounds(
        int $tenantId,
        int $contextId,
        int $limit = 50
    ): array {
        $sql = "
            SELECT *
            FROM v_atak_pending_sounds
            WHERE tenant_id = :tenant_id
              AND context_id = :context_id
            ORDER BY created_at DESC
            LIMIT :limit
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'tenant_id' => $tenantId,
            'context_id' => $contextId,
            'limit' => $limit
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Statistiques sons joués
     */
    public function getSoundStats(
        int $tenantId,
        int $contextId,
        string $period = '7d'
    ): array {
        $interval = match($period) {
            '1d' => 'INTERVAL 1 DAY',
            '7d' => 'INTERVAL 7 DAY',
            '30d' => 'INTERVAL 30 DAY',
            default => 'INTERVAL 7 DAY'
        };
        
        $sql = "
            SELECT 
                sound_type,
                COUNT(*) as total_played,
                COUNT(DISTINCT target_identifier) as unique_targets,
                COUNT(CASE WHEN acknowledged THEN 1 END) as acknowledged_count
            FROM atak_sound_history
            WHERE tenant_id = :tenant_id
              AND context_id = :context_id
              AND played_at > DATE_SUB(NOW(), {$interval})
            GROUP BY sound_type
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'tenant_id' => $tenantId,
            'context_id' => $contextId
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Activer/désactiver mode troll
     */
    public function setTrollMode(int $tenantId, bool $enabled): bool
    {
        $sql = "
            INSERT INTO atak_sound_config (tenant_id, troll_mode_enabled)
            VALUES (:tenant_id, :enabled)
            ON DUPLICATE KEY UPDATE troll_mode_enabled = :enabled
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'tenant_id' => $tenantId,
            'enabled' => $enabled ? 1 : 0
        ]);
        
        return true;
    }
}
