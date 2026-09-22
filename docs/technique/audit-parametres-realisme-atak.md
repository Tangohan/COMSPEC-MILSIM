# Audit complet — paramètres de réalisme ATAK / ATHENA C2

_Date : 2026-09-22 · Source de vérité : code `main` (mod UptoDate, API PHP, web tactique)._

Objectif : inventaire de **tous** les paramètres liés au réalisme (relais, portée, certificats, itinéraires, symbologie, zones, dommages, débit, etc.), avec emplacement, défaut, usage et domaine.

---

## 1. Tableau des paramètres

| Nom | Fichier | Ligne | Description | Valeur défaut | Utilisé ailleurs ? | Domaine |
|-----|---------|-------|-------------|---------------|--------------------|---------|
| `realism` (expérience communauté) | `app/Services/Tactical/AtakExperienceService.php` | 26–32 | Mode réalisme immersion (moins d’aides / alertes confort) | `false` | Oui → sync mod via `fn_applyTenantExperience` / GetExperience | autre (ambiance) |
| `troll` | `app/Services/Tactical/AtakExperienceService.php` | 35–41 | Mode fun / alertes exagérées ; forcé off si `realism` | `false` | Oui → mutuellement exclusif avec `realism` | autre |
| `atak_realism` (expérience) | `app/Services/Tactical/AtakExperienceService.php` | 114–127 | Dommages téléphone ATAK (off/1/2/3/player) | `"player"` | Oui → écrase CBA `comspec_overwatch_atak_realism` | dommages |
| `screen_notifications` | `app/Services/Tactical/AtakExperienceService.php` | 44–51 | Bandeaux info écran | `"player"` | Oui → CBA | autre |
| `vehicle_detail` | `app/Services/Tactical/AtakExperienceService.php` | 54–61 | Orientation/vitesse véhicule carte | `"player"` | Oui → CBA | symbologie carte |
| `require_equipment` | `app/Services/Tactical/AtakExperienceService.php` | 64–71 | Exiger tablette/GPS | `"player"` | Oui → CBA `comspec_overwatch_require_item` | autre |
| `show_opfor` / `show_independent` / `show_civilian` | `app/Services/Tactical/AtakExperienceService.php` | 74–101 | Affichage camps sur Tacmap | `"player"` | Oui → CBA + Extension SimplifyExperience | symbologie carte |
| `sync_map_markers` | `app/Services/Tactical/AtakExperienceService.php` | 104–111 | Repères jeu → poste | `"player"` | Oui → CBA | symbologie carte |
| `radio_proximity` | `app/Services/Tactical/AtakExperienceService.php` | 130–137 | Écoute radio proximité | `"player"` | Oui → CBA | radio/relais |
| `ace_menus` / `order_compose` / `sse_require_item` / `playtime` / `athena_feed` | `app/Services/Tactical/AtakExperienceService.php` | 140–188 | Fonctions mission (menus, ordres, SEEK, playtime, cam) | `"player"` | Oui → CBA | autre |
| `comspec_overwatch_atak_realism` (CBA) | `mod/.../connect/XEH_preInit.sqf` | 950–958 | Niveau dommages physiques ATAK 0–3 | `0` (Désactivé) | Oui → `fn_checkAtakDamage` ; écrasable par tenant | dommages |
| `comspec_overwatch_roleplay_enabled` | `mod/.../connect/XEH_preInit.sqf` | 926–929 | Active dysfonctionnements roleplay | `false` | Oui → zones forcent `true` ; `fn_applyZoneEffects` | radio/relais |
| `comspec_overwatch_roleplay_network_failures` | `mod/.../connect/XEH_preInit.sqf` | 932–935 | Délais / pertes / coupures | `false` | Oui → crash jammer, sim réseau | radio/relais |
| `comspec_overwatch_roleplay_sensor_failures` | `mod/.../connect/XEH_preInit.sqf` | 938–941 | Capteur HR défaillant (web) | `false` | Oui → effets capteurs | autre |
| `comspec_overwatch_roleplay_visual_effects` | `mod/.../connect/XEH_preInit.sqf` | 944–947 | Glitchs / parasites UI | `true` | Oui → overlay device | autre |
| `comspec_overwatch_radio_proximity_enabled` | `mod/.../connect/XEH_preInit.sqf` | 629–632 | Surveillance radio proximité | `true` | Oui → monitor radio net | radio/relais |
| `comspec_overwatch_radio_proximity_radius` | `mod/.../connect/XEH_preInit.sqf` | 635–638 | Rayon écoute radio (m) | `75` (slider 10–300) | Oui → liste proximité | radio/relais |
| `comspec_overwatch_radio_proximity_interval` | `mod/.../connect/XEH_preInit.sqf` | 641–644 | Intervalle scan radio (s) | `2` (1–10) | Oui → tick local | radio/relais |
| `comspec_sse_require_item` | `mod/.../connect/XEH_preInit.sqf` | 967–973 | Terminal SEEK requis pour fiche | `true` | Oui → SSE open | autre |
| Impact choc écran (`_impact * 50`) | `mod/.../connect/functions/fn_checkAtakDamage.sqf` | 29 | Proba écran détruit si realism≥2 | seuil impact `>0.25` ; proba `impact×50%` | Local à check damage | dommages |
| Impact choc extinction (`_impact * 40`) | `mod/.../connect/functions/fn_checkAtakDamage.sqf` | 42 | Proba extinction si realism≥1 | `impact×40%` ; rallumage 20 s | Local | dommages |
| Seuil bras (`_armDamage > 0.65`) | `mod/.../connect/functions/fn_checkAtakDamage.sqf` | 100 | Impossible tenir ATAK | 0.65 ; proba 25 % ; 45 s | Local | dommages |
| Seuil torse niv.1 (`>0.5`, 30 %) | `fn_checkAtakDamage.sqf` | 118–129 | Extinction 30 s | 0.5 / 30 % / 30 s | Local | dommages |
| Seuil torse niv.2 (`>0.7`, 40 %) | `fn_checkAtakDamage.sqf` | 134–146 | Écran détruit | 0.7 / 40 % | Local | dommages |
| Seuil torse niv.3 (`>0.8`, 50 %) | `fn_checkAtakDamage.sqf` | 149–164 | Device détruit + disconnect | 0.8 / 50 % | Local | dommages |
| KAT pneumothorax / SpO2 | `fn_checkAtakDamage.sqf` | 91–96 | Aggrave torse ; SpO2&lt;85 → HR unreliable | 0.75 / 0.85 / SpO2 85 | Local | dommages |
| `COMSPEC_LinkViaRelays` | `fn_pollRoleplayConfig.sqf` | 48–49 | Exige proximité d’un relais live | `false` (`"0"`) | Oui → `fn_canTransmit` L77 | radio/relais |
| `link_via_relays` (DB) | `app/Repositories/TenantAtakConfigRepository.php` | 61, 90–95 | Colonne `atak_link_via_relays` | `false` / `0` | Oui → API roleplay, admin roleplay + server_control | radio/relais |
| `atak_link_via_relays` migration | `bootstrap/atak_overwatch_ops_migration.php` | 59–62 | Schéma SQL | `DEFAULT 0` | Oui | radio/relais |
| `COMSPEC_AtakRelayRange` | `fn_placeAtakRelay.sqf` | 9, 15, 23 | Portée mât (m) | `2000` ; clamp 50–8000 | Oui → `isNearLiveRelay`, `getNearestAtakRelay`, API `range_m` | radio/relais |
| `RangeM` (module Eden) | `modules/module_atak_relay.hpp` | 33–42, 139–144 | Portée module Eden/Zeus | `"2000"` | Oui → `fn_moduleAtakRelay` | radio/relais |
| `range_m` (API/DB) | `app/Repositories/AtakRelayRepository.php` | 31 | Upsert relais | `2000` ; min 50 | Oui → Extension `UpdateRelay`, JS overlay | radio/relays |
| `range_m` table | `bootstrap/atak_overwatch_ops_migration.php` | 46 | Colonne `atak_relays.range_m` | `DEFAULT 2000` | Oui | radio/relais |
| `slots` / `COMSPEC_AtakRelaySlots` | `fn_placeAtakRelay.sqf` | 39–41 ; repo L41 | Places téléphones simultanés | `8` ; clamp 1–64 | Oui → UI Relais AT, fiche web | radio/relais |
| `power_w` / `COMSPEC_AtakRelayPowerW` | `fn_placeAtakRelay.sqf` | 42–43 ; repo L44 | Puissance W (0 si mort) | `25` | Oui → sync API | radio/relais |
| `throughput_mbps` / `COMSPEC_AtakRelayThroughput` | `fn_placeAtakRelay.sqf` | 44–45 ; repo L45 | Débit Mbit/s de base | `12` | Oui → dégradé par distance/dégâts dans `getNearestAtakRelay` | radio/relais |
| `reliability_pct` / `COMSPEC_AtakRelayReliability` | `fn_placeAtakRelay.sqf` | 46–47 ; repo L46 | Fiabilité % | `92` | Oui → API + UI | radio/relais |
| Colonnes fiche relais | `bootstrap/atak_relays_fiche_migration.php` | 23–34 | display_name, identity, ip, gateway, certificate, slots… | slots 8, power 25, thru 12.0, rel 92 | Oui | radio/relais |
| `UpdateRelay` range fallback | `mod/.../COMSPECExtension/Extension.cs` | 7142 | Si arg range absent | `"2000"` | Oui → POST `/api/atak/relays` | radio/relais |
| Dégradation débit vs distance | `fn_getNearestAtakRelay.sqf` | 70–80 | Formule `thru * (0.45+0.55*ratio) * (1-dmg*0.7)` | hardcodé | Local (affiché Relais AT + sync) | radio/relais |
| Zone radius défaut | `fn_createRoleplayZone.sqf` | 17 | Rayon zone roleplay | `100` m | Oui → modules / Zeus | radio/relais |
| Intensité défaut par type | `fn_createRoleplayZone.sqf` | 33–38 | no_coverage 100, interference 50, degraded 30, jammer 80 | selon type | Oui | radio/relais |
| Intensité modules Eden | `modules/module_roleplay_zone.hpp` | 46–263 | Attributs Intensity par type zone | 50 / 100 / 50 / 30 / 80 | Oui → `moduleApplyRoleplayZone` | radio/relais |
| Rayon défaut module apply | `fn_moduleApplyRoleplayZone.sqf` | 20 | Fallback rayon Zeus | `200` ; clamp 5–5000 | Oui | radio/relais |
| Zone portail `zone_radius[]` | `views/admin/atak/roleplay.php` | 269, 307 | Rayon zone admin | template `500` | Oui → `zones_config` JSON | radio/relais |
| `packet_loss_floor` / `tx_drop_chance` / `latency_add` | `fn_applyZoneEffects.sqf` | 56–124 | Effets réseau selon type zone | formules hardcodées (ex. no_cov latency 2000 ms) | Oui → `canTransmit`, `getPacketLossStats` | radio/relais |
| Jammer crash seuil | `fn_applyZoneEffects.sqf` | 104–111 | Gel terminal si jammer fort | intensité≥65 ; cooldown 40 s ; durée 6–20 s | Local | dommages |
| `network_enabled` | `TenantAtakConfigRepository.php` | 39 | Simulation réseau portail | `false` | Oui → API + poll SQF | radio/relais |
| `network_mode` | `TenantAtakConfigRepository.php` | 40 | Mode normal/hostile/degraded/equipment | `'normal'` | Oui → admin + API | radio/relais |
| `latency_min_ms` / `latency_max_ms` | `TenantAtakConfigRepository.php` | 41–42 | Latence simulée portail | `0` / `0` | Oui → admin roleplay | radio/relais |
| `packet_loss_percent` | `TenantAtakConfigRepository.php` | 43 | Pertes paquets % | `0.0` | Oui → poll + stats | radio/relais |
| `disconnect_enabled` | `TenantAtakConfigRepository.php` | 44 | Coupures périodiques portail | `false` | Partiel (portail ; timing ≠ mod) | radio/relais |
| `disconnect_min_sec` / `max` / `interval` | `TenantAtakConfigRepository.php` | 45–47 | Durée/intervalle coupures | `5` / `30` / `600` | Portail ; **pas aligné** sur sim client | radio/relais |
| `sensor_enabled` + % failure/error/missing | `TenantAtakConfigRepository.php` | 50–53 | Capteur cardiaque | `false` / `0` | Oui → web roleplay | autre |
| `zones_enabled` | `TenantAtakConfigRepository.php` | 56 | Zones géo portail | `false` | Oui → sync zones SQF | radio/relais |
| `intel_scramble_enabled` | `TenantAtakConfigRepository.php` | 60 | Données chiffrées / illisibles terrain | `false` | Oui → poll → scramble UI | certificats |
| Sim client `tx_drop_chance` fallback | `fn_canTransmit.sqf` | 63–65 | Drop TX sim locale | `8` | Oui si LinkDegradeSim | radio/relais |
| Sim client loss_floor init | `fn_simulateNetworkDisconnect.sqf` | 20–28 | Plancher pertes + drop pulsés | `12+rand10` puis `10+rand28` | Local sim | radio/relais |
| Sim client durée coupure | `fn_simulateNetworkDisconnect.sqf` | 62 | Durée offline | `4 + random 18` s | **≠** portail 5–30 / 600 | radio/relais |
| Sim client 1ère coupure | `fn_simulateNetworkDisconnect.sqf` | 36 | `next_disconnect_at` | `time + 180 + random 240` | Local | radio/relais |
| `comspec_overwatch_link_degrade_sim` | `fn_linkDegradeSimApplySetting.sqf` | 6–10 | Toggle sim liaison dégradée client | `false` | Oui → settings ATAK + disconnect | radio/relais |
| `automatic_pairing` | `TenantAdminSettingsRepository.php` | 64, 145 | Appairage/cert auto | `true` | Oui → API cert 403 si off ; SQF `auto_pairing` | certificats |
| `certificate_duration_days` | `TenantAdminSettingsRepository.php` | 66, 147 | Durée cert (jours) | `365` ; borne 1–1825 | Oui → issueCertificate + Extension | certificats |
| `minimum_client_version` | `TenantAdminSettingsRepository.php` | 65, 146 | Version client min | `'5.1.8'` | Exposé dans atak_defaults | autre |
| `off_op_position_sharing` | `TenantAdminSettingsRepository.php` | 67, 148 | Partage pos hors OP | `false` | Admin defaults | autre |
| `duration_days` issueCertificate | `AtakRealismRepository.php` | 868–869 | Fallback durée si pas expires_at | `365` | Oui | certificats |
| `certificate_type` défaut | `AtakRealismRepository.php` | 859 | Type cert | `'device'` | Oui | certificats |
| `authority_label` défaut | `AtakRealismRepository.php` | 858 | Autorité | `'Autorité ATAK locale'` | Oui | certificats |
| `status` cert défaut | `AtakRealismRepository.php` | 860 | Statut | `'issued'` | API game force souvent `'active'` | certificats |
| `compromise_state` | `bootstrap/atak_realism_registry_migration.php` | 40 ; repo L1113 | Capture/compromission terminal | `'none'` | Oui → Zeus/ACE + scramble | certificats |
| Domaine crypto défaut | `AtakRealismRepository.php` | 1093–1098 | `FRIENDLY-NET` / Réseau ami | `faction_key=friendly`, `active` | Oui → issue cert | certificats |
| Sync réalisme intervalle | `fn_syncAtakRealism.sqf` | 29–31 | Anti-spam sync terminal/cert | `180` s | Local | certificats |
| `auto_pairing` SQF fallback | `fn_syncAtakRealism.sqf` | 167 | Si API omet le champ | `"1"` | Local | certificats |
| `SimplifyTerminalRealismJson` | `Extension.cs` | 6692–6761 | Relais champs cert/defaults vers SQF | N/A (parseur) | Oui | certificats |
| `session_ttl_sec` (roleplay API) | `AtakApiController.php` | 1844, 1881 | TTL session roleplay côté réponse | `86400` | Poll SQF le lit ; **peu consommé** | autre |
| Waypoint `radius_m` | `migrations/2026_07_27_001_atak_waypoints.sql` | 56 | Rayon franchissement WP | `NULL` | Oui → GetWaypoints / MarkWaypointReached | itinéraires |
| Route `route_type` | même migration | 15 | Type itinéraire | `'PATROL'` | Oui | itinéraires |
| Route `status` | même migration | 21 | Statut route | `'PLANNED'` | Oui | itinéraires |
| Route `visibility_level` | même migration | 27 | Visibilité | `'PUBLIC'` | Oui | itinéraires |
| `radius_m` UI GPS routes | `public/assets/js/atak-gps-routes.js` | 316 | Rayon à la création WP web | `25` | Oui → API waypoints | itinéraires |
| Marker detection `radius_m` | `bootstrap/atak_marker_detection_rules_migration.php` | 30 | Rayon détection marqueur PO | `DEFAULT 20` | Oui → rules API + Overwatch | symbologie carte |
| Viewshed `radius_m` | `bootstrap/atak_viewshed_overlays_migration.php` | 26 | Portée viewshed | `DEFAULT 500` | Oui | couverture |
| Viewshed défaut Athena | `atak_athena/XEH_postInitClient.sqf` | 177–180 | radiusM publish | `500` | Oui | couverture |
| Viewshed GL ops | `public/assets/js/overwatch-gl/OverwatchGlTactics.js` | 86 | Appel viewshed | `radius_m: 800` | **≠** défaut 500 DB | couverture |
| `COMSPEC_AtakPhoneProximityM` | `fn_athena_phoneProximityTick.sqf` | 8 | Alerte proximité téléphone ATAK | `200` m | Local settings | radio/relais |
| Reach overlay `MIN_RADIUS_M` | `public/assets/js/atak-reach-overlay.js` | 6 | Rayon min overlay portée | `25` | Local UI | couverture |
| Rally `RALLY_RADIUS_M` | `public/assets/js/atak-overwatch-beta.js` | 99 | Rayon rally | `50` | Local | itinéraires |
| PO radius | `AtakPoMarker` / overwatch-beta | ~1395 | Rayon PO / détection | `20` | Oui | symbologie carte |
| `AFFILIATION_COLORS` | `public/assets/js/map/TacticalSymbol.js` | 11–16 | Couleurs cadres MIL-STD simplifiés | FRIENDLY/HOSTILE/UNKNOWN/NEUTRAL hex | Oui → MarkerManager C2 | symbologie carte |
| `statusStyle` liaison | `TacticalSymbol.js` | 80–89 | Opacité/dash ONLINE…KIA | ONLINE opaque | Oui | symbologie carte |
| `affiliationKey` mapping | `TacticalSymbol.js` | 18–24 | Mapping factions → affiliation | défaut FRIENDLY | Oui | symbologie carte |
| Drain period Extension | `Extension.cs` | 78, 722, 740 | Flush positions coalescées | clamp 250–2000 ms | Profil réseau SQF | autre |

