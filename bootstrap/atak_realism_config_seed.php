<?php

declare(strict_types=1);

/**
 * Migration des données existantes vers atak_realism_config.
 * Consolide les paramètres dispersés (experience, roleplay, certificats) en une config JSON unique.
 * Idempotent : ne crée une config que si elle n'existe pas déjà pour le tenant.
 */
function run_atak_realism_config_seed(PDO $pdo): void
{
    // Vérifier que la table existe
    $hasTable = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if (!$hasTable('atak_realism_config') || !$hasTable('tenants') || !$hasTable('tenant_atak_config')) {
        return; // Tables nécessaires manquantes
    }

    // Récupérer tous les tenants actifs
    $st = $pdo->query('SELECT id FROM tenants WHERE deleted_at IS NULL ORDER BY id');
    $tenants = $st->fetchAll(PDO::FETCH_ASSOC);

    $atakConfigRepo = new \App\Repositories\TenantAtakConfigRepository($pdo);
    $experienceService = new \App\Services\Tactical\AtakExperienceService();
    $realismRepo = new \App\Repositories\AtakRealismConfigRepository($pdo);

    foreach ($tenants as $tenant) {
        $tenantId = (int) $tenant['id'];

        // Vérifier si une config existe déjà
        $existing = $realismRepo->getActiveConfig($tenantId);
        if ($existing !== null) {
            continue; // Déjà migré
        }

        // Lire les données existantes
        $experienceData = $experienceService->get($tenantId);
        $experience = $experienceData['settings'] ?? [];
        $roleplay = $atakConfigRepo->getRoleplayConfig($tenantId);
        $atakConfig = $atakConfigRepo->getByTenantId($tenantId) ?? [];

        // Construire la configuration JSON centralisée
        $configJson = [
            'version' => '1.0.0',
            'radio_relays' => [
                'link_via_relays' => (bool) ($roleplay['link_via_relays'] ?? false),
                'relay_range_m' => 2000, // default unifié (résout incohérence #11)
                'relay_range_min_m' => 50,
                'relay_range_max_m' => 8000,
                'relay_slots' => 8,
                'relay_power_w' => 25,
                'relay_throughput_mbps' => 12.0,
                'relay_reliability_pct' => 92,
                'radio_proximity_enabled' => true,
                'radio_proximity_radius_m' => 75,
                'radio_proximity_interval_s' => 2,
                'phone_proximity_radius_m' => 200,
            ],
            'zones_roleplay' => [
                'zones_enabled' => (bool) ($roleplay['zones_enabled'] ?? false),
                'default_zone_radius_m' => 200, // résout incohérence #11
                'zone_intensities' => [
                    'no_coverage' => 100,
                    'interference' => 50,
                    'degraded' => 30,
                    'jammer' => 80,
                ],
                'zone_effects' => [
                    'no_coverage' => 'Aucune couverture réseau',
                    'interference' => 'Interférences radio',
                    'degraded' => 'Signal dégradé',
                    'jammer' => 'Brouillage actif',
                ],
            ],
            'network_simulation' => [
                'portal_enabled' => (bool) ($roleplay['network_enabled'] ?? false),
                'portal_mode' => (string) ($roleplay['network_mode'] ?? 'normal'),
                'portal_latency_min_ms' => (int) ($roleplay['latency_min_ms'] ?? 0),
                'portal_latency_max_ms' => (int) ($roleplay['latency_max_ms'] ?? 0),
                'portal_packet_loss_pct' => (float) ($roleplay['packet_loss_percent'] ?? 0.0),
                'portal_disconnect_enabled' => (bool) ($roleplay['disconnect_enabled'] ?? false),
                'portal_disconnect_min_s' => (int) ($roleplay['disconnect_min_sec'] ?? 5),
                'portal_disconnect_max_s' => (int) ($roleplay['disconnect_max_sec'] ?? 30),
                'portal_disconnect_interval_s' => (int) ($roleplay['disconnect_interval_sec'] ?? 600),
                'client_sim_enabled' => false, // CBA setting, pas dans DB actuellement
                'client_disconnect_first_min_s' => 180,
                'client_disconnect_first_max_s' => 420,
                'client_disconnect_duration_min_s' => 4,
                'client_disconnect_duration_max_s' => 22,
                'client_disconnect_next_min_s' => 240,
                'client_disconnect_next_max_s' => 600,
                'client_tx_drop_chance_pct' => 8,
                'client_loss_floor_base_pct' => 12,
                'client_loss_floor_random_pct' => 10,
            ],
            'certificates' => [
                'automatic_pairing' => (bool) ($atakConfig['automatic_pairing'] ?? true),
                'certificate_duration_days' => (int) ($atakConfig['certificate_duration_days'] ?? 365),
                'certificate_duration_min_days' => 1,
                'certificate_duration_max_days' => 1825,
                'certificate_type_default' => 'device',
                'authority_label_default' => 'Autorité ATAK locale',
                'crypto_domain_default_ref' => 'FRIENDLY-NET',
                'crypto_domain_default_label' => 'Réseau ami',
                'crypto_domain_default_faction' => 'friendly',
                'intel_scramble_enabled' => (bool) ($roleplay['intel_scramble_enabled'] ?? false),
                'sync_realism_interval_s' => 180,
            ],
            'terminal_damage' => [
                'atak_realism_level' => $experience['atak_realism'] ?? 0, // 0=off, 1=player, 2=server
                'impact_screen_destroy_threshold' => 0.25,
                'impact_screen_destroy_prob_multiplier' => 50,
                'impact_shutdown_prob_multiplier' => 40,
                'impact_shutdown_duration_s' => 20,
                'arm_damage_threshold' => 0.65,
                'arm_damage_prob_pct' => 25,
                'arm_damage_duration_s' => 45,
                'torso_level1_threshold' => 0.5,
                'torso_level1_prob_pct' => 30,
                'torso_level1_duration_s' => 30,
                'torso_level2_threshold' => 0.7,
                'torso_level2_prob_pct' => 40,
                'torso_level3_threshold' => 0.8,
                'torso_level3_prob_pct' => 50,
                'kat_pneumothorax_threshold' => 0.75,
                'kat_pneumothorax_multiplier' => 0.85,
                'kat_spo2_threshold' => 85,
            ],
            'waypoints_routes' => [
                'waypoint_radius_default_m' => 25, // résout incohérence #7
                'route_type_default' => 'PATROL',
                'route_status_default' => 'PLANNED',
                'route_visibility_default' => 'PUBLIC',
                'rally_radius_m' => 50,
            ],
            'symbology_map' => [
                'vehicle_detail_mode' => (string) ($experience['vehicle_detail'] ?? 'player'),
                'show_opfor_mode' => (string) ($experience['show_opfor'] ?? 'player'),
                'show_independent_mode' => (string) ($experience['show_independent'] ?? 'player'),
                'show_civilian_mode' => (string) ($experience['show_civilian'] ?? 'player'),
                'sync_map_markers_mode' => (string) ($experience['sync_map_markers'] ?? 'player'),
                'affiliation_colors' => [
                    'FRIENDLY' => '#0080ff',
                    'HOSTILE' => '#ff4040',
                    'UNKNOWN' => '#ffff00',
                    'NEUTRAL' => '#00ff00',
                ],
                'marker_detection_radius_m' => 20,
                'po_marker_radius_m' => 20,
            ],
            'coverage_viewshed' => [
                'viewshed_radius_default_m' => 500, // résout incohérence #8
                'viewshed_radius_min_m' => 25,
                'reach_overlay_min_radius_m' => 25,
            ],
            'experience_ambiance' => [
                'realism_mode' => (bool) ($experience['realism'] ?? false),
                'troll_mode' => (bool) ($experience['troll'] ?? false),
                'screen_notifications_mode' => (string) ($experience['screen_notifications'] ?? 'player'),
                'require_equipment_mode' => (string) ($experience['require_equipment'] ?? 'player'),
                'sse_require_item' => true,
                'ace_menus_mode' => (string) ($experience['ace_menus'] ?? 'player'),
                'order_compose_mode' => (string) ($experience['order_compose'] ?? 'player'),
                'playtime_mode' => (string) ($experience['playtime'] ?? 'player'),
                'athena_feed_mode' => (string) ($experience['athena_feed'] ?? 'player'),
            ],
            'other_settings' => [
                'minimum_client_version' => (string) ($atakConfig['minimum_client_version'] ?? '5.1.8'),
                'off_op_position_sharing' => (bool) ($atakConfig['off_op_position_sharing'] ?? false),
                'session_ttl_sec' => 86400,
                'drain_period_min_ms' => 250,
                'drain_period_max_ms' => 2000,
            ],
        ];

        // Insérer la config
        try {
            $realismRepo->upsertConfig(
                $tenantId,
                $configJson,
                null, // pas de created_by pour seed initial
                'Configuration initiale (migration)',
                '1.0.0'
            );
        } catch (\Throwable $e) {
            // Log l'erreur mais continue pour les autres tenants
            error_log("Failed to seed realism config for tenant {$tenantId}: " . $e->getMessage());
        }
    }
}

// Charger les dépendances avant d'exécuter le seed
// Note: ce fichier doit être exécuté après bootstrap/migrations.php
if (isset($pdo)) {
    // Vérifier que les classes sont disponibles
    if (!class_exists('App\Repositories\AtakRealismConfigRepository')) {
        require_once __DIR__ . '/../app/Repositories/AtakRealismConfigRepository.php';
    }
    if (!class_exists('App\Repositories\TenantAtakConfigRepository')) {
        require_once __DIR__ . '/../app/Repositories/TenantAtakConfigRepository.php';
    }
    if (!class_exists('App\Services\Tactical\AtakExperienceService')) {
        require_once __DIR__ . '/../app/Services/Tactical/AtakExperienceService.php';
    }

    run_atak_realism_config_seed($pdo);
}
