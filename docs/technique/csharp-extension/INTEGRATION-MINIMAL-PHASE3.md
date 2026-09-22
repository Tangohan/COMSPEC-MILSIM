# Intégration C# — Guide minimal Phase 3

## Contexte

Le fichier `Extension.cs` fait 10494 lignes. Pour éviter de le casser, on ajoute **uniquement le routing** des 5 nouveaux appels dans `TryGetSyncResponse` (ligne ~2473-3700), puis on créera les méthodes implémentées dans un fichier séparé.

---

## Étape 1 : Ajouter routing dans TryGetSyncResponse

**Fichier :** `mod/UptoDate/COMSPECExtension/Extension.cs`

**Emplacement :** À la fin de `TryGetSyncResponse` (après les autres `if (function == "...")` existants, avant le `return null;` final)

**Code à ajouter :**

```csharp
        // ============================================================================
        // Phase 3 : Configuration réalisme centralisée
        // ============================================================================
        
        if (function == "GetRealismConfig")
        {
            try
            {
                using var ct = new CancellationTokenSource(TimeSpan.FromSeconds(SyncTimeoutSeconds));
                return GetRealismConfigSync(ct.Token);
            }
            catch (Exception ex) { return FormatCaughtError(ex); }
        }
        
        if (function == "GetRealismParam" && args.Length >= 2)
        {
            try
            {
                var domain = (args[0] ?? "").Trim();
                var key = (args[1] ?? "").Trim();
                if (domain.Length == 0 || key.Length == 0) 
                    return "ERR|missing_args";
                    
                using var ct = new CancellationTokenSource(TimeSpan.FromSeconds(SyncTimeoutSeconds));
                return GetRealismParamSync(domain, key, ct.Token);
            }
            catch (Exception ex) { return FormatCaughtError(ex); }
        }
        
        if (function == "ApplyRealismProfile" && args.Length >= 1)
        {
            try
            {
                var profileKey = (args[0] ?? "").Trim();
                if (profileKey.Length == 0) 
                    return "ERR|missing_profile_key";
                    
                using var ct = new CancellationTokenSource(TimeSpan.FromSeconds(SyncTimeoutSeconds));
                return ApplyRealismProfileSync(profileKey, ct.Token);
            }
            catch (Exception ex) { return FormatCaughtError(ex); }
        }
        
        if (function == "CalculateWeatherEffects" && args.Length >= 5)
        {
            try
            {
                if (!double.TryParse((args[0] ?? "0").Trim(), out var baseRange)) 
                    return "ERR|invalid_base_range";
                if (!double.TryParse((args[1] ?? "0").Trim(), out var rain)) 
                    return "ERR|invalid_rain";
                if (!double.TryParse((args[2] ?? "0").Trim(), out var fog)) 
                    return "ERR|invalid_fog";
                if (!double.TryParse((args[3] ?? "0").Trim(), out var overcast)) 
                    return "ERR|invalid_overcast";
                if (!double.TryParse((args[4] ?? "0").Trim(), out var windKmh)) 
                    return "ERR|invalid_wind";
                    
                return CalculateWeatherEffectsSync(baseRange, rain, fog, overcast, windKmh);
            }
            catch (Exception ex) { return FormatCaughtError(ex); }
        }
        
        if (function == "SyncRelay" && args.Length >= 1)
        {
            try
            {
                var relayDataJson = (args[0] ?? "").Trim();
                if (relayDataJson.Length == 0) 
                    return "ERR|missing_relay_data";
                    
                using var ct = new CancellationTokenSource(TimeSpan.FromSeconds(SyncTimeoutSeconds));
                return SyncRelaySync(relayDataJson, ct.Token);
            }
            catch (Exception ex) { return FormatCaughtError(ex); }
        }
```