---

## 2. Incohérences / chevauchements mod ↔ web

| # | Sujet | Côté A | Côté B | Risque |
|---|-------|--------|--------|--------|
| 1 | **Dommages ATAK** | CBA `comspec_overwatch_atak_realism` défaut **0** (mission) | Expérience portail `atak_realism` défaut **`player`** (laisse la mission) | OK si portail reste `player` ; si admin force 1–3, écrase le réglage local sans UI mission claire |
| 2 | **Mode réalisme vs troll** | `realism` et `troll` mutuellement exclusifs côté PHP | CBA roleplay séparé (`roleplay_enabled`, etc.) | Deux « réalismes » : immersion UX (`realism`) ≠ dommages (`atak_realism`) ≠ roleplay réseau — vocabulaire confus |
| 3 | **Coupures réseau** | Portail : intervalle **600 s**, durée **5–30 s** | Client `fn_simulateNetworkDisconnect` : 1ère ~**180–420 s**, durée **4–22 s**, puis **240–600 s** | Doc officielle l’admet : **double pénalité** possible si les deux actifs |
| 4 | **Pertes / drop TX** | Portail `packet_loss_percent` | Zones (formules) + LinkDegradeSim (`tx_drop` ~4–18 %) | Trois moteurs de perte qui s’additionnent |
| 5 | **Exiger relais** | Admin **roleplay.php** ET **server_control.php** écrivent `link_via_relays` | Même colonne DB | Double UI → risque de désync perçue selon l’écran utilisé |
| 6 | **Portée relais** | Eden/SQF/API/DB tous défaut **2000 m** | Clamp SQF 50–8000 ; repo PHP `max(50, range)` sans max 8000 | Valeurs >8000 possibles côté API/DB non clampées comme le mod |
| 7 | **Certificat durée** | Admin `certificate_duration_days` **365** | `issueCertificate` fallback **365** ; régénération terminal force **365** | Cohérent ; mais `COMSPEC_CertDurationDays` n’est qu’affiché (pas re-lu pour renouvellement local) |
| 8 | **Rayon waypoint** | Web GPS crée avec **25 m** | Schéma SQL `radius_m` NULL | Pas de défaut serveur unique |
| 9 | **Viewshed** | DB défaut **500** ; Athena publish **500** | Overwatch GL appelle **800** | Cercles de couverture incohérents selon outil |
| 10 | **Proximité radio vs téléphone** | Radio CBA rayon **75 m** | Téléphone ATAK proximité **200 m** | Deux notions « proximité » distinctes, pas documentées ensemble |
| 11 | **Symbologie** | Web `TacticalSymbol.js` (MIL-STD simplifié) | Mod natif `comspec_atak_native_fnc_symbology` + legacy cTab | Deux pipelines de rendu ; pas un paramètre partagé |
| 12 | **`session_ttl_sec`** | Toujours **86400** hardcodé dans `AtakApiController` | Listé dans poll SQF | Quasi orphelin (pas de config admin) |
| 13 | **Débit / fiabilité relais** | Affichés / sync web | Formules dégradation **hardcodées** dans SQF uniquement | Web ne recalcule pas la même courbe |
| 14 | **Zones Zeus vs portail** | Rayon défaut create **100** / module apply **200** / formulaire portail **500** | Trois defaults selon source | Mission makers confus |

