# Système de gestion des relais ATAK — Zeus/Eden & Tableau de bord

## 🎯 Vue d'ensemble

Système complet pour gérer les relais radio ATAK depuis Zeus/Eden et en jeu, avec détection automatique d'erreurs, UI style relevé terrain, et tableau de bord temps réel.

---

## 📦 Composants

### 1. **Module Zeus : Scanner réseau relais** 🔍

**Fonction** : `ATHENA_fnc_zeusModuleScanRelays`  
**Icône** : 📡 Signal

**Ce qu'il fait** :
- Scanne tous les relais dans un rayon (par défaut 5000m)
- Détecte les relais jamais remontés ou > 5 min sans sync
- Upload automatique vers le serveur ATHENA
- Affiche un rapport style **relevé terrain** avec feedback visuel :
  ```
  ╔════════════════════════════════════╗
  ║  SCANNER RÉSEAU RELAIS ATAK       ║
  ╠════════════════════════════════════╣
  ║  Total détecté : 12               ║
  ║  À remonter    : 5                ║
  ║  Déjà sync     : 7                ║
  ║  Erreurs       : 2                ║
  ╚════════════════════════════════════╝
  ```
- **Effet visuel** : Particules vertes sur chaque relais uploadé
- **Marqueur temporaire** : Zone de scan visible 30 secondes

**Utilisation Zeus** :
1. Modules → COMSPEC ATAK → Scanner réseau relais
2. Placer le module sur la carte (centre du scan)
3. Clic gauche pour activer
4. Voir le rapport dans le hint Zeus

**Paramètres** :
- `ScanRadius` : Rayon de scan en mètres (défaut: 5000)
- `AutoUpload` : Upload automatique (défaut: Oui)

---

### 2. **Module Zeus : Resync relais** ↻

**Fonction** : `ATHENA_fnc_zeusResyncRelay`  
**Icône** : ⬆️ Upload

**Ce qu'il fait** :
- Resynchronise immédiatement **un seul relais**
- Trouve le relais le plus proche (< 50m du module)
- Effet visuel : Particules jaunes → vertes si succès
- Message de confirmation Zeus

**Utilisation Zeus** :
1. Modules → COMSPEC ATAK → Resync relais
2. Placer le module **sur ou près** du relais
3. Clic gauche pour activer
4. Voir confirmation ✅/❌

---

### 3. **Module Zeus : Tableau de bord relais** 📊

**Fonction** : `ATHENA_fnc_openRelayDashboard`  
**Icône** : 📡 Signal

**Ce qu'il fait** :
- Ouvre le tableau de bord en plein écran
- (Voir section "Tableau de bord" ci-dessous)

**Utilisation Zeus** :
1. Modules → COMSPEC ATAK → Tableau de bord relais
2. Placer n'importe où
3. Clic gauche → tableau de bord s'ouvre

---

### 4. **Tableau de bord en jeu** 📋

**Fonction** : `ATHENA_fnc_openRelayDashboard`  
**Ouverture** :
- Via module Zeus (ci-dessus)
- Action ACE sur ordinateurs/tablettes en mission
- Action scroll (si pas ACE) : `player addAction`
- Objets marqués `COMSPEC_HasRelayDashboard = true`

**Interface** :
```
╔══════════════════════════════════════════════════════════╗
║       TABLEAU DE BORD RELAIS ATAK                       ║
║       Réseau radio tactique — Statut temps réel          ║
╠══════════════════════════════════════════════════════════╣
║  ● 8 ACTIFS   ● 2 HORS LIGNE   ⚠ 3 ERREURS   TOTAL: 10 ║
╠══════════════════════════════════════════════════════════╣
║  Nom          | Portée | Conn. | Dist | Sync | Erreur  ║
║───────────────────────────────────────────────────────────║
║  RELAY-01     | 3000m  | 4/8   | 125m | ● EN DIRECT     ║ (vert)
║  RELAY-02     | 2500m  | 6/8   | 892m | Il y a 2 min    ║ (vert)
║  RELAY-03     | 2000m  | 8/8   | 1.2km| Il y a 1 min   ║ [SATURÉ] (jaune)
║  RELAY-04     | 3000m  | 0/8   | 3.5km| JAMAIS         ║ [HORS LIGNE] (rouge)
║  RELAY-05     | 2000m  | 2/8   | 650m | Il y a 8 min   ║ [ENDOMMAGÉ] (orange)
╠══════════════════════════════════════════════════════════╣
║  [🔄 RESYNC TOUS]  [📍 TÉLÉPORTER]  [✖]                 ║
╚══════════════════════════════════════════════════════════╝
```

**Fonctionnalités** :
- **Stats globales** : Actifs, hors ligne, erreurs, total
- **Liste triée par distance** : Plus proche en haut
- **Couleurs par statut** :
  - 🟢 Vert : En ligne, OK
  - 🟡 Jaune : Saturé (slots pleins)
  - 🟠 Orange : Endommagé (dégâts > 50%)
  - 🔴 Rouge : Hors ligne ou détruit
- **Détection erreurs automatique** :
  - `[HORS LIGNE]` : alive = false ou damage > 95%
  - `[ENDOMMAGÉ]` : damage > 50%
  - `[SATURÉ]` : slots utilisés ≥ slots max
  - `JAMAIS` : Jamais sync depuis le déploiement
- **Boutons actions** :
  - **🔄 RESYNC TOUS** : Resynchronise tous les relais (refresh auto)
  - **📍 TÉLÉPORTER** : TP au relais sélectionné
  - **✖** : Fermer
