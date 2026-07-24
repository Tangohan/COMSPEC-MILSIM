# Guide d'activation du mode Roleplay ATAK

Ce guide explique **comment activer et configurer** les fonctionnalités roleplay du mod COMSPEC Overwatch.

## Vue d'ensemble

Le mode roleplay se configure en **deux étapes** :

1. **Configuration serveur** (admin web) : Définir les paramètres de simulation
2. **Activation client** (CBA Arma) : Les joueurs activent les effets dans leur jeu

## Étape 1 : Configuration serveur (Admin)

### Accès à l'interface

1. Connectez-vous au portail Athena en tant qu'**administrateur**
2. Allez dans **Administration** → **ATAK** → **Mode Roleplay**
3. URL directe : `https://votre-portail.com/admin/atak/roleplay`

### Paramètres disponibles

#### Simulation réseau

Active les délais, pertes de paquets et déconnexions temporaires.

**Paramètres configurables** :
- **Mode de simulation** : Normal, Zone hostile, Réseau dégradé, Défaut matériel
- **Perte de paquets** : 0-50% (recommandé : 5-15%)
- **Latence supplémentaire** : 0-5000ms (recommandé : 200-800ms)
- **Déconnexions aléatoires** : Activer/désactiver
- **Durée des coupures** : 5-60 secondes
- **Fréquence des coupures** : 60-600 secondes

**Configuration recommandée pour démarrer** :
```
Mode : Zone hostile
Perte de paquets : 8%
Latence : 300ms
Déconnexions : Activé
Durée : 15s
Fréquence : 180s
```

#### Défauts de capteurs médicaux

Simule des dysfonctionnements du capteur de rythme cardiaque.

**Paramètres configurables** :
- **Taux d'échec de lecture** : 0-50% (recommandé : 5-15%)
- **Valeurs erronées** : Activer/désactiver
- **Marge d'erreur** : 0-50 BPM

**Configuration recommandée** :
```
Taux d'échec : 10%
Valeurs erronées : Activé
Marge d'erreur : 15 BPM
```

### Enregistrer la configuration

Cliquez sur **"Enregistrer la configuration"** en bas de page.

⚠️ **Important** : La configuration serveur seule **ne suffit pas**. Les joueurs doivent aussi activer le mode roleplay côté client (voir étape 2).

## Étape 2 : Activation client (Joueurs)

### Paramètres CBA Arma

Les joueurs doivent configurer les paramètres CBA dans Arma 3 :

1. **Dans le menu principal Arma** ou **en jeu (ESC)** :
   - Cliquez sur **"Options"**
   - Puis **"Addon Options"**
   - Cherchez la section **"COMSPEC Overwatch — Roleplay"**

2. **Activez les paramètres souhaités** :

#### comspec_overwatch_roleplay_enabled

- **Type** : Case à cocher
- **Défaut** : Désactivé
- **Description** : Active/désactive **tous** les effets roleplay
- **❗ REQUIS** : Doit être activé pour que les autres paramètres fonctionnent

#### comspec_overwatch_roleplay_network_failures

- **Type** : Case à cocher
- **Défaut** : Désactivé
- **Description** : Active les simulations réseau (délais, packet loss, déconnexions)
- **Effets** : Délais d'envoi, pertes de paquets mesurées, coupures temporaires

#### comspec_overwatch_roleplay_sensor_failures

- **Type** : Case à cocher
- **Défaut** : Désactivé
- **Description** : Active les défauts de capteur de rythme cardiaque
- **Effets** : Valeurs manquantes, erronées, ou nulles pour le rythme cardiaque

#### comspec_overwatch_roleplay_visual_effects

- **Type** : Case à cocher
- **Défaut** : Activé (si roleplay activé)
- **Description** : Affiche les glitchs et parasites dans l'ATAK Enhanced in-game
- **Effets** : Overlays de déconnexion, alertes de zone, indicateur packet loss, effet glitch

#### comspec_overwatch_atak_realism