---

## 3. Orphelins / dead-ish

| Élément | Verdict |
|---------|---------|
| `session_ttl_sec` | Exposé API + parsé SQF ; **aucune branche métier** évidente consommant la valeur |
| `COMSPEC_CertDurationDays` | Affiché status panel ; renouvellement suit `atak_defaults` serveur, pas cette var |
| Extracts `mod/Workshop/_extract/*/fn_syncatakrealism.sqf` | Copies d’archive Workshop — **non source de vérité** (UptoDate l’est) |
| `DiscordEventRelayService` | « Relay » Discord, **hors** réalisme radio ATAK |
| Probe `_tmp_marker_probe/iceman/ATAK_WaveRelay` | Prototype / probe, pas pipeline prod Athena |

---

## 4. Cartographie pipeline (rappel)

```
Admin expérience / roleplay / atak_defaults
        ↓
API PHP (AtakExperience / Roleplay / Realism / Relays / Waypoints)
        ↓
Extension.cs (Simplify* / UpdateRelay / GetTerminalRealism / GetWaypoints)
        ↓
SQF (poll + canTransmit + zones + damage + placeAtakRelay)
        ↓
Web TOC (atak-overwatch-ops, TacticalSymbol, gps-routes, roleplay effects)
```

---

## 5. Fichiers audités (traçabilité)

