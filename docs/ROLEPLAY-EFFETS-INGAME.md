# Effets roleplay ingame (Arma 3)

**Date** : 24 juillet 2026  
**Module** : COMSPEC Overwatch Connect

---

## 🎯 Vue d'ensemble

Système d'affichage ingame dans Arma 3 pour visualiser l'état de la liaison ATAK en temps réel. Les effets visuels et sonores renforcent l'immersion lors des dégradations de réseau.

---

## 📺 Interface utilisateur

### Overlay permanent (RscTitle)

**IDD** : 16800  
**Durée** : Permanent (1e10 secondes)  
**Position** : Plein écran avec éléments positionnés

#### Éléments affichés

**⚠️ IMPORTANT** : Aucun filtre visuel gênant ! Vision toujours claire.

| Élément | IDC | Position | Description |
|---------|-----|----------|-------------|
| Network Quality | 16810 | Coin supérieur droit | Indicateur de qualité en temps réel |
| Disconnect Message | 16811 | Centre écran | Message de déconnexion avec compte à rebours |
| Packet Loss Indicator | 16812 | Bas de l'écran | Avertissement si perte > 5% |
| Disconnect Progress | 16813 | Sous message | Barre de progression déconnexion |

**Retirés volontairement** :
- ❌ Parasites (scan lines)
- ❌ Flash glitch rouge
- ❌ Voile noir de déconnexion
- ❌ Tout effet qui gêne la vue du joueur

---

## 🎨 Effets visuels

### 1. Indicateur de qualité réseau (coin supérieur droit)

**Affiché** : En permanence (sauf si déconnecté)

**Contenu** :
```
LIAISON ATAK
EXCELLENTE     (vert)
Pertes: 0.5%
18/20 reçus
```

**Couleurs selon packet loss** :
- `#00ff00` (vert) : 0-1%
- `#88ff00` (vert clair) : 1-3%
- `#ffff00` (jaune) : 3-5%
- `#ffaa00` (orange) : 5-10%
- `#ff4444` (rouge) : >10%

**États** :
- `EXCELLENTE` : 0-1%
- `BONNE` : 1-3%
- `ACCEPTABLE` : 3-5%
- `DÉGRADÉE` : 5-10%
- `CRITIQUE` : >10%

### 2. Message de déconnexion (centre écran)

**Condition** : Déconnexion active

**Composition** :
- ✅ Message central stylisé avec ombre
- ✅ Fond discret (0.8 alpha) limité au message
- ✅ Barre de progression
- ❌ PAS de voile noir plein écran

**Message** :
```
⚠ LIAISON ATAK PERDUE ⚠
Reconnexion dans 15s
Aucune donnée transmise
```

**Barre de progression** :
- Remplie progressivement
- Colorée en rouge (0.8, 0.2, 0.2)
- Calcul : `1 - (remaining / totalDuration)`

### 3. Avertissement packet loss (bas écran)

**Condition** : Packet loss > 5% ET non déconnecté

**Contenu** :
```
⚠ Qualité de liaison dégradée
8.5% de paquets perdus
```

**Style** :
- Fond noir semi-transparent (0.7 alpha)
- Texte orange/rouge
- Centré horizontalement

---

## 🔊 Effets sonores

### Sons utilisés

Tous les sons proviennent d'Arma 3 vanilla (pas de fichiers custom).

| Événement | Son | Description |
|-----------|-----|-------------|
| Déconnexion | `FD_CP_Not_Clear_F` + `AddItemFailed` | Static radio + bip d'erreur |
| Reconnexion | `FD_CP_Clear_F` | Bip de confirmation |
| Glitch | `AddItemFailed` | Bip d'erreur court |
| Avertissement | `Orange_NotificationDefault_01` | Notification orange |
| Dégradé | `Orange_NotificationDefault_02` | Notification grave |

### Déclenchement

**Déconnexion** :
```sqf
["disconnect"] call comspec_overwatch_connect_fnc_playRoleplaySound;
// Joue FD_CP_Not_Clear_F, puis AddItemFailed après 0.3s
```