- **Type** : Liste déroulante
- **Défaut** : 0 (Désactivé)
- **Options** :
  - `0` : **Désactivé** (pas de dommages physiques)
  - `1` : **Niveau 1** — L'ATAK peut s'éteindre (réparable gratuitement)
  - `2` : **Niveau 2** — L'écran peut être détruit (connexion maintenue, réparable avec Toolkit ACE)
  - `3` : **Niveau 3** — L'ATAK peut être totalement détruit (irréparable, connexion coupée)
- **Description** : Les blessures au torse peuvent endommager l'appareil ATAK

#### comspec_overwatch_troll_mode

- **Type** : Liste déroulante
- **Défaut** : 0 (Désactivé)
- **Options** :
  - `0` : **Désactivé**
  - `1` : **Occasionnel** (10% de chance à chaque ouverture)
  - `2` : **Fréquent** (40% de chance)
  - `3` : **Systématique** (100%, toujours)
- **Description** : Force le joueur à valider des captcha/tests anti-robot avant d'accéder à l'ATAK Enhanced
- **⚠ MODE TROLL** : Réservé aux sessions de jeu détendues ou événements spéciaux

### Configuration recommandée (débutant)

```
✓ comspec_overwatch_roleplay_enabled = Activé
✓ comspec_overwatch_roleplay_network_failures = Activé
✓ comspec_overwatch_roleplay_sensor_failures = Activé
✓ comspec_overwatch_roleplay_visual_effects = Activé
  comspec_overwatch_atak_realism = 1 (Niveau 1)
  comspec_overwatch_troll_mode = 0 (Désactivé)
```

### Configuration immersive complète

```
✓ comspec_overwatch_roleplay_enabled = Activé
✓ comspec_overwatch_roleplay_network_failures = Activé
✓ comspec_overwatch_roleplay_sensor_failures = Activé
✓ comspec_overwatch_roleplay_visual_effects = Activé
  comspec_overwatch_atak_realism = 3 (Niveau 3)
  comspec_overwatch_troll_mode = 0 (Désactivé)
```

### Configuration troll (événements)

```
✓ comspec_overwatch_roleplay_enabled = Activé
✓ comspec_overwatch_roleplay_network_failures = Activé
✓ comspec_overwatch_roleplay_sensor_failures = Activé
✓ comspec_overwatch_roleplay_visual_effects = Activé
  comspec_overwatch_atak_realism = 2 (Niveau 2)
  comspec_overwatch_troll_mode = 1 (Occasionnel)
```

## Étape 3 : Zones géographiques (Zeus/Eden)

### Création de zones (optionnel)

Les Zeus et créateurs de mission peuvent placer des **zones de dégradation réseau** sur la carte via des modules :

1. **Ouvrir Zeus** ou **Eden Editor**
2. Chercher la catégorie **"COMSPEC Roleplay"** dans les modules
3. **4 types de zones disponibles** :

#### Zone sans couverture (No Coverage)

- **Effet** : Déconnexion totale de l'ATAK Enhanced
- **Icône** : 📡🚫
- **Couleur** : Rouge
- **Usage** : Tunnels, bunkers, zones urbaines denses

#### Zone d'interférence (Interference)

- **Effet** : Packet loss élevé + délai accru
- **Icône** : 📡⚡
- **Couleur** : Orange
- **Usage** : Proximité d'installations radio ennemies

#### Zone dégradée (Degraded)

- **Effet** : Packet loss modéré
- **Icône** : 📡⚠
- **Couleur** : Jaune
- **Usage** : Zones éloignées des bases

#### Brouilleur actif (Jammer)

- **Effet** : Packet loss très élevé + délai important
- **Icône** : 📡❌
- **Couleur** : Violet
- **Usage** : Brouilleurs ennemis, guerre électronique

### Paramètres des zones

Pour chaque zone, configurez :

- **Rayon** : Distance d'effet (en mètres)
- **Intensité** : Puissance de la dégradation (0-100%)
- **Nom** : Libellé affiché au joueur (ex. "Tunnel Kavala")

### Visualisation in-game

Quand un joueur entre dans une zone, il voit :