### PHP / bootstrap / migrations / vues
- `/workspace/app/Repositories/AtakRealismRepository.php`
- `/workspace/app/Repositories/AtakRelayRepository.php`
- `/workspace/app/Repositories/TenantAtakConfigRepository.php`
- `/workspace/app/Repositories/TenantAdminSettingsRepository.php`
- `/workspace/app/Repositories/AtakWaypointRepository.php` (réf. radius)
- `/workspace/app/Services/Tactical/AtakExperienceService.php`
- `/workspace/app/Controllers/Api/AtakRealismApiController.php`
- `/workspace/app/Controllers/Api/AtakApiController.php` (roleplay payload)
- `/workspace/app/Controllers/Admin/AdminAtakRoleplayController.php`
- `/workspace/app/Controllers/Admin/AdminOverwatchServerControlController.php`
- `/workspace/app/Controllers/Admin/AdminAtakRealismController.php` (présence)
- `/workspace/bootstrap/atak_realism_registry_migration.php`
- `/workspace/bootstrap/atak_relays_fiche_migration.php`
- `/workspace/bootstrap/atak_overwatch_ops_migration.php`
- `/workspace/bootstrap/atak_marker_detection_rules_migration.php`
- `/workspace/bootstrap/atak_viewshed_overlays_migration.php`
- `/workspace/migrations/2026_07_27_001_atak_waypoints.sql`
- `/workspace/views/admin/atak/roleplay.php`
- `/workspace/views/admin/atak/server_control.php`
- `/workspace/views/admin/atak_realism/index.php`
- `/workspace/views/admin/atak_realism/certificates.php`
- `/workspace/views/admin/atak_realism/logs.php`
- `/workspace/views/admin/organization/atak_marker_detection.php`
- `/workspace/views/atak/mod/guide/_part_realisme.php`

