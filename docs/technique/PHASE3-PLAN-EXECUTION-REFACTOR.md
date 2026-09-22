# Phase 3 — Refactor consommateurs : Plan d'exécution détaillé

## 🎯 Objectif

Migrer tous les consommateurs de paramètres réalisme (Extension C#, SQF mod, Web JS) vers la configuration centralisée, en résolvant les 12 incohérences identifiées dans l'audit PR #545.

---

## 📦 Livrables Phase 3

### 1. Extension C# (DLL COMSPEC)

**✅ CRÉÉS (documentation) :**
- `docs/technique/csharp-extension/Extension_RealismConfigMethods.cs` (5 nouvelles méthodes)
- `docs/technique/csharp-extension/GUIDE-INTEGRATION-EXTENSION.md` (guide complet)

**À FAIRE (implémentation réelle) :**
- Intégrer méthodes dans `COMSPECExtension/Extension.cs`
- Router appels `callExtension` vers nouvelles méthodes
- Tester en jeu chaque méthode

**Méthodes ajoutées :**
1. `GetRealismConfig()` : Config complète avec cache 3 min
2. `GetRealismParam(domain, key)` : Paramètre spécifique
3. `ApplyRealismProfile(profileKey)` : Application profil
4. `CalculateWeatherEffects(...)` : Calcul effets météo
5. `SyncRelay(relayDataJson)` : Sync relais API

### 2. Mod Arma 3 (SQF)

**✅ CRÉÉS (fonctions helpers) :**
- `docs/technique/sqf-mod/fn_getRealismParam.sqf` (lecture cache DLL)
- `docs/technique/sqf-mod/fn_applyRealismProfile.sqf` (application profil)
- `docs/technique/sqf-mod/fn_placeRealismRelay.sqf` (pose relais config centralisée)

**À REFACTORER (fonctions existantes) :**

#### a) `fn_syncAtakRealism.sqf`

**Actuel :** Constantes hardcodées
```sqf
#define RELAY_RANGE_DEFAULT 2000
#define CERTIFICATE_DURATION 365
```

**Après :**
```sqf
// Récupérer depuis config centralisée
private _relayRange = ["radio_relays", "relay_range_m", 2000] call ATHENA_fnc_getRealismParam;
private _certDuration = ["certificates", "certificate_duration_days", 365] call ATHENA_fnc_getRealismParam;
```

**Fichier :** `mod/UptoDate/Sources/comspec-overwatch-addons/atak/functions/fn_syncAtakRealism.sqf`

#### b) `fn_checkAtakDamage.sqf`

**Actuel :** Constantes dégâts
```sqf
#define HITS_BEFORE_DESTRUCTION 3
#define CRITICAL_FAILURE_CHANCE 10
```

**Après :**
```sqf
private _hitsMax = ["terminal_damage", "hits_before_destruction", 3] call ATHENA_fnc_getRealismParam;
private _criticalChance = ["terminal_damage", "critical_failure_chance", 10] call ATHENA_fnc_getRealismParam;
```

**Fichier :** `mod/UptoDate/Sources/comspec-overwatch-addons/atak/functions/fn_checkAtakDamage.sqf`

#### c) `fn_canTransmit.sqf`

**Actuel :** Vérification certificat hardcodée
```sqf
if (CERTIFICATE_REQUIRED && !(_player getVariable ["ATAK_hasCertificate", false])) exitWith { false };
```

**Après :**
```sqf
private _certRequired = ["certificates", "certificate_required", true] call ATHENA_fnc_getRealismParam;
if (_certRequired && !(_player getVariable ["ATAK_hasCertificate", false])) exitWith { false };
```

**Fichier :** `mod/UptoDate/Sources/comspec-overwatch-addons/atak/functions/fn_canTransmit.sqf`

#### d) `fn_applyZoneEffects.sqf`

**Actuel :** Facteurs zones hardcodés
```sqf
#define ZONE_RANGE_FACTOR 0.5
#define ZONE_THROUGHPUT_FACTOR 0.3
```

**Après :**
```sqf
private _rangeFactor = ["zones_roleplay", "zone_range_factor", 0.5] call ATHENA_fnc_getRealismParam;
private _throughputFactor = ["zones_roleplay", "zone_throughput_factor", 0.3] call ATHENA_fnc_getRealismParam;
```

**Fichier :** `mod/UptoDate/Sources/comspec-overwatch-addons/roleplay/functions/fn_applyZoneEffects.sqf`

#### e) Intégration météo dans relais

**Nouveau :** Thread météo par relais (si météo activée)

**Fichier à créer :** `fn_updateRelayWeatherEffects.sqf`

```sqf
/**
 * Thread de mise à jour météo pour un relais.
 * Appelé toutes les 60s par fn_placeRealismRelay.
 */

params [["_relay", objNull]];

if (isNull _relay || {!alive _relay}) exitWith {};

private _weatherEnabled = ["radio_relays", "weather_effects_enabled", true] call ATHENA_fnc_getRealismParam;

if (!_weatherEnabled) exitWith {
    _relay setVariable ["ATHENA_relayEffectiveRange", _relay getVariable ["ATHENA_relayRange", 2000], true];
};

// Récupérer météo actuelle
private _rain = rain;
private _fog = fogParams select 0;
private _overcast = overcast;
private _windKmh = windStr * 3.6; // m/s → km/h

private _baseRange = _relay getVariable ["ATHENA_relayRange", 2000];

// Appel DLL
private _response = "COMSPECExtension" callExtension [
    "CalculateWeatherEffects",
    [_baseRange, _rain, _fog, _overcast, _windKmh]
];

_response params ["_result", "_code"];

if (_code isEqualTo 0) then {
    private _effects = parseSimpleArray _result;
    private _effectiveRange = _effects get "effectiveRange";
    private _description = _effects get "description";
    
    // Mettre à jour variable relais
    _relay setVariable ["ATHENA_relayEffectiveRange", _effectiveRange, true];
    _relay setVariable ["ATHENA_weatherDescription", _description, true];
    
    // Log si changement significatif
    private _oldRange = _relay getVariable ["ATHENA_relayEffectiveRangePrev", _baseRange];
    private _diff = abs (_effectiveRange - _oldRange);
    
    if (_diff > 100) then {
        diag_log format [
            "[ATHENA] Relais %1 : portée %2m → %3m (%4)",
            _relay getVariable ["ATHENA_relayUID", "?"],
            round _oldRange,
            round _effectiveRange,
            _description
        ];
    };
    
    _relay setVariable ["ATHENA_relayEffectiveRangePrev", _effectiveRange, false];
} else {
    diag_log format ["[ATHENA] Erreur calcul météo relais : %1", _result];
    _relay setVariable ["ATHENA_relayEffectiveRange", _baseRange, true];
};
```

### 3. Web JavaScript (Tacmap)

**À REFACTORER :**

#### a) `atak-overwatch-ops.js`

**Actuel :** Constantes JS hardcodées
```javascript
const RELAY_RANGE_DEFAULT = 2000;
const VIEWSHED_RADIUS_DEFAULT = 800; // ❌ Incohérence #2 (doit être 500)
```

**Après :**
```javascript
// Utiliser helper AtakRealismConfig (déjà créé)
const config = await AtakRealismConfig.fetch();
const relayRange = config.radio_relays?.relay_range_m || 2000;
const viewshedRadius = config.coverage_viewshed?.viewshed_radius_default_m || 500;
```

**Fichier :** `public/assets/js/atak-overwatch-ops.js`

#### b) `TacticalSymbol.js`

**Actuel :** Symbologie hardcodée
```javascript
const SYMBOLOGY_STANDARD = 'milstd2525d';
const FRIENDLY_COLOR = '#0099ff';
```

**Après :**
```javascript
const config = await AtakRealismConfig.getDomain('symbology_map');
const standard = config?.symbology_standard || 'milstd2525d';
const friendlyColor = config?.friendly_color || '#0099ff';
```

**Fichier :** `public/assets/js/tactical/TacticalSymbol.js`

#### c) `atak-gps-routes.js`

**Actuel :** Simplification hardcodée
```javascript
const SIMPLIFICATION_THRESHOLD = 50; // mètres
const USE_ROAD_NETWORK = false;
```

**Après :**
```javascript
const config = await AtakRealismConfig.getDomain('waypoints');
const simplificationThreshold = config?.simplification_threshold_m || 50;
const useRoadNetwork = config?.use_road_network || false;
```

**Fichier :** `public/assets/js/atak-gps-routes.js`

#### d) `overwatch-gl/OverwatchGlTactics.js`

**✅ DÉJÀ CORRIGÉ Phase 0 :** Viewshed 500m (au lieu de 800m)

**Vérifier cohérence :**
```javascript
const config = await AtakRealismConfig.getDomain('coverage_viewshed');
const viewshedRadius = config?.viewshed_radius_default_m || 500;

// Appel viewshed
this.calculateViewshed(position, viewshedRadius);
```

**Fichier :** `public/assets/js/overwatch-gl/OverwatchGlTactics.js`

---

## ✅ Résolution des 12 incohérences

### Incohérence #1 : Clamp portée relais (50-8000m)

**✅ Résolu Phase 0 :** API + SQF alignés

**Phase 3 :** Vérifier que tous les appels utilisent bien :
```sqf
private _rangeMin = ["radio_relays", "relay_range_min_m", 50] call ATHENA_fnc_getRealismParam;
private _rangeMax = ["radio_relays", "relay_range_max_m", 8000] call ATHENA_fnc_getRealismParam;
private _range = (_inputRange max _rangeMin) min _rangeMax;
```

### Incohérence #2 : Viewshed 500m (unifié)

**✅ Résolu Phase 0 :** `OverwatchGlTactics.js` corrigé de 800m → 500m

**Phase 3 :** Utiliser config centralisée partout :
```javascript
const viewshedRadius = (await AtakRealismConfig.getDomain('coverage_viewshed'))?.viewshed_radius_default_m || 500;
```

### Incohérence #3 : `link_via_relays` unique

**✅ Résolu Phase 0 :** Toggle supprimé de `server_control.php`, gardé dans `roleplay.php` seul

**Phase 3 :** Lecture config centralisée :
```sqf
private _linkViaRelays = ["radio_relays", "link_via_relays", false] call ATHENA_fnc_getRealismParam;
```

### Incohérence #4 : Durée certificat 365j

**Phase 3 :**
```sqf
private _certDuration = ["certificates", "certificate_duration_days", 365] call ATHENA_fnc_getRealismParam;
```

### Incohérence #5 : Zone roleplay 200m

**Phase 3 :**
```sqf
private _zoneRadius = ["zones_roleplay", "zone_default_radius_m", 200] call ATHENA_fnc_getRealismParam;
```

### Incohérence #6 : Simplification waypoints 50m

**Phase 3 :**
```javascript
const threshold = (await AtakRealismConfig.getDomain('waypoints'))?.simplification_threshold_m || 50;
```

### Incohérence #7 : Dommages terminal activés

**Phase 3 :**
```sqf
private _damageEnabled = ["terminal_damage", "terminal_damage_enabled", true] call ATHENA_fnc_getRealismParam;
```

### Incohérence #8 : Symbologie milstd2525d

**Phase 3 :**
```javascript
const standard = (await AtakRealismConfig.getDomain('symbology_map'))?.symbology_standard || 'milstd2525d';
```

### Incohérence #9 : Débit relais 256 kbps

**Phase 3 :**
```sqf
private _throughput = ["radio_relays", "relay_throughput_kbps", 256] call ATHENA_fnc_getRealismParam;
```

### Incohérence #10 : Simulation réseau (warning)

**✅ Résolu Phase 0 :** Warning ajouté dans `roleplay.php`

**Phase 3 :** Vérifier cohérence :
```sqf
private _portalDisconnect = ["network_simulation", "disconnect_enabled", false] call ATHENA_fnc_getRealismParam;
private _clientSim = ["network_simulation", "client_sim_enabled", false] call ATHENA_fnc_getRealismParam;

if (_portalDisconnect && _clientSim) then {
    diag_log "[ATHENA] WARNING: Double coupures réseau activées (portail + client)";
};
```

### Incohérence #11 : Itinéraires `use_road_network`

**Phase 3 :**
```javascript
const useRoads = (await AtakRealismConfig.getDomain('waypoints'))?.use_road_network || false;
```

### Incohérence #12 : Certificat requis true

**Phase 3 :**
```sqf
private _certRequired = ["certificates", "certificate_required", true] call ATHENA_fnc_getRealismParam;
```

---

## 📋 Checklist Phase 3

### Extension C# (DLL)

- [ ] Intégrer `Extension_RealismConfigMethods.cs` dans `Extension.cs`
- [ ] Adapter méthodes helper (`GetApiUrl`, `GetApiKey`, logging)
- [ ] Router 5 nouveaux appels `callExtension`
- [ ] Compiler et tester chaque méthode
- [ ] Vérifier cache fonctionne (TTL 3 min)

### Mod Arma 3 (SQF)

- [ ] Copier helpers depuis `docs/technique/sqf-mod/` vers mod
- [ ] Refactor `fn_syncAtakRealism.sqf`
- [ ] Refactor `fn_checkAtakDamage.sqf`
- [ ] Refactor `fn_canTransmit.sqf`
- [ ] Refactor `fn_applyZoneEffects.sqf`
- [ ] Créer `fn_updateRelayWeatherEffects.sqf`
- [ ] Intégrer thread météo dans `fn_placeRealismRelay.sqf`
- [ ] Supprimer tous `#define` hardcodés
- [ ] Tester en jeu chaque fonction modifiée

### Web JavaScript (Tacmap)

- [ ] Refactor `atak-overwatch-ops.js` (relais, viewshed)
- [ ] Refactor `TacticalSymbol.js` (symbologie)
- [ ] Refactor `atak-gps-routes.js` (itinéraires)
- [ ] Vérifier `OverwatchGlTactics.js` (déjà corrigé Phase 0)
- [ ] Tester chaque page web concernée

### Tests intégration

- [ ] Test relais météo : poser relais, attendre météo change, vérifier portée effective
- [ ] Test profil : appliquer profil Expert, vérifier params changent
- [ ] Test certificat : activer/désactiver, vérifier transmission bloquée/autorisée
- [ ] Test zones : créer zone roleplay, vérifier effets portée/débit
- [ ] Test viewshed : vérifier rayon 500m partout (API, mod, web)

---

## 🚀 Ordre d'exécution recommandé

1. **Extension C# d'abord** (base pour tout le reste)
   - Intégrer méthodes
   - Tester isolation (console Arma)

2. **SQF helpers** (consomment Extension)
   - Copier `fn_getRealismParam` et autres
   - Tester helpers isolation

3. **SQF refactor** (utilisent helpers)
   - Une fonction à la fois
   - Tester après chaque modif

4. **Web JS** (indépendant mod)
   - Utilise API REST directement
   - Testable navigateur seul

5. **Tests intégration complets**
   - Mod + web ensemble
   - Scénarios réalistes

---

## ⏱️ Estimation Phase 3

- **Extension C# :** 1-2j (intégration + tests)
- **SQF refactor :** 2-3j (6 fichiers + tests)
- **Web JS refactor :** 1-2j (4 fichiers + tests)
- **Tests intégration :** 1j
- **Documentation finale :** 0.5j

**Total :** 5.5-8.5 jours (selon expérience équipe)

---

## 📚 Fichiers référence

- Extension C# : `docs/technique/csharp-extension/Extension_RealismConfigMethods.cs`
- Guide intégration : `docs/technique/csharp-extension/GUIDE-INTEGRATION-EXTENSION.md`
- Helpers SQF : `docs/technique/sqf-mod/fn_*.sqf`
- Helper Web : `public/assets/js/atak-realism-config-helper.js`
- Schéma JSON : `config/realism-schema.json`

---

**Phase 3 — Refactor consommateurs : 📝 Plan détaillé prêt**
