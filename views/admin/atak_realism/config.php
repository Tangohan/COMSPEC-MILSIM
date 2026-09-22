<?php
declare(strict_types=1);

$h = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$config = $config ?? [];
$configMeta = $configMeta ?? [];
$history = is_array($history ?? null) ? $history : [];
$csrfToken = (string) ($csrfToken ?? '');
$success = $success ?? null;
$error = $error ?? null;

// Extraire les domaines de config
$radioRelays = $config['radio_relays'] ?? [];
$zonesRoleplay = $config['zones_roleplay'] ?? [];
$networkSim = $config['network_simulation'] ?? [];
$certificates = $config['certificates'] ?? [];
$terminalDamage = $config['terminal_damage'] ?? [];
$waypoints = $config['waypoints_routes'] ?? [];
$symbology = $config['symbology_map'] ?? [];
$controlMeasures = $config['control_measures'] ?? [];
$coverage = $config['coverage_viewshed'] ?? [];
$experience = $config['experience_ambiance'] ?? [];
$other = $config['other_settings'] ?? [];
?>

<div class="min-h-0 flex-1 bg-slate-50">
    <div class="max-w-[1240px] mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:py-10 space-y-8">

        <!-- Header -->
        <header class="relative overflow-hidden rounded-2xl border border-blue-200/80 bg-gradient-to-br from-blue-50/90 via-white to-slate-50 shadow-sm">
            <div class="relative px-5 sm:px-8 py-7">
                <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-blue-900/80 mb-2">COMSPEC ATAK — Configuration centralisée</p>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Réalisme ATAK</h1>
                <p class="mt-2 text-sm text-slate-600 max-w-3xl leading-relaxed">
                    Configuration unifiée de tous les paramètres de réalisme : relais radio, certificats, dommages terminal, simulation réseau, zones tactiques, itinéraires, symbologie, couverture terrain.
                    Source de vérité pour le mod Arma 3 et le web ATAK/Tacmap.
                </p>
                <div class="mt-5 flex flex-wrap gap-2">
                    <a href="<?= $h(url('back-office/atak/controle-serveur')) ?>" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">
                        ← Contrôle de mission
                    </a>
                    <button type="button" id="btn-history" class="inline-flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-900 hover:bg-blue-100">
                        📜 Historique
                    </button>
                </div>
            </div>
        </header>

        <?php if ($success): ?>
            <div class="rounded-xl border border-green-200 bg-green-50 px-5 py-4">
                <p class="text-sm text-green-900"><?= $h($success) ?></p>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="rounded-xl border border-red-200 bg-red-50 px-5 py-4">
                <p class="text-sm text-red-900"><?= $h($error) ?></p>
            </div>
        <?php endif; ?>

        <!-- Métadonnées config -->
        <div class="rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-bold text-slate-900"><?= $h($configMeta['name'] ?? 'Configuration actuelle') ?></p>
                    <p class="text-xs text-slate-500 mt-1">
                        Version <?= $h($configMeta['version'] ?? '1.0.0') ?> 
                        · Modifiée le <?= $h($configMeta['updated_at'] ?? 'N/A') ?>
                    </p>
                </div>
                <button type="button" id="btn-save-config" class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                    💾 Enregistrer
                </button>
            </div>
        </div>

        <!-- Navigation onglets -->
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="border-b border-slate-200 bg-slate-50/50 px-5 py-3">
                <nav class="flex flex-wrap gap-2" id="tabs-nav">
                    <button class="tab-btn active" data-tab="radio">📡 Relais radio</button>
                    <button class="tab-btn" data-tab="zones">🗺️ Zones roleplay</button>
                    <button class="tab-btn" data-tab="network">🌐 Simulation réseau</button>
                    <button class="tab-btn" data-tab="certificates">🔐 Certificats</button>
                    <button class="tab-btn" data-tab="damage">💥 Dommages terminal</button>
                    <button class="tab-btn" data-tab="waypoints">📍 Itinéraires</button>
                    <button class="tab-btn" data-tab="symbology">🎯 Symbologie</button>
                    <button class="tab-btn" data-tab="control">🎖️ Control Measures</button>
                    <button class="tab-btn" data-tab="coverage">📡 Couverture</button>
                    <button class="tab-btn" data-tab="experience">🎮 Expérience</button>
                </nav>
            </div>

            <form id="realism-config-form" class="px-5 py-6">
                <input type="hidden" name="_csrf_token" value="<?= $h($csrfToken) ?>">

                <!-- Onglet 1: Relais radio -->
                <div class="tab-content active" data-tab="radio">
                    <h2 class="text-lg font-bold text-slate-900 mb-4">Relais radio et proximité</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="radio_relays.link_via_relays" <?= !empty($radioRelays['link_via_relays']) ? 'checked' : '' ?>>
                            <span class="text-sm font-medium">Exiger un relais pour la liaison de données</span>
                        </label>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Portée relais par défaut (m)</label>
                            <input type="number" name="radio_relays.relay_range_m" value="<?= $h($radioRelays['relay_range_m'] ?? 2000) ?>" min="50" max="8000" class="w-full rounded border border-slate-300 px-3 py-2">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Slots par relais</label>
                            <input type="number" name="radio_relays.relay_slots" value="<?= $h($radioRelays['relay_slots'] ?? 8) ?>" min="1" max="64" class="w-full rounded border border-slate-300 px-3 py-2">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Puissance (W)</label>
                            <input type="number" name="radio_relays.relay_power_w" value="<?= $h($radioRelays['relay_power_w'] ?? 25) ?>" min="0" max="999" class="w-full rounded border border-slate-300 px-3 py-2">
                        </div>
                    </div>
                    
                    <h3 class="text-md font-bold text-slate-900 mb-3 mt-6">☔ Effet météo sur les communications</h3>
                    <div class="space-y-4">
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="radio_relays.weather_effects_enabled" <?= !empty($radioRelays['weather_effects_enabled']) ? 'checked' : '' ?>>
                            <span class="text-sm font-medium">Activer les effets météo sur les comms</span>
                        </label>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Pluie - Portée (%)</label>
                                <input type="number" name="radio_relays.rain_range_multiplier" value="<?= $h($radioRelays['rain_range_multiplier'] ?? 0.85) ?>" min="0" max="1" step="0.01" class="w-full rounded border border-slate-300 px-3 py-2">
                                <p class="text-xs text-slate-500 mt-1">0.85 = réduction à 85%</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Brouillard - Portée (%)</label>
                                <input type="number" name="radio_relays.fog_range_multiplier" value="<?= $h($radioRelays['fog_range_multiplier'] ?? 0.70) ?>" min="0" max="1" step="0.01" class="w-full rounded border border-slate-300 px-3 py-2">
                                <p class="text-xs text-slate-500 mt-1">0.70 = réduction à 70%</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Orage - Portée (%)</label>
                                <input type="number" name="radio_relays.storm_range_multiplier" value="<?= $h($radioRelays['storm_range_multiplier'] ?? 0.60) ?>" min="0" max="1" step="0.01" class="w-full rounded border border-slate-300 px-3 py-2">
                                <p class="text-xs text-slate-500 mt-1">0.60 = réduction à 60%</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Seuil vent (km/h)</label>
                                <input type="number" name="radio_relays.wind_threshold_kmh" value="<?= $h($radioRelays['wind_threshold_kmh'] ?? 50) ?>" min="0" max="200" class="w-full rounded border border-slate-300 px-3 py-2">
                                <p class="text-xs text-slate-500 mt-1">Vent au-delà de ce seuil affecte les comms</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Pénalité vent (%/10km/h)</label>
                                <input type="number" name="radio_relays.wind_range_penalty_per_10kmh" value="<?= $h($radioRelays['wind_range_penalty_per_10kmh'] ?? 0.05) ?>" min="0" max="0.5" step="0.01" class="w-full rounded border border-slate-300 px-3 py-2">
                                <p class="text-xs text-slate-500 mt-1">0.05 = -5% portée par tranche de 10 km/h</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Onglet 2: Zones roleplay -->
                <div class="tab-content" data-tab="zones" style="display:none;">
                    <h2 class="text-lg font-bold text-slate-900 mb-4">Zones tactiques à effets</h2>
                    <div class="space-y-4">
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="zones_roleplay.zones_enabled" <?= !empty($zonesRoleplay['zones_enabled']) ? 'checked' : '' ?>>
                            <span class="text-sm font-medium">Activer les zones roleplay</span>
                        </label>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Rayon par défaut (m)</label>
                            <input type="number" name="zones_roleplay.default_zone_radius_m" value="<?= $h($zonesRoleplay['default_zone_radius_m'] ?? 200) ?>" min="25" class="w-full rounded border border-slate-300 px-3 py-2">
                        </div>
                    </div>
                </div>

                <!-- Onglet 3: Simulation réseau -->
                <div class="tab-content" data-tab="network" style="display:none;">
                    <h2 class="text-lg font-bold text-slate-900 mb-4">Simulation réseau (portail + client)</h2>
                    <div class="space-y-4">
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="network_simulation.portal_enabled" <?= !empty($networkSim['portal_enabled']) ? 'checked' : '' ?>>
                            <span class="text-sm font-medium">Activer simulation portail</span>
                        </label>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Latence min (ms)</label>
                                <input type="number" name="network_simulation.portal_latency_min_ms" value="<?= $h($networkSim['portal_latency_min_ms'] ?? 0) ?>" min="0" class="w-full rounded border border-slate-300 px-3 py-2">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Latence max (ms)</label>
                                <input type="number" name="network_simulation.portal_latency_max_ms" value="<?= $h($networkSim['portal_latency_max_ms'] ?? 0) ?>" min="0" class="w-full rounded border border-slate-300 px-3 py-2">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Onglet 4: Certificats -->
                <div class="tab-content" data-tab="certificates" style="display:none;">
                    <h2 class="text-lg font-bold text-slate-900 mb-4">Certificats et appairage</h2>
                    <div class="space-y-4">
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="certificates.automatic_pairing" <?= !empty($certificates['automatic_pairing']) ? 'checked' : '' ?>>
                            <span class="text-sm font-medium">Appairage automatique</span>
                        </label>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Durée certificat (jours)</label>
                            <input type="number" name="certificates.certificate_duration_days" value="<?= $h($certificates['certificate_duration_days'] ?? 365) ?>" min="1" max="1825" class="w-full rounded border border-slate-300 px-3 py-2">
                        </div>
                    </div>
                </div>

                <!-- Onglet 5: Dommages terminal -->
                <div class="tab-content" data-tab="damage" style="display:none;">
                    <h2 class="text-lg font-bold text-slate-900 mb-4">Dommages au terminal ATAK</h2>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Niveau réalisme</label>
                            <select name="terminal_damage.atak_realism_level" class="w-full rounded border border-slate-300 px-3 py-2">
                                <option value="0" <?= ($terminalDamage['atak_realism_level'] ?? 0) == 0 ? 'selected' : '' ?>>Désactivé</option>
                                <option value="1" <?= ($terminalDamage['atak_realism_level'] ?? 0) == 1 ? 'selected' : '' ?>>Au choix du joueur</option>
                                <option value="2" <?= ($terminalDamage['atak_realism_level'] ?? 0) == 2 ? 'selected' : '' ?>>Forcé serveur</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Onglet 6: Itinéraires -->
                <div class="tab-content" data-tab="waypoints" style="display:none;">
                    <h2 class="text-lg font-bold text-slate-900 mb-4">Itinéraires et waypoints</h2>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Rayon waypoint par défaut (m)</label>
                        <input type="number" name="waypoints_routes.waypoint_radius_default_m" value="<?= $h($waypoints['waypoint_radius_default_m'] ?? 25) ?>" min="5" class="w-full rounded border border-slate-300 px-3 py-2">
                    </div>
                </div>

                <!-- Onglet 7: Symbologie -->
                <div class="tab-content" data-tab="symbology" style="display:none;">
                    <h2 class="text-lg font-bold text-slate-900 mb-4">Symbologie et carte</h2>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Détail véhicule</label>
                            <select name="symbology_map.vehicle_detail_mode" class="w-full rounded border border-slate-300 px-3 py-2">
                                <option value="player" <?= ($symbology['vehicle_detail_mode'] ?? 'player') === 'player' ? 'selected' : '' ?>>Au choix du joueur</option>
                                <option value="on" <?= ($symbology['vehicle_detail_mode'] ?? 'player') === 'on' ? 'selected' : '' ?>>Toujours actif</option>
                                <option value="off" <?= ($symbology['vehicle_detail_mode'] ?? 'player') === 'off' ? 'selected' : '' ?>>Toujours désactivé</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Onglet 8: Control Measures -->
                <div class="tab-content" data-tab="control" style="display:none;">
                    <h2 class="text-lg font-bold text-slate-900 mb-4">🎖️ Control Measures MIL-STD-2525D</h2>
                    <p class="text-sm text-slate-600 mb-4">Mesures de contrôle doctrine US Army : axes d'avance, lignes de départ, limites de progression, phase lines, objectifs, checkpoints.</p>
                    
                    <div class="space-y-6">
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="control_measures.enabled" <?= !empty($controlMeasures['enabled']) ? 'checked' : '' ?>>
                            <span class="text-sm font-medium">Activer les Control Measures</span>
                        </label>

                        <div class="border-t border-slate-200 pt-4">
                            <h3 class="text-md font-bold text-slate-900 mb-3">Axis of Advance (Axes d'avance nommés)</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" name="control_measures.axis_naming_enabled" <?= !empty($controlMeasures['axis_naming_enabled']) ? 'checked' : '' ?>>
                                    <span class="text-sm font-medium">Nommage des axes (NEPTUNE, MARS, etc.)</span>
                                </label>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">Largeur par défaut (m)</label>
                                    <input type="number" name="control_measures.axis_default_width_m" value="<?= $h($controlMeasures['axis_default_width_m'] ?? 500) ?>" min="50" max="5000" class="w-full rounded border border-slate-300 px-3 py-2">
                                </div>
                            </div>
                        </div>

                        <div class="border-t border-slate-200 pt-4">
                            <h3 class="text-md font-bold text-slate-900 mb-3">Line of Departure (LD)</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" name="control_measures.ld_enabled" <?= !empty($controlMeasures['ld_enabled']) ? 'checked' : '' ?>>
                                    <span class="text-sm font-medium">Activer les lignes de départ</span>
                                </label>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">Couleur par défaut</label>
                                    <input type="color" name="control_measures.ld_default_color" value="<?= $h($controlMeasures['ld_default_color'] ?? '#00ff00') ?>" class="w-full rounded border border-slate-300 px-3 py-2">
                                </div>
                            </div>
                        </div>

                        <div class="border-t border-slate-200 pt-4">
                            <h3 class="text-md font-bold text-slate-900 mb-3">Limit of Advance (LOA)</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" name="control_measures.loa_enabled" <?= !empty($controlMeasures['loa_enabled']) ? 'checked' : '' ?>>
                                    <span class="text-sm font-medium">Activer les limites de progression</span>
                                </label>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">Couleur par défaut</label>
                                    <input type="color" name="control_measures.loa_default_color" value="<?= $h($controlMeasures['loa_default_color'] ?? '#ff0000') ?>" class="w-full rounded border border-slate-300 px-3 py-2">
                                </div>
                            </div>
                        </div>

                        <div class="border-t border-slate-200 pt-4">
                            <h3 class="text-md font-bold text-slate-900 mb-3">Phase Lines (PL)</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" name="control_measures.phase_line_enabled" <?= !empty($controlMeasures['phase_line_enabled']) ? 'checked' : '' ?>>
                                    <span class="text-sm font-medium">Activer les phase lines</span>
                                </label>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">Couleur par défaut</label>
                                    <input type="color" name="control_measures.phase_line_default_color" value="<?= $h($controlMeasures['phase_line_default_color'] ?? '#ffff00') ?>" class="w-full rounded border border-slate-300 px-3 py-2">
                                </div>
                            </div>
                        </div>

                        <div class="border-t border-slate-200 pt-4">
                            <h3 class="text-md font-bold text-slate-900 mb-3">Objectifs nommés (OBJ)</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" name="control_measures.objective_enabled" <?= !empty($controlMeasures['objective_enabled']) ? 'checked' : '' ?>>
                                    <span class="text-sm font-medium">Activer les objectifs nommés</span>
                                </label>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">Rayon par défaut (m)</label>
                                    <input type="number" name="control_measures.objective_default_radius_m" value="<?= $h($controlMeasures['objective_default_radius_m'] ?? 200) ?>" min="25" max="2000" class="w-full rounded border border-slate-300 px-3 py-2">
                                </div>
                            </div>
                        </div>

                        <div class="border-t border-slate-200 pt-4">
                            <h3 class="text-md font-bold text-slate-900 mb-3">Checkpoints numérotés (CP)</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" name="control_measures.checkpoint_enabled" <?= !empty($controlMeasures['checkpoint_enabled']) ? 'checked' : '' ?>>
                                    <span class="text-sm font-medium">Activer les checkpoints</span>
                                </label>
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" name="control_measures.checkpoint_auto_number" <?= !empty($controlMeasures['checkpoint_auto_number']) ? 'checked' : '' ?>>
                                    <span class="text-sm font-medium">Numérotation automatique</span>
                                </label>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">Rayon par défaut (m)</label>
                                    <input type="number" name="control_measures.checkpoint_default_radius_m" value="<?= $h($controlMeasures['checkpoint_default_radius_m'] ?? 50) ?>" min="10" max="500" class="w-full rounded border border-slate-300 px-3 py-2">
                                </div>
                            </div>
                        </div>

                        <div class="border-t border-slate-200 pt-4">
                            <h3 class="text-md font-bold text-slate-900 mb-3">Permissions</h3>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Visibilité par défaut</label>
                                <select name="control_measures.control_measure_visibility" class="w-full rounded border border-slate-300 px-3 py-2">
                                    <option value="public" <?= ($controlMeasures['control_measure_visibility'] ?? 'team') === 'public' ? 'selected' : '' ?>>Public (tout le monde)</option>
                                    <option value="team" <?= ($controlMeasures['control_measure_visibility'] ?? 'team') === 'team' ? 'selected' : '' ?>>Équipe uniquement</option>
                                    <option value="private" <?= ($controlMeasures['control_measure_visibility'] ?? 'team') === 'private' ? 'selected' : '' ?>>Privé (créateur uniquement)</option>
                                </select>
                                <p class="text-xs text-slate-500 mt-1">Qui peut voir les control measures créés</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Onglet 9: Couverture -->
                <div class="tab-content" data-tab="coverage" style="display:none;">
                    <h2 class="text-lg font-bold text-slate-900 mb-4">Couverture et viewshed</h2>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Rayon viewshed par défaut (m)</label>
                        <input type="number" name="coverage_viewshed.viewshed_radius_default_m" value="<?= $h($coverage['viewshed_radius_default_m'] ?? 500) ?>" min="25" max="2000" class="w-full rounded border border-slate-300 px-3 py-2">
                    </div>
                </div>

                <!-- Onglet 10: Expérience -->
                <div class="tab-content" data-tab="experience" style="display:none;">
                    <h2 class="text-lg font-bold text-slate-900 mb-4">Expérience et ambiance</h2>
                    <div class="space-y-4">
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="experience_ambiance.realism_mode" <?= !empty($experience['realism_mode']) ? 'checked' : '' ?>>
                            <span class="text-sm font-medium">Mode réalisme général</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="experience_ambiance.troll_mode" <?= !empty($experience['troll_mode']) ? 'checked' : '' ?>>
                            <span class="text-sm font-medium">Mode troll</span>
                        </label>
                    </div>
                </div>

            </form>
        </div>

        <!-- Modal historique (simple pour l'instant) -->
        <div id="history-modal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
            <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full mx-4 max-h-[80vh] overflow-auto">
                <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-slate-900">Historique des configurations</h3>
                    <button type="button" id="close-history" class="text-slate-500 hover:text-slate-900">✕</button>
                </div>
                <div class="px-6 py-4">
                    <?php if ($history === []): ?>
                        <p class="text-sm text-slate-500">Aucun historique disponible.</p>
                    <?php else: ?>
                        <ul class="space-y-2">
                            <?php foreach ($history as $item): ?>
                                <li class="p-3 rounded border border-slate-200 hover:bg-slate-50">
                                    <p class="text-sm font-medium text-slate-900"><?= $h($item['config_name'] ?? 'Sans nom') ?></p>
                                    <p class="text-xs text-slate-500">
                                        Version <?= $h($item['config_version'] ?? 'N/A') ?> 
                                        · Modifiée le <?= $h($item['updated_at'] ?? 'N/A') ?>
                                        · <?= !empty($item['is_active']) ? '✓ Active' : 'Archivée' ?>
                                    </p>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</div>

<style>
.tab-btn {
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
    font-weight: 600;
    border-radius: 0.5rem;
    border: 1px solid #e2e8f0;
    background: white;
    color: #64748b;
    cursor: pointer;
    transition: all 0.2s;
}
.tab-btn:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
}
.tab-btn.active {
    background: #0f172a;
    color: white;
    border-color: #0f172a;
}
</style>

<script>
// Gestion des onglets
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const tab = btn.dataset.tab;
        
        // Activer l'onglet
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        
        // Afficher le contenu
        document.querySelectorAll('.tab-content').forEach(content => {
            content.style.display = content.dataset.tab === tab ? 'block' : 'none';
        });
    });
});