### Extension C#
- `/workspace/mod/UptoDate/COMSPECExtension/Extension.cs` (relais, realism, waypoints, certificates, experience, roleplay)

### Mod SQF / HPP (canon UptoDate)
- `/workspace/mod/UptoDate/Sources/comspec-overwatch-addons/connect/XEH_preInit.sqf`
- `/workspace/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_syncAtakRealism.sqf`
- `/workspace/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_checkAtakDamage.sqf`
- `/workspace/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_canTransmit.sqf`
- `/workspace/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_isNearLiveRelay.sqf`
- `/workspace/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_placeAtakRelay.sqf`
- `/workspace/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_getNearestAtakRelay.sqf`
- `/workspace/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_moduleAtakRelay.sqf`
- `/workspace/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_createRoleplayZone.sqf`
- `/workspace/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_applyZoneEffects.sqf`
- `/workspace/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_pollRoleplayConfig.sqf`
- `/workspace/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_applyTenantExperience.sqf`
- `/workspace/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_simulateNetworkDisconnect.sqf`
- `/workspace/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_linkDegradeSimApplySetting.sqf`
- `/workspace/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_registerAtakCertificate.sqf`
- `/workspace/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_moduleApplyRoleplayZone.sqf`
- `/workspace/mod/UptoDate/Sources/comspec-overwatch-addons/connect/modules/module_atak_relay.hpp`
- `/workspace/mod/UptoDate/Sources/comspec-overwatch-addons/connect/modules/module_roleplay_zone.hpp`
- `/workspace/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updateRelay.sqf`
- `/workspace/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updateStatus.sqf`
- `/workspace/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_phoneProximityTick.sqf`
- `/workspace/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/XEH_postInitClient.sqf`