**Reconnexion** :
```sqf
["reconnect"] call comspec_overwatch_connect_fnc_playRoleplaySound;
// Joue FD_CP_Clear_F
```

---

## ⚙️ Architecture technique

### Fichiers créés

```
mod/@COMSPECOverwatch/addons/connect/
├── displays/
│   └── display_roleplay_overlay.hpp    [Display avec 7 contrôles]
├── functions/
│   ├── fn_initRoleplayOverlay.sqf      [Création display + PFH]
│   ├── fn_updateRoleplayOverlay.sqf    [MAJ toutes les 0.5s]
│   └── fn_playRoleplaySound.sqf        [Gestion sons]
└── config.cpp                          [RscTitles + CfgFunctions]
```

### Initialisation

**Quand** : XEH_postInit.sqf, si CBA `comspec_overwatch_roleplay_visual_effects` activé

**Code** :
```sqf
if (missionNamespace getVariable ["comspec_overwatch_roleplay_visual_effects", false]) then {
    0 spawn comspec_overwatch_connect_fnc_initRoleplayOverlay;
};
```

**Processus** :
1. Création du RscTitle via `cutRsc`
2. Attente de création du display (max 2s)
3. Cacher tous les contrôles par défaut
4. Démarrer PFH de mise à jour (toutes les 0.5s)

### Mise à jour en boucle

**PFH** : `COMSPEC_RoleplayOverlayPFH`  
**Fréquence** : 0.5 secondes  
**Fonction** : `fn_updateRoleplayOverlay`

**Étapes** :
1. Vérifier activation CBA
2. Récupérer display via uiNamespace
3. Obtenir stats packet loss
4. Obtenir info déconnexion
5. Mettre à jour chaque contrôle selon l'état
6. Gérer effets visuels (parasites, glitchs)

---

## 🎮 Expérience joueur

### Principe : Indicateurs informatifs, vision claire

**Philosophie** : Le joueur doit **toujours** avoir une vue dégagée pour jouer. Les effets roleplay sont **informatifs uniquement**, jamais gênants.

### Scénario type : Opération hostile

**T=0** : Liaison normale
```
[Coin supérieur droit]
LIAISON ATAK
EXCELLENTE
Pertes: 0.8%
19/20 reçus
```

**T=5min** : Packet loss augmente
```
[Coin supérieur droit]
LIAISON ATAK
DÉGRADÉE      (orange)
Pertes: 7.5%
93/100 reçus

[Bas de l'écran]
⚠ Qualité de liaison dégradée
7.5% de paquets perdus

[Vue]
→ Aucun filtre visuel
→ Vision totalement claire
→ Gameplay non affecté
```

**T=10min** : Déconnexion complète
```
[Son] Static radio + bip d'erreur

[Centre écran - message compact]
⚠ LIAISON ATAK PERDUE ⚠
Reconnexion dans 27s
Aucune donnée transmise
[=========>....] 65%

[Vue]
→ Aucun voile noir
→ Vision totalement claire
→ Juste le message informatif
→ Plus d'indicateur qualité (coin droit)

[Hub ATAK]
→ Affiche "offline"
```

**T=10min27s** : Reconnexion
```
[Son] Bip de confirmation

[Overlay disparaît progressivement]

[Coin supérieur droit]
LIAISON ATAK
BONNE
Pertes: 2.1%
98/100 reçus
```

---

## 🔧 Configuration

### Activation

**Via CBA Settings** (Arma 3) :
```
COMSPEC Overwatch — Roleplay
  ☑ Activer le mode roleplay
  ☑ Effets visuels ingame
```

### Désactivation

**Temporaire** :
```sqf
// Stopper le PFH
[COMSPEC_RoleplayOverlayPFH] call CBA_fnc_removePerFrameHandler;

// Fermer le display
private _display = uiNamespace getVariable ["COMSPEC_RoleplayOverlay", displayNull];
if (!isNull _display) then {
    _display closeDisplay 0;
};
```

**Permanente** :
- Décocher `Effets visuels ingame` dans CBA
- Redémarrer la mission

---

## 📊 Performance

### Impact CPU

**Overlay** : Négligeable
- RscTitle natif (BI)
- 7 contrôles statiques

