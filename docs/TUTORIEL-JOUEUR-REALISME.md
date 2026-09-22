# 🎮 Tutoriel Joueur — Système réalisme ATHENA C2

> **Pour qui ?** Joueurs utilisant ATAK/Tacmap et les terminaux tactiques en jeu.  
> **Prérequis :** Mod ATHENA chargé, compte Tacmap configuré.

---

## 📡 1. Les relais radio

### 🎯 Qu'est-ce qu'un relais ?

Un **relais radio** étend la portée de transmission des terminaux ATAK. Sans relais, vos données tactiques (marqueurs, positions, contacts) ne sont transmises qu'aux joueurs **à portée directe** (selon config serveur).

Avec un réseau de relais bien déployé, vous couvrez toute la zone opérationnelle.

### 📦 Obtenir un kit relais

1. Au QG, ouvrez l'**Arsenal ACE** (molette ACE > Arsenal)
2. Allez dans **Items**
3. Cherchez `ATHENA - Kit Relais Radio`
4. Prenez-en 1 ou 2 (selon votre rôle)

💡 **Rôles recommandés :** Chef de section, opérateur comm, ingénieur.

### 🛠️ Poser un relais

1. **Choisissez bien votre position :**
   - En hauteur (toit, colline) = meilleure portée
   - Pas dans un bâtiment fermé (murs bloquent signal)
   - Visible depuis vos routes d'approche

2. **Poser le relais :**
   - Molette ACE > `ATHENA` > `📡 Poser relais radio`
   - Une antenne portable apparaît au sol
   - Un message affiche : `✅ Relais posé - Portée : 2000m - UID : RELAY_xxx`

3. **Vérifier le statut :**
   - Visez le relais
   - Molette ACE > `📡 Voir statut relais`
   - Infos affichées :
     - **Portée** : distance max de couverture
     - **Débit** : bande passante disponible (kbps)
     - **Connexions** : nombre de terminaux connectés / max
     - **Statut** : ACTIVE, DEGRADED (météo), OFFLINE (détruit)

### 🌐 Réseau maillé (mesh)

Les relais **communiquent entre eux** automatiquement si à portée :

```
[Joueur A] ←→ [Relais 1] ←→ [Relais 2] ←→ [Joueur B]
     500m          2000m         2000m         500m
```

Joueur A et B sont séparés de 5 km mais **reliés** via 2 relais.

💡 **Astuce :** Espacez vos relais de 1500-1800m pour garantir liaison (marge pour terrain).

### 🌦️ Effet de la météo

Si le **mode réalisme météo** est activé :

| Météo | Effet portée | Effet débit | 🎯 Conseil |
|-------|-------------|------------|-----------|
| ☀️ Clair | Aucun | Aucun | Conditions idéales |
| 🌧️ Pluie | -15% | -10% | Acceptable |
| 🌫️ Brouillard | -30% | -20% | Prévoir relais supplémentaires |
| ⛈️ Orage | -40% | -35% | Délais transmissions accentués |
| 💨 Vent fort (>50 km/h) | -5% par 10 km/h | Aucun | Antenne physiquement affectée |

**Exemple réel :**
- Portée nominale : 2000m
- Météo : pluie + vent 70 km/h
- Portée effective : 2000m × 0.85 (pluie) × 0.90 (vent) = **1530m**

🎮 **En jeu :**
- Votre **HUD** affiche la météo en temps réel (coin supérieur gauche)
- Le **Tacmap** affiche les cercles de portée **effectifs** (ajustés météo)
- Les relais en zone dégradée apparaissent en **orange** au lieu de bleu

### 🔐 Certificats électroniques

Sur certains serveurs, chaque terminal doit avoir un **certificat valide** pour transmettre.

**Obtenir un certificat :**
1. Connectez-vous à **Tacmap** (web)
2. Menu `Mon profil` > `Certificats ATAK`
3. Cliquez `Générer certificat`
4. Le certificat est automatiquement synchronisé en jeu

