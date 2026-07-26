# Guide d'Installation et Test - Fonctionnalités ATAK

## Installation Complète

### 1. Prérequis

#### Arma 3
- ✅ Arma 3 installé
- ✅ CBA A3 (Community Base Addons)
- ✅ ACE3 (recommandé pour menus contextuels)

#### Backend
- ✅ Base de données avec migrations ATAK appliquées
- ✅ PHP 8.1+ avec routes API ATAK configurées
- ✅ Token ATAK généré

### 2. Installation Mod

#### A. Copier fichiers mod

```
📁 Arma 3/@COMSPECOverwatch/
  ├─ addons/
  │   └─ comspec_overwatch_connect.pbo   ← Fonctions SQF compilées
  ├─ COMSPECExtension_x64.dll            ← Extension native
  └─ mod.cpp
```

#### B. Activer mod dans launcher

1. Ouvrir launcher Arma 3
2. Onglet "MODS"
3. Cocher :
   - ☑ CBA_A3
   - ☑ ace
   - ☑ @COMSPECOverwatch

#### C. Configuration CBA (paramètres Athena)

**Option 1 : Liaison rapide (recommandée)**

1. Sur Athena web : ATAK → Générer code liaison
2. En jeu : **Touche K** → "Connecter mon compte Athena"
3. Coller le code → "Établir liaison"

**Option 2 : Configuration manuelle**

1. Menu Arma → Options → Configurer les mods
2. Chercher "COMSPEC Overwatch"
3. Renseigner :
   - URL Athena : `https://athena.ttrd.fr/public`
   - Clé d'accès : `[VOTRE_TOKEN_ATAK]`
   - ID Communauté : `[ID_NUMERIQUE]`

### 3. Vérification Extension

Lancer mission → ouvrir console debug (Shift+Tab) :

```sqf
"COMSPECExtension" callExtension ["GetVersion", []]
```

**Résultat attendu** :
```
["2.0", "COMSPEC Extension ATAK"]
```

**Si erreur** :
- ❌ "Extension not found" → Vérifier DLL présente dans `@COMSPECOverwatch/`
- ❌ "Insufficient resources" → Désactiver BattlEye ou configurer whitelist (voir README)

---

## Test Fonctionnalités

### Test 1 : Rapport Tactique

#### En jeu

1. Ouvrir menu ACE Self-Interact (Touche Windows par défaut)
2. 📡 ATAK Tactique → 📝 Rapports Tactiques → CONTACT
3. Observer notification en jeu "✅ Rapport CONTACT transmis"

#### Vérification web

1. Ouvrir Athena → ATAK Web
2. Vérifier nouveau rapport dans panneau "Rapports"
3. Détails : type=CONTACT, priorité=IMMEDIATE, position joueur

---

### Test 2 : Marquage POI

#### En jeu

1. Se positionner près d'un bâtiment
2. Menu ACE Self-Interact → 📡 ATAK Tactique → 📍 Marquer POI → Cache d'armes
3. Observer marker local temporaire (cercle rouge)

**OU** : Raccourci rapide (si configuré dans CBA Settings)

#### Vérification web

1. ATAK Web → Onglet "POI"
2. Nouveau POI visible sur carte
3. Catégorie : CACHE, Affiliation : ENEMY

---

### Test 3 : MEDEVAC

#### En jeu

1. Menu ACE Self-Interact → 📡 ATAK Tactique → 🚁 Demander Appui → MEDEVAC
2. Observer :
   - Hint "9-Line MEDEVAC transmis"
   - Son radio
   - Fréquence radio affichée (si ACRE/TFAR)

#### Vérification web

1. ATAK Web → Panneau "MEDEVAC"
2. Nouvelle demande avec :
   - Priorité : URGENT
   - Position pickup (grille)
   - Patients T1/T2/T3
   - Statut : REQUESTED
3. Golden hour calculé automatiquement

---

### Test 4 : QRF (Quick Reaction Force)

#### En jeu

1. Menu ACE Self-Interact → 📡 ATAK Tactique → 🚁 Demander Appui → QRF
2. Observer notification "🚨 QRF demandé - IMMEDIATE"
3. Marker local "QRF REQUESTED" créé

#### Vérification web

1. ATAK Web → Panneau "QRF"
2. Nouvelle demande :
   - Type menace : TROOPS_IN_CONTACT
   - Position contact
   - Estimation force ennemie
   - Statut : REQUESTED

---

### Test 5 : Tracking Véhicule

#### En jeu