- **Double-clic sur un relais** : Affiche détails complets
  ```
  ═══════════════════════════════════
     DÉTAILS RELAIS : RELAY-01
  ═══════════════════════════════════
  UID         : 12345_67
  Position    : [5234, 2891, 15]
  Portée      : 3000 m
  IP          : 192.168.1.10
  Puissance   : 25 W
  Débit       : 12 Mbps
  Fiabilité   : 92 %
  État        : ✅ EN LIGNE
  Dégâts      : 5 %
  ═══════════════════════════════════
  ```
- **ESC** : Fermer le tableau de bord

---

### 5. **Actions ACE** 🎬

**Fonction** : `ATHENA_fnc_addRelayDashboardActions`  
**Appelé** : Au démarrage de la mission (postInit)

**Ajoute l'action à** :
- `Land_Laptop_unfolded_F` (ordinateurs)
- `Land_Tablet_02_F` (tablettes)
- Tout objet avec `COMSPEC_HasRelayDashboard = true`

**Action** : `📡 Tableau de bord relais ATAK`

**Fallback** : Si pas ACE, ajoute scroll action au joueur

---

## 🎨 Effets visuels

### Particules de confirmation
- **Scan/Upload** : Particules vertes montantes (2 sec)
- **Resync** : Particules jaunes → vertes (3 sec)
- **Erreur** : Pas de particules, message rouge

### Marqueurs temporaires
- **Zone scan** : Cercle jaune semi-transparent (30 sec)
- Auto-suppression après timeout

---

## 📂 Fichiers créés

```
comspec-overwatch-addons/
└── connect/
    ├── functions/
    │   ├── fn_zeusModuleScanRelays.sqf         (Scanner Zeus)
    │   ├── fn_zeusResyncRelay.sqf              (Resync rapide Zeus)
    │   ├── fn_openRelayDashboard.sqf           (Tableau de bord UI)
    │   └── fn_addRelayDashboardActions.sqf     (Actions ACE)
    └── CfgZeusRelayModules.hpp                 (Config modules Zeus)
```

---

## 🔧 Installation

### 1. **Ajouter au config.cpp**

Dans `comspec-overwatch-addons/connect/config.cpp`, ajouter :

```cpp
#include "CfgZeusRelayModules.hpp"
```

### 2. **Ajouter aux fonctions CBA**

Dans `CfgFunctions`, classe `ATHENA` :

```cpp
class RelayManagement {
    file = "comspec-overwatch-addons\connect\functions";
    class zeusModuleScanRelays {};
    class zeusResyncRelay {};
    class openRelayDashboard {};
    class addRelayDashboardActions {
        postInit = 1; // Auto-run au démarrage
    };
};
```

### 3. **Recompiler le mod**

```bash
cd mod/UptoDate/
# Recompiler avec Arma 3 Tools ou hemtt
```

---

## 🚀 Utilisation en mission

### Scénario 1 : Zeus vérifie le réseau
1. Zeus ouvre Zeus interface (Y)
2. Modules → COMSPEC ATAK → Scanner réseau relais
3. Placer au centre de la zone d'intérêt
4. Activer → Rapport dans hint
5. Si des relais ne sont pas remontés, ils sont uploadés automatiquement

### Scénario 2 : Joueur consulte le réseau
1. Trouver un ordinateur ou tablette en mission
2. ACE Interaction → `📡 Tableau de bord relais ATAK`
3. Consulter la liste, voir les erreurs
4. Double-clic sur un relais pour détails
5. Si Zeus : Téléporter pour réparation

### Scénario 3 : Resync manuel d'un relais
1. Zeus détecte un relais "JAMAIS" sync
2. Modules → Resync relais
3. Placer sur le relais
4. Activer → Effet visuel + confirmation

---

## 🐛 Détection d'erreurs

### Types d'erreurs détectés

| Erreur | Condition | Action recommandée |
|--------|-----------|-------------------|
| `[HORS LIGNE]` | alive = false ou damage > 95% | Réparer ou remplacer |
| `[ENDOMMAGÉ]` | damage > 50% | Réparer avec ACE |
| `[SATURÉ]` | Connexions = slots max | Déployer relais supplémentaire |
| `JAMAIS` sync | lastSync = 0 | Resync manuel ou scanner |
| Sync ancienne | lastSync > 5 min | Resync recommandé |

### Logs
```
[COMSPEC ATAK][Scanner] Scan terminé : 12 relais détectés, 5 remontés
[COMSPEC ATAK][Dashboard] Ouvert : 12 relais affichés
[COMSPEC ATAK][Zeus] Resync manuel : 12345_67
```

---

## 🎯 Avantages

✅ **UI style relevé terrain** : Feedback visuel immédiat, professionnelle  
✅ **Détection auto des erreurs** : Plus besoin de vérifier manuellement  
✅ **Tableau de bord temps réel** : Tout le réseau visible en 1 coup d'œil  
✅ **Actions Zeus rapides** : Scan + upload en 1 clic  
✅ **Triée par distance** : Les relais proches en premier  
✅ **Double-clic pour détails** : Infos complètes sans quitter l'UI  
✅ **Téléportation Zeus** : Aller au relais en 1 clic pour réparation  
✅ **Resync global** : Tous les relais en 1 bouton  
✅ **Effets visuels** : Particules de confirmation, immersif  

---

**Système prêt à l'emploi ! 🚀**