**PFH** : Très faible
- Fréquence : 0.5s
- Opérations : lecture variables + mise à jour texte
- Estimation : <0.1ms par cycle

**Sons** : Négligeable
- `playSound` non bloquant
- Sons vanilla pré-chargés

### Optimisations

1. **Contrôles cachés par défaut** : Pas de rendu inutile
2. **Mise à jour conditionnelle** : Contrôles modifiés seulement si changement d'état
3. **Fréquence adaptée** : 0.5s suffisant pour fluidité
4. **Pas de textures custom** : Texture procédurale légère

---

## 🐛 Dépannage

### L'overlay ne s'affiche pas

**Causes possibles** :
1. `comspec_overwatch_roleplay_visual_effects` désactivé
2. Display non créé (timeout 2s)
3. PFH non démarré

**Debug** :
```sqf
// Vérifier variable
hint str (missionNamespace getVariable ["comspec_overwatch_roleplay_visual_effects", false]);

// Vérifier display
private _display = uiNamespace getVariable ["COMSPEC_RoleplayOverlay", displayNull];
hint str (isNull _display);

// Vérifier PFH
hint str COMSPEC_RoleplayOverlayPFH;
```

**Solution** :
```sqf
// Réinitialiser manuellement
0 spawn comspec_overwatch_connect_fnc_initRoleplayOverlay;
```

### Les sons ne jouent pas

**Causes** :
1. Effets sonores désactivés dans CBA
2. Volumes ingame trop faibles

**Vérification** :
```sqf
// Tester son manuellement
playSound "FD_CP_Clear_F";
```

### Les contrôles sont mal positionnés

**Cause** : UI scale différent

**Solution** : Les positions utilisent `safeZone*` qui s'adapte automatiquement. Pas de modification nécessaire.

---

## 🚀 Améliorations futures

### Prévues

- [ ] **Texture scanlines custom** : PAA avec lignes de balayage animées
- [ ] **Effet de distorsion** : PP effect pour flou/déformation lors de glitchs
- [ ] **Sons custom** : Enregistrements radio réalistes
- [ ] **Indicateur directionnel** : Flèche vers base/relais pour retrouver signal
- [ ] **Journal des coupures** : Historique visible dans le hub
- [ ] **Notifications push** : Avertissement 30s avant déconnexion prévue

### Idées supplémentaires

- **Mode "Zone morte"** : Overlay permanent dans certaines régions
- **Effet de brouillage** : Distorsion visuelle selon brouilleurs ennemis
- **Interférence solaire** : Dégradation cyclique selon heure ingame
- **Altitude** : Meilleure qualité en hauteur (avions)
- **Météo** : Orages affectent la liaison

---

## 📸 Captures d'écran (conceptuelles)

### État normal
```
┌─────────────────────────────────┐
│                    [LIAISON ATAK│
│                     EXCELLENTE  │
│                    Pertes: 0.8% │
│                    19/20 reçus] │
│                                 │
│                                 │
│         [Gameplay normal]       │
│                                 │
│                                 │
└─────────────────────────────────┘
```

### État dégradé
```
┌─────────────────────────────────┐
│                    [LIAISON ATAK│
│                      DÉGRADÉE   │
│                     Pertes: 7.5%│
│                     93/100 reçus]│
│                                 │
│                                 │
│      [Vue claire 100%]          │
│                                 │
│ [⚠ Qualité de liaison dégradée]│
└─────────────────────────────────┘
```

### Déconnexion
```
┌─────────────────────────────────┐
│                                 │
│  ┌───────────────────────────┐ │
│  │ ⚠ LIAISON ATAK PERDUE ⚠  │ │
│  │  Reconnexion dans 15s    │ │
│  │ Aucune donnée transmise  │ │
│  │ [========>......] 60%    │ │
│  └───────────────────────────┘ │
│                                 │
│      [Vue claire 100%]          │
└─────────────────────────────────┘
```

**Note** : Message compact avec fond discret, JAMAIS de voile plein écran.

---

**Document rédigé pour COMSPEC MILSIM — Juillet 2026**
