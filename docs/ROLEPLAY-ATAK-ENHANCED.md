# Effets Roleplay ATAK Enhanced (In-Game)

Ce document détaille les effets visuels et sonores intégrés dans **l'ATAK Enhanced** (le menu Hub in-game d'Arma 3, touche `K`).

## Vue d'ensemble

Les effets roleplay s'affichent **directement dans l'interface ATAK Enhanced** en jeu. Le joueur voit les dégradations réseau, les dommages physiques à son appareil ATAK, et reçoit des alertes contextuelles sans impact sur sa vue FPS.

## Effets visuels

### 1. Écran cassé / ATAK éteint

Lorsque l'appareil ATAK du joueur est endommagé (blessures au torse), trois états sont possibles selon le niveau de réalisme configuré (`comspec_overwatch_atak_realism`) :

#### Niveau 0 : Désactivé
Aucun effet de dommage physique.

#### Niveau 1 : ATAK éteint (temporaire)
- **Effet** : Écran noir avec texte centré "ATAK ÉTEINT"
- **Indication** : "ACE Self Interact → Rallumer"
- **Cause** : Blessure légère au torse
- **Réparation** : ACE Self Interact → "Rallumer ATAK" (gratuit, immédiat)
- **Son** : Bruit de collision au moment du dommage

#### Niveau 2 : Écran détruit
- **Effet** : Écran gris/noir avec texte "ÉCRAN ENDOMMAGÉ"
- **Indication** : "Connexion maintenue · Toolkit ACE requis"
- **Cause** : Blessure modérée au torse
- **Réparation** : Nécessite un Toolkit ACE + 10 secondes
- **Connexion** : Les données sont toujours envoyées au serveur Athena, mais l'écran n'affiche rien

#### Niveau 3 : ATAK détruit (irréparable)
- **Effet** : Écran noir total, aucun message
- **Cause** : Blessure grave au torse
- **Réparation** : **Impossible** jusqu'à réanimation complète ou respawn
- **Connexion** : Coupée, plus aucune donnée transmise

### 2. Déconnexion réseau

Overlay central semi-transparent affiché en cas de perte de liaison ATAK (simulation ou zone sans couverture) :

```
⚠ LIAISON ATAK PERDUE ⚠
Reconnexion dans 12s
Aucune donnée transmise
```

