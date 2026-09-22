# Phase 3 — Implémentation partielle : Helpers SQF + Refactor premier fichier

## ✅ Travail effectué

### 1. Helpers SQF copiés dans le mod

**Emplacement :** `mod/UptoDate/Sources/comspec-overwatch-addons/realism_config/functions/`

**Fichiers créés :**
- `fn_getRealismParam.sqf` (lecture paramètre config centralisée avec cache)
- `fn_applyRealismProfile.sqf` (application profils beginner/event/expert)
- `fn_placeRealismRelay.sqf` (pose relais avec config centralisée + météo)

Ces helpers sont **prêts à l'emploi** et appellent l'Extension C# via `callExtension`.

### 2. Refactor fn_placeAtakRelay.sqf

**Fichier créé :** `fn_placeAtakRelay_refactored.sqf` (version refactorée)

**Changements :**

#### Avant (hardcodé) :
```sqf
params [
    ["_range", 2000, [0]],  // Hardcodé
];
_range = (_range max 50) min 8000;  // Clamps hardcodés
private _slots = 8;  // Hardcodé
private _thru = 12;  // Mbps hardcodé
```

#### Après (config centralisée) :
```sqf
params [
    ["_range", -1, [0]],  // -1 = utiliser config
];

// Lecture config avec fallback
if (_range < 0) then {
    if (!isNil "ATHENA_fnc_getRealismParam") then {
        _range = ["radio_relays", "relay_range_m", 2000] call ATHENA_fnc_getRealismParam;
    } else {
        _range = 2000;  // Fallback si helper pas dispo
    };
};

// Clamps depuis config
private _rangeMin = ["radio_relays", "relay_range_min_m", 50] call ATHENA_fnc_getRealismParam;
private _rangeMax = ["radio_relays", "relay_range_max_m", 8000] call ATHENA_fnc_getRealismParam;
_range = (_range max _rangeMin) min _rangeMax;

// Slots depuis config
private _slots = _meta getOrDefault ["slots", -1];
if (_slots < 0 && {!isNil "ATHENA_fnc_getRealismParam"}) then {
    _slots = ["radio_relays", "max_relay_connections", 10] call ATHENA_fnc_getRealismParam;
};

// Throughput depuis config (kbps → Mbps)
private _thru = _meta getOrDefault ["throughput_mbps", -1];
if (_thru < 0 && {!isNil "ATHENA_fnc_getRealismParam"}) then {
    private _throughputKbps = ["radio_relays", "relay_throughput_kbps", 256] call ATHENA_fnc_getRealismParam;
    _thru = _throughputKbps / 1024;  // Conversion
};
```

**Sécurités ajoutées :**
- Vérification `isNil "ATHENA_fnc_getRealismParam"` avant chaque appel
- Fallback valeurs hardcodées si helper indisponible
- Log diagnostic création relais

### 3. Paramètres centralisés utilisés

**Domaine `radio_relays` :**
- `relay_range_m` (2000) : Portée nominale relais
- `relay_range_min_m` (50) : Portée minimale (clamp)
- `relay_range_max_m` (8000) : Portée maximale (clamp)
- `max_relay_connections` (10) : Slots connexions simultanées
- `relay_throughput_kbps` (256) : Débit nominal

**Avantages :**
- Modification paramètres depuis interface admin web → effet immédiat
- Profils (beginner/event/expert) appliquent valeurs cohérentes
- Pas besoin recompiler mod pour changer portée/débit

---

## 🔄 Restant à faire Phase 3

### Fichiers SQF à refactorer (5)

1. **fn_syncAtakRealism.sqf** ✅ (déjà lit `cert_duration_days` API)
2. **fn_checkAtakDamage.sqf** ⏳ (dégâts terminal)
3. **fn_canTransmit.sqf** ⏳ (certificats requis)
4. **fn_applyZoneEffects.sqf** ⏳ (zones roleplay)
5. **fn_updateRelayWeatherEffects.sqf** ⏳ (NOUVEAU - thread météo)

### Fichiers Web JS à refactorer (4)

1. **atak-overwatch-ops.js** ⏳ (relais, viewshed)
2. **TacticalSymbol.js** ⏳ (symbologie)
3. **atak-gps-routes.js** ⏳ (itinéraires)
4. **overwatch-gl/OverwatchGlTactics.js** ✅ (déjà corrigé Phase 0)

