# Roadmap C2 — COMSPEC Overwatch / Athena

Pistes d’évolution pour transformer le mod Arma et l’interface ATAK en outil de Command & Control (C2) professionnel.

---

## 1. Module « Blue Force Tracking » (BFT) avancé

**Objectif :** Enrichir le payload PLI pour que l’overlay ATAK affiche des infos tactiques utiles.

| Donnée | Source Arma | Usage côté ATAK |
|--------|-------------|------------------|
| État santé | ACE Medical (`ace_medical_woundReceived`, `ace_medical_bloodVolume`, `incapacitated`) | Icône unité : clignoter rouge si blessé/inconscient |
| Inventaire / carburant | Si dans véhicule : `fuel vehicle player`, munitions (inventory) | Badge ou tooltip sur l’icône (fuel %, ammo) |
| Rôle tactique | `roleDescription player` (BIS) ou variable mission | Icône MIL-STD-2525 adaptée (Medic, JTAC, Squad Leader) |

**Implémentation :**
- Étendre le payload dans **fnc_updatePosition.sqf** (ou équivalent) : ajouter champs optionnels `health`, `fuel`, `ammo`, `role`.
- Côté DLL : accepter un JSON enrichi ou des args supplémentaires et les transmettre à `POST /api/atak/position` (ou endpoint dédié BFT).
- Côté serveur : stocker ces champs (colonnes ou JSON `extra`) et les exposer au front pour affichage (couleur, icône, tooltip).

---

## 2. Module « Remote Designator » (liaison JTAC)

**Objectif :** Envoyer en temps réel la position de la cible désignée au laser vers l’ATAK.

- **Laser hook :** Si le joueur utilise un désignateur laser, récupérer la cible avec `laserTarget player` (ou API cTAB/ACE selon le mod).
- Envoyer périodiquement (ou à changement) : position du laser (monde ou écran) vers l’API (ex. `POST /api/atak/designator` ou type INTEL `DESIGNATOR`).
- **Cas d’usage :** Le commandant sur l’interface web voit où le JTAC pointe et peut valider la frappe via le chat ou un bouton dédié.

**Implémentation :**
- SQF : boucle ou event qui lit `laserTarget player` ; si valide, appeler la DLL (nouvelle commande `SendDesignator` ou `SendIntel` type `DESIGNATOR`).
- DLL : POST vers endpoint dédié (ex. `/api/atak/designator`) avec `pos_x`, `pos_y`, `call_sign`, `timestamp`.
- Serveur : stocker la dernière position designator par unité ; front : afficher un marqueur spécial (croix, ligne JTAC → cible).

---

## 3. Système bi-directionnel — Marqueurs & ordres (Web → Arma)

**Objectif :** Permettre au Web d'écrire dans Arma : le commandement dessine sur le dashboard Athena (LZ, axe d'effort, zone d'exclusion) et les opérateurs voient ces marqueurs en jeu. Données aujourd'hui Arma → ATAK ; l'enrichissement ultime est API → Arma.

### 3.1 Sync sortante (Arma → API)

- Détecter les marqueurs créés/modifiés/supprimés en jeu (events ou polling des marqueurs joueur/side).
- Pour chaque marqueur : type, position, texte, forme, couleur.
- Envoyer à l’API (ex. `POST /api/markers` ou bulk `POST /api/atak/markers/sync`) pour dessin sur la Tacmap.

### 3.2 Sync entrante (API → Arma) — « le graal »