- **Alerte visuelle** dans l'ATAK Enhanced (coin supérieur droit)
- **Son d'alarme** (joué une fois à l'entrée)
- **Effets** selon le type de zone (déconnexion, glitchs, packet loss)

## Vérification de l'activation

### Côté joueur (in-game)

1. **Ouvrir l'ATAK Enhanced** (touche `K`)
2. Si le mode roleplay est actif, vous devriez voir :
   - **Badge "Roleplay actif"** (si configuré)
   - **Indicateur de packet loss** (si > 5%)
   - **Overlays de déconnexion** (si simulation de coupure)
   - **Glitchs visuels** (si packet loss élevé)

3. **Tester l'ACE Self Interact** :
   - ACE Self Interact → "Diagnostics ATAK"
   - Affiche l'état actuel (alimenté, écran OK, connexion OK)

### Côté admin (web)

1. Allez sur la **carte tactique ATAK** (`/atak`)
2. Ouvrez la **console développeur** (F12)
3. Tapez : `console.log(window.roleplayStats);`
4. Vous devriez voir les paramètres roleplay actifs

## Désactivation rapide

### En cours de partie (joueur)

Dans Arma, appuyez sur **ESC** → **Options** → **Addon Options** → Décochez `comspec_overwatch_roleplay_enabled`.

**Ou** via la console debug :

```sqf
missionNamespace setVariable ["comspec_overwatch_roleplay_enabled", false];
```

### Via l'admin web

Allez dans `/admin/atak/roleplay` et cliquez sur **"Réinitialiser"**.

## Dépannage

### Les effets ne s'affichent pas

**Vérifiez** :
1. ✓ Le mode roleplay est activé **côté serveur** (admin web)
2. ✓ Le paramètre `comspec_overwatch_roleplay_enabled` est **activé côté client** (CBA)
3. ✓ Le paramètre `comspec_overwatch_roleplay_visual_effects` est activé
4. ✓ Vous avez ouvert l'**ATAK Enhanced** (touche `K`), pas cTab

### Le captcha troll ne s'affiche jamais

**Vérifiez** :
1. ✓ Le paramètre `comspec_overwatch_troll_mode` est > 0
2. ✓ Vous n'avez pas déjà passé un captcha il y a moins de 60s (cooldown)
3. ✓ Le niveau 1 (10%) peut mettre du temps avant de se déclencher (aléatoire)

### L'ATAK ne se répare pas

**Pour réparer l'ATAK** :
- **Niveau 1** : ACE Self Interact → "Rallumer ATAK" (gratuit)
- **Niveau 2** : ACE Self Interact → "Réparer écran ATAK" (nécessite Toolkit ACE + 10s)
- **Niveau 3** : **Irréparable** jusqu'à réanimation complète

### Zones géographiques invisibles

**Vérifiez** :
1. ✓ Les modules Zeus/Eden sont bien placés sur la carte
2. ✓ Vous êtes **dans** le rayon d'effet de la zone
3. ✓ Le mod est chargé côté serveur

Pour lister les zones actives (console debug) :

```sqf
[] call comspec_overwatch_connect_fnc_listRoleplayZones;
```

## Compatibilité

- **Arma 3** : Version 2.00 ou supérieure
- **CBA_A3** : Requis
- **ACE3** : Recommandé (pour réparations ATAK et capteurs médicaux)
- **Multijoueur** : Oui, chaque joueur a ses propres effets

## Documentation complète

- **Technique** : `/docs/technique/atak-roleplay-simulation.md`
- **Nouvelles fonctionnalités** : `/docs/ROLEPLAY-NOUVELLES-FONCTIONNALITES.md`
- **ATAK Enhanced** : `/docs/ROLEPLAY-ATAK-ENHANCED.md`
- **Zones géographiques** : `/docs/ROLEPLAY-ZONES-GEOGRAPHIQUES.md`
- **Mode Troll** : `/docs/ROLEPLAY-MODE-TROLL.md`

---

**Besoin d'aide ?** Contactez l'équipe technique sur le Discord COMSPEC.
