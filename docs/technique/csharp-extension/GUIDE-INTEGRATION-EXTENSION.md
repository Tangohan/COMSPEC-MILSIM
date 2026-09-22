# Guide d'intégration — Méthodes réalisme Extension C#

## 📋 Vue d'ensemble

Ce guide détaille l'intégration des nouvelles méthodes de configuration réalisme dans l'extension COMSPEC existante.

**Fichier source :** `docs/technique/csharp-extension/Extension_RealismConfigMethods.cs`

**À intégrer dans :** `COMSPECExtension/Extension.cs` (ou classe partielle séparée)

---

## 🔧 Méthodes ajoutées (5)

### 1. `GetRealismConfig()`

**Signature :**
```csharp
public async Task<string> GetRealismConfig()
```

**Appelé depuis SQF :**
```sqf
private _response = "COMSPECExtension" callExtension ["GetRealismConfig", []];
_response params ["_result", "_code"];
// _result = JSON string config complète
// _code = 0 si succès, 1 si erreur
```

**Fonctionnalités :**
- Récupère config depuis `GET /api/atak/realism/config`
- Cache 3 minutes (TTL configurable)
- Retourne JSON complet avec 11 domaines
- Gestion erreurs HTTP/parsing

**Cache :**
```csharp
private static string _realismConfigCache = null;
private static DateTime _realismConfigCacheTime = DateTime.MinValue;
private static readonly TimeSpan _realismConfigCacheTTL = TimeSpan.FromMinutes(3);
```

**Exemple retour :**
```json
{
  "radio_relays": {
    "relay_range_m": 2000,
    "relay_throughput_kbps": 256,
    "weather_effects_enabled": true,
    ...
  },
  "certificates": { ... },
  ...
}
```

---

### 2. `GetRealismParam(domain, key)`

**Signature :**
```csharp
public async Task<string> GetRealismParam(string domain, string key)
```

**Appelé depuis SQF :**
```sqf
private _response = "COMSPECExtension" callExtension ["GetRealismParam", ["radio_relays", "relay_range_m"]];
_response params ["_result", "_code"];
// _result = "2000" (JSON value)
```

**Fonctionnalités :**
- Récupère paramètre spécifique via `GetRealismConfig()` (utilise cache)
- Navigation JSON : `config[domain][key]`
- Retourne valeur brute (number, string, bool, array...)
- Gestion erreurs domain/key introuvables

**Cas d'usage :**
Optimisé pour récupération paramètre unique sans parser tout le JSON côté SQF.

---

### 3. `ApplyRealismProfile(profileKey)`

**Signature :**
```csharp
public async Task<string> ApplyRealismProfile(string profileKey)
```

**Appelé depuis SQF (serveur uniquement) :**
```sqf
private _response = "COMSPECExtension" callExtension ["ApplyRealismProfile", ["expert"]];
_response params ["_result", "_code"];
// _result = { "ok": true, "message": "Profil appliqué" }
```

**Fonctionnalités :**
- POST vers `API /api/atak/realism/apply-profile`
- Payload : `{ "profile": "beginner|event|expert" }`
- Invalide cache immédiatement (force reload)
- Nécessite auth API (X-API-Key)

**Profils disponibles :**
- `beginner` : Arcade (relais 5000m, pas certificats)
- `event` : Équilibré (relais 3000m)
- `expert` : Hardcore (relais 2000m, tout activé)

---

### 4. `CalculateWeatherEffects(baseRange, rain, fog, overcast, windKmh)`

**Signature :**
```csharp
public async Task<string> CalculateWeatherEffects(
    float baseRange, 
    float rain, 
    float fog, 
    float overcast, 
    float windKmh)
```

**Appelé depuis SQF :**
```sqf
// Récupérer météo Arma
private _rain = rain;         // 0.0 - 1.0
private _fog = fogParams select 0;  // 0.0 - 1.0
private _overcast = overcast; // 0.0 - 1.0
private _wind = windStr;      // m/s → convertir en km/h

private _windKmh = _wind * 3.6;
private _baseRange = 2000;

private _response = "COMSPECExtension" callExtension [
    "CalculateWeatherEffects", 
    [_baseRange, _rain, _fog, _overcast, _windKmh]
];

_response params ["_result", "_code"];
private _effects = parseSimpleArray _result;
// _effects = { effectiveRange, weatherMultiplier, windMultiplier, description }
```

**Fonctionnalités :**
- Lit config `radio_relays` (multiplicateurs météo)
- Calcule portée effective selon :
  - **Pluie** : >0.7 = orage (-40%), >0.3 = pluie (-15%)
  - **Brouillard** : >0.3 = -30%
  - **Vent** : >50 km/h = -5% par 10 km/h supplémentaires