**Durée de validité :** 365 jours (par défaut)

**Si expiré :**
- Message en jeu : `⚠️ Certificat expiré, transmission bloquée`
- Renouvelez via Tacmap ou demandez au QG

💡 **Mode arcade :** Certificats désactivés, transmission libre.

---

## 🎯 2. Control Measures (Doctrine)

### 📍 Qu'est-ce qu'un control measure ?

Les **control measures** sont des symboles doctrine (MIL-STD-2525D) pour planifier et coordonner les opérations.

**Types disponibles :**

| Symbole | Nom | Usage |
|---------|-----|-------|
| ➡️ | **Axis of Advance** (axe d'attaque) | Corridor d'attaque nommé (ex: AXIS NEPTUNE) |
| 🟢 | **Line of Departure (LD)** | Ligne depuis laquelle l'attaque démarre |
| 🔴 | **Limit of Advance (LOA)** | Ligne au-delà de laquelle ne pas progresser |
| 🟡 | **Phase Line (PL)** | Marqueur de phase opérationnelle |
| 🎯 | **Objective (OBJ)** | Zone/point à capturer (ex: OBJ WOLF) |
| 📍 | **Checkpoint (CP)** | Point de repère navigation/rapports |

### 🖥️ Créer un control measure (Chef d'équipe / Commandant)

**Via Tacmap (web) :**

1. Ouvrez **Tacmap**
2. Menu `Planification` > `Control Measures`
3. Cliquez `+ Nouveau`
4. Choisissez le type (Axis, LD, Objectif...)
5. Dessinez sur la carte :
   - **Axis / LD / LOA / Phase Line** : cliquez pour tracer une ligne
   - **Objective / Checkpoint** : cliquez pour placer un cercle
6. Nommez l'élément (ex: "AXIS NEPTUNE", "OBJ HOTEL")
7. Sauvegardez

**Le control measure apparaît immédiatement :**
- Sur **Tacmap** pour tous les joueurs autorisés
- Sur **HUD Zeus** pour les game masters
- Sur **carte en jeu** (M) pour les joueurs équipés ATAK

### 🎮 Utilisation en jeu

**Axes d'attaque (Axis of Advance) :**

```
        AXIS MARS (corridor 500m)
            ↓
    [LD BLUE]──────────────→
         ↓                 ↓
    [PL ORANGE]       [OBJ WOLF]
         ↓                 ↓
    [LOA RED]─────────────→
```

1. **LD BLUE** : à H-Hour, unités franchissent la LD
2. **AXIS MARS** : progression dans le corridor de 500m de large
3. **PL ORANGE** : phase 1 complétée, début phase 2
4. **OBJ WOLF** : objectif à sécuriser (rayon 200m)
5. **LOA RED** : limite max, ne pas dépasser sans ordre

**Checkpoints (navigation) :**

Dans vos rapports radio :
> "Blue 1 to HQ, passing CP3, heading CP4, ETA 5 mikes"

Au lieu de coordonnées GPS complexes.

### 👁️ Visibilité

**Par défaut :**
- Control measures visibles par **votre équipe uniquement**
- Admin peut changer : équipe, faction, tous

**Permissions édition :**
- Commander
- Platoon Leader
- Squad Leader
- (configurable par serveur)

---

## 🛰️ 3. Renseignement satellite (ISR)

### 📡 Demander un passage satellite

> **Rôle requis :** Officier renseignement, Commandant, ou autorisé RBAC

**Via Tacmap :**

1. Menu `Renseignement` > `Tasking satellite`
2. Sélectionnez **zone d'intérêt (AOI)** sur la carte (rectangle)
3. Choisissez **capteur** :
   - **EO (électro-optique)** : imagerie visuelle, bloquée par nuages
   - **Thermique** : signatures chaleur, fonctionne de nuit/nuages
4. Cliquez `Soumettre demande`

**Latence :** 5 à 30 minutes (fenêtre de passage satellite réaliste)

### 📊 Résultats

Le satellite **NE DONNE PAS** positions exactes ennemies (ce serait trop facile).

**Vous recevez :**
- **Nombre de contacts thermiques** détectés
- **Clusters** (groupes) et dispersion
- **Mouvement** (statique / mobile)
- **Signatures** (infanterie / véhicule léger / blindé)

**Exemple :**

```
📡 RÉSULTAT ISR — ZONE KAVALA NORD
Capteur: Thermique
Heure: 14:32:17 UTC

Contacts détectés: 18
  - Cluster A (12 contacts) : dispersés 50m, statiques
    → Probable infanterie embusquée
  - Cluster B (4 contacts) : compacts 10m, mobiles 15 km/h
    → Probable patrouille véhicule léger
  - Isolé C (2 contacts) : statiques, signature élevée
    → Probable observateurs isolés
```

💡 **Vous devez interpréter :** Ennemis ? Civils ? Alliés égarés ?

### ☁️ Météo et ISR

- **EO** : bloqué si couverture nuageuse > 70%
- **Thermique** : toujours fonctionnel, mais légère dégradation si pluie forte

### 📡 Téléchargement imagerie

Le **téléchargement consomme du débit de votre relais** le plus proche.

- **Imagerie basse résolution** : 2 MB → ~10s via relais (256 kbps)
- **Haute résolution** : 20 MB → ~2 minutes

⚠️ Si relais saturé (plusieurs demandes simultanées), **délai augmenté**.

---

## 🗺️ 4. Itinéraires GPS

### 🛣️ Calcul itinéraire réaliste

Si le serveur active **"use_road_network"**, les itinéraires GPS suivent les **vraies routes** de la map Arma.

**Via Tacmap :**

1. Menu `Navigation` > `Nouvel itinéraire`
2. Cliquez point départ, puis destination
3. L'itinéraire se trace automatiquement sur le réseau routier
4. Villes traversées affichées : `Kavala → Agios Dionysios → Athira`

**En jeu :**

- Itinéraire synchronisé sur votre **GPS terminal ATAK**
- Waypoints visibles sur carte (M)
- **HUD** affiche prochain waypoint + distance

💡 **Astuce :** Créez itinéraires **avant mission** pour gagner du temps sur le terrain.

### 📏 Simplification waypoints

Config serveur : `simplification_threshold_m` (défaut: 50m)

Waypoints rapprochés fusionnés automatiquement → itinéraire plus lisible.

---

## 💥 5. Dommages terminaux

### 🔫 Impacts et explosions

Si **"terminal_damage_enabled"** est actif, votre terminal ATAK peut être endommagé :

- **Impact balle proche** (< 2m) : 1 point dégât
- **Explosion** (< 10m) : 1 à 3 points selon distance
- **Destruction** : après 3 impacts (par défaut)

**Symptômes terminal endommagé :**
- ⚠️ Icône fissurée sur HUD
- Ralentissements transmissions
- (si config avancée) Perte GPS ou caméra

### 🛠️ Réparation

1. Obtenez **Kit réparation ATAK** (Arsenal ACE > Items)
2. Molette ACE > `ATHENA` > `🔧 Réparer terminal ATAK`
3. Action de 5 minutes (configurable)
4. Terminal restauré

💡 **Mode arcade :** Dommages terminaux désactivés.

---

## 🎨 6. Symbologie tactique

### 🎖️ Standard MIL-STD-2525D

Tous les symboles sur Tacmap et en jeu suivent **MIL-STD-2525D** (US) ou **APP-6D** (OTAN).

**Couleurs standard :**
- 🔵 **Bleu** : Amis
- 🔴 **Rouge** : Ennemis
- 🟢 **Vert** : Neutres / Civils
- 🟡 **Jaune** : Inconnus

### 📐 Taille symboles

3 tailles disponibles (config serveur) :
- **Petit** : cartes très denses (>100 unités)
- **Moyen** : recommandé (lisibilité optimale)
- **Grand** : briefings, événements

---

## 📊 7. HUD Réalisme (In-Game)

### 🖥️ Éléments affichés

**En haut à gauche :**

```
🌦️ MÉTÉO
  Pluie: 0.3 (légère)
  Brouillard: 0.1
  Vent: 35 km/h NE

📡 RELAIS
  Connecté: RELAY_4891
  Portée effective: 1700m (météo -15%)
  Débit: 230 kbps (10% utilisé)

🔐 CERTIFICAT
  Statut: ✅ Valide
  Expire: 234j
```

**En bas :**
- Prochain waypoint + distance
- Contacts ATAK synchronisés
- Alertes (certificat expiré, relais hors portée...)

### ⚙️ Masquer HUD

Si vous trouvez le HUD trop chargé :

- **CBA Settings** (Échap > Addons > CBA Settings)
- `ATHENA` > `Interface` > `Afficher HUD réalisme` : décocher

---

## 🎯 8. Profils de jeu

Votre serveur peut utiliser des **profils préconfigurés** :

### 🟢 Débutant (Arcade)

- ✅ Portée relais : 5000m
- ❌ Certificats désactivés
- ❌ Dommages terminaux désactivés
- ❌ Météo n'affecte pas comms
- 🎯 **Pour :** Découverte, événements publics

### 🟡 Événement (Équilibré)

- ✅ Portée relais : 3000m
- ✅ Certificats activés (renouvelables)
- ✅ Dommages terminaux (5 impacts max)
- ✅ Météo affecte portée (-15% max)
- 🎯 **Pour :** Missions communautaires, gameplay équilibré

### 🔴 Expert (Simulation)

- ✅ Portée relais : 2000m
- ✅ Certificats requis (révocation si capturé)
- ✅ Dommages terminaux (2 impacts → destruction)
- ✅ Météo (-40% max), vent, orages
- ✅ Coupures réseau aléatoires
- ✅ Zones roleplay (brouillage)
- 🎯 **Pour :** Unités milsim, entraînements réalistes

---

## ❓ FAQ

**Q : Mon terminal dit "Hors portée relais", que faire ?**  
R : Rapprochez-vous d'un relais ou demandez à un coéquipier d'en poser un entre vous et le réseau.

**Q : Pourquoi mon marqueur n'apparaît pas sur Tacmap ?**  
R : 1) Vérifiez connexion relais, 2) Vérifiez certificat valide, 3) Attendez 10-15s (latence sync).

**Q : Puis-je détruire le relais d'un adversaire ?**  
R : Oui, tirez dessus ou posez explosif. Il disparaîtra du réseau ennemi.

**Q : Comment savoir si je suis sous couverture satellite ?**  
R : Vous ne savez pas (réaliste). Évitez zones découvertes si renseignement ennemi actif.

**Q : Dois-je poser des relais en mode Débutant ?**  
R : Non obligatoire (portée 5 km couvre souvent toute AO), mais utile pour cohésion d'équipe.

---

## 🎓 Entraînement recommandé

1. **Solo (VR) :** Posez 3 relais, testez connexion à différentes distances
2. **Duo :** Un pose relais, l'autre se déplace + vérifie portée
3. **Section :** Mission réelle, réseau de 5-6 relais, coordonnez via Tacmap

---

## 🔗 Liens utiles

- **Tacmap web** : https://athena.votreserveur.com/tacmap
- **Arsenal loadouts** : Guide dans #briefings Discord
- **Rapports bugs** : #support-technique Discord
- **Guide Zeus** : Pour game masters (séparé)

---

**Bon jeu, et bonne chance sur le terrain ! 🎖️**