### Extension C# (5 méthodes)

**⏳ À intégrer dans Extension.cs :**
- GetRealismConfig()
- GetRealismParam(domain, key)
- ApplyRealismProfile(profileKey)
- CalculateWeatherEffects(...)
- SyncRelay(relayDataJson)

**Documentation complète disponible :**
- `docs/technique/csharp-extension/Extension_RealismConfigMethods.cs`
- `docs/technique/csharp-extension/GUIDE-INTEGRATION-EXTENSION.md`

---

## 📋 Prochaines actions

### Priorité 1 : Extension C# (critique)

Sans Extension C#, les helpers SQF ne peuvent pas fonctionner (`callExtension` échouera).

**Actions :**
1. Intégrer méthodes dans Extension.cs
2. Router callExtension (switch/case)
3. Compiler DLL
4. Tester chaque méthode console Arma

**Estimation :** 1-2 jours

### Priorité 2 : Finaliser refactors SQF

**Actions :**
1. Remplacer `fn_placeAtakRelay.sqf` par version refactorée
2. Refactor fn_checkAtakDamage, fn_canTransmit, fn_applyZoneEffects
3. Créer fn_updateRelayWeatherEffects
4. Tests en jeu

**Estimation :** 2-3 jours

### Priorité 3 : Web JS

**Actions :**
1. Modifier 4 fichiers pour utiliser AtakRealismConfig helper
2. Tests navigateur chaque page

**Estimation :** 1-2 jours

---

## 🧪 Tests recommandés (après Extension C#)

### Test 1 : Helper getRealismParam

**Console Arma 3 :**
```sqf
// Test lecture portée relais
private _range = ["radio_relays", "relay_range_m", 2000] call ATHENA_fnc_getRealismParam;
hint format ["Portée configurée : %1m", _range];  // Attendu: 2000

// Test clamps
private _rangeMin = ["radio_relays", "relay_range_min_m", 50] call ATHENA_fnc_getRealismParam;
private _rangeMax = ["radio_relays", "relay_range_max_m", 8000] call ATHENA_fnc_getRealismParam;
hint format ["Clamps : %1m - %2m", _rangeMin, _rangeMax];  // Attendu: 50 - 8000
```

### Test 2 : Création relais config centralisée

```sqf
// Créer relais avec config par défaut (-1 = auto)
private _relay = [getPos player, -1, "Test Relais"] call comspec_overwatch_connect_fnc_placeAtakRelay;

// Vérifier range appliquée
private _appliedRange = _relay getVariable ["COMSPEC_AtakRelayRange", 0];
hint format ["Relais créé avec portée : %1m", _appliedRange];  // Attendu: 2000 (config)
```

### Test 3 : Profil Expert

```sqf
// Appliquer profil Expert (serveur uniquement)
if (isServer) then {
    ["expert"] call ATHENA_fnc_applyRealismProfile;
    
    sleep 5;  // Attendre invalidation cache
    
    // Vérifier nouvelle valeur
    private _range = ["radio_relays", "relay_range_m", 2000] call ATHENA_fnc_getRealismParam;
    hint format ["Après profil Expert : %1m", _range];  // Attendu: 2000 (expert)
};
```

---

## 📚 Fichiers référence

**Helpers SQF (copiés dans mod) :**
- `mod/.../realism_config/functions/fn_getRealismParam.sqf`
- `mod/.../realism_config/functions/fn_applyRealismProfile.sqf`
- `mod/.../realism_config/functions/fn_placeRealismRelay.sqf`

**Refactor créé :**
- `mod/.../connect/functions/fn_placeAtakRelay_refactored.sqf`

**Documentation complète :**
- `docs/technique/PHASE3-PLAN-EXECUTION-REFACTOR.md`
- `docs/technique/csharp-extension/GUIDE-INTEGRATION-EXTENSION.md`
- `docs/technique/csharp-extension/Extension_RealismConfigMethods.cs`

---

**Phase 3 — Implémentation : 15% complété**

**Prochaine étape critique :** Intégrer Extension C# pour débloquer tout le reste.