- Combine multiplicateurs : `effectiveRange = baseRange * weatherMult * windMult`
- Retourne description lisible : "Pluie, Vent modéré (60 km/h) (-22%)"

**Seuils configurables :**
```json
{
  "rain_range_multiplier": 0.85,
  "fog_range_multiplier": 0.70,
  "storm_range_multiplier": 0.60,
  "wind_threshold_kmh": 50,
  "wind_range_penalty_per_10kmh": 0.05
}
```

**Exemple retour :**
```json
{
  "effectiveRange": 1530,
  "weatherMultiplier": 0.85,
  "windMultiplier": 0.90,
  "description": "Pluie, Vent modéré (60 km/h) (-23%)"
}
```

**Fallback :**
Si config indisponible, utilise valeurs par défaut hardcodées dans `CalculateWeatherEffectsDefault()`.

---

### 5. `SyncRelay(relayDataJson)`

**Signature :**
```csharp
public async Task<string> SyncRelay(string relayDataJson)
```

**Appelé depuis SQF (lors pose relais) :**
```sqf
private _relayData = [
    ["uid", "RELAY_12345"],
    ["position", getPos _relay],
    ["range_m", 2000],
    ["throughput_kbps", 256],
    ["owner_uid", getPlayerUID player],
    ["owner_name", name player],
    ["created_at", systemTime]
];

private _json = str _relayData; // Convertir en JSON
private _response = "COMSPECExtension" callExtension ["SyncRelay", [_json]];
_response params ["_result", "_code"];
// _result = { "ok": true, "relayId": 42 }
```

**Fonctionnalités :**
- POST vers `API /api/atak/relays/sync`
- Crée/met à jour relais dans DB ATHENA
- Retourne ID relais pour tracking
- Visible immédiatement sur Tacmap web

**Payload JSON attendu :**
```json
{
  "uid": "RELAY_12345",
  "position": [1234.5, 5678.9, 42.0],
  "range_m": 2000,
  "throughput_kbps": 256,
  "max_connections": 10,
  "owner_uid": "76561198012345678",
  "owner_name": "Joueur1",
  "certificate_required": true,
  "weather_affected": true,
  "created_at": [2026, 9, 22, 11, 30, 0]
}
```

---

## 🔌 Intégration dans Extension.cs

### Option 1 : Classe partielle (recommandé)

**Fichier :** `Extension.cs` (existant)
```csharp
namespace COMSPECExtension
{
    public partial class Extension
    {
        // Méthodes existantes...
        public string CallExtension(string function, string[] args)
        {
            // Router existant
        }
    }
}
```

**Fichier :** `Extension_RealismConfig.cs` (nouveau)
```csharp
namespace COMSPECExtension
{
    public partial class Extension
    {
        // Coller ici le contenu de Extension_RealismConfigMethods.cs
    }
}
```

### Option 2 : Intégration directe

Copier tout le contenu de `Extension_RealismConfigMethods.cs` dans `Extension.cs` existant.

---

## 🎯 Router les appels callExtension

Modifier le router existant pour exposer les nouvelles méthodes :

```csharp
public string CallExtension(string function, string[] args)
{
    try
    {
        switch (function)
        {
            // Méthodes existantes...
            case "Ping":
                return Ping();
            
            // Nouvelles méthodes réalisme
            case "GetRealismConfig":
                return GetRealismConfig().GetAwaiter().GetResult();
            
            case "GetRealismParam":
                if (args.Length < 2) return JsonError("Missing arguments: domain, key");
                return GetRealismParam(args[0], args[1]).GetAwaiter().GetResult();
            
            case "ApplyRealismProfile":
                if (args.Length < 1) return JsonError("Missing argument: profileKey");
                return ApplyRealismProfile(args[0]).GetAwaiter().GetResult();
            
            case "CalculateWeatherEffects":
                if (args.Length < 5) return JsonError("Missing arguments: baseRange, rain, fog, overcast, windKmh");
                float baseRange = float.Parse(args[0]);
                float rain = float.Parse(args[1]);
                float fog = float.Parse(args[2]);
                float overcast = float.Parse(args[3]);
                float windKmh = float.Parse(args[4]);
                return CalculateWeatherEffects(baseRange, rain, fog, overcast, windKmh).GetAwaiter().GetResult();
            
            case "SyncRelay":
                if (args.Length < 1) return JsonError("Missing argument: relayDataJson");
                return SyncRelay(args[0]).GetAwaiter().GetResult();
            
            default:
                return JsonError($"Unknown function: {function}");
        }
    }
    catch (Exception ex)
    {
        LogError($"CallExtension error: {ex.Message}");
        return JsonError(ex.Message);
    }
}

private string JsonError(string message)
{
    return System.Text.Json.JsonSerializer.Serialize(new { error = message });
}
```

