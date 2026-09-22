# Audit complet — Paramètres de réalisme ATHENA C2 (COMSPEC-MILSIM)

**Date** : 22 septembre 2026  
**Contexte** : Projet MILSIM Arma 3 · Architecture mod (SQF) ↔ extension DLL (C#) ↔ API ATHENA (PHP/REST) ↔ web tactique (ATAK/Tacmap)  
**Objectif** : Inventaire exhaustif des paramètres de réalisme + proposition de configuration centralisée

---

## PARTIE 1 : INVENTAIRE DES PARAMÈTRES

### 1.1. Tableau complet des paramètres (~ 100 entrées)

| # | Nom paramètre | Fichier source | Ligne | Description concrète | Valeur par défaut | Utilisé ailleurs ? | Domaine |
|---|---------------|----------------|-------|----------------------|-------------------|-------------------|---------|
| **DOMAINE : EXPÉRIENCE IMMERSIVE / AMBIANCE** |
| 1 | `realism` (expérience communauté) | `app/Services/Tactical/AtakExperienceService.php` | 26-32 | Mode réalisme immersion : moins d'aides, moins d'alertes confort | `false` | ✓ Oui → sync mod via `fn_applyTenantExperience` | Autre (ambiance UX) |
| 2 | `troll` | `app/Services/Tactical/AtakExperienceService.php` | 35-41 | Mode fun / alertes exagérées ; forcé OFF si `realism=true` | `false` | ✓ Oui → mutuellement exclusif avec `realism` | Autre |
| 3 | `screen_notifications` | `app/Services/Tactical/AtakExperienceService.php` | 44-51 | Bandeaux info écran in-game | `"player"` | ✓ Oui → CBA `comspec_overwatch_screen_notifications` | Autre |
| **DOMAINE : DOMMAGES TERMINAL / SIMULATION PHYSIQUE** |
| 4 | `atak_realism` (expérience) | `app/Services/Tactical/AtakExperienceService.php` | 114-127 | Dommages téléphone ATAK : off / 1 / 2 / 3 / player | `"player"` | ✓ Oui → écrase CBA `comspec_overwatch_atak_realism` | **Dommages** |
| 5 | `comspec_overwatch_atak_realism` (CBA) | `mod/.../connect/XEH_preInit.sqf` | 950-958 | Niveau dommages physiques ATAK : 0 (off) à 3 (max) | `0` (Désactivé) | ✓ Oui → `fn_checkAtakDamage` ; écrasé par tenant si défini | **Dommages** |
| 6 | Impact choc écran (`_impact * 50`) | `fn_checkAtakDamage.sqf` | 29 | Probabilité écran détruit si `realism≥2` lors choc > 0.25 | `impact × 50%` | ✗ Local hardcodé | **Dommages** |
| 7 | Impact choc extinction (`_impact * 40`) | `fn_checkAtakDamage.sqf` | 42 | Proba extinction temporaire (20 s) si `realism≥1` | `impact × 40%` | ✗ Local hardcodé | **Dommages** |
| 8 | Seuil bras (`_armDamage > 0.65`) | `fn_checkAtakDamage.sqf` | 100 | Impossible tenir terminal si bras blessé | `0.65` dommage ; 25% proba ; 45 s | ✗ Local hardcodé | **Dommages** |
| 9 | Seuil torse niveau 1 (`>0.5`, 30%) | `fn_checkAtakDamage.sqf` | 118-129 | Extinction 30 s | `0.5` dommage / 30% proba | ✗ Local hardcodé | **Dommages** |
| 10 | Seuil torse niveau 2 (`>0.7`, 40%) | `fn_checkAtakDamage.sqf` | 134-146 | Écran détruit | `0.7` / 40% | ✗ Local hardcodé | **Dommages** |
| 11 | Seuil torse niveau 3 (`>0.8`, 50%) | `fn_checkAtakDamage.sqf` | 149-164 | Device détruit + déconnexion | `0.8` / 50% | ✗ Local hardcodé | **Dommages** |
| 12 | KAT pneumothorax / SpO2 | `fn_checkAtakDamage.sqf` | 91-96 | Aggravation dommages si complications thoraciques ; SpO2 < 85 rend HR unreliable | Seuils 0.75 / 0.85 / SpO2 85 | ✗ Local hardcodé | **Dommages** |
| **DOMAINE : RADIO / RELAIS / PORTÉE** |
| 13 | `link_via_relays` (DB + SQF) | `TenantAtakConfigRepository.php` + `fn_pollRoleplayConfig.sqf` | L61,90-95 / L48-49 | Exige proximité d'un relais actif pour transmettre | `false` (DB: `0`) | ✓ Oui → API roleplay + admin roleplay + server_control + `fn_canTransmit` | **Radio/relais** |
| 14 | `COMSPEC_LinkViaRelays` (var SQF) | `fn_pollRoleplayConfig.sqf` | 48-49 | Variable mission SQF de `link_via_relays` | `false` (`"0"`) | ✓ Oui → `fn_canTransmit` L77 | **Radio/relais** |
| 15 | `atak_link_via_relays` (colonne DB) | `bootstrap/atak_overwatch_ops_migration.php` | 59-62 | Colonne schéma SQL `tenants_atak_config` | `DEFAULT 0` (TINYINT) | ✓ Oui | **Radio/relais** |
| 16 | `COMSPEC_AtakRelayRange` | `fn_placeAtakRelay.sqf` | 9,15,23 | Portée mât relais (m) | `2000` ; clamp 50-8000 | ✓ Oui → `isNearLiveRelay`, `getNearestAtakRelay`, API `range_m` | **Radio/relais** |
| 17 | `RangeM` (module Eden) | `modules/module_atak_relay.hpp` | 33-42, 139-144 | Portée module Eden / Zeus | `"2000"` (string) | ✓ Oui → `fn_moduleAtakRelay` | **Radio/relais** |
| 18 | `range_m` (API/DB) | `app/Repositories/AtakRelayRepository.php` + migration | L31 + bootstrap L46 | Colonne table `atak_relays.range_m` + upsert API | `2000` ; min 50 (pas de max PHP) | ✓ Oui → Extension `UpdateRelay`, overlay JS | **Radio/relais** |
| 19 | `range_m` UpdateRelay fallback | `mod/.../COMSPECExtension/Extension.cs` | 7142 | Fallback si arg range absent lors POST relais | `"2000"` | ✓ Oui → POST `/api/atak/relays` | **Radio/relais** |
| 20 | `slots` / `COMSPEC_AtakRelaySlots` | `fn_placeAtakRelay.sqf` + repo | L39-41 + repo L30,41 | Nombre places téléphones simultanés sur relais | `8` ; clamp 1-64 | ✓ Oui → UI Relais AT, fiche web | **Radio/relais** |
| 21 | `power_w` / `COMSPEC_AtakRelayPowerW` | `fn_placeAtakRelay.sqf` + repo | L42-43 + repo L31,44 | Puissance (W) ; 0 si relais détruit | `25` | ✓ Oui → sync API, fiche web | **Radio/relais** |
| 22 | `throughput_mbps` / `COMSPEC_AtakRelayThroughput` | `fn_placeAtakRelay.sqf` + repo | L44-45 + repo L32,45 | Débit Mbit/s de base | `12.0` | ✓ Oui → dégradé par distance/dégâts dans `getNearestAtakRelay` | **Radio/relais** |
| 23 | `reliability_pct` / `COMSPEC_AtakRelayReliability` | `fn_placeAtakRelay.sqf` + repo | L46-47 + repo L33,46 | Fiabilité % | `92` | ✓ Oui → API + UI | **Radio/relais** |
| 24 | Colonnes fiche relais (display_name, identity, ip_addr, gateway, certificate) | `bootstrap/atak_relays_fiche_migration.php` | 23-34 | Métadonnées fiche relais web (réseau) | Vides sauf slots 8, power 25, throughput 12.0, reliability 92 | ✓ Oui → admin web relais | **Radio/relais** |
| 25 | Dégradation débit vs distance | `fn_getNearestAtakRelay.sqf` | 70-80 | Formule `thru * (0.45+0.55*ratio) * (1-dmg*0.7)` | Hardcodé (45% min + interp) | ✓ Oui → affiché Relais AT + sync web | **Radio/relais** |
| 26 | `comspec_overwatch_radio_proximity_enabled` | `mod/.../XEH_preInit.sqf` | 629-632 | Surveillance radio proximité | `true` | ✓ Oui → monitor radio net | **Radio/relais** |
| 27 | `comspec_overwatch_radio_proximity_radius` | `mod/.../XEH_preInit.sqf` | 635-638 | Rayon écoute radio proximité (m) | `75` ; slider 10-300 | ✓ Oui → liste proximité | **Radio/relais** |
| 28 | `comspec_overwatch_radio_proximity_interval` | `mod/.../XEH_preInit.sqf` | 641-644 | Intervalle scan radio (s) | `2` ; range 1-10 | ✓ Oui → tick local | **Radio/relais** |
| 29 | `radio_proximity` (expérience) | `AtakExperienceService.php` | 130-137 | Écoute radio proximité (réglage communauté) | `"player"` | ✓ Oui → CBA | **Radio/relais** |
| 30 | `COMSPEC_AtakPhoneProximityM` | `fn_athena_phoneProximityTick.sqf` | 8 | Alerte proximité téléphone ATAK allié | `200` m | ✗ Local settings | **Radio/relais** |
| **DOMAINE : ZONES ROLEPLAY / COUVERTURE / BROUILLAGE** |
| 31 | `zones_enabled` | `TenantAtakConfigRepository.php` | 56 | Active zones géo portail | `false` | ✓ Oui → sync zones SQF | **Radio/relais** |
| 32 | `comspec_overwatch_roleplay_enabled` | `mod/.../XEH_preInit.sqf` | 926-929 | Active dysfonctionnements roleplay | `false` | ✓ Oui → zones forcent `true` ; `fn_applyZoneEffects` | **Radio/relais** |
| 33 | `comspec_overwatch_roleplay_network_failures` | `mod/.../XEH_preInit.sqf` | 932-935 | Délais / pertes / coupures réseau | `false` | ✓ Oui → crash jammer, sim réseau | **Radio/relais** |
| 34 | `comspec_overwatch_roleplay_sensor_failures` | `mod/.../XEH_preInit.sqf` | 938-941 | Capteur HR défaillant (web) | `false` | ✓ Oui → effets capteurs | Autre |
| 35 | `comspec_overwatch_roleplay_visual_effects` | `mod/.../XEH_preInit.sqf` | 944-947 | Glitchs / parasites UI | `true` | ✓ Oui → overlay device | Autre |
| 36 | Zone `radius` défaut (create) | `fn_createRoleplayZone.sqf` | 17 | Rayon zone roleplay lors création | `100` m | ✓ Oui → modules / Zeus | **Radio/relais** |
| 37 | Intensité défaut par type zone | `fn_createRoleplayZone.sqf` | 33-38 | no_coverage 100, interference 50, degraded 30, jammer 80 | Selon type | ✓ Oui → apply zone effects | **Radio/relais** |
| 38 | Intensité modules Eden | `modules/module_roleplay_zone.hpp` | 46-263 | Attributs `Intensity` par type zone (4 types) | 50 / 100 / 50 / 30 / 80 selon type | ✓ Oui → `moduleApplyRoleplayZone` | **Radio/relais** |
| 39 | Rayon défaut module apply Zeus | `fn_moduleApplyRoleplayZone.sqf` | 20 | Fallback rayon Zeus | `200` ; clamp 5-5000 | ✓ Oui | **Radio/relais** |
| 40 | Zone portail `zone_radius[]` | `views/admin/atak/roleplay.php` | 269, 307 | Rayon zone admin web | Template `500` | ✓ Oui → `zones_config` JSON | **Radio/relais** |
| 41 | `packet_loss_floor` / `tx_drop_chance` / `latency_add` | `fn_applyZoneEffects.sqf` | 56-124 | Effets réseau selon type zone (formules hardcodées) | Ex: no_coverage latency +2000 ms, loss 90-100% ; interference +500 ms, 30-50% ; degraded +200 ms, 10-20% ; jammer pulse 50-80% | ✓ Oui → `canTransmit`, `getPacketLossStats` | **Radio/relais** |
| 42 | Jammer crash terminal seuil | `fn_applyZoneEffects.sqf` | 104-111 | Gel terminal si jammer fort | Intensité ≥65 ; cooldown 40 s ; durée 6-20 s | ✗ Local hardcodé | **Dommages** |
| 43 | Viewshed `radius_m` (DB) | `bootstrap/atak_viewshed_overlays_migration.php` | 26 | Portée viewshed vision | `DEFAULT 500` | ✓ Oui → admin + API | **Couverture** |
| 44 | Viewshed défaut Athena publish | `atak_athena/XEH_postInitClient.sqf` | 177-180 | Rayon publish viewshed côté mod | `500` | ✓ Oui | **Couverture** |
| 45 | Viewshed GL ops (INCOHÉRENT) | `overwatch-gl/OverwatchGlTactics.js` | 86 | Appel viewshed Web GL | `radius_m: 800` | **✗ INCOHÉRENT avec défaut 500** | **Couverture** |
| 46 | Reach overlay `MIN_RADIUS_M` | `public/assets/js/atak-reach-overlay.js` | 6 | Rayon min overlay portée UI | `25` | ✗ Local UI | **Couverture** |
| **DOMAINE : SIMULATION RÉSEAU PORTAIL** |
| 47 | `network_enabled` | `TenantAtakConfigRepository.php` | 39 | Simulation réseau portail | `false` | ✓ Oui → API + poll SQF | **Radio/relais** |
| 48 | `network_mode` | `TenantAtakConfigRepository.php` | 40 | Mode normal / hostile / degraded / equipment | `'normal'` | ✓ Oui → admin + API | **Radio/relais** |
| 49 | `latency_min_ms` | `TenantAtakConfigRepository.php` | 41 | Latence simulée portail (min) | `0` | ✓ Oui → admin roleplay | **Radio/relais** |
| 50 | `latency_max_ms` | `TenantAtakConfigRepository.php` | 42 | Latence simulée portail (max) | `0` | ✓ Oui → admin roleplay | **Radio/relais** |
| 51 | `packet_loss_percent` | `TenantAtakConfigRepository.php` | 43 | Pertes paquets % portail | `0.0` | ✓ Oui → poll + stats | **Radio/relais** |
| 52 | `disconnect_enabled` | `TenantAtakConfigRepository.php` | 44 | Coupures périodiques portail | `false` | **⚠ Partiel** (portail ; timing ≠ mod) | **Radio/relais** |
| 53 | `disconnect_min_sec` | `TenantAtakConfigRepository.php` | 45 | Durée min coupure portail (s) | `5` | **⚠ Portail seulement** | **Radio/relais** |
| 54 | `disconnect_max_sec` | `TenantAtakConfigRepository.php` | 46 | Durée max coupure portail (s) | `30` | **⚠ Portail seulement** | **Radio/relais** |
| 55 | `disconnect_interval_sec` | `TenantAtakConfigRepository.php` | 47 | Intervalle coupures portail (s) | `600` | **⚠ Portail ; PAS ALIGNÉ sur sim client** | **Radio/relais** |
| 56 | Sim client `tx_drop_chance` fallback | `fn_canTransmit.sqf` | 63-65 | Drop TX sim locale | `8` | ✓ Oui si LinkDegradeSim actif | **Radio/relais** |
| 57 | Sim client `loss_floor` init | `fn_simulateNetworkDisconnect.sqf` | 20-28 | Plancher pertes + drop pulsés | `12 + random 10` puis `10 + random 28` | ✗ Local sim | **Radio/relais** |
| 58 | Sim client durée coupure | `fn_simulateNetworkDisconnect.sqf` | 62 | Durée offline | `4 + random 18` s | **⚠ ≠ portail 5-30 / 600** | **Radio/relais** |
| 59 | Sim client 1ère coupure | `fn_simulateNetworkDisconnect.sqf` | 36 | `next_disconnect_at` init | `time + 180 + random 240` s | ✗ Local | **Radio/relais** |
| 60 | `comspec_overwatch_link_degrade_sim` | `fn_linkDegradeSimApplySetting.sqf` | 6-10 | Toggle sim liaison dégradée client | `false` | ✓ Oui → settings ATAK + disconnect | **Radio/relais** |
| 61 | `sensor_enabled` | `TenantAtakConfigRepository.php` | 50 | Capteur cardiaque défaillant | `false` | ✓ Oui → web roleplay | Autre |
| 62 | `sensor_failure_percent` | `TenantAtakConfigRepository.php` | 51 | % échec capteur | `0` | ✓ Oui | Autre |
| 63 | `sensor_error_percent` | `TenantAtakConfigRepository.php` | 52 | % valeur erronée capteur | `0` | ✓ Oui | Autre |
| 64 | `sensor_missing_percent` | `TenantAtakConfigRepository.php` | 53 | % données manquantes capteur | `0` | ✓ Oui | Autre |
| **DOMAINE : CERTIFICATS / CRYPTO / DOMAINES** |
| 65 | `automatic_pairing` | `TenantAdminSettingsRepository.php` | 64, 145 | Appairage / délivrance cert auto | `true` | ✓ Oui → API cert 403 si OFF ; SQF `auto_pairing` | **Certificats** |
| 66 | `certificate_duration_days` | `TenantAdminSettingsRepository.php` | 66, 147 | Durée certificat (jours) | `365` ; borne 1-1825 | ✓ Oui → `issueCertificate` + Extension | **Certificats** |
| 67 | `duration_days` (fallback issue) | `AtakRealismRepository.php` | 868-869 | Fallback durée si `expires_at` absent | `365` | ✓ Oui | **Certificats** |
| 68 | `certificate_type` défaut | `AtakRealismRepository.php` | 859 | Type certificat | `'device'` | ✓ Oui | **Certificats** |
| 69 | `authority_label` défaut | `AtakRealismRepository.php` | 858 | Autorité de certification | `'Autorité ATAK locale'` | ✓ Oui | **Certificats** |
| 70 | `status` cert défaut | `AtakRealismRepository.php` | 860 | Statut certificat | `'issued'` | ✓ Oui (API game force souvent `'active'`) | **Certificats** |
| 71 | `compromise_state` | `bootstrap/atak_realism_registry_migration.php` + repo | L40 + L1113 | Capture / compromission terminal (none / captured / compromised) | `'none'` | ✓ Oui → Zeus/ACE + scramble | **Certificats** |
| 72 | Domaine crypto défaut | `AtakRealismRepository.php` | 1093-1098 | Domaine `FRIENDLY-NET` / Réseau ami | `faction_key=friendly`, `status=active` | ✓ Oui → issue cert | **Certificats** |
| 73 | Sync réalisme intervalle | `fn_syncAtakRealism.sqf` | 29-31 | Anti-spam sync terminal/cert | `180` s | ✗ Local | **Certificats** |
| 74 | `auto_pairing` SQF fallback | `fn_syncAtakRealism.sqf` | 167 | Si API omet le champ | `"1"` | ✗ Local | **Certificats** |
| 75 | `SimplifyTerminalRealismJson` | `Extension.cs` | 6692-6761 | Relais champs cert/defaults vers SQF (parseur) | N/A (fonction) | ✓ Oui | **Certificats** |
| 76 | `intel_scramble_enabled` | `TenantAtakConfigRepository.php` | 60 | Données chiffrées / illisibles terrain si cert invalide | `false` | ✓ Oui → poll → scramble UI | **Certificats** |
| 77 | `COMSPEC_CertDurationDays` (SQF var) | `fn_syncAtakRealism.sqf` | 175-177 | Variable affichage durée cert | De l'API | **⚠ ORPHELIN** (affiché seulement, pas re-lu) | **Certificats** |
| **DOMAINE : ITINÉRAIRES / WAYPOINTS / GPS** |
| 78 | Waypoint `radius_m` (DB) | `migrations/2026_07_27_001_atak_waypoints.sql` | 56 | Rayon franchissement WP | `NULL` (pas de défaut SQL) | ✓ Oui → GetWaypoints / MarkWaypointReached | **Itinéraires** |
| 79 | `radius_m` UI GPS routes web | `public/assets/js/atak-gps-routes.js` | 316 | Rayon création WP web | `25` | ✓ Oui → POST API waypoints | **Itinéraires** |
| 80 | Route `route_type` | `migrations/2026_07_27_001_atak_waypoints.sql` | 15 | Type itinéraire | `'PATROL'` | ✓ Oui | **Itinéraires** |
| 81 | Route `status` | `migrations/2026_07_27_001_atak_waypoints.sql` | 21 | Statut route | `'PLANNED'` | ✓ Oui | **Itinéraires** |
| 82 | Route `visibility_level` | `migrations/2026_07_27_001_atak_waypoints.sql` | 27 | Visibilité route | `'PUBLIC'` | ✓ Oui | **Itinéraires** |
| 83 | Rally `RALLY_RADIUS_M` | `public/assets/js/atak-overwatch-beta.js` | 99 | Rayon rally point | `50` | ✗ Local UI | **Itinéraires** |
| **DOMAINE : SYMBOLOGIE CARTE / MARQUEURS** |
| 84 | `vehicle_detail` (expérience) | `AtakExperienceService.php` | 54-61 | Orientation / vitesse véhicule sur carte | `"player"` | ✓ Oui → CBA | **Symbologie carte** |
| 85 | `show_opfor` | `AtakExperienceService.php` | 74-87 | Affichage OPFOR sur Tacmap | `"player"` | ✓ Oui → CBA + Extension SimplifyExperience | **Symbologie carte** |
| 86 | `show_independent` | `AtakExperienceService.php` | 88-95 | Affichage INDEP sur Tacmap | `"player"` | ✓ Oui → CBA + Extension | **Symbologie carte** |
| 87 | `show_civilian` | `AtakExperienceService.php` | 96-101 | Affichage CIV sur Tacmap | `"player"` | ✓ Oui → CBA + Extension | **Symbologie carte** |
| 88 | `sync_map_markers` | `AtakExperienceService.php` | 104-111 | Repères jeu → poste de commandement | `"player"` | ✓ Oui → CBA | **Symbologie carte** |
| 89 | `AFFILIATION_COLORS` | `public/assets/js/map/TacticalSymbol.js` | 11-16 | Couleurs cadres MIL-STD simplifiés | FRIENDLY #0080ff, HOSTILE #ff4040, UNKNOWN #ffff00, NEUTRAL #00ff00 | ✓ Oui → MarkerManager C2 | **Symbologie carte** |
| 90 | `statusStyle` liaison | `TacticalSymbol.js` | 80-89 | Opacité / dash selon état (ONLINE…KIA) | ONLINE opaque, DEGRADED semi, OFFLINE dash, KIA hidden | ✓ Oui | **Symbologie carte** |
| 91 | `affiliationKey` mapping | `TacticalSymbol.js` | 18-24 | Mapping factions → affiliation | Défaut FRIENDLY si inconnu | ✓ Oui | **Symbologie carte** |
| 92 | Marker detection `radius_m` | `bootstrap/atak_marker_detection_rules_migration.php` | 30 | Rayon détection marqueur PO | `DEFAULT 20` | ✓ Oui → rules API + Overwatch | **Symbologie carte** |
| 93 | PO radius (overwatch-beta) | `AtakPoMarker` / overwatch-beta | ~1395 | Rayon PO / détection | `20` | ✓ Oui | **Symbologie carte** |
| **DOMAINE : AUTRE / DIVERS** |
| 94 | `require_equipment` | `AtakExperienceService.php` | 64-71 | Exiger tablette/GPS physique | `"player"` | ✓ Oui → CBA `comspec_overwatch_require_item` | Autre |
| 95 | `comspec_sse_require_item` | `mod/.../XEH_preInit.sqf` | 967-973 | Terminal SEEK requis pour fiche SSE | `true` | ✓ Oui → SSE open | Autre |
| 96 | `ace_menus` / `order_compose` / `sse_require_item` / `playtime` / `athena_feed` | `AtakExperienceService.php` | 140-188 | Fonctions mission (menus ACE, ordres, SEEK, playtime, cam) | Tous `"player"` | ✓ Oui → CBA | Autre |
| 97 | `minimum_client_version` | `TenantAdminSettingsRepository.php` | 65, 146 | Version client min | `'5.1.8'` | ✓ Exposé dans atak_defaults | Autre |
| 98 | `off_op_position_sharing` | `TenantAdminSettingsRepository.php` | 67, 148 | Partage pos hors opération | `false` | ✓ Admin defaults | Autre |
| 99 | `session_ttl_sec` (roleplay API) | `AtakApiController.php` | 1844, 1881 | TTL session roleplay côté réponse | `86400` hardcodé | **⚠ ORPHELIN** (poll lit ; aucune branche métier consomme) | Autre |
| 100 | Drain period Extension | `Extension.cs` | 78, 722, 740 | Flush positions coalescées (ms) | Clamp 250-2000 ms | ✓ Profil réseau SQF | Autre |

---

### 1.2. Récapitulatif par domaine

| Domaine | Nombre de paramètres | Commentaire |
|---------|----------------------|-------------|
| **Radio / relais** | 36 | Portées, relais, zones, simulation réseau, brouillage |
| **Certificats / crypto** | 13 | Durée, appairage auto, domaines crypto, compromission |
| **Dommages terminal** | 13 | Niveaux de réalisme, seuils blessures, extinction, destruction |
| **Itinéraires / GPS** | 6 | Waypoints, routes, rallies, rayons de franchissement |
| **Symbologie carte** | 10 | Affichage factions, couleurs MIL-STD, marqueurs PO |
| **Couverture / viewshed** | 3 | Viewshed, reach overlay |
| **Autre (ambiance, expérience)** | 19 | Réglages UX, menus, playtime, équipement requis |
| **TOTAL** | **~100** | |

---

## PARTIE 2 : INCOHÉRENCES IDENTIFIÉES

### 2.1. Tableau des incohérences mod ↔ web

| # | Sujet | Côté A (mod/SQF) | Côté B (web/API) | Risque / Impact |
|---|-------|------------------|------------------|-----------------|
| **1** | **Dommages ATAK** | CBA `comspec_overwatch_atak_realism` défaut **0** (mission) | Expérience portail `atak_realism` défaut **`player`** (laisse la mission décider) | ⚠ Si admin force 1-3 sur portail, écrase le réglage mission sans UI claire in-game |
| **2** | **Lexique confus "réalisme"** | `realism` (ambiance) ≠ `atak_realism` (dommages) ≠ roleplay réseau (`roleplay_enabled`) | Trois concepts appelés "réalisme" | ⚠ Confusion utilisateurs/admins |
| **3** | **Coupures réseau** | Client : intervalle ~**180-420 s** puis **240-600 s**, durée **4-22 s** | Portail : intervalle **600 s**, durée **5-30 s** | **⚠ DOUBLE PÉNALITÉ** possible si les deux actifs simultanément |
| **4** | **Pertes paquets (triple moteur)** | Zones (formules hardcodées) + LinkDegradeSim (4-18%) + portail `packet_loss_percent` | Trois moteurs indépendants qui s'additionnent | ⚠ Pertes cumulées peuvent atteindre 100% sans le savoir |
| **5** | **UI double `link_via_relays`** | Poll SQF lit `link_via_relays` | Admin **roleplay.php** ET **server_control.php** écrivent la même colonne DB | ⚠ Double UI → risque désync perçue |
| **6** | **Portée relais max** | SQF clamp 50-**8000 m** | API PHP `max(50, range)` sans max 8000 | ⚠ Valeurs >8000 possibles côté DB non clampées comme le mod |
| **7** | **Rayon waypoint** | Web GPS crée avec **25 m** | Schéma SQL `radius_m` **NULL** (pas de défaut serveur) | ⚠ Pas de défaut unique |
| **8** | **Viewshed** | DB/Athena défaut **500 m** | Overwatch GL appelle **800 m** | **⚠ INCOHÉRENT** : cercles couverture différents selon outil |
| **9** | **Proximité radio vs téléphone** | Radio CBA rayon **75 m** | Téléphone ATAK proximité **200 m** | ⚠ Deux notions "proximité" distinctes, pas documentées ensemble |
| **10** | **Symbologie** | Mod natif `comspec_atak_native_fnc_symbology` + legacy cTab | Web `TacticalSymbol.js` (MIL-STD simplifié) | ⚠ Deux pipelines de rendu ; pas un paramètre partagé |
| **11** | **Zones Zeus/portail** | Create **100 m** / apply **200 m** | Formulaire portail **500 m** | ⚠ Trois defaults selon source → confusion mission makers |
| **12** | **Débit relais (formules)** | Formules dégradation hardcodées dans `fn_getNearestAtakRelay` | Web affiche les valeurs brutes DB | ⚠ Web ne recalcule pas la même courbe → affichage inexact |

### 2.2. Paramètres quasi-orphelins / dead code

| Paramètre | Constat |
|-----------|---------|
| `session_ttl_sec` | Exposé API roleplay + parsé SQF ; **aucune branche métier évidente** consommant la valeur (toujours 86400 hardcodé) |
| `COMSPEC_CertDurationDays` (var SQF) | Affiché dans status panel ; renouvellement suit `atak_defaults` serveur, pas cette var → **orphelin pratique** |
| Extracts `mod/Workshop/_extract/*/` | Copies d'archives Workshop — **pas source de vérité** (UptoDate l'est) |