**Remarques :**
- Utilise les patterns existants (`FormatCaughtError`, `SyncTimeoutSeconds`, `CancellationTokenSource`)
- Validation args basique
- Délègue à des méthodes synchrones `*Sync` (pas d'`async` dans `TryGetSyncResponse`)

---

## Étape 2 : Créer les 5 méthodes implémentées

**Fichier :** `mod/UptoDate/COMSPECExtension/Extension_Realism.cs` (nouveau fichier)

**Raison :** Classe `partial Extension` pour séparer la logique réalisme du fichier principal de 10494 lignes.

**Contenu complet :**

```csharp
using System;
using System.Net.Http;
using System.Text;
using System.Text.Json;
using System.Threading;

namespace COMSPECExtension;

public partial class Extension
{
    // Cache config réalisme (3 minutes TTL)
    private static string _realismConfigCache = "";
    private static long _realismConfigCacheTicks = 0;
    private static readonly long _realismConfigCacheTTL = TimeSpan.FromMinutes(3).Ticks;

    /// <summary>
    /// GetRealismConfig : Récupère config complète avec cache 3 min
    /// </summary>
    private static string GetRealismConfigSync(CancellationToken token)
    {
        try
        {
            var now = DateTime.UtcNow.Ticks;
            
            // Cache hit ?
            if (_realismConfigCache.Length > 0 && 
                (now - _realismConfigCacheTicks) < _realismConfigCacheTTL)
            {
                return "OK|" + _realismConfigCache;
            }
            
            // Cache miss : appel API
            if (!TryBuildRequestUri(_baseUrl, "/api/atak/realism/config", out var uri, out var err) || uri is null)
                return "ERR|" + err;
            
            using var resp = SendGet(uri.ToString(), token);
            var body = ReadContentUtf8(resp, token);
            
            if (!resp.IsSuccessStatusCode)
            {
                var code = (int)resp.StatusCode;
                if (code == 401) return "ERR|unauthorized";
                if (code == 403) return "ERR|forbidden";
                if (code == 404) return "ERR|not_found";
                return "ERR|http_" + code;
            }
            
            // Parser JSON
            using var doc = JsonDocument.Parse(body);
            if (!doc.RootElement.TryGetProperty("ok", out var okProp) || !okProp.GetBoolean())
                return "ERR|api_error";
            
            if (!doc.RootElement.TryGetProperty("config", out var configProp))
                return "ERR|missing_config";
            
            var configJson = configProp.GetRawText();
            
            // Mettre en cache
            _realismConfigCache = configJson;
            _realismConfigCacheTicks = now;
            
            return "OK|" + configJson;
        }
        catch (OperationCanceledException)
        {
            return "ERR|timeout";
        }
        catch (HttpRequestException ex)
        {
            return "ERR|network_" + (ex.Message.Length > 50 ? ex.Message.Substring(0, 50) : ex.Message);
        }
        catch (Exception ex)
        {
            return "ERR|" + ex.GetType().Name;
        }
    }

    /// <summary>
    /// GetRealismParam : Lecture paramètre spécifique (domain, key)
    /// </summary>
    private static string GetRealismParamSync(string domain, string key, CancellationToken token)
    {
        try
        {
            // Récupérer config complète (avec cache)
            var configResult = GetRealismConfigSync(token);
            if (!configResult.StartsWith("OK|"))
                return configResult; // Propager l'erreur
            
            var configJson = configResult.Substring(3); // Skip "OK|"
            
            // Parser et extraire paramètre
            using var doc = JsonDocument.Parse(configJson);
            var root = doc.RootElement;
            
            if (!root.TryGetProperty(domain, out var domainProp))
                return "ERR|domain_not_found";
            
            if (!domainProp.TryGetProperty(key, out var keyProp))
                return "ERR|key_not_found";
            
            // Retourner valeur brute (nombre, bool, string, array, object)
            var valueJson = keyProp.GetRawText();
            return "OK|" + valueJson;
        }
        catch (Exception ex)
        {
            return "ERR|" + ex.GetType().Name;
        }
    }

    /// <summary>
    /// ApplyRealismProfile : Applique profil (beginner/event/expert)
    /// </summary>
    private static string ApplyRealismProfileSync(string profileKey, CancellationToken token)
    {
        try
        {
            if (!TryBuildRequestUri(_baseUrl, "/api/atak/realism/apply-profile", out var uri, out var err) || uri is null)
                return "ERR|" + err;
            
            var payload = $"{{\"profile_key\":\"{EscapeJson(profileKey)}\"}}";
            using var resp = SendJsonPost(uri.ToString(), payload, token);
            var body = ReadContentUtf8(resp, token);
            
            if (!resp.IsSuccessStatusCode)
            {
                var code = (int)resp.StatusCode;
                if (code == 400) return "ERR|invalid_profile";
                if (code == 401) return "ERR|unauthorized";
                if (code == 403) return "ERR|forbidden";
                if (code == 404) return "ERR|not_found";
                return "ERR|http_" + code;
            }
            
            // Invalider cache
            _realismConfigCache = "";
            _realismConfigCacheTicks = 0;
            
            // Parser réponse
            using var doc = JsonDocument.Parse(body);
            if (!doc.RootElement.TryGetProperty("ok", out var okProp) || !okProp.GetBoolean())
                return "ERR|api_error";
            
            var profileLabel = doc.RootElement.TryGetProperty("profile_label", out var labelProp) 
                ? labelProp.GetString() ?? profileKey 
                : profileKey;
            
            return "OK|" + profileLabel;
        }
        catch (OperationCanceledException)
        {
            return "ERR|timeout";
        }
        catch (HttpRequestException ex)
        {
            return "ERR|network_" + (ex.Message.Length > 50 ? ex.Message.Substring(0, 50) : ex.Message);
        }
        catch (Exception ex)
        {
            return "ERR|" + ex.GetType().Name;
        }
    }

    /// <summary>
    /// CalculateWeatherEffects : Calcul effets météo sur portée relais
    /// </summary>
    private static string CalculateWeatherEffectsSync(double baseRange, double rain, double fog, double overcast, double windKmh)
    {
        try
        {
            // Facteurs d'atténuation (0.0-1.0, 1.0 = pas d'effet)
            double rainFactor = 1.0 - (rain * 0.15);        // Pluie max -15%
            double fogFactor = 1.0 - (fog * 0.25);          // Brouillard max -25%
            double overcastFactor = 1.0 - (overcast * 0.10); // Couvert max -10%
            double windFactor = Math.Max(0.85, 1.0 - (windKmh / 150.0 * 0.15)); // Vent fort max -15%
            
            // Facteur combiné
            double combinedFactor = rainFactor * fogFactor * overcastFactor * windFactor;
            combinedFactor = Math.Max(0.5, Math.Min(1.0, combinedFactor)); // Clamp 0.5-1.0
            
            // Portée effective
            double effectiveRange = baseRange * combinedFactor;
            effectiveRange = Math.Max(50, effectiveRange); // Minimum 50m
            
            // Facteur réduction débit (1.0 = plein débit, 0.5 = moitié)
            double throughputFactor = Math.Max(0.7, combinedFactor);
            
            // Retour : effectiveRange|throughputFactor|combinedFactor
            return $"OK|{effectiveRange:F0}|{throughputFactor:F2}|{combinedFactor:F2}";
        }
        catch (Exception ex)
        {
            return "ERR|" + ex.GetType().Name;
        }
    }

    /// <summary>
    /// SyncRelay : Synchronise relais avec API ATHENA
    /// </summary>
    private static string SyncRelaySync(string relayDataJson, CancellationToken token)
    {
        try
        {
            // Valider JSON
            using var testDoc = JsonDocument.Parse(relayDataJson);
            
            if (!TryBuildRequestUri(_baseUrl, "/api/atak/relays/sync", out var uri, out var err) || uri is null)
                return "ERR|" + err;
            
            using var resp = SendJsonPost(uri.ToString(), relayDataJson, token);
            var body = ReadContentUtf8(resp, token);
            
            if (!resp.IsSuccessStatusCode)
            {
                var code = (int)resp.StatusCode;
                if (code == 400) return "ERR|invalid_data";
                if (code == 401) return "ERR|unauthorized";
                if (code == 403) return "ERR|forbidden";
                if (code == 404) return "ERR|not_found";
                return "ERR|http_" + code;
            }
            
            // Parser réponse
            using var doc = JsonDocument.Parse(body);
            if (!doc.RootElement.TryGetProperty("ok", out var okProp) || !okProp.GetBoolean())
                return "ERR|api_error";
            
            var relayId = doc.RootElement.TryGetProperty("relay_id", out var idProp) && idProp.ValueKind == JsonValueKind.Number
                ? idProp.GetInt32()
                : 0;
            
            var status = doc.RootElement.TryGetProperty("status", out var statusProp)
                ? statusProp.GetString() ?? "synced"
                : "synced";
            
            return $"OK|{relayId}|{status}";
        }
        catch (JsonException)
        {
            return "ERR|invalid_json";
        }
        catch (OperationCanceledException)
        {
            return "ERR|timeout";
        }
        catch (HttpRequestException ex)
        {
            return "ERR|network_" + (ex.Message.Length > 50 ? ex.Message.Substring(0, 50) : ex.Message);
        }
        catch (Exception ex)
        {
            return "ERR|" + ex.GetType().Name;
        }
    }
}
```

**Remarques :**
- Classe `partial` pour étendre `Extension.cs` sans le modifier directement
- Utilise méthodes existantes (`TryBuildRequestUri`, `SendGet`, `SendJsonPost`, `ReadContentUtf8`, `EscapeJson`)
- Cache simple avec `Ticks` pour éviter `DateTime` comparisons complexes
- Format retour uniforme : `OK|data` ou `ERR|code`

---

## Étape 3 : Compilation

**Commandes (exemple) :**

```bash
cd /workspace/mod/UptoDate/COMSPECExtension

# NativeAOT publish
dotnet publish -c Release -r win-x64

# Copier DLL
cp bin/Release/net8.0/win-x64/publish/COMSPECExtension.dll \
   ../../Workshop/_pack/@comspec_overwatch/COMSPECExtension_x64.dll
```

**Vérifier :**
- `Extension_Realism.cs` compilé automatiquement (classe partielle)
- Pas d'erreurs de liaison
- Taille DLL similaire (quelques Ko de plus)

---

## Étape 4 : Tests console Arma 3

**Test 1 : GetRealismConfig**

```sqf
private _response = "COMSPECExtension" callExtension ["GetRealismConfig", []];
private _result = _response select 0;
private _code = _response select 1;

if (_code isEqualTo 0 && {(_result select [0, 3]) isEqualTo "OK|"}) then {
    private _json = _result select [3];
    hint format ["Config récupérée : %1 caractères", count _json];
} else {
    hint format ["Erreur : %1", _result];
};
```

**Attendu :** `Config récupérée : ~3000 caractères` (JSON complet domaines)

---

**Test 2 : GetRealismParam**

```sqf
private _response = "COMSPECExtension" callExtension ["GetRealismParam", ["radio_relays", "relay_range_m"]];
private _result = _response select 0;

if ((_result select [0, 3]) isEqualTo "OK|") then {
    private _value = parseNumber (_result select [3]);
    hint format ["Portée relais : %1m", _value];
} else {
    hint format ["Erreur : %1", _result];
};
```

**Attendu :** `Portée relais : 2000m` (valeur config par défaut)

---

**Test 3 : ApplyRealismProfile (serveur uniquement)**

```sqf
if (isServer) then {
    private _response = "COMSPECExtension" callExtension ["ApplyRealismProfile", ["expert"]];
    private _result = _response select 0;
    
    if ((_result select [0, 3]) isEqualTo "OK|") then {
        hint "Profil Expert appliqué !";
    } else {
        hint format ["Erreur : %1", _result];
    };
};
```

**Attendu :** `Profil Expert appliqué !` puis vérifier valeurs changent

---

**Test 4 : CalculateWeatherEffects**

```sqf
// baseRange, rain, fog, overcast, windKmh
private _response = "COMSPECExtension" callExtension ["CalculateWeatherEffects", ["2000", "0.5", "0.3", "0.8", "25"]];
private _result = _response select 0;

if ((_result select [0, 3]) isEqualTo "OK|") then {
    private _data = (_result select [3]) splitString "|";
    private _effectiveRange = parseNumber (_data select 0);
    private _throughputFactor = parseNumber (_data select 1);
    private _combinedFactor = parseNumber (_data select 2);
    
    hint format ["Portée effective : %1m (x%2)", _effectiveRange, _combinedFactor];
} else {
    hint format ["Erreur : %1", _result];
};
```

**Attendu :** `Portée effective : ~1500m (x0.75)` (2000m réduit par météo)

---

**Test 5 : SyncRelay**

```sqf
private _relayData = str createHashMapFromArray [
    ["uid", "relay_test_123"],
    ["pos_x", 5000],
    ["pos_y", 5000],
    ["range", 2000],
    ["status", "active"]
];

private _response = "COMSPECExtension" callExtension ["SyncRelay", [_relayData]];
private _result = _response select 0;

if ((_result select [0, 3]) isEqualTo "OK|") then {
    private _data = (_result select [3]) splitString "|";
    private _relayId = parseNumber (_data select 0);
    private _status = _data select 1;
    
    hint format ["Relais sync : ID %1, statut %2", _relayId, _status];
} else {
    hint format ["Erreur : %1", _result];
};
```

**Attendu :** `Relais sync : ID 42, statut synced` (ID varie)

---

## Résumé

**2 fichiers modifiés/créés :**
1. `Extension.cs` : +70 lignes (routing seulement)
2. `Extension_Realism.cs` : +290 lignes (nouveau fichier, implémentation)

**Avantages :**
- Pas de risque de casser le fichier de 10k lignes existant
- Séparation claire logique réalisme
- Patterns existants respectés
- Tests unitaires possibles sur `Extension_Realism`

**Temps estimé :**
- Intégration : 30 minutes
- Compilation : 5 minutes
- Tests : 15 minutes
- **Total : 50 minutes**

---

**Phase 3 C# : 0% → 100% après cette intégration ✅**