---

## ⚙️ Adapter méthodes helper

Les méthodes helper doivent être adaptées selon structure Extension existante :

### `GetApiUrl()` et `GetApiKey()`

**Exemple actuel (à adapter) :**
```csharp
private string GetApiUrl()
{
    // Récupérer depuis config existante
    return ConfigManager.GetApiUrl(); // Adapter
}

private string GetApiKey()
{
    return ConfigManager.GetApiKey(); // Adapter
}
```

### Logging

**Exemple actuel (à adapter) :**
```csharp
private void LogInfo(string message)
{
    Logger.Info(message); // Adapter selon logger existant
}

private void LogWarning(string message)
{
    Logger.Warn(message);
}

private void LogError(string message)
{
    Logger.Error(message);
}
```

---

## 🧪 Tests

### Test 1 : GetRealismConfig

**Console Arma 3 :**
```sqf
private _response = "COMSPECExtension" callExtension ["GetRealismConfig", []];
_response params ["_result", "_code"];

if (_code isEqualTo 0) then {
    hint format ["Config récupérée : %1 caractères", count _result];
    copyToClipboard _result; // Voir JSON complet
} else {
    hint format ["Erreur : %1", _result];
};
```

### Test 2 : GetRealismParam

```sqf
private _response = "COMSPECExtension" callExtension ["GetRealismParam", ["radio_relays", "relay_range_m"]];
_response params ["_result", "_code"];

if (_code isEqualTo 0) then {
    private _range = parseNumber _result;
    hint format ["Portée relais : %1m", _range]; // 2000m
};
```

### Test 3 : CalculateWeatherEffects

```sqf
private _rain = rain;
private _fog = fogParams select 0;
private _overcast = overcast;
private _wind = windStr * 3.6; // m/s → km/h
private _baseRange = 2000;

private _response = "COMSPECExtension" callExtension [
    "CalculateWeatherEffects", 
    [_baseRange, _rain, _fog, _overcast, _wind]
];

_response params ["_result", "_code"];

if (_code isEqualTo 0) then {
    private _effects = parseSimpleArray _result;
    private _effectiveRange = _effects get "effectiveRange";
    private _description = _effects get "description";
    
    hint format ["Portée effective : %1m\n%2", _effectiveRange, _description];
};
```

### Test 4 : ApplyRealismProfile (serveur uniquement)

```sqf
if (isServer) then {
    private _response = "COMSPECExtension" callExtension ["ApplyRealismProfile", ["expert"]];
    _response params ["_result", "_code"];
    
    if (_code isEqualTo 0) then {
        hint "Profil Expert appliqué avec succès !";
    };
};
```

---

## 📦 Dépendances NuGet

Vérifier que les packages suivants sont installés :

```xml
<PackageReference Include="System.Text.Json" Version="8.0.0" />
<PackageReference Include="System.Net.Http" Version="4.3.4" />
```

---

## 🚀 Build et déploiement

### 1. Compiler extension

```bash
dotnet build COMSPECExtension.csproj -c Release
```

### 2. Copier DLL

```bash
cp bin/Release/net8.0/COMSPECExtension.dll "@comspec_overwatch/COMSPECExtension_x64.dll"
```

### 3. Tester en jeu

Lancer Arma 3, charger mission COMSPEC, ouvrir console debug et tester méthodes ci-dessus.

---

## ⚠️ Points d'attention

1. **Async/await** : Les méthodes utilisent `async Task<string>`. Le router utilise `.GetAwaiter().GetResult()` pour bloquer jusqu'à completion. C'est acceptable ici car l'extension Arma tolère attente courte (< 10s).

2. **Cache TTL** : 3 minutes par défaut. Augmenter si serveur très chargé, diminuer si besoin réactivité accrue.

3. **Parsing SQF → C#** : Les arguments SQF `callExtension` sont des strings. Parsing manuel nécessaire (`float.Parse`, `JsonDocument.Parse`).

4. **Sécurité API** : Vérifier que `X-API-Key` est bien fournie si API nécessite auth.

5. **Gestion erreurs** : Toutes méthodes retournent JSON avec `{ error: string }` en cas d'échec. Toujours vérifier côté SQF.

---

## 📚 Ressources

- Code source complet : `docs/technique/csharp-extension/Extension_RealismConfigMethods.cs`
- Fonctions SQF correspondantes : `docs/technique/sqf-mod/fn_*.sqf`
- API endpoints : `/api/atak/realism/config`, `/api/atak/realism/apply-profile`

---

**Intégration Phase 3 — Extension C# : ✅ Prête pour implémentation**