- **Couleur** : Rouge (#ff4444)
- **Position** : Centre de l'écran ATAK Enhanced
- **Déclenchement** : Fonction `fn_simulateNetworkDisconnect` ou zone "no_coverage"
- **Son** : Bruit radio statique à la déconnexion, bip à la reconnexion

### 3. Avertissement zone géographique

Indicateur dans le coin supérieur droit lorsque le joueur entre dans une zone de dégradation réseau :

```
📡 Zone Brouillée
Intensité: 65%
```

- **Couleurs** :
  - Rouge (#ff4444) : Aucune couverture
  - Orange (#ffaa00) : Interférence
  - Jaune (#ffff00) : Liaison dégradée
  - Violet (#ff88ff) : Brouilleur actif
- **Son** : Alarme courte à l'entrée dans la zone

### 4. Indicateur packet loss

Bandeau en bas de l'écran ATAK Enhanced si la perte de paquets dépasse 5% :

```
⚠ Pertes: 12.3%
```

- **Couleur** : Orange si < 10%, Rouge si ≥ 10%
- **Position** : Bas centre de l'interface

### 5. Effet glitch

Flash rouge semi-transparent sur tout l'écran ATAK Enhanced, déclenché aléatoirement (10% de chance) quand le packet loss dépasse 10%.

- **Durée** : 100ms
- **Couleur** : Rouge translucide (#ff4444, 20% opacité)

## Effets sonores

Tous les sons utilisent des fichiers audio intégrés à Arma 3 (pas de ressources externes) :

| Événement | Fichier | Volume | Description |
|-----------|---------|--------|-------------|
| Déconnexion | `ambient_radio18.wss` | 0.8 | Parasites radio |
| Reconnexion | `beep_target.wss` | 0.5 | Bip de confirmation |
| Interférence zone | `ambient_radio17.wss` | 0.4 | Grésillements |
| Alerte zone | `alarm_independent.wss` | 0.6 | Alarme courte |
| Écran cassé | `vehicle_collision.wss` | 0.7 | Impact/collision |

## Configuration

### CBA Settings

#### `comspec_overwatch_roleplay_enabled`
- **Type** : Boolean
- **Défaut** : `false`
- **Description** : Active/désactive tous les effets roleplay (ATAK Enhanced + Web)

#### `comspec_overwatch_atak_realism`
- **Type** : Integer (0-3)
- **Défaut** : `0`
- **Description** :
  - `0` : Aucun dommage physique à l'ATAK
  - `1` : ATAK peut s'éteindre (réparable)
  - `2` : Écran peut être détruit (connexion maintenue, réparable avec Toolkit)
  - `3` : ATAK peut être totalement détruit (irréparable)

### Admin Web

Les paramètres de simulation réseau (délais, packet loss simulé, défaillances capteurs) se configurent via l'interface admin Athena (`/admin/atak/roleplay`).

## Techniques d'implémentation

### Display Hub (9969)

Les contrôles roleplay sont définis dans `display_hub.hpp` avec des IDC dédiés :

- **9200** : Overlay déconnexion
- **9201** : Avertissement zone
- **9202** : Indicateur packet loss
- **9203** : Écran cassé/éteint
- **9204** : Effet glitch

### Fonction de mise à jour

`fn_updateAtakEnhancedRoleplay.sqf` est appelée **chaque seconde** tant que le display Hub est ouvert (via `onLoad` spawn).

Elle :
1. Récupère l'état réseau (`fn_getPacketLossStats`, `fn_getNetworkDisconnectInfo`)
2. Récupère la zone géographique (`fn_getPlayerRoleplayZone`)
3. Récupère l'état ATAK (`fn_isAtakFunctional`)
4. Affiche/masque les contrôles roleplay en conséquence
5. Joue les sons si changement d'état

### Détection de changement d'état

Pour éviter de jouer les sons en boucle, on stocke l'état précédent dans `missionNamespace` :

- `COMSPEC_Roleplay_WasDisconnected` : Était déconnecté au dernier tick ?
- `COMSPEC_Roleplay_WasInZone` : Était dans une zone au dernier tick ?
- `COMSPEC_Roleplay_WasScreenBroken` : Écran était cassé au dernier tick ?

Les sons ne sont joués que lors d'une **transition d'état** (ex. passage de connecté → déconnecté).

## Interaction avec ACE Medical

Le système détecte les blessures au torse via `ace_medical_hitpoints` et applique les dommages ATAK automatiquement (fonction `fn_checkAtakDamage` appelée toutes les 5 secondes).

Les actions de réparation sont disponibles via **ACE Self Interact** :

- **Rallumer ATAK** : Disponible si niveau 1 et ATAK éteint
- **Réparer écran ATAK** : Disponible si niveau 2, écran cassé, et Toolkit en inventaire
- **Diagnostics ATAK** : Affiche l'état actuel (toujours disponible)

## Interaction avec les zones géographiques

Les modules Zeus/Eden (`COMSPEC_Roleplay_NoCoverage`, `COMSPEC_Roleplay_Interference`, etc.) affectent directement l'affichage ATAK Enhanced :

- **Zone sans couverture** : Force l'overlay de déconnexion + coupe les updates serveur
- **Zone d'interférence** : Affiche l'avertissement + augmente visuellement le packet loss
- **Zone dégradée** : Affiche l'avertissement + packet loss modéré
- **Brouilleur** : Affiche l'avertissement + packet loss élevé + délai

## Différence avec cTab

**Important** : L'ATAK Enhanced est le **menu Hub in-game** du mod COMSPEC Overwatch (touche `K`), distinct de cTab (ItemAndroid).

Les effets décrits dans ce document s'appliquent **uniquement à l'ATAK Enhanced**. Le cTab a ses propres effets définis dans `atak-roleplay-ctab.js/css`.

## Exemple de flux complet

1. **Joueur reçoit une blessure au torse** (explosion, tir)
2. **fn_checkAtakDamage** détecte le dommage
3. Selon le niveau de réalisme configuré :
   - Niveau 1 : `COMSPEC_AtakPowered` → `false`
   - Niveau 2 : `COMSPEC_AtakScreenOK` → `false`
   - Niveau 3 : `COMSPEC_AtakConnected` → `false`
4. **Son** de collision joué
5. **Joueur ouvre l'ATAK Enhanced** (touche `K`)
6. **fn_updateAtakEnhancedRoleplay** détecte l'écran cassé
7. **Overlay "ÉCRAN ENDOMMAGÉ"** s'affiche
8. **Joueur fait ACE Self Interact** → "Réparer écran ATAK" (avec Toolkit)
9. **Après 10 secondes** : écran rétabli, overlay masqué

## Fichiers impliqués

### SQF (Arma)
- `functions/fn_updateAtakEnhancedRoleplay.sqf` : Logique principale de mise à jour
- `functions/fn_playAtakEnhancedSound.sqf` : Gestion des sons
- `functions/fn_checkAtakDamage.sqf` : Détection des blessures
- `functions/fn_isAtakFunctional.sqf` : État de l'appareil ATAK
- `functions/fn_repairAtak.sqf` : Actions de réparation

### Configuration (Arma)
- `display_hub.hpp` : Définition des contrôles visuels
- `config.cpp` : Déclaration des fonctions
- `XEH_postInit.sqf` : Initialisation des event handlers

### Documentation
- `ROLEPLAY-NOUVELLES-FONCTIONNALITES.md` : Vue d'ensemble globale
- `ROLEPLAY-ZONES-GEOGRAPHIQUES.md` : Détails sur les modules Zeus/Eden
- `technique/atak-roleplay-simulation.md` : Détails techniques backend (PHP/Web)