---

## PARTIE 3 : PROPOSITION DE CENTRALISATION

### 3.1. Source de vérité unique : table `atak_realism_config`

**Principe** : une seule table SQL/JSON côté ATHENA (API PHP) lit, valide et distribue tous les paramètres de réalisme vers :
- Le mod (SQF) via extension DLL (C#)
- Le web tactique (JS)
- Le back-office admin

**Avantages** :
- ✓ Fin des triples defaults (100/200/500 pour zones)
- ✓ Fin des duplications de colonnes (`link_via_relays` dans 2 UI)
- ✓ Cohérence garantie mod ↔ web
- ✓ Historisation / audit des changements de config
- ✓ Un seul écran d'admin pour tout paramétrer

### 3.2. Schéma proposé : table `atak_realism_config`

```sql
CREATE TABLE atak_realism_config (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    
    -- Métadonnées
    config_version VARCHAR(16) NOT NULL DEFAULT '1.0.0',
    config_name VARCHAR(160) NOT NULL DEFAULT 'Configuration par défaut',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    
    -- CONFIG JSON (source de vérité)
    config_json JSON NOT NULL COMMENT 'Tous les paramètres de réalisme en JSON structuré',
    
    -- Audit
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT UNSIGNED DEFAULT NULL,
    updated_by INT UNSIGNED DEFAULT NULL,
    
    PRIMARY KEY (id),
    UNIQUE KEY uk_realism_tenant_active (tenant_id, is_active),
    KEY idx_realism_tenant_version (tenant_id, config_version),
    CONSTRAINT fk_realism_tenant FOREIGN KEY (tenant_id) 
        REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_realism_created_by FOREIGN KEY (created_by) 
        REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_realism_updated_by FOREIGN KEY (updated_by) 
        REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.3. Structure JSON proposée (`config_json`)

```json
{
  "version": "1.0.0",
  "radio_relays": {
    "link_via_relays": false,
    "relay_range_m": 2000,
    "relay_range_min_m": 50,
    "relay_range_max_m": 8000,
    "relay_slots": 8,
    "relay_power_w": 25,
    "relay_throughput_mbps": 12.0,
    "relay_reliability_pct": 92,
    "radio_proximity_enabled": true,
    "radio_proximity_radius_m": 75,
    "radio_proximity_interval_s": 2,
    "phone_proximity_radius_m": 200
  },
  "zones_roleplay": {
    "zones_enabled": false,
    "default_zone_radius_m": 200,
    "zone_intensities": {
      "no_coverage": 100,
      "interference": 50,
      "degraded": 30,
      "jammer": 80
    },
    "zone_effects": {
      "no_coverage": {
        "latency_add_ms": 2000,
        "packet_loss_floor_pct": 90,
        "packet_loss_ceiling_pct": 100
      },
      "interference": {
        "latency_add_ms": 500,
        "packet_loss_floor_pct": 30,
        "packet_loss_ceiling_pct": 50
      },
      "degraded": {
        "latency_add_ms": 200,
        "packet_loss_floor_pct": 10,
        "packet_loss_ceiling_pct": 20
      },
      "jammer": {
        "latency_add_ms": 1000,
        "packet_loss_floor_pct": 50,
        "packet_loss_ceiling_pct": 80,
        "crash_threshold": 65,
        "crash_cooldown_s": 40,
        "crash_duration_min_s": 6,
        "crash_duration_max_s": 20
      }
    }
  },
  "network_simulation": {
    "portal_enabled": false,
    "portal_mode": "normal",
    "portal_latency_min_ms": 0,
    "portal_latency_max_ms": 0,
    "portal_packet_loss_pct": 0.0,
    "portal_disconnect_enabled": false,
    "portal_disconnect_min_s": 5,
    "portal_disconnect_max_s": 30,
    "portal_disconnect_interval_s": 600,
    "client_sim_enabled": false,
    "client_disconnect_first_min_s": 180,
    "client_disconnect_first_max_s": 420,
    "client_disconnect_duration_min_s": 4,
    "client_disconnect_duration_max_s": 22,
    "client_disconnect_next_min_s": 240,
    "client_disconnect_next_max_s": 600,
    "client_tx_drop_chance_pct": 8,
    "client_loss_floor_base_pct": 12,
    "client_loss_floor_random_pct": 10
  },
  "certificates": {
    "automatic_pairing": true,
    "certificate_duration_days": 365,
    "certificate_duration_min_days": 1,
    "certificate_duration_max_days": 1825,
    "certificate_type_default": "device",
    "authority_label_default": "Autorité ATAK locale",
    "crypto_domain_default_ref": "FRIENDLY-NET",
    "crypto_domain_default_label": "Réseau ami",
    "crypto_domain_default_faction": "friendly",
    "intel_scramble_enabled": false,
    "sync_realism_interval_s": 180
  },
  "terminal_damage": {
    "atak_realism_level": 0,
    "impact_screen_destroy_threshold": 0.25,
    "impact_screen_destroy_prob_multiplier": 50,
    "impact_shutdown_prob_multiplier": 40,
    "impact_shutdown_duration_s": 20,
    "arm_damage_threshold": 0.65,
    "arm_damage_prob_pct": 25,
    "arm_damage_duration_s": 45,
    "torso_level1_threshold": 0.5,
    "torso_level1_prob_pct": 30,
    "torso_level1_duration_s": 30,
    "torso_level2_threshold": 0.7,
    "torso_level2_prob_pct": 40,
    "torso_level3_threshold": 0.8,
    "torso_level3_prob_pct": 50,
    "kat_pneumothorax_threshold": 0.75,
    "kat_pneumothorax_multiplier": 0.85,
    "kat_spo2_threshold": 85
  },
  "waypoints_routes": {
    "waypoint_radius_default_m": 25,
    "route_type_default": "PATROL",
    "route_status_default": "PLANNED",
    "route_visibility_default": "PUBLIC",
    "rally_radius_m": 50
  },
  "symbology_map": {
    "vehicle_detail_mode": "player",
    "show_opfor_mode": "player",
    "show_independent_mode": "player",
    "show_civilian_mode": "player",
    "sync_map_markers_mode": "player",
    "affiliation_colors": {
      "FRIENDLY": "#0080ff",
      "HOSTILE": "#ff4040",
      "UNKNOWN": "#ffff00",
      "NEUTRAL": "#00ff00"
    },
    "marker_detection_radius_m": 20,
    "po_marker_radius_m": 20
  },
  "coverage_viewshed": {
    "viewshed_radius_default_m": 500,
    "viewshed_radius_min_m": 25,
    "reach_overlay_min_radius_m": 25
  },
  "experience_ambiance": {
    "realism_mode": false,
    "troll_mode": false,
    "screen_notifications_mode": "player",
    "require_equipment_mode": "player",
    "sse_require_item": true,
    "ace_menus_mode": "player",
    "order_compose_mode": "player",
    "playtime_mode": "player",
    "athena_feed_mode": "player"
  },
  "other_settings": {
    "minimum_client_version": "5.1.8",
    "off_op_position_sharing": false,
    "session_ttl_sec": 86400,
    "drain_period_min_ms": 250,
    "drain_period_max_ms": 2000
  }
}
```

### 3.4. Pipeline de lecture proposé

```
┌─────────────────────────────────────────┐
│  Admin web : écran "Réalisme ATAK"      │
│  UN SEUL écran avec onglets par domaine │
└─────────────┬───────────────────────────┘
              │ POST /api/admin/atak/realism/config
              ↓
┌─────────────────────────────────────────┐
│  AtakRealismConfigRepository.php        │
│  Valide JSON schema + bornes            │
│  Upsert `atak_realism_config`           │
└─────────────┬───────────────────────────┘
              │
              ↓
┌─────────────────────────────────────────┐
│  API /api/atak/realism/config (GET)     │
│  Retourne config_json de la config      │
│  active pour le tenant                  │
└─────────────┬───────────────────────────┘
              │
              ├──────────────────┬─────────────────┐
              ↓                  ↓                 ↓
     ┌────────────────┐  ┌──────────────┐  ┌──────────────┐
     │ Extension C#   │  │ SQF Polling  │  │ Web JS       │
     │ Simplify*      │  │ fn_poll*     │  │ TacticalMap  │
     │ GetTerminal*   │  │ fn_apply*    │  │ Overlays     │
     │ UpdateRelay    │  │              │  │              │
     └────────────────┘  └──────────────┘  └──────────────┘
```

### 3.5. Écran d'admin proposé (mockup structure)

```
┌──────────────────────────────────────────────────────────────┐
│  ATAK · Configuration réalisme                     [Sauvegarder] │
├──────────────────────────────────────────────────────────────┤
│  [Profil] : Configuration par défaut (active)                │
│  [Créer nouveau profil] [Dupliquer] [Historique versions]   │
├──────────────────────────────────────────────────────────────┤
│  Onglets :                                                   │
│  • Radio / Relais  • Zones  • Réseau  • Certificats         │
│  • Dommages  • Itinéraires  • Carte  • Expérience           │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│  [Onglet : Radio / Relais]                                  │
│                                                              │
│  ☐ Exiger proximité relais (`link_via_relays`)              │
│     Portée relais par défaut : [2000] m (50-8000)           │
│     Places téléphones : [8] (1-64)                          │
│     Puissance : [25] W                                       │
│     Débit : [12.0] Mbps                                      │
│     Fiabilité : [92] %                                       │
│                                                              │
│  ☑ Surveillance radio proximité                             │
│     Rayon écoute radio : [75] m (10-300)                    │
│     Intervalle scan : [2] s (1-10)                          │
│     Rayon proximité téléphone : [200] m                     │
│                                                              │
│  [Aide contextuelle : Les relais simulent des mâts de       │
│   communication. Si activé, les joueurs doivent être à      │
│   proximité d'un relais actif pour transmettre.]            │
│                                                              │
├──────────────────────────────────────────────────────────────┤
│  [Onglet : Zones roleplay]                                  │
│                                                              │
│  ☐ Activer zones géo (`zones_enabled`)                      │
│     Rayon par défaut : [200] m                              │
│                                                              │
│  Intensités par type de zone :                              │
│    Sans couverture : [100]                                  │
│    Interférence    : [50]                                   │
│    Dégradé         : [30]                                   │
│    Brouilleur      : [80]                                   │
│                                                              │
│  Effets réseau :                                            │
│    [Tableau éditable : type | latency_add | loss_floor |    │
│     loss_ceiling]                                           │
│                                                              │
├──────────────────────────────────────────────────────────────┤
│  [Onglet : Simulation réseau]                               │
│                                                              │
│  Portail (serveur) :                                        │
│  ☐ Activer simulation réseau portail                        │
│     Mode : [Normal ▼]                                       │
│     Latence : [0] - [0] ms                                  │
│     Pertes paquets : [0.0] %                                │
│  ☐ Coupures périodiques                                     │
│     Durée : [5] - [30] s                                    │
│     Intervalle : [600] s                                    │
│                                                              │
│  Client (mod) :                                             │
│  ☐ Activer simulation liaison dégradée client               │
│     Durée coupure : [4] - [22] s                            │
│     Intervalle 1ère : [180] - [420] s                       │
│     Intervalle suivantes : [240] - [600] s                  │
│     Drop TX : [8] %                                         │
│                                                              │
│  ⚠ Attention : si portail ET client actifs = double         │
│     pénalité réseau !                                       │
│                                                              │
├──────────────────────────────────────────────────────────────┤
│  [Onglet : Certificats]                                     │
│                                                              │
│  ☑ Appairage automatique (`automatic_pairing`)              │
│     Durée certificat : [365] jours (1-1825)                 │
│     Type par défaut : [device ▼]                            │
│     Autorité : [Autorité ATAK locale]                       │
│                                                              │
│  Domaine crypto par défaut :                                │
│     Référence : [FRIENDLY-NET]                              │
│     Label : [Réseau ami]                                    │
│     Faction : [friendly ▼]                                  │
│                                                              │
│  ☐ Chiffrement intel (scramble si cert invalide)            │
│     Intervalle sync réalisme : [180] s                      │
│                                                              │
├──────────────────────────────────────────────────────────────┤
│  [Onglet : Dommages terminal]                               │
│                                                              │
│  Niveau réalisme ATAK : [0 (off) ▼]                         │
│    0 = Désactivé                                            │
│    1 = Extinction temporaire                                │
│    2 = Écran inutilisable (GPS OK)                          │
│    3 = Device détruit                                       │
│    player = Laisse la mission décider (CBA)                 │
│                                                              │
│  Seuils dommages (avancé) :                                 │
│    [Tableau : type | threshold | prob_pct | duration_s]     │
│    Choc écran      : 0.25 / 50% / —                         │
│    Choc extinction : — / 40% / 20 s                         │
│    Bras            : 0.65 / 25% / 45 s                      │
│    Torse niv.1     : 0.5 / 30% / 30 s                       │
│    Torse niv.2     : 0.7 / 40% / —                          │
│    Torse niv.3     : 0.8 / 50% / —                          │
│    KAT pneumo      : 0.75 / 0.85 / SpO2 85                  │
│                                                              │
├──────────────────────────────────────────────────────────────┤
│  [Onglet : Itinéraires / GPS]                               │
│                                                              │
│  Rayon waypoint par défaut : [25] m                         │
│  Type route par défaut : [PATROL ▼]                         │
│  Statut route par défaut : [PLANNED ▼]                      │
│  Visibilité route par défaut : [PUBLIC ▼]                   │
│  Rayon rally point : [50] m                                 │
│                                                              │
├──────────────────────────────────────────────────────────────┤
│  [Onglet : Symbologie carte]                                │
│                                                              │
│  Détails véhicules : [player ▼]                             │
│  Afficher OPFOR : [player ▼]                                │
│  Afficher INDEP : [player ▼]                                │
│  Afficher CIV : [player ▼]                                  │
│  Sync marqueurs jeu : [player ▼]                            │
│                                                              │
│  Couleurs affiliation MIL-STD :                             │
│    FRIENDLY  : [#0080ff] (bleu)                             │
│    HOSTILE   : [#ff4040] (rouge)                            │
│    UNKNOWN   : [#ffff00] (jaune)                            │
│    NEUTRAL   : [#00ff00] (vert)                             │
│                                                              │
│  Rayon détection marqueur PO : [20] m                       │
│                                                              │
├──────────────────────────────────────────────────────────────┤
│  [Onglet : Couverture / Viewshed]                           │
│                                                              │
│  Rayon viewshed par défaut : [500] m                        │
│  Rayon min overlay portée : [25] m                          │
│                                                              │
├──────────────────────────────────────────────────────────────┤
│  [Onglet : Expérience / Ambiance]                           │
│                                                              │
│  ☐ Mode réalisme immersion (moins d'aides)                  │
│  ☐ Mode fun / troll (alertes exagérées)                     │
│     ⚠ Mutuellement exclusifs                                │
│                                                              │
│  Notifications écran : [player ▼]                           │
│  Exiger équipement (tablette/GPS) : [player ▼]              │
│  ☑ Terminal SEEK requis pour fiche SSE                      │
│                                                              │
│  Fonctions mission :                                        │
│    Menus ACE : [player ▼]                                   │
│    Composer ordre : [player ▼]                              │
│    Playtime : [player ▼]                                    │
│    Flux caméra Athena : [player ▼]                          │
│                                                              │
│  Valeurs "player" = laisse le joueur / mission décider      │
│  via CBA settings in-game                                   │
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

### 3.6. Catégories de toggles proposées

| Catégorie | Sous-catégories | Nombre de paramètres |
|-----------|-----------------|----------------------|
| **Radio / Relais** | Relais terrain, Radio proximité | ~13 |
| **Zones roleplay** | Zones géo, Intensités, Effets réseau | ~15 |
| **Simulation réseau** | Portail (serveur), Client (mod) | ~13 |
| **Certificats** | Appairage, Durée, Domaines crypto, Scramble | ~10 |
| **Dommages terminal** | Niveau réalisme, Seuils avancés | ~13 |
| **Itinéraires / GPS** | Waypoints, Routes, Rallies | ~5 |
| **Symbologie carte** | Affichage factions, Couleurs, Marqueurs | ~10 |
| **Couverture / Viewshed** | Viewshed, Reach overlay | ~3 |
| **Expérience / Ambiance** | Modes réalisme/troll, Notifications, Équipement, Fonctions mission | ~15 |
| **Autre / Divers** | Version client min, Partage pos hors OP, Session TTL | ~3 |

**Total** : ~100 paramètres organisés en **9 onglets** d'interface admin.

### 3.7. Recommandations d'implémentation

#### Phase 1 : Migration (conservatrice)
1. Créer table `atak_realism_config` + migration
2. Créer `AtakRealismConfigRepository.php` avec JSON schema validator
3. Créer route API `GET /api/atak/realism/config` lisant le JSON
4. Migration de données : lire colonnes actuelles (`atak_link_via_relays`, etc.) → générer JSON initial pour chaque tenant
5. **Conserver colonnes existantes** pour compatibilité ascendante (v1)

#### Phase 2 : Nouveau back-office
1. Créer écran admin unique `/admin/atak/realism` (onglets)
2. Remplacer les écrans `roleplay.php` + partie `server_control.php` par redirection vers nouvel écran
3. CRUD JSON via API POST `/api/admin/atak/realism/config`
4. Validation JS + PHP avec bornes (50-8000 pour relais, 1-1825 pour certs, etc.)

#### Phase 3 : Refactor consumers
1. Extension C# : ajouter `GetRealismConfig` retournant le JSON complet (tab-separated ou JSON)
2. SQF : `fn_pollRealismConfig` lit le nouveau endpoint et applique tous les params
3. Web JS : importer config via `fetch('/api/atak/realism/config')` et appliquer overlays
4. Supprimer les defaults hardcodés (100/200/500 zones, etc.) → tout lire depuis JSON

#### Phase 4 : Cleanup
1. Supprimer colonnes dupliquées (`atak_link_via_relays`, etc.) après migration validée
2. Supprimer anciens écrans `roleplay.php` / `server_control.php` sections dupliquées
3. Documenter structure JSON dans `/docs/technique/atak-realism-config-schema.md`
4. Ajouter unit tests pour validator JSON + bornes

### 3.8. Avantages attendus

| Avantage | Description |
|----------|-------------|
| **Cohérence mod ↔ web** | Une seule source de vérité élimine les désynchronisations |
| **Fin des triples defaults** | Un seul rayon de zone (ex: 200 m) au lieu de 100/200/500 selon source |
| **Maintenance facilitée** | Ajouter un paramètre = 1 ligne JSON + 1 champ UI, pas 4 fichiers à modifier |
| **Historisation / audit** | Versions de config avec `created_by`, `updated_by`, `updated_at` |
| **Profils de config** | Possibilité de créer plusieurs profils (débutant / expert / événement) et les activer à la volée |
| **Validation stricte** | JSON schema + bornes PHP + JS = impossible de mettre range=50000 par erreur |
| **Documentation intégrée** | Un seul schéma JSON à documenter au lieu de 30 fichiers éparpillés |
| **Tests unitaires** | Validator JSON testable unitairement (PHPUnit) |
| **UX admin améliorée** | Un seul écran avec onglets au lieu de 3 écrans dispersés |

---

## PARTIE 4 : PLAN D'ACTION RECOMMANDÉ

### 4.1. Actions immédiates (quick wins)

| # | Action | Impact | Effort |
|---|--------|--------|--------|
| 1 | **Aligner viewshed** : fixer Overwatch GL à 500 m au lieu de 800 | ✓ Cohérence carte | 10 min |
| 2 | **Clamp API relais** : ajouter `max(50, min(8000, $range))` dans `AtakRelayRepository` | ✓ Évite range absurdes >8000 | 15 min |
| 3 | **Unifier UI `link_via_relays`** : supprimer le toggle de `server_control.php`, garder que `roleplay.php` | ✓ Fin double UI | 20 min |
| 4 | **Doc lexique** : ajouter glossaire distinguant `realism` / `atak_realism` / roleplay réseau | ✓ Clarté utilisateurs | 30 min |
| 5 | **Ajouter warning** : bandeau admin si `portal.disconnect_enabled` ET `client.link_degrade_sim` = true | ⚠ Évite double pénalité | 20 min |

### 4.2. Plan complet centralisation (4 phases)

| Phase | Tâches | Durée estimée |
|-------|--------|---------------|
| **Phase 1** : Migration DB + API read | Créer table + migration données + route GET | 2-3 jours |
| **Phase 2** : Back-office | Écran admin onglets + API POST + validation | 3-5 jours |
| **Phase 3** : Refactor consumers | Extension C#, SQF polling, Web JS | 5-7 jours |
| **Phase 4** : Cleanup | Supprimer colonnes dupliquées + anciens écrans | 2-3 jours |
| **TOTAL** | | **12-18 jours** (hors tests validation terrain) |

### 4.3. Priorisation par domaine

Si refonte complète trop lourde, prioriser par domaine :

**Priorité 1 (critique)** :
- Radio / relais (13 params) : impact gameplay majeur
- Zones roleplay (15 params) : confusion defaults 100/200/500

**Priorité 2 (important)** :
- Certificats (10 params) : sécurité / confiance
- Dommages terminal (13 params) : expérience joueur

**Priorité 3 (confort)** :
- Simulation réseau (13 params) : déjà fonctionnel mais incohérent
- Itinéraires / GPS (5 params) : peu de params

**Priorité 4 (cosmétique)** :
- Symbologie carte (10 params) : fonctionne bien
- Couverture / viewshed (3 params) : bug mineur
- Expérience / ambiance (15 params) : UX secondaire

---

## ANNEXES

### A. Fichiers sources audités (traçabilité complète)

**PHP / Repositories**
- `app/Repositories/AtakRealismRepository.php` (1697 lignes)
- `app/Repositories/AtakRelayRepository.php`
- `app/Repositories/TenantAtakConfigRepository.php`
- `app/Repositories/TenantAdminSettingsRepository.php`
- `app/Repositories/AtakWaypointRepository.php`
- `app/Services/Tactical/AtakExperienceService.php`
- `app/Controllers/Api/AtakRealismApiController.php`
- `app/Controllers/Api/AtakApiController.php` (roleplay payload)
- `app/Controllers/Admin/AdminAtakRoleplayController.php`
- `app/Controllers/Admin/AdminOverwatchServerControlController.php`
- `app/Controllers/Admin/AdminAtakRealismController.php`

**Migrations / Bootstrap**
- `bootstrap/atak_realism_registry_migration.php`
- `bootstrap/atak_relays_fiche_migration.php`
- `bootstrap/atak_overwatch_ops_migration.php`
- `bootstrap/atak_marker_detection_rules_migration.php`
- `bootstrap/atak_viewshed_overlays_migration.php`
- `migrations/2026_07_27_001_atak_waypoints.sql`

**Vues admin / guide**
- `views/admin/atak/roleplay.php`
- `views/admin/atak/server_control.php`
- `views/admin/atak_realism/index.php`
- `views/admin/atak_realism/certificates.php`
- `views/admin/atak_realism/logs.php`
- `views/admin/organization/atak_marker_detection.php`
- `views/atak/mod/guide/_part_realisme.php`

**Extension C# (DLL COMSPEC)**
- `mod/UptoDate/COMSPECExtension/Extension.cs` (7200+ lignes)
  - `UpdateRelay` (L7136-7170)
  - `GetTerminalRealism` (L4329-4350)
  - `SimplifyTerminalRealismJson` (L6692-6761)
  - `SimplifyCertificateRegisterJson` (L6669-6690)
  - `RegisterCertificate` (L4237-4280)
  - `SimplifyExperience` (L5252-5258, L5379-5383)

**Mod SQF / HPP (canon UptoDate)**
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/XEH_preInit.sqf` (CBA settings)
- `mod/UptoDate/Sources/.../connect/functions/fn_syncAtakRealism.sqf`
- `mod/UptoDate/Sources/.../connect/functions/fn_checkAtakDamage.sqf`
- `mod/UptoDate/Sources/.../connect/functions/fn_canTransmit.sqf`
- `mod/UptoDate/Sources/.../connect/functions/fn_isNearLiveRelay.sqf`
- `mod/UptoDate/Sources/.../connect/functions/fn_placeAtakRelay.sqf`
- `mod/UptoDate/Sources/.../connect/functions/fn_getNearestAtakRelay.sqf`
- `mod/UptoDate/Sources/.../connect/functions/fn_moduleAtakRelay.sqf`
- `mod/UptoDate/Sources/.../connect/functions/fn_createRoleplayZone.sqf`
- `mod/UptoDate/Sources/.../connect/functions/fn_applyZoneEffects.sqf`
- `mod/UptoDate/Sources/.../connect/functions/fn_pollRoleplayConfig.sqf`
- `mod/UptoDate/Sources/.../connect/functions/fn_applyTenantExperience.sqf`
- `mod/UptoDate/Sources/.../connect/functions/fn_simulateNetworkDisconnect.sqf`
- `mod/UptoDate/Sources/.../connect/functions/fn_linkDegradeSimApplySetting.sqf`
- `mod/UptoDate/Sources/.../connect/functions/fn_registerAtakCertificate.sqf`
- `mod/UptoDate/Sources/.../connect/functions/fn_moduleApplyRoleplayZone.sqf`
- `mod/UptoDate/Sources/.../connect/modules/module_atak_relay.hpp`
- `mod/UptoDate/Sources/.../connect/modules/module_roleplay_zone.hpp`
- `mod/UptoDate/Sources/.../atak_athena/functions/fn_athena_updateRelay.sqf`
- `mod/UptoDate/Sources/.../atak_athena/functions/fn_athena_phoneProximityTick.sqf`
- `mod/UptoDate/Sources/.../atak_athena/XEH_postInitClient.sqf`

**Web JS**
- `public/assets/js/atak-overwatch-ops.js`
- `public/assets/js/atak-gps-routes.js`
- `public/assets/js/atak-reach-overlay.js`
- `public/assets/js/atak-viewshed.js`
- `public/assets/js/atak-overwatch-beta.js`
- `public/assets/js/map/TacticalSymbol.js`
- `public/assets/js/overwatch-gl/OverwatchGlTactics.js`

**Documentation technique**
- `mod/UptoDate/docs/realisme-liaison-atak.md`
- `docs/technique/analyse-features-realisme-milsim-2026-04.md`
- `docs/archive/legacy-atak/MODE-REALISME-COMPLET.md`

---

## CONCLUSION

Cet audit exhaustif révèle **~100 paramètres de réalisme** dispersés dans 5 registres indépendants (expérience communauté, roleplay réseau, defaults certificats, constantes SQF/CBA hardcodées, fiches relais DB/Eden), avec **12 incohérences majeures** mod ↔ web.

La **proposition de centralisation** via une table `atak_realism_config` (JSON) comme source de vérité unique permettrait de :
- Éliminer les désynchronisations
- Unifier l'expérience admin (1 écran au lieu de 3+)
- Faciliter la maintenance et l'évolution
- Garantir la cohérence mod ↔ web ↔ API

L'implémentation complète représente **12-18 jours** de développement, mais peut être phasée par domaine prioritaire (radio/relais + zones en priorité 1).

Des **quick wins** immédiats (viewshed alignment, clamp API relais, unification UI) peuvent être réalisés en **<2h** pour résoudre les bugs les plus visibles.

---

**Document généré le 22 septembre 2026**  
**Audit réalisé par : Agent Cloud Cursor (bc-86ce68c8-be12-5221-9696-b27756233c8b)**  
**Source code version : main (mod UptoDate + API PHP + web tactique)**
