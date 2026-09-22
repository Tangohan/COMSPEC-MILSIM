# Phase 3 — Refactor des consommateurs : Guide de migration

## Vue d'ensemble

Cette phase migre tous les consommateurs de configuration réalisme (Extension C#, SQF mod, Web JS) vers la nouvelle source centralisée créée en Phase 1. À la fin de cette phase, **plus aucun code ne doit lire les anciennes colonnes SQL ou constantes hardcodées**.

---

## 1. Extension C# (COMSPECExtension.dll)

### Fichiers à modifier

#### `Extension.cs` (principal)

**Localisation** : `/arma3-mod/comspec-extension/Extension.cs` (hors dépôt Git)

**Modifications** :

1. **Ajouter les nouvelles méthodes documentées dans** `/workspace/docs/technique/csharp-extension/Extension_RealismConfig.cs` :
   - `GetRealismConfig()` : Fetch et cache de la config centralisée
   - `CalculateWeatherEffects()` : Calcul portée effective selon météo

2. **Remplacer les constantes hardcodées** :

   **Avant** :
   ```csharp
   private const int DEFAULT_RELAY_RANGE = 2000;
   private const int DEFAULT_CERT_DURATION = 365;
   private const bool DEFAULT_LINK_VIA_RELAYS = false;
   ```

   **Après** :
   ```csharp
   // Supprimer toutes les constantes, lire depuis GetRealismConfig()
   private async Task<int> GetRelayRange()
   {
       string configJson = await GetRealismConfig();
       using (JsonDocument doc = JsonDocument.Parse(configJson))
       {
           if (doc.RootElement.TryGetProperty("radio_relays", out JsonElement relays) &&
               relays.TryGetProperty("relay_range_m", out JsonElement rangeProp))
           {
               return rangeProp.GetInt32();
           }
           return 2000; // Fallback
       }
   }
   ```

3. **Refactor méthodes impactées** :

   **`UpdateRelay()`** :
   ```csharp
   // Avant : range clamped à 50-10000
   // Après : lire bornes depuis config + appliquer météo
   public async Task<string> UpdateRelay(string uid, float x, float y, float z, float range, bool alive)
   {
       string configJson = await GetRealismConfig();
       // ... lire radio_relays.relay_range_min/max ...
       float clampedRange = Math.Max(minRange, Math.Min(maxRange, range));
       
       // Appliquer météo si activé
       float effectiveRange = clampedRange;
       if (weatherEnabled)
       {
           // ... appeler CalculateWeatherEffects ...
       }
       
       // ... suite API call ...
   }
   ```

   **`GetTerminalRealism()`** :
   ```csharp
   // Avant : retourne hardcodé { "damage_enabled": true, ... }
   // Après : retourne config.terminal_damage depuis GetRealismConfig()
   public async Task<string> GetTerminalRealism()
   {
       string configJson = await GetRealismConfig();
       using (JsonDocument doc = JsonDocument.Parse(configJson))
       {
           if (doc.RootElement.TryGetProperty("terminal_damage", out JsonElement terminalDamage))
           {
               return terminalDamage.GetRawText();
           }
           return "{}";
       }
   }
   ```

   **`RegisterCertificate()`** :
   ```csharp
   // Avant : durée hardcodée 365 jours
   // Après : lire config.certificates.certificate_duration_days
   public async Task<string> RegisterCertificate(string terminalId, string certificateName)
   {
       string configJson = await GetRealismConfig();
       int durationDays = 365; // default
       using (JsonDocument doc = JsonDocument.Parse(configJson))
       {
           if (doc.RootElement.TryGetProperty("certificates", out JsonElement certs) &&
               certs.TryGetProperty("certificate_duration_days", out JsonElement durationProp))
           {
               durationDays = durationProp.GetInt32();
           }
       }
       
       DateTime expiresAt = DateTime.UtcNow.AddDays(durationDays);
       // ... suite API call ...
   }
   ```

---

## 2. Mod Arma 3 (SQF)

### Fichiers à modifier

**Localisation** : `/arma3-mod/addons/athena_c2/functions/` (hors dépôt Git)

#### `fn_syncAtakRealism.sqf`

**Objectif** : Synchroniser périodiquement la config réalisme depuis le serveur.

**Avant** :
```sqf
// Polling des paramètres individuels via plusieurs appels API
_relayRange = "COMSPECExtension" callExtension ["GetRelayRange", []];
_linkViaRelays = "COMSPECExtension" callExtension ["GetLinkViaRelays", []];
// ... 10+ appels séparés ...
```

**Après** :
```sqf
// Un seul appel pour tout récupérer
_config = call ATHENA_fnc_getRealismConfig;

// Accès par clé unifiée
_relayRange = _config getVariable ["radio_relays.relay_range_m", 2000];
_linkViaRelays = _config getVariable ["radio_relays.link_via_relays", false];
_weatherEnabled = _config getVariable ["radio_relays.weather_effects_enabled", true];

// Appliquer météo si activé
if (_weatherEnabled) then {
    [_relayRange] call ATHENA_fnc_calculateWeatherEffects;
};
```

**Références** :
- Helper créé : `/workspace/docs/technique/sqf-helpers/fn_getRealismConfig.sqf`
- Helper météo : `/workspace/docs/technique/sqf-helpers/fn_calculateWeatherEffects.sqf`

---

#### `fn_checkAtakDamage.sqf`

**Objectif** : Vérifier si un terminal ATAK peut être endommagé et appliquer les règles.

**Avant** :
```sqf
// Constantes hardcodées
_damageEnabled = true;
_maxHits = 3;
_repairTime = 300;
_criticalChance = 0.1;
```

**Après** :
```sqf
_config = call ATHENA_fnc_getRealismConfig;

_damageEnabled = _config getVariable ["terminal_damage.terminal_damage_enabled", true];
_maxHits = _config getVariable ["terminal_damage.hits_before_destruction", 3];
_repairTime = _config getVariable ["terminal_damage.repair_time_seconds", 300];
_criticalChance = _config getVariable ["terminal_damage.critical_failure_chance", 0.1];

if (!_damageEnabled) exitWith { false };

// ... logique de dégâts ...
```

---

#### `fn_canTransmit.sqf`

**Objectif** : Vérifier si une transmission ATAK peut passer (relais requis, portée, certificat).

**Avant** :
```sqf
_linkViaRelays = missionNamespace getVariable ["ATHENA_linkViaRelays", false];
_relayRange = 2000;
```

**Après** :
```sqf
_config = call ATHENA_fnc_getRealismConfig;

_linkViaRelays = _config getVariable ["radio_relays.link_via_relays", false];
_relayRange = _config getVariable ["radio_relays.relay_range_m", 2000];
_requireCert = _config getVariable ["certificates.certificate_required", true];

// Appliquer météo
_effectiveRange = _relayRange;
if (_config getVariable ["radio_relays.weather_effects_enabled", true]) then {
    private _weatherEffects = [_relayRange] call ATHENA_fnc_calculateWeatherEffects;
    _effectiveRange = _weatherEffects select 0;
};

// ... logique transmission ...
```

---

#### `fn_placeAtakRelay.sqf`

**Objectif** : Placer un relais radio en jeu (Zeus ou script).

**Avant** :
```sqf
_defaultRange = 2000;
_maxSlots = 10;
```

**Après** :
```sqf
_config = call ATHENA_fnc_getRealismConfig;

_defaultRange = _config getVariable ["radio_relays.relay_range_m", 2000];
_maxSlots = _config getVariable ["radio_relays.max_relay_connections", 10];
_throughput = _config getVariable ["radio_relays.relay_throughput_kbps", 256];

// Création du relais avec valeurs centralisées
```

---

#### `fn_applyZoneEffects.sqf`

**Objectif** : Appliquer les effets d'une zone roleplay (portée réduite, débit limité).

**Avant** :
```sqf
_zoneEnabled = true;
_defaultRadius = 200;
_rangeFactor = 0.5;
```

**Après** :
```sqf
_config = call ATHENA_fnc_getRealismConfig;

_zoneEnabled = _config getVariable ["zones_roleplay.zones_enabled", true];
_defaultRadius = _config getVariable ["zones_roleplay.zone_default_radius_m", 200];
_rangeFactor = _config getVariable ["zones_roleplay.zone_range_factor", 0.5];
_throughputFactor = _config getVariable ["zones_roleplay.zone_throughput_factor", 0.3];

if (!_zoneEnabled) exitWith {};

// ... logique zones ...
```

---

#### Nouveaux helpers à créer

**`fn_getRealismConfig.sqf`** :
- Déjà créé : `/workspace/docs/technique/sqf-helpers/fn_getRealismConfig.sqf`
- À copier dans `/arma3-mod/addons/athena_c2/functions/`

**`fn_calculateWeatherEffects.sqf`** :
- Déjà créé : `/workspace/docs/technique/sqf-helpers/fn_calculateWeatherEffects.sqf`
- À copier dans `/arma3-mod/addons/athena_c2/functions/`

---

## 3. Web JS (Tacmap + Overwatch)

### Fichiers à modifier

**Localisation** : `/workspace/public/assets/js/`

#### `atak-overwatch-ops.js`

**Objectif** : Gestion des opérations tactiques (relais, terminaux, zones).

**Avant** :
```javascript
const DEFAULT_RELAY_RANGE = 2000;
const LINK_VIA_RELAYS = false;
```

**Après** :
```javascript
// Charger config au démarrage
await AtakRealismConfig.fetch();

// Accès aux valeurs
const relayRange = (await AtakRealismConfig.getDomain('radio_relays')).relay_range_m || 2000;
const linkViaRelays = (await AtakRealismConfig.getDomain('radio_relays')).link_via_relays || false;
```

**Modifications** :

1. **Fonction `updateRelayMarkers()`** :
   ```javascript
   async function updateRelayMarkers(relays) {
       const config = await AtakRealismConfig.getDomain('radio_relays');
       const weatherEnabled = config.weather_effects_enabled || false;
       
       relays.forEach(relay => {
           let displayRange = relay.range_m;
           
           // Appliquer météo si activé
           if (weatherEnabled && window.currentWeather) {
               const effects = await AtakRealismConfig.calculateWeatherEffects(
                   relay.range_m,
                   window.currentWeather
               );
               displayRange = effects.effectiveRange;
               
               // Afficher tooltip avec description météo
               relay.weatherDescription = effects.description;
           }
           
           // Dessiner cercle de portée
           drawRelayCircle(relay.position, displayRange);
       });
   }
   ```

2. **Fonction `checkTransmissionPossible()`** :
   ```javascript
   async function checkTransmissionPossible(from, to) {
       const config = await AtakRealismConfig.getDomain('radio_relays');
       
       if (!config.link_via_relays) {
           return true; // Pas de contrainte relais
       }
       
       // Trouver relais entre from et to
       const relaysInRange = findRelaysBetween(from, to, config.relay_range_m);
       return relaysInRange.length > 0;
   }
   ```

---

#### `TacticalSymbol.js`

**Objectif** : Affichage des symboles tactiques sur la carte.

**Avant** :
```javascript
const WAYPOINT_SIMPLIFY_THRESHOLD_M = 50;
const SHOW_WAYPOINT_NAMES = true;
```

**Après** :
```javascript
const waypointConfig = await AtakRealismConfig.getDomain('waypoints');

const simplifyThreshold = waypointConfig.simplification_threshold_m || 50;
const showNames = waypointConfig.show_waypoint_names !== false;
const autoCalculate = waypointConfig.auto_calculate_routes || false;
```

**Modifications** :

1. **Fonction `renderWaypoints()`** :
   ```javascript
   async function renderWaypoints(waypoints) {
       const config = await AtakRealismConfig.getDomain('waypoints');
       
       if (config.auto_calculate_routes && config.use_road_network) {
           // Calculer itinéraire sur réseau routier
           waypoints = await calculateRoadRoute(waypoints);
       }
       
       if (config.simplification_threshold_m > 0) {
           // Simplifier tracé
           waypoints = simplifyPath(waypoints, config.simplification_threshold_m);
       }
       
       // ... render ...
   }
   ```

---

#### `atak-gps-routes.js`

**Objectif** : Calcul et affichage des itinéraires GPS.

**Avant** :
```javascript
const USE_ROAD_NETWORK = false;
const SNAP_TO_ROADS = false;
```

**Après** :
```javascript
const routeConfig = await AtakRealismConfig.getDomain('waypoints');

const useRoadNetwork = routeConfig.use_road_network || false;
const snapToRoads = routeConfig.snap_to_roads || false;
const detectTowns = routeConfig.detect_town_names || false;
```

---

#### `overwatch-gl/OverwatchGlTactics.js`

**Objectif** : Rendu 3D WebGL des éléments tactiques.

**Avant** :
```javascript
call('/api/atak/terrain/viewshed', {
    method: 'POST',
    body: { mapId: apiOw.mapId, observer: observer, radius_m: 500 }
});
```

**Après** :
```javascript
const coverageConfig = await AtakRealismConfig.getDomain('coverage_viewshed');
const viewshedRadius = coverageConfig.viewshed_radius_default_m || 500;

call('/api/atak/terrain/viewshed', {
    method: 'POST',
    body: { mapId: apiOw.mapId, observer: observer, radius_m: viewshedRadius }
});
```

---

#### Inclure le helper JS dans les pages

**Fichiers à modifier** :
- `/workspace/views/layouts/tacmap_base.php`
- `/workspace/views/layouts/overwatch_base.php`

**Ajout** :
```php
<script src="<?= $h(url('/assets/js/atak-realism-config-helper.js')) ?>"></script>
```

---

## 4. Controllers PHP (API)

### Fichiers à modifier

#### `AtakRelayApiController.php`

**Objectif** : Gérer les relais via API (UPDATE, DELETE).

**Avant** :
```php
$range = max(50, min(8000, $range));
```

**Après** :
```php
$realismConfig = $this->realismConfigRepo->getActiveConfig($tenantId);
$configJson = json_decode($realismConfig['config_json'], true);
$radioConfig = $configJson['radio_relays'] ?? [];

$minRange = $radioConfig['relay_range_min_m'] ?? 50;
$maxRange = $radioConfig['relay_range_max_m'] ?? 8000;

$range = max($minRange, min($maxRange, $range));

// Appliquer météo si activé
if (!empty($radioConfig['weather_effects_enabled'])) {
    $weatherService = new AtakWeatherEffectsService($this->realismConfigRepo);
    $weather = $this->getCurrentWeather($tenantId); // À implémenter
    $effects = $weatherService->calculateEffectiveRange($tenantId, $range, $weather);
    $range = $effects['effective_range'];
}
```

---

#### `AtakTerminalApiController.php`

**Objectif** : Gérer les terminaux ATAK (dégâts, réparation).

**Avant** :
```php
$damageEnabled = true;
$maxHits = 3;
```

**Après** :
```php
$realismConfig = $this->realismConfigRepo->getActiveConfig($tenantId);
$configJson = json_decode($realismConfig['config_json'], true);
$damageConfig = $configJson['terminal_damage'] ?? [];

$damageEnabled = $damageConfig['terminal_damage_enabled'] ?? true;
$maxHits = $damageConfig['hits_before_destruction'] ?? 3;
```

---

## 5. Résolution des 12 incohérences

### Incohérence #1 : Portée relais (50-8000m vs 50-10000m)
**Résolution** : Valeur unique centralisée `relay_range_min_m: 50`, `relay_range_max_m: 8000`.
**Fichiers** : Extension C#, SQF, PHP (AtakRelayRepository, AtakRelayApiController).

### Incohérence #2 : Viewshed radius (500m DB, 800m JS)
**Résolution** : ✅ Déjà fait en Phase 0 (OverwatchGlTactics.js → 500m).

### Incohérence #3 : `link_via_relays` dupliqué
**Résolution** : ✅ Déjà fait en Phase 0 (supprimé de server_control.php).

### Incohérence #4 : Durée certificat (365j vs 180j)
**Résolution** : Valeur unique `certificate_duration_days: 365`.
**Fichiers** : Extension C#, SQF.

### Incohérence #5 : Zone roleplay radius (200m vs 500m)
**Résolution** : Valeur unique `zone_default_radius_m: 200`.
**Fichiers** : SQF (fn_applyZoneEffects).

### Incohérence #6 : Simplification waypoints (50m vs 100m)
**Résolution** : Valeur unique `simplification_threshold_m: 50`.
**Fichiers** : atak-gps-routes.js, TacticalSymbol.js.

### Incohérence #7 : Dommages terminal activés/désactivés selon code
**Résolution** : Valeur unique `terminal_damage_enabled: true`.
**Fichiers** : Extension C#, SQF (fn_checkAtakDamage).

### Incohérence #8 : Symbologie (MIL-STD-2525D vs APP-6)
**Résolution** : Valeur unique `symbology_standard: 'milstd2525d'`.
**Fichiers** : TacticalSymbol.js, SQF (affichage markers).

### Incohérence #9 : Débit relais (256 kbps vs 512 kbps)
**Résolution** : Valeur unique `relay_throughput_kbps: 256`.
**Fichiers** : Extension C#, SQF.

### Incohérence #10 : Simulation réseau (client vs portail)
**Résolution** : ✅ Warning ajouté en Phase 0 (roleplay.php).

### Incohérence #11 : Calcul itinéraires (réseau routier vs ligne droite)
**Résolution** : Valeur unique `use_road_network: false` (false par défaut, activable).
**Fichiers** : atak-gps-routes.js.

### Incohérence #12 : Certificat requis ou optionnel
**Résolution** : Valeur unique `certificate_required: true`.
**Fichiers** : Extension C#, SQF (fn_canTransmit).

---

## 6. Checklist de migration

### Extension C# (COMSPECExtension.dll)

- [ ] Ajouter `GetRealismConfig()` et cache
- [ ] Ajouter `CalculateWeatherEffects()`
- [ ] Refactor `UpdateRelay()` → lire bornes + météo
- [ ] Refactor `GetTerminalRealism()` → lire config centralisée
- [ ] Refactor `RegisterCertificate()` → lire durée
- [ ] Supprimer toutes les constantes hardcodées

### Mod Arma 3 (SQF)

- [ ] Copier `fn_getRealismConfig.sqf` dans mod
- [ ] Copier `fn_calculateWeatherEffects.sqf` dans mod
- [ ] Refactor `fn_syncAtakRealism.sqf` → appel unique config
- [ ] Refactor `fn_checkAtakDamage.sqf` → lire config
- [ ] Refactor `fn_canTransmit.sqf` → lire config + météo
- [ ] Refactor `fn_placeAtakRelay.sqf` → lire config
- [ ] Refactor `fn_applyZoneEffects.sqf` → lire config
- [ ] Supprimer toutes les constantes hardcodées

### Web JS

- [ ] Inclure `atak-realism-config-helper.js` dans layouts
- [ ] Refactor `atak-overwatch-ops.js` → lire config + météo
- [ ] Refactor `TacticalSymbol.js` → lire config waypoints/symbologie
- [ ] Refactor `atak-gps-routes.js` → lire config itinéraires
- [ ] Refactor `OverwatchGlTactics.js` → lire viewshed radius
- [ ] Supprimer toutes les constantes hardcodées

### Controllers PHP (API)

- [ ] Refactor `AtakRelayApiController.php` → lire config + météo
- [ ] Refactor `AtakTerminalApiController.php` → lire config dégâts
- [ ] Refactor `AtakCertificatesApiController.php` → lire config durée

### Services PHP

- [ ] `AtakWeatherEffectsService.php` déjà créé ✅
- [ ] Intégrer dans API relais (fetch météo actuelle)

---

## 7. Tests après migration

### Tests unitaires (à ajouter)

- Extension C# : Test `GetRealismConfig()` retourne JSON valide
- Extension C# : Test `CalculateWeatherEffects()` avec différentes météos
- PHP : Test `AtakWeatherEffectsService::calculateEffectiveRange()`
- JS : Test `AtakRealismConfig.fetch()` et cache

### Tests E2E (manuel)

1. **Relais** :
   - Placer un relais en jeu (Zeus)
   - Vérifier portée respecte config centralisée
   - Changer météo → vérifier portée effective change
   - Vérifier synchronisation web Tacmap

2. **Certificats** :
   - Créer un certificat côté mod
   - Vérifier expiration = `certificate_duration_days` de la config
   - Modifier durée dans admin → vérifier nouveau certificat utilise nouvelle durée

3. **Dommages terminal** :
   - Tirer sur un terminal ATAK
   - Vérifier `hits_before_destruction` respecté
   - Désactiver dommages dans admin → vérifier invulnérabilité

4. **Zones roleplay** :
   - Entrer dans une zone
   - Vérifier portée/débit réduits selon config
   - Modifier facteurs dans admin → vérifier effets changent

5. **Itinéraires** :
   - Tracer un itinéraire GPS
   - Vérifier simplification selon seuil config
   - Activer réseau routier → vérifier snap to roads

---

## 8. Rollback si problème

Si la migration pose problème :

1. **Ne pas merger la PR Phase 3**
2. **Revenir à la branche précédente** (Phase 2 complétée)
3. **Identifier le problème** (logs Extension C#, logs SQF, console JS)
4. **Fix puis retry**

**Branches Git recommandées** :
- `main` : Phase 2 complétée (config centralisée fonctionnelle mais pas encore consommée)
- `phase3-refactor` : Migration en cours (cette PR)

---

## 9. Documentation finale

Après migration complète :

- [ ] Mettre à jour README principal avec nouvelle architecture
- [ ] Documenter l'API `/api/atak/realism/config` (format JSON, cache)
- [ ] Guide utilisateur admin (comment config impacte le jeu)
- [ ] Guide développeur (comment ajouter un nouveau paramètre réalisme)

---

## Estimation

**Durée totale Phase 3** : 5-7 jours de développement

- Extension C# : 2j
- Mod SQF : 2j
- Web JS : 1.5j
- Tests E2E : 1j
- Doc + fixes : 0.5j

**Dépendances critiques** :
- Phase 1 (DB + API) doit être complétée ✅
- Phase 2 (Admin UI) doit être complétée ✅
- Environnement de test Arma 3 fonctionnel (serveur + mod COMSPEC)
