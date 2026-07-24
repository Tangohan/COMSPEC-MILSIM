# Extension C# COMSPEC - Code Source

## Vue d'ensemble

Cette extension native `.dll` permet la communication entre Arma 3 et l'API REST backend COMSPEC Athena.

**Technologies** :
- .NET 6.0
- Newtonsoft.Json (sérialization JSON)
- UnmanagedExports (export DLL pour Arma 3)

---

## Prérequis

### Développement

- **Visual Studio 2022** (Community ou supérieur) avec charge de travail `.NET Desktop`
- **.NET 6 SDK** : https://dotnet.microsoft.com/download/dotnet/6.0
- **Git** (pour cloner dépendances)

### Compilation en ligne de commande

```bash
dotnet --version  # Doit afficher 6.x ou supérieur
```

---

## Installation dépendances

```bash
dotnet restore COMSPECExtension.csproj
```

Packages NuGet installés :
- `Newtonsoft.Json` v13.0.3
- `UnmanagedExports` v2.0.7

---

## Build

### Windows (script automatique)

```cmd
build.bat
```

### Linux/Mac (script automatique)

```bash
chmod +x build.sh
./build.sh
```

### Ligne de commande manuelle

```bash
dotnet build COMSPECExtension.csproj -c Release -p:Platform=x64
```

**Output** : `bin/x64/Release/net6.0/COMSPECExtension_x64.dll`

---

## Installation dans le mod

Après build réussi, la DLL est automatiquement copiée dans :

```
@COMSPECOverwatch/
    COMSPECExtension_x64.dll   ← Extension compilée
```

**⚠️ Important BattlEye** :

Pour serveurs avec BattlEye activé, ajouter dans `battleye/beserver_x64.cfg` :

```cpp
allowedLoadFileExtensions[] = {"dll"};
allowedPreloadFileExtensions[] = {"dll"};
```

---

## Commandes disponibles

| Commande | Description | Endpoint API |
|----------|-------------|--------------|
| `GetVersion` | Version extension | - |
| `Connect` | Initialisation connexion | - |
| `SubmitTacticalReport` | Soumettre rapport tactique | `POST /api/atak/reports` |
| `CreatePOI` | Créer Point of Interest | `POST /api/atak/poi` |
| `RequestMEDEVAC` | Demander évacuation médicale | `POST /api/atak/medevac` |
| `RequestQRF` | Demander Quick Reaction Force | `POST /api/atak/qrf` |
| `UpdateVehicleTracking` | Update position véhicule | `POST /api/atak/vehicles` |
| `RequestVehicleService` | Demander service véhicule | `POST /api/atak/vehicles/service` |

---

## Utilisation depuis SQF

### 1. Vérifier extension chargée

```sqf
private _version = "COMSPECExtension" callExtension ["GetVersion", []];
systemChat format ["Extension v%1 chargée", _version select 0];
```

### 2. Initialiser connexion

```sqf
private _config = createHashMapFromArray [
    ["api_url", "https://athena.comspec.com"],
    ["token", "YOUR_ATAK_TOKEN_HERE"]
];
private _configJson = [_config] call comspec_overwatch_connect_fnc_hashMapToJson;
private _result = "COMSPECExtension" callExtension ["Connect", [_configJson]];
```

### 3. Utiliser fonctions ATAK

Les fonctions SQF dans `/addons/connect/functions/` encapsulent les appels extension.

**Exemple** : Soumettre rapport

```sqf
["CONTACT", "IMMEDIATE", "Ennemi détecté", "Section 10 hommes nord village"] 
    call comspec_overwatch_connect_fnc_submitTacticalReport;
```

---

## Debug

### Logs extension

Logs écrits dans :
- **Windows** : `%LOCALAPPDATA%\Arma 3\COMSPECExtension.log`
- **Linux** : `~/.local/share/Arma 3/COMSPECExtension.log`

### Activer logs détaillés

Dans `ExtensionMain.cs`, changer niveau log :

```csharp
private static LogLevel logLevel = LogLevel.DEBUG;
```

### Tests unitaires

```bash
dotnet test
```

---

## Architecture code

```
ExtensionMain.cs
    └─ RVExtensionArgs()           ← Point d'entrée Arma 3
        └─ ProcessCommand()         ← Router commandes
            └─ SubmitTacticalReport()
            └─ CreatePOI()
            └─ RequestMEDEVAC()
            └─ RequestQRF()
            └─ UpdateVehicleTracking()
            └─ RequestVehicleService()
                └─ SendHttpRequest()  ← Helper HTTP générique
```

---

## Performance

### Cache local

La DLL maintient un cache pour :
- **vehicle_id** : Évite lookups répétés par callsign
- **HttpClient** : Réutilisé pour toutes requêtes (connection pooling)

### Timeouts

```csharp
httpClient.Timeout = TimeSpan.FromSeconds(10);
```

### Retry policy

- **5xx (erreurs serveur)** : 3 tentatives avec backoff exponentiel
- **4xx (erreurs client)** : Pas de retry
- **Timeout réseau** : 2 tentatives

---

## Contribution

### Ajouter nouvelle commande

1. Ajouter case dans `ProcessCommand()` :
```csharp
case "MaCommande":
    return MaCommande(jsonData);
```

2. Implémenter méthode :
```csharp
private static string MaCommande(string jsonData)
{
    return SendHttpRequest("POST", "/api/mon-endpoint", jsonData).Result;
}
```

3. Déclarer fonction SQF correspondante dans `/addons/connect/functions/`

4. Build et test

---

## Sécurité

### Tokens

⚠️ **Ne jamais commit tokens** dans code source !

Les tokens sont passés dynamiquement depuis config CBA :

```sqf
private _token = ["COMSPEC_Overwatch_AccessKey", ""] call CBA_settings_fnc_get;
```

### HTTPS

**Production** : Toujours utiliser `https://` pour `api_url`

**Dev local** : `http://localhost` accepté mais logger warning

---

## Troubleshooting

### "Extension not found"

- Vérifier `COMSPECExtension_x64.dll` présent dans `@COMSPECOverwatch/`
- Vérifier BattlEye autorise DLL (voir config ci-dessus)
- Redémarrer serveur Arma

### "ERROR: Could not load file or assembly"

Installer **Visual C++ Redistributable 2015-2022** :
https://aka.ms/vs/17/release/vc_redist.x64.exe

### "NETWORK_ERROR"

- Vérifier URL API correcte dans config CBA
- Vérifier firewall autorise connexions sortantes Arma 3
- Tester endpoint avec `curl` depuis machine serveur

### "ERROR: 401 Unauthorized"

Token ATAK invalide ou expiré. Vérifier dans config CBA :
```sqf
force COMSPEC_Overwatch_AccessKey = "TOKEN_VALIDE_ICI";
```

---

## Roadmap

- [ ] Support Linux native (`.so`)
- [ ] WebSocket pour notifications push temps réel
- [ ] Batch requests pour optimiser bandwidth
- [ ] Compression gzip pour gros payloads
- [ ] Signature cryptographique payloads (anti-tampering)

---

**Version** : 2.0  
**Licence** : Propriétaire COMSPEC  
**Contact** : dev@comspec.com