// Enregistrement de la config
document.getElementById('btn-save-config')?.addEventListener('click', async () => {
    const form = document.getElementById('realism-config-form');
    const formData = new FormData(form);
    
    // Construire la config JSON depuis le formulaire
    const config = {
        radio_relays: {},
        zones_roleplay: {},
        network_simulation: {},
        certificates: {},
        terminal_damage: {},
        waypoints_routes: {},
        symbology_map: {},
        control_measures: {},
        coverage_viewshed: {},
        experience_ambiance: {},
        other_settings: {}
    };
    
    for (const [key, value] of formData.entries()) {
        if (key === '_csrf_token') continue;
        
        const parts = key.split('.');
        if (parts.length === 2) {
            const [domain, field] = parts;
            const input = form.querySelector(`[name="${key}"]`);
            
            if (input.type === 'checkbox') {
                config[domain][field] = input.checked;
            } else if (input.type === 'number') {
                config[domain][field] = parseFloat(value) || 0;
            } else {
                config[domain][field] = value;
            }
        }
    }
    
    try {
        const response = await fetch('<?= url('admin/atak/realism/save') ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': formData.get('_csrf_token')
            },
            body: JSON.stringify({
                config,
                config_name: 'Configuration modifiée le ' + new Date().toLocaleString('fr-FR'),
                _csrf_token: formData.get('_csrf_token')
            })
        });
        
        const data = await response.json();
        
        if (data.ok) {
            alert('✓ Configuration enregistrée avec succès !');
            location.reload();
        } else {
            alert('Erreur : ' + (data.error || 'Erreur inconnue'));
        }
    } catch (e) {
        alert('Erreur de communication avec le serveur');
        console.error(e);
    }
});

// Modal historique
document.getElementById('btn-history')?.addEventListener('click', () => {
    document.getElementById('history-modal').classList.remove('hidden');
    document.getElementById('history-modal').classList.add('flex');
});

document.getElementById('close-history')?.addEventListener('click', () => {
    document.getElementById('history-modal').classList.add('hidden');
    document.getElementById('history-modal').classList.remove('flex');
});
</script>
