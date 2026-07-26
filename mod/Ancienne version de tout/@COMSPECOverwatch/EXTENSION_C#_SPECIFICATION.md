# Extension C# COMSPEC - Spécification Nouvelles Commandes ATAK

**Version** : 2.0  
**Date** : 24 juillet 2026  
**Namespace** : `COMSPECExtension`

---

## Vue d'ensemble

L'extension C# native (`COMSPECExtension.dll`) fait le pont entre Arma 3 (SQF) et l'API REST backend.

**Nouvelles commandes à ajouter** pour supporter features ATAK Phase 1-2.

---

## Architecture

```
Arma 3 (SQF)
    ↓ callExtension
Extension C# Native
    ↓ HTTP REST
Backend API PHP
    ↓ SQL
Base de données MySQL
```

---

## Commandes existantes (à conserver)

```csharp
"GetVersion"          → Retourne version extension
"Connect"             → Connexion initiale Athena
"UpdatePosition"      → Update position joueur
"SendIntel"           → Envoi intel générique
// ... autres commandes existantes
```

---

## Nouvelles commandes ATAK

### 1. SubmitTacticalReport

**Signature** :
```csharp
"COMSPECExtension" callExtension ["SubmitTacticalReport", [jsonString]]
```

**Endpoint** : `POST /api/atak/reports`

**JSON attendu** :
```json
{
  "report_type": "SPOTREP",
  "priority": "IMMEDIATE",
  "submitter_callsign": "ALPHA-1",
  "pos_x": 15234.56,
  "pos_y": 8765.43,
  "grid_reference": "152087",
  "summary": "Contact ennemi",
  "details": "Section 10 hommes",
  "structured_data": {
    "size": "SQUAD",
    "activity": "MOVING"
  }
}
```

**Implémentation C#** :
```csharp
public string[] SubmitTacticalReport(string jsonData)
{
    try
    {
        var response = await httpClient.PostAsync(
            $"{apiBaseUrl}/api/atak/reports",
            new StringContent(jsonData, Encoding.UTF8, "application/json")
        );
        
        if (response.IsSuccessStatusCode)
        {
            return new[] { "OK", "Report submitted" };
        }
        
        var error = await response.Content.ReadAsStringAsync();
        return new[] { "ERROR", error };
    }
    catch (Exception ex)
    {
        return new[] { "ERROR", ex.Message };
    }
}
```

---

### 2. CreatePOI

**Signature** :
```csharp
"COMSPECExtension" callExtension ["CreatePOI", [jsonString]]
```

**Endpoint** : `POST /api/atak/poi`

**JSON attendu** :
```json
{
  "poi_name": "Cache d'armes",
  "category": "CACHE",
  "affiliation": "ENEMY",
  "certainty": "PROBABLE",
  "pos_x": 12345.67,
  "pos_y": 7890.12,
  "description": "Bâtiment abandonné",
  "threat_level": "MEDIUM",
  "source_type": "VISUAL",
  "reported_by_callsign": "RECON-2"
}
```

**Implémentation C#** :
```csharp
public string[] CreatePOI(string jsonData)
{
    try
    {
        var response = await httpClient.PostAsync(
            $"{apiBaseUrl}/api/atak/poi",
            new StringContent(jsonData, Encoding.UTF8, "application/json")
        );
        
        if (response.IsSuccessStatusCode)
        {
            return new[] { "OK", "POI created" };
        }
        
        return new[] { "ERROR", await response.Content.ReadAsStringAsync() };
    }
    catch (Exception ex)
    {
        return new[] { "ERROR", ex.Message };
    }
}
```

---

### 3. RequestMEDEVAC

**Signature** :
```csharp
"COMSPECExtension" callExtension ["RequestMEDEVAC", [jsonString]]
```

**Endpoint** : `POST /api/atak/medevac`

**JSON attendu** :
```json
{
  "priority": "URGENT",
  "pickup_grid": "152087",
  "pickup_pos_x": 15000.0,
  "pickup_pos_y": 8000.0,
  "radio_frequency": "30.0",
  "radio_callsign": "ALPHA-1",
  "patients_t1_urgent": 1,
  "patients_t2_urgent": 0,
  "patients_t3_delayed": 2,
  "patients_litter": 2,
  "patients_ambulatory": 1,
  "security_status": "POSSIBLE_ENEMY",
  "lz_marking": "SMOKE",
  "lz_marking_color": "GREEN",
  "requested_by_callsign": "MEDIC-1"
}
```

---

### 4. RequestQRF

**Signature** :
```csharp
"COMSPECExtension" callExtension ["RequestQRF", [jsonString]]
```

**Endpoint** : `POST /api/atak/qrf`

**JSON attendu** :
```json
{
  "priority": "IMMEDIATE",
  "contact_pos_x": 18000.0,
  "contact_pos_y": 9000.0,
  "threat_type": "AMBUSH",
  "threat_description": "Embuscade convoi",
  "enemy_strength": "SQUAD",
  "requesting_callsign": "CHARLIE-6",
  "friendly_casualties": 2,
  "friendly_status": "PINNED",
  "support_requested": ["infantry", "cas"]
}
```

---

