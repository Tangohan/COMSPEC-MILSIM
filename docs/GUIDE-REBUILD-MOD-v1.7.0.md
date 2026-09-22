# Guide de Rebuild — COMSPEC Overwatch v1.7.0

**Date** : 22 septembre 2026  
**Version** : 1.7.0  
**Branche** : `cursor/audit-realisme-centralisation-317c`

---

## Vue d'ensemble

Ce guide détaille le processus complet de **rebuild du mod COMSPEC Overwatch** suite à l'implémentation de la configuration réalisme centralisée.

---

## Prérequis

### Outils requis
- ✅ **Arma 3 Tools** (installés via Steam)
- ✅ **Visual Studio 2022** (pour compilation C#)
- ✅ **.NET 7.0 SDK** (pour NativeAOT)
- ✅ **Git** (pour récupération du code)
- ✅ **PHP 8.1+** (pour migration DB)

### Structure du mod
```
/workspace/mod/UptoDate/
├── Sources/
│   ├── comspec-overwatch-addons/
│   │   ├── connect/              # Addon principal (fonctions, config)
│   │   ├── atak_athena/          # ATAK terminal
│   │   └── realism_config/       # Nouveaux helpers réalisme
│   └── comspec_overwatch.pbo     # PBO final
├── COMSPECExtension/
│   ├── Extension.cs              # Extension principale
│   ├── Extension_Realism.cs      # Partial class réalisme (NEW)
│   └── COMSPECExtension.dll      # DLL compilée
└── @comspec_overwatch/           # Dossier distribution
```

---

## Étape 1 : Récupération du code

```bash
# Clone ou pull de la branche
cd /workspace
git fetch origin
git checkout cursor/audit-realisme-centralisation-317c
git pull origin cursor/audit-realisme-centralisation-317c
```

**Vérifications** :
- ✅ Branche active : `cursor/audit-realisme-centralisation-317c`
- ✅ Dernier commit : "feat(mod): intégration dashboard relais en jeu + modules Zeus"
- ✅ Fichiers présents :
  - `mod/UptoDate/COMSPECExtension/Extension_Realism.cs`
  - `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_zeusModuleScanRelays.sqf`
  - `mod/UptoDate/Sources/comspec-overwatch-addons/realism_config/functions/fn_getRealismParam.sqf`

---

## Étape 2 : Compilation de l'extension C#

### 2.1 Ouvrir le projet
```bash
cd /workspace/mod/UptoDate/COMSPECExtension
# Ouvrir COMSPECExtension.csproj dans Visual Studio 2022
```

### 2.2 Vérifier les fichiers
- ✅ `Extension.cs` : Routing des 5 nouvelles méthodes (lignes ~2850-2950)
- ✅ `Extension_Realism.cs` : Partial class avec implémentation
- ✅ Dépendances : `System.Net.Http`, `System.Text.Json`

### 2.3 Configuration NativeAOT
Dans `COMSPECExtension.csproj`, vérifier :
```xml
<PropertyGroup>
  <PublishAot>true</PublishAot>
  <InvariantGlobalization>true</InvariantGlobalization>
  <IlcOptimizationPreference>Speed</IlcOptimizationPreference>
</PropertyGroup>
```

### 2.4 Compilation
```bash
# En mode Release pour NativeAOT
dotnet publish -c Release -r win-x64 --self-contained

# Ou depuis Visual Studio 2022 :
# Build → Publish COMSPECExtension
```

### 2.5 Vérifier la DLL
```bash
# La DLL devrait être ici :
ls -lh bin/Release/net7.0/win-x64/publish/COMSPECExtension.dll

# Taille attendue : ~8-15 MB (avec NativeAOT)
```

### 2.6 Copier la DLL
```bash
# Copier dans le dossier mod
cp bin/Release/net7.0/win-x64/publish/COMSPECExtension.dll \
   ../Sources/@comspec_overwatch/COMSPECExtension_x64.dll
```

---

## Étape 3 : Génération de l'icône relais PAA

### 3.1 Préparer l'image source
```bash
cd /workspace/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/data/icons

# Vérifier que app_relay_ca.png existe (1024x1024)
file app_relay_ca.png
```

### 3.2 Utiliser Arma 3 Tools - ImageToPAA
```bash
# Chemin typique : C:\Program Files (x86)\Steam\steamapps\common\Arma 3 Tools\ImageToPAA

# Méthode 1 : Interface graphique
# 1. Lancer ImageToPAA.exe
# 2. Glisser app_relay_ca.png dans la fenêtre
# 3. Choisir format : PAA (DXT1 ou DXT5 avec alpha)
# 4. Convertir

# Méthode 2 : Ligne de commande
"C:\Program Files (x86)\Steam\steamapps\common\Arma 3 Tools\ImageToPAA\ImageToPAA.exe" \
  app_relay_ca.png app_relay_ca.paa
```

### 3.3 Vérifier la PAA
```bash
# Le fichier doit exister
ls -lh app_relay_ca.paa

# Taille attendue : ~700 KB - 2 MB (selon compression)
```

---

## Étape 4 : Build du mod avec Addon Builder

### 4.1 Lancer Addon Builder
```
Arma 3 Tools → Addon Builder
```

### 4.2 Configuration
- **Addon source directory** : `/workspace/mod/UptoDate/Sources/comspec-overwatch-addons`
- **Destination directory** : `/workspace/mod/UptoDate/@comspec_overwatch/addons`
- **PBO name prefix** : `z\comspec_overwatch\addons\`
- **Binarize** : ✅ Yes (recommandé pour production)
- **Sign addon** : ❌ No (ou si vous avez une clé de signature)

### 4.3 Options avancées
- **Include** : `*.sqf;*.hpp;*.cpp;*.paa;*.ogg;*.p3d;*.rvmat`
- **Exclude** : `*.md;*.txt;*.log;.git*`
- **List of files to copy directly** : `*.paa;*.ogg;*.p3d`

### 4.4 Build
1. Cliquer sur **Pack**
2. Attendre la fin de la compilation
3. Vérifier les erreurs dans la console

### 4.5 Vérifier les PBOs
```bash
cd /workspace/mod/UptoDate/@comspec_overwatch/addons
ls -lh

# Devrait contenir :
# comspec_overwatch_connect.pbo
# comspec_overwatch_atak_athena.pbo
# comspec_overwatch_realism_config.pbo (NEW)
# ...
```

---

## Étape 5 : Structure finale du mod

```
@comspec_overwatch/
├── addons/
│   ├── comspec_overwatch_connect.pbo        # Addon principal
│   ├── comspec_overwatch_atak_athena.pbo    # ATAK terminal
│   ├── comspec_overwatch_realism_config.pbo # Helpers réalisme (NEW)
│   └── ...
├── COMSPECExtension_x64.dll                 # Extension C# (NEW VERSION)
├── mod.cpp
└── meta.cpp
```

---

## Étape 6 : Tests de validation

### 6.1 Test extension C#
Lancer Arma 3 avec le mod chargé, puis dans le debug console :

```sqf
// Test 1 : GetRealismConfig
private _result = "COMSPECExtension" callExtension ["GetRealismConfig", []];
hint str _result;
// Attendu : "OK|{...json...}"

// Test 2 : GetRealismParam
private _range = "COMSPECExtension" callExtension ["GetRealismParam", ["radio_relays", "relay_range_m"]];
hint _range;
// Attendu : "OK|2000"

// Test 3 : CalculateWeatherEffects
private _effects = "COMSPECExtension" callExtension ["CalculateWeatherEffects", ["2000", "0.8", "0.5", "0.9", "45"]];
hint _effects;
// Attendu : "OK|{range:..., throughput:...}"
```

### 6.2 Test helpers SQF
```sqf
// Test 1 : fn_getRealismParam
private _range = ["radio_relays", "relay_range_m", 2000] call ATHENA_fnc_getRealismParam;
hint str _range;
// Attendu : 2000 (ou valeur config)

// Test 2 : fn_placeRealismRelay
private _pos = getPos player;
private _relay = [_pos, 2000, "Test Relay"] call ATHENA_fnc_placeRealismRelay;
hint format ["Relay created: %1", _relay];
// Attendu : Objet Land_Antenna_01_F créé
```

### 6.3 Test modules Zeus
1. Ouvrir Zeus (Y)
2. Modules → COMSPEC ATAK
3. Vérifier présence de :
   - Scanner réseau relais
   - Tableau de bord relais
   - Resync relais
4. Tester chaque module

### 6.4 Test actions joueur
1. Placer un laptop en mission : `"Land_Laptop_unfolded_F" createVehicle (getPos player)`
2. ACE Self Interact → ATAK
3. Vérifier présence de "Tableau de bord relais"
4. Ouvrir le dashboard

---

## Étape 7 : Migration base de données

### 7.1 Exécuter le script
```bash
cd /workspace
php setup-realism-migration.php --tenant-id=1

# Pour tous les tenants :
php setup-realism-migration.php --all-tenants
```

### 7.2 Vérifier la sortie
Attendu :
```
=== MIGRATION CONFIGURATION RÉALISME ATAK ===
Tenant: 1 (Default Tenant)

[1/6] Création des tables...
  ✅ Table atak_realism_config créée
  ✅ Table atak_relay_overrides créée

[2/6] Migration des paramètres existants...
  ✅ 105 paramètres migrés

[3/6] Création des profils par défaut...
  ✅ 3 profils créés

[4/6] Validation de la migration...
  ✅ Aucun paramètre orphelin
  ✅ 12 incohérences résolues

[5/6] Génération du rapport...
  ✅ Rapport enregistré

Migration terminée avec succès !
```

### 7.3 Vérification web
Accéder à : `https://athena.ttrd.fr/admin/atak/realism/verify`

Tous les tests doivent être ✅ :
- Schéma chargé
- Tables créées
- Config active présente
- Validation JSON
- Incohérences résolues
- Profils disponibles
- API responsive

---

## Étape 8 : Déploiement

### 8.1 Copier le mod sur le serveur
```bash
# Serveur Arma 3
scp -r @comspec_overwatch/ user@server:/arma3/mods/

# Serveur dédié : Ajouter dans serveur.cfg
serverMod = "@comspec_overwatch";
```

### 8.2 Copier la DLL
```bash
# Dans le dossier du mod
cp COMSPECExtension_x64.dll /arma3/mods/@comspec_overwatch/
```

### 8.3 Redémarrer le serveur Arma 3
```bash
systemctl restart arma3server
# ou
./arma3server.sh restart
```

---

## Étape 9 : Checklist finale

### Code
- [ ] Extension C# compilée (COMSPECExtension_x64.dll)
- [ ] Icône PAA générée (app_relay_ca.paa)
- [ ] PBOs créés (comspec_overwatch_*.pbo)
- [ ] Pas d'erreur dans Addon Builder

### Base de données
- [ ] Migration exécutée pour tous les tenants
- [ ] Page `/admin/atak/realism/verify` : tous tests ✅
- [ ] Config active présente dans `atak_realism_config`

### Tests en jeu
- [ ] Extension C# répond (GetRealismConfig, GetRealismParam)
- [ ] Helpers SQF fonctionnent (fn_getRealismParam)
- [ ] Modules Zeus disponibles (3 modules COMSPEC ATAK)
- [ ] Actions ACE joueur (laptop/tablette → dashboard)
- [ ] Dashboard full-screen s'ouvre

### Documentation
- [ ] CHANGELOG-v1.7.0.md créé
- [ ] Guides disponibles (7 documents)
- [ ] PR #548 à jour

---

## Troubleshooting

### Extension C# ne charge pas
**Erreur** : `Cannot load COMSPECExtension_x64.dll`

**Solutions** :
1. Vérifier l'architecture (x64)
2. Installer Visual C++ Redistributable 2022
3. Vérifier les dépendances : `dumpbin /dependents COMSPECExtension_x64.dll`

### Modules Zeus n'apparaissent pas
**Erreur** : Faction COMSPEC ATAK vide

**Solutions** :
1. Vérifier `config.cpp` : faction `COMSPEC_ATAK` déclarée
2. Recompiler le PBO avec Addon Builder
3. Vérifier les logs RPT : `[CfgVehicles] COMSPEC_ModuleScanRelays`

### Actions ACE absentes
**Erreur** : Pas d'action "Tableau de bord relais"

**Solutions** :
1. Vérifier `XEH_postInit.sqf` : appel à `fn_addRelayDashboardActions`
2. Marquer manuellement un objet : `this setVariable ["COMSPEC_RelayDashboard", true, true];`
3. Vérifier logs RPT : `[COMSPEC Overwatch][Boot]`

### API ne répond pas
**Erreur** : `GetRealismConfig` retourne `ERR|http_error`

**Solutions** :
1. Vérifier URL API dans `Extension.cs` : `https://athena.ttrd.fr`
2. Tester manuellement : `curl https://athena.ttrd.fr/api/atak/realism/config`
3. Vérifier firewall serveur

---

## Support

- **Logs Arma 3** : `C:\Users\<user>\AppData\Local\Arma 3\arma3_x64_*.rpt`
- **Logs extension** : Rechercher `[COMSPEC]` ou `[Extension]`
- **Logs SQF** : Rechercher `[COMSPEC Overwatch]`
- **Documentation** : `/workspace/docs/`

---

## Versions

- **Mod** : v1.7.0
- **Extension C#** : v1.7.0 (avec partial class `Extension_Realism.cs`)
- **API ATHENA** : Compatible v2.x+
- **Arma 3** : 2.14+ (stable branch)
- **CBA** : 3.16+
- **ACE3** : 3.16+

---

**Date de création** : 22 septembre 2026  
**Auteur** : Cursor Cloud Agent  
**Projet** : ATHENA C2 (COMSPEC-MILSIM)  
**Statut** : ✅ Prêt pour production