### Web JS
- `/workspace/public/assets/js/atak-overwatch-ops.js`
- `/workspace/public/assets/js/atak-gps-routes.js`
- `/workspace/public/assets/js/atak-reach-overlay.js`
- `/workspace/public/assets/js/atak-viewshed.js`
- `/workspace/public/assets/js/atak-overwatch-beta.js`
- `/workspace/public/assets/js/map/TacticalSymbol.js`
- `/workspace/public/assets/js/overwatch-gl/OverwatchGlTactics.js`

### Documentation croisée
- `/workspace/mod/UptoDate/docs/realisme-liaison-atak.md`
- `/workspace/docs/technique/analyse-features-realisme-milsim-2026-04.md`
- `/workspace/docs/archive/legacy-atak/MODE-REALISME-COMPLET.md`

### Scannés mais non retenus comme source de vérité
- `/workspace/mod/Workshop/_extract/01-09-2026|03-09-2026|06-09-2026/connect/functions/*` (snapshots Workshop)
- `/workspace/mod/UptoDate/_tmp_marker_probe/**` (probes)

---

## 6. Recommandations synthétiques (hors scope fix)

1. **Registre unique** : une table / JSON `atak_realism_params` avec clés stables consommées par API + Extension + SQF (éviter triples defaults 100/200/500 pour zones).
2. **Aligner coupures** portail ↔ `fn_simulateNetworkDisconnect` (même intervalle/durée ou exclusivité).
3. **Unifier UI** `link_via_relays` (une seule page admin).
4. **Clamp API** `range_m` comme le mod (50–8000).
5. **Clarifier lexique** : `realism` (ambiance) ≠ `atak_realism` (dommages) ≠ roleplay réseau.