### 5. UpdateVehicleTracking

**Signature** :
```csharp
"COMSPECExtension" callExtension ["UpdateVehicleTracking", [jsonString]]
```

**Endpoint** : `POST /api/atak/vehicles` (upsert)

**JSON attendu** :
```json
{
  "vehicle_callsign": "ARMORED-1",
  "vehicle_class": "IFV",
  "pos_x": 14000.0,
  "pos_y": 11000.0,
  "heading": 270.0,
  "speed": 45.5,
  "fuel_percent": 65.0,
  "ammo_percent": 80.0,
  "engine_health": 95.0,
  "hull_health": 100.0,
  "status": "OPERATIONAL"
}
```

**Note** : Appel fréquent (toutes les 10s) - optimiser avec cache local et envoi batch si possible.

---

### 6. RequestVehicleService

**Signature** :
```csharp
"COMSPECExtension" callExtension ["RequestVehicleService", [jsonString]]
```

**Endpoint** : `POST /api/atak/vehicles/{id}/service`

**JSON attendu** :
```json
{
  "vehicle_callsign": "ARMORED-1",
  "request_type": "REFUEL",
  "priority": "HIGH",
  "request_details": "Carburant critique",
  "service_pos_x": 14000.0,
  "service_pos_y": 11000.0,
  "requested_by_callsign": "ALPHA-6"
}
```

**Note** : Nécessite d'abord lookup vehicle_id par callsign, ou endpoint modifié pour accepter callsign directement.

---

## Configuration HTTP Client

### Headers requis

```csharp
httpClient.DefaultRequestHeaders.Add("X-ATAK-Token", atakToken);
httpClient.DefaultRequestHeaders.Add("User-Agent", "COMSPEC-Extension/2.0");
```

### Timeout recommandé

```csharp
httpClient.Timeout = TimeSpan.FromSeconds(10);
```

### Retry policy

```csharp
// Retry 3 fois avec backoff exponentiel pour erreurs réseau
// Ne pas retry sur 4xx (erreurs client)
```

---

## Gestion erreurs

### Codes retour

```csharp
// Succès
return new[] { "OK", "Success message" };

// Erreur API (4xx/5xx)
return new[] { "ERROR", errorMessage };

// Erreur réseau
return new[] { "NETWORK_ERROR", exceptionMessage };

// Timeout
return new[] { "TIMEOUT", "Request timeout" };
```

### Logging

```csharp
// Logger toutes erreurs dans fichier:
// %LOCALAPPDATA%\Arma 3\COMSPECExtension.log

Logger.Error($"[{DateTime.Now}] SubmitTacticalReport failed: {error}");
```

---

## Optimisations recommandées

### 1. Cache local

```csharp
// Cache vehicle_id après premier upsert pour éviter lookups
private Dictionary<string, int> vehicleIdCache = new();
```

### 2. Request batching

```csharp
// Pour UpdateVehicleTracking appelé fréquemment:
// Accumuler updates dans buffer et envoyer batch toutes les 30s
private List<VehicleUpdate> updateBuffer = new();
```

### 3. Async/await

```csharp
// Toutes opérations HTTP en async pour ne pas bloquer Arma
public async Task<string[]> SubmitTacticalReportAsync(string jsonData)
{
    // ...
}
```

### 4. Connection pooling

```csharp
// Réutiliser même HttpClient
private static readonly HttpClient httpClient = new HttpClient();
```

---

## Tests recommandés

### Test unitaire pour chaque commande

```csharp
[Test]
public async Task SubmitTacticalReport_ValidData_ReturnsOK()
{
    var json = @"{""report_type"":""SPOTREP"",""priority"":""ROUTINE""}";
    var result = await extension.SubmitTacticalReport(json);
    
    Assert.AreEqual("OK", result[0]);
}
```

### Test intégration

```sqf
// Test depuis Arma
private _result = "COMSPECExtension" callExtension ["SubmitTacticalReport", [_jsonTest]];
systemChat format ["Test result: %1", _result select 0];
```

---

## Compilation

### Prérequis

- Visual Studio 2022
- .NET Framework 4.8 ou .NET 6+
- NuGet packages: `Newtonsoft.Json`

### Build

```bash
dotnet build COMSPECExtension.sln -c Release
```

### Output

```
COMSPECExtension_x64.dll → @COMSPECOverwatch\
COMSPECExtension_x64.so  → @COMSPECOverwatch\ (Linux server)
```

---

## Documentation utilisateur

À ajouter dans README mod :

```markdown
### Extension Native

L'extension `COMSPECExtension.dll` doit être autorisée par BattlEye.

**Configuration BattlEye** :
Ajouter dans `battleye/beserver_x64.cfg` :
```
allowedLoadFileExtensions[] = {"dll"};
allowedPreloadFileExtensions[] = {"dll"};
```

**Vérification** :
```sqf
"COMSPECExtension" callExtension ["GetVersion", []];
// Doit retourner ["2.0", ""]
```
```

---

**Status** : Spécification complète pour développement extension C#  
**Priorité** : Haute (nécessaire pour fonctionnement features ATAK)  
**Estimation** : 4-6h développement + 2h tests