1. Monter dans un véhicule (n'importe lequel)
2. Observer notification "🚗 Tracking véhicule [CALLSIGN] activé"
3. Conduire quelques mètres
4. Système envoie position automatiquement toutes les 10s

#### Vérification web

1. ATAK Web → Onglet "Véhicules"
2. Véhicule apparaît avec :
   - Callsign, classe, position
   - Carburant, munitions, santé
   - Trajectoire temps réel

#### Test alerte carburant critique

1. En jeu : Vider carburant avec `vehicle player setFuel 0.05`
2. Observer :
   - Fumée verte automatique
   - Marker local "⛽ CARBURANT CRITIQUE"
   - Son d'alerte
3. Web : Service request auto-créé avec priorité CRITICAL

---

### Test 6 : Service Véhicule

#### En jeu

1. Dans un véhicule endommagé
2. Menu ACE Self-Interact → 📡 ATAK Tactique → 🔧 Demander Service → 🔨 Réparation
3. Observer :
   - Notification "Service réparation demandé"
   - Marker temporaire
   - Fumée si priorité HIGH/CRITICAL

#### Vérification web

1. ATAK Web → Panneau "Services Véhicules"
2. Nouvelle demande :
   - Type : REPAIR
   - Position service
   - Priorité : HIGH
   - Statut : REQUESTED

---

## Tests Automatiques

### Script de test complet

Exécuter en console debug Arma :

```sqf
// Test 1: Rapport
["SPOTREP", "ROUTINE", "Test rapport", "Détails test"] call comspec_overwatch_connect_fnc_submitTacticalReport;
sleep 2;

// Test 2: POI
["POI Test", "OTHER", "NEUTRAL", "CONFIRMED", "Test POI système"] call comspec_overwatch_connect_fnc_createPOI;
sleep 2;

// Test 3: MEDEVAC
["PRIORITY", 1, 0, 0, "NO_ENEMY", "NONE", ""] call comspec_overwatch_connect_fnc_requestMEDEVAC;
sleep 2;

// Test 4: QRF
["AMBUSH", "URGENT", "Test QRF", "SQUAD", 0, "ENGAGED"] call comspec_overwatch_connect_fnc_requestQRF;
sleep 2;

// Test 5: Véhicule (nécessite être dans véhicule)
if (vehicle player != player) then {
    [vehicle player] call comspec_overwatch_connect_fnc_updateVehicleTracking;
};

systemChat "✅ Tous tests lancés - Vérifier ATAK Web";
```

**Résultat attendu** : 5 notifications en jeu, 5 nouvelles entrées dans ATAK Web

---

## Troubleshooting

### Extension non chargée

**Symptôme** : "Extension not found"

**Solutions** :
1. Vérifier `COMSPECExtension_x64.dll` dans `@COMSPECOverwatch/`
2. Installer Visual C++ Redistributable 2015-2022
3. Désactiver BattlEye pour test local
4. Vérifier logs RPT : `%LOCALAPPDATA%\Arma 3\Arma3_x64_*.rpt`

### Menus ACE absents

**Symptôme** : Pas de menu "📡 ATAK Tactique"

**Solutions** :
1. Vérifier ACE3 activé : `ace_interact_menu` chargé ?
2. Forcer réinitialisation :
   ```sqf
   [] call comspec_overwatch_connect_fnc_initATAKMenu;
   ```
3. Utiliser fonctions directement sans menu :
   ```sqf
   ["CONTACT", "IMMEDIATE", "Test", "Test"] call comspec_overwatch_connect_fnc_submitTacticalReport;
   ```

### Rapports non reçus backend

**Symptôme** : Notifications en jeu mais rien dans ATAK Web

**Solutions** :
1. Vérifier URL Athena correcte (avec `/public` si nécessaire)
2. Vérifier token valide dans config CBA
3. Tester endpoint manuellement :
   ```bash
   curl -X POST https://athena.ttrd.fr/public/api/atak/reports \
     -H "X-ATAK-Token: VOTRE_TOKEN" \
     -H "Content-Type: application/json" \
     -d '{"report_type":"SPOTREP","priority":"ROUTINE"}'
   ```
4. Vérifier logs extension : `%LOCALAPPDATA%\Arma 3\COMSPECExtension.log`

### Tracking véhicule ne démarre pas

**Symptôme** : Pas de notification montée véhicule

**Solutions** :
1. Forcer initialisation :
   ```sqf
   [] call comspec_overwatch_connect_fnc_initVehicleTracking;
   ```
2. Vérifier CBA chargé : `CBA_missionTime` doit retourner un nombre
3. Test manuel :
   ```sqf
   [vehicle player] call comspec_overwatch_connect_fnc_updateVehicleTracking;
   ```

### Erreur 401 Unauthorized

**Symptôme** : "ERROR: 401 Unauthorized" dans logs

**Solutions** :
1. Régénérer token ATAK sur Athena
2. Mettre à jour config CBA avec nouveau token
3. Vérifier header `X-ATAK-Token` envoyé par extension

---

## Checklist Déploiement Production

### Backend
- [ ] Migrations SQL appliquées (phases 1, 2, 2.5)
- [ ] Routes API ATAK configurées (`/api/atak/*`)
- [ ] Token ATAK généré pour unité
- [ ] Repositories PHP déployés
- [ ] HTTPS activé (production)

### Mod Arma
- [ ] `comspec_overwatch_connect.pbo` compilé avec nouvelles fonctions
- [ ] `COMSPECExtension_x64.dll` version 2.0
- [ ] Config CBA : URL + Token + ID Communauté
- [ ] BattlEye configuré si nécessaire
- [ ] Tests manuels OK (voir ci-dessus)

### Client Web
- [ ] Interface ATAK Web déployée
- [ ] Panneaux Rapports, POI, MEDEVAC, QRF, Véhicules visibles
- [ ] Carte Leaflet fonctionnelle
- [ ] Polling/WebSocket actif pour temps réel

---

## Support

**Documentation complète** :
- Backend : `/docs/GUIDE-INTEGRATION-API-ATAK.md`
- Extension : `/mod/@COMSPECOverwatch/EXTENSION_C#_SPECIFICATION.md`
- Features : `/docs/NOUVELLES-FEATURES-ATAK-MOD.md`

**Logs** :
- Arma RPT : `%LOCALAPPDATA%\Arma 3\Arma3_x64_*.rpt`
- Extension : `%LOCALAPPDATA%\Arma 3\COMSPECExtension.log`
- Backend : `/var/log/athena/api.log`