- **Polling (C#) :** La DLL interroge **`GET /api/atak/markers`** **toutes les 10 secondes** avec `HttpClient.GetAsync`.
- Parser la réponse JSON (liste de marqueurs).
- Retourner les données à Arma (format lisible par l’extension : string ou plusieurs appels).
- **Marqueurs dynamiques :** Côté SQF, utiliser **`createMarkerLocal`** pour afficher sur la carte de tous les opérateurs les zones (LZ, axe d'effort) dessinées par le commandement sur Athena ; mise à jour / suppression avec `setMarkerPosLocal`, `deleteMarkerLocal`.

**BFT de groupe :** Synchroniser les positions des autres groupes (IA ou joueurs) que le groupe local ne voit pas forcément (GET ex. `GET /api/atak/units`) ; en SQF créer des marqueurs locaux pour chaque unité amie distante → situation tactique consolidée.

**Contraintes :**
- Limite de taille de la sortie extension Arma (buffer ~8 KB) : pagination ou delta (seuls marqueurs modifiés depuis dernier GET).
- Fréquence du GET : **10 s** (polling côté DLL).

---

## 4. Améliorations DLL (C#)

### 4.1 Upload d’images cTAB (lecture disque)

- **Problème :** Arma ne peut pas envoyer un gros Base64 dans l’extension (buffer ~8 KB).
- **Solution :** La DLL reçoit uniquement le **chemin fichier** (ex. `_imgPath` fourni par cTAB).
- Nouvelle commande (ex. `UploadImage`) : `args[0]` = chemin fichier.
- En C# : `File.ReadAllBytes(path)` → `Convert.ToBase64String(bytes)` → POST vers `POST /api/intel/photos` (body JSON avec base64 ou multipart selon l’API).

### 4.2 File d’attente (queue) en cas de perte de réseau

- **Objectif :** En cas d’indisponibilité du backend, ne pas perdre les mises à jour.
- Utiliser une `ConcurrentQueue` (ou équivalent) pour les requêtes (position, intel, marqueurs).
- Envoi asynchrone : en cas d’échec HTTP, ré-enfiler la requête (avec limite de taille pour éviter saturation mémoire).
- Quand le réseau revient (succès HTTP), vider la queue par rafales (avec throttle pour ne pas surcharger le serveur).

---

## 5. Intégration radio (TFAR / ACRE)

**Objectif :** Afficher sur le dashboard Athena la fréquence radio actuelle de chaque opérateur.

- **TFAR :** Variables / API TFAR pour récupérer la fréquence active du joueur.
- **ACRE :** Idem selon l’API ACRE.
- Envoyer dans le payload PLI (ou champ dédié) : `radio_freq`, `radio_lr`, etc.
- Côté ATAK : afficher la fréquence dans le tooltip ou un panneau « Radios » pour faciliter la coordination des appuis.

**Implémentation :**
- SQF : détection du mod (TFAR / ACRE) et récupération des fréquences (voir doc des mods).
- Ajout des champs au payload BFT ou à un message dédié ; API + front pour affichage.

---

## 6. Signal Intelligence (SIGINT)

**Objectif :** Enrichir la Tacmap avec les métadonnées radio (TFAR / ACRE2) et, en option, simuler une capacité de renseignement d'origine électromagnétique.

### 6.1 Fréquences (métadonnée unités)

- Afficher sur l'ATAK la **fréquence active de chaque leader** (ou de chaque unité équipée radio).
- Données envoyées via le payload BFT / radio (§5) ; le front affiche ces infos dans un panneau « Radios » ou dans le tooltip des icônes BFT.

### 6.2 Analyse de spectre / triangulation (simulation)

- **Principe :** Si une unité IA ennemie utilise une radio (simulée en mission), simuler une **triangulation** à partir de plusieurs écoutes (positions des opérateurs amis + « détection » de l'émission).
- **Côté serveur :** À partir des rapports « émission détectée » (position de l'écouteur, bearing optionnel), calculer une zone d'incertitude (cercle ou ellipse).
- **Affichage ATAK :** Faire apparaître une **zone d'incertitude** (ex. **cercle rouge**) sur la Tacmap pour la position probable de l'émetteur ennemi, sans révéler la position exacte (effet SIGINT réaliste).

---

## Ordre de priorité suggéré

1. **BFT avancé** (santé, rôle) — impact direct sur la lisibilité de la carte.
2. **Upload image cTAB** (lecture disque dans la DLL) — finalise le flux photos sans dépasser le buffer.
3. **Marker sync sortante** (Arma → API) — les marqueurs en jeu apparaissent sur la Tacmap.
4. **Remote Designator** (JTAC) — forte valeur pour l’immersion et la coordination.
5. **Système bi-directionnel** (polling 10 s, createMarkerLocal, BFT de groupe) — le Web écrit dans Arma (marqueurs & ordres).
6. **Queue DLL** — robustesse en conditions réseau dégradées.
7. **Radio TFAR/ACRE** — affichage des fréquences sur le dashboard (prérequis SIGINT).
8. **SIGINT** — fréquences leaders + (optionnel) triangulation / zone d’incertitude (cercle rouge) sur l’ATAK.

---

## Références techniques

- **DLL actuelle :** `mod/COMSPECExtension/Extension.cs` (Connect, UpdatePosition, SendIntel).
- **API PHP :** `app/Controllers/Api/AtakApiController.php` (`/api/atak/position`, `/api/atak/intel`, `/api/atak/markers`, etc.). Ancien serveur Node archivé dans `server/`.
- **Connect SQF :** `addons/connect/functions/` (fnc_connect, fnc_updatePosition, fnc_sendIntel).
