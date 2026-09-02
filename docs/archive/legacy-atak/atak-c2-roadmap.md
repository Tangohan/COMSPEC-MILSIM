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

## 5. Intégration radio (TFAR / ACRE) — livré (MVP)

**Objectif :** Afficher qui émet / sur quel réseau près d’un opérateur, et permettre de surveiller un canal **en jeu**.

### Ce qui marche

| Capacité | Avec ACRE2 | Avec TFAR | Sans module |
|----------|------------|-----------|-------------|
| Pastille « Émet » (Tacmap + tablette) | Oui (`isBroadcasting` / `isSpeaking`) | Oui (`tf_isSpeaking`) | Non — message « Module radio non détecté » |
| Canal / réseau dans `extra` BFT | Oui | Fréquence SW | Champs absents / vides |
| Liste proximité (rayon CBA) | Oui (scan local) | Oui (speaking) | UI grisée |
| Surveiller le réseau (écoute audio) | Oui en jeu : bascule canal radio active ; spectateur ACRE si déjà en mode observation | Intention + message (régler la freq) | Impossible |

### Limites honnêtes

- **Pas d’écoute audio dans le navigateur** (`/atak`) : métadonnées seulement (pastilles, canal, distance, suivi de canal). Un éventuel WebRTC est hors scope.
- Sur `/atak`, « Surveiller ce réseau » = **suivi qui émet** (highlight + toast/bip), pas de bascule audio. La bascule canal / écoute se fait **en jeu** (tablette Overwatch).
- Relais = **même réseau radio** (canal), pas un flux audio 3D monde séparé côté Athena.
- Canal distant ACRE : best-effort via événements `acre_remoteStartedSpeaking` (cache radioId/canal) ; sinon canal local uniquement.
- Pas de stream serveur : enrichissement du payload `POST /api/atak/position` → `extra.radio_*`.

### Où voir les pastilles

- Tacmap `/atak` : marqueurs BFT + pastille orange « Émet », vitals Effectifs, onglet **Radio** (proximité + surveillance de canal web).
- Tablette Overwatch HTML : pastilles sur contacts + vue **Radio** (Surveiller ce réseau → audio en jeu).

### Settings CBA (groupe COMSPEC Overwatch)

- `Surveillance radio à proximité` (défaut : oui)
- `Rayon radio proximité (m)` (défaut : **75**, plage 10–300)
- `Intervalle scan radio (s)` (défaut : **2**, plage 1–10)

### Retest

1. Charger Overwatch + ACRE2 (ou TFAR) → PTT → pastille « Émet » sur Tacmap sous ~1–2 s.
2. Sans ACRE/TFAR → onglet Radio affiche « Module radio non détecté » ; option masquer l’onglet.
3. `/atak` → Radio → « Surveiller ce réseau » → badge À l’écoute ; PTT sur ce canal → toast + bip.
4. Tablette → Radio → « Surveiller ce réseau » bascule le canal (ACRE) ; notification en jeu.
5. Régler le rayon (UI web ou CBA) et vérifier la liste proximité.

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

## Backlog gameplay réaliste — lots à ajouter ou à peaufiner

Cette priorisation privilégie les fonctionnalités qui obligent le commandement à prendre une décision, plutôt que la simple collecte de télémétrie. Chaque donnée doit avoir une **source**, une **fraîcheur**, un **niveau de confiance** et un **effet gameplay** visible. Une valeur inconnue ne doit jamais être remplacée par une valeur rassurante (`100 %`, « OK » ou position exacte).

### Lot G1 — BFT enrichi et honnêteté de l'information (priorité P0)

**À peaufiner**

- Ajouter à chaque contact `observed_at`, `received_at`, `source`, `confidence` et `link_state` (`live`, `delayed`, `lost`).
- Distinguer la dernière position reçue de la position extrapolée. Une trace extrapolée doit être en pointillés, afficher son âge et disparaître après un TTL configurable.
- Consolider dans la fiche unité : indicatif, rôle, groupe, état médical synthétique, véhicule embarqué et dernier rapport logistique.
- Appliquer le fog of war par rôle et par réseau : un opérateur ne voit que les contacts effectivement publiés sur son réseau ou relayés par le C2.

**Critères d'acceptation**

1. Une coupure n'immobilise pas silencieusement l'icône : le badge passe à « retardé », puis « liaison perdue ».
2. L'âge et la source de chaque contact sont visibles sans ouvrir un écran d'administration.
3. Le replay conserve la donnée reçue et l'éventuelle extrapolation comme deux états distincts.
4. Le serveur refuse qu'une télémétrie plus ancienne écrase une observation plus récente.

**Effet gameplay :** le chef choisit s'il agit sur une information fraîche, attend une confirmation ou envoie une reconnaissance.

### Lot G2 — Santé, alertes et chaîne MEDEVAC (priorité P0)

**À ajouter**

- Produire un état médical normalisé indépendant du mod : `green`, `wounded`, `critical`, `unconscious`, `dead`, `unknown`.
- Avec ACE/KAT, remonter uniquement les vitaux utiles à la décision : conscience, fréquence cardiaque, pression artérielle, SpO2, perte de sang estimée et horodatage de mesure.
- Déclencher une alerte sur **transition d'état**, pas à chaque poll, avec acquittement et déduplication.
- Transformer une alerte critique en demande MEDEVAC : position de prise en charge, nombre et priorité des patients, menace/LZ, moyens requis et destination médicale.
- Suivre les étapes `requested → assigned → en_route → on_scene → evacuated → closed`, avec annulation motivée et temps passé dans chaque étape.

**Critères d'acceptation**

1. Sans ACE/KAT ou sans mesure récente, l'interface affiche « inconnu » et n'invente aucun vital.
2. Un inconscient génère une seule alerte active ; un changement réel de gravité peut la réouvrir.
3. Le décès clôt ou requalifie explicitement la demande au lieu de laisser un statut « inconscient ».
4. Le journal AAR calcule les délais détection–demande, demande–affectation et affectation–évacuation.

**Effet gameplay :** le commandement arbitre entre poursuite de mission, stabilisation sur place et mobilisation d'un vecteur d'évacuation.

### Lot G3 — Logistique véhicules et munitions (priorité P0)

**À ajouter**

- Créer un manifeste par véhicule : carburant, état moteur/roues, équipage, capacité cargo et état de liaison du terminal embarqué.
- Remonter les munitions par **catégorie tactique** dans le MVP (`rifle`, `mg`, `at`, `aa`, `grenade`, `smoke`, `medical`) afin d'éviter un payload inventaire trop volumineux.
- Calculer une autonomie estimée à partir de la consommation observée, mais afficher simultanément la quantité brute et l'heure de la dernière mesure.
- Définir des seuils par type d'unité et mission ; un niveau bas crée une demande logistique, pas un réapprovisionnement automatique.
- Suivre les demandes `draft → requested → approved → loaded → en_route → delivered → reconciled` et rapprocher quantité chargée, livrée et consommée.

**Critères d'acceptation**

1. Les consommations dans Arma diminuent les stocks visibles ; aucune ressource ne revient à sa valeur initiale après reconnexion.
2. Deux terminaux ne peuvent pas valider deux fois la même livraison (clé d'idempotence).
3. Une estimation est clairement distinguée d'un comptage d'inventaire récent.
4. La destruction ou l'abandon d'un véhicule rend son stock indisponible et laisse une trace dans l'AAR.

**Effet gameplay :** les joueurs doivent anticiper le ravitaillement, sécuriser les convois et adapter leur consommation.

### Lot G4 — Liaison réaliste et terminal dégradé (priorité P1)

**À peaufiner**

- Modéliser une qualité de service par zone et relais : latence, gigue, perte de paquets, débit et coupure complète.
- Appliquer ces contraintes à la donnée métier : BFT basse priorité regroupé, urgence médicale prioritaire, photo différée ou compressée.
- Introduire une boîte d'envoi locale bornée, avec identifiant idempotent, tentative suivante, expiration et raison d'abandon.
- Séparer quatre états dans l'UI : **serveur joignable**, **flux à jour**, **données en attente**, **terminal endommagé**.
- Pour un écran endommagé, dégrader la lisibilité et certaines interactions sans fabriquer une panne réseau ; prévoir réparation, remplacement ou terminal de secours.

**Critères d'acceptation**

1. Un scénario de zone blanche rejoue les messages prioritaires dans l'ordre après retour de liaison, sans doublon serveur.
2. Une photo en attente ne bloque jamais un PANIC/MEDEVAC.
3. L'opérateur voit le nombre et l'âge des éléments en file, ainsi que la dernière synchronisation réussie.
4. Les paramètres de dégradation sont pilotables par mission et enregistrés dans le replay.

**Effet gameplay :** le terrain et les relais conditionnent réellement le C2 ; les procédures dégradées deviennent utiles.

### Lot G5 — Renseignement structuré SSE / DOMEX (priorité P1)

**À ajouter**

- Utiliser un cycle commun `captured → triaged → exploited → assessed → disseminated → archived` pour notes, photos, documents et matériels saisis.
- Imposer le triplet **source–fiabilité–crédibilité** et conserver les hypothèses séparément des faits observés.
- Gérer la provenance : auteur, position/heure de collecte, pièce d'origine, transformations, détenteurs successifs et empreinte du fichier.
- Relier chaque élément à une entité (personne, véhicule, lieu, unité, événement) et permettre la fusion sans perdre les alias ni les sources contradictoires.
- Générer des rapports normalisés (SALUTE, SPOTREP, SITREP, exploitation DOMEX) et contrôler leur diffusion par réseau et rôle.

**Critères d'acceptation**

1. Une photo ou une note non triée n'apparaît pas comme renseignement confirmé sur la carte.
2. Toute modification d'une conclusion conserve son auteur, sa justification et la version précédente.
3. Deux rapports contradictoires restent consultables ; la consolidation ne détruit aucune source.
4. Une transmission hors liaison rejoint la file locale et conserve son horodatage de collecte initial.

**Effet gameplay :** fouiller, exploiter, recouper et diffuser devient une boucle de jeu complète au lieu d'un simple dépôt de marqueur GPS.

### Lot G6 — Extensions ACRE, KAT et inventaire détaillé (priorité P2)

Ces intégrations arrivent **après** les modèles génériques précédents : elles doivent enrichir une capacité existante, jamais rendre le cœur ATAK dépendant d'un mod.

| Extension | Incrément conseillé | Mode de repli obligatoire |
|---|---|---|
| ACRE2 complet | réseaux réellement accessibles, puissance/antenne, relais, événement PTT et qualité de réception ; le contenu audio reste hors navigateur | métadonnées radio absentes, réseau C2 générique |
| KAT Medical | vitaux avancés, voies aériennes, pneumothorax, traitements et tendance clinique | état médical ACE ou état Arma normalisé |
| Inventaire détaillé | comptage chargeurs/munitions, compatibilité arme, lot cargo, masse et transferts joueur–véhicule | catégories tactiques agrégées du lot G3 |

**Garde-fous :** détection explicite de la disponibilité du mod, version de schéma dans chaque payload, champs optionnels, fréquence d'échantillonnage plafonnée et tests avec/sans extension.

### Découpage de livraison recommandé

| Incrément | Contenu démontrable | Indicateur de réussite |
|---|---|---|
| **MVP 1 — Décider sous information imparfaite** | fraîcheur BFT, perte de liaison, états santé normalisés | aucune donnée obsolète présentée comme « live » |
| **MVP 2 — Sauver** | alerte dédupliquée, demande et suivi MEDEVAC | chaîne complète rejouable dans l'AAR |
| **MVP 3 — Soutenir** | stocks agrégés, seuils, demandes et livraisons | bilan matière cohérent avant/après mission |
| **MVP 4 — Exploiter** | collecte SSE/DOMEX, qualification, rapports et diffusion | chaque conclusion retourne à ses sources |
| **Extension 1 — Dégrader** | profils radio/terrain, priorité et file hors ligne | reprise sans perte ni doublon après coupure |
| **Extension 2 — Spécialiser** | ACRE2, KAT et inventaire fin | même scénario valide avec extension absente |

### Mesures transverses à instrumenter

- **BFT :** âge médian/p95 des positions, taux de contacts `unknown`, nombre de corrections après extrapolation.
- **Médical :** délais de prise en compte et d'évacuation, alertes dupliquées évitées, patients sans destination.
- **Logistique :** écart de rapprochement des stocks, ruptures, demandes non honorées et pertes en transit.
- **Liaison :** taux de perte, profondeur/âge maximal de file, délai de reprise et messages expirés.
- **Intel :** délai collecte–diffusion, proportion de rapports qualifiés et conclusions sans provenance.

Ces métriques doivent alimenter le débriefing et l'équilibrage ; elles ne doivent pas devenir un classement individuel hors contexte.

---

## Références techniques

- **DLL actuelle :** `mod/COMSPECExtension/Extension.cs` (Connect, UpdatePosition, SendIntel).
- **API PHP :** `app/Controllers/Api/AtakApiController.php` (`/api/atak/position`, `/api/atak/intel`, `/api/atak/markers`, etc.). Ancien serveur Node archivé dans `server/`.
- **Connect SQF :** `addons/connect/functions/` (fnc_connect, fnc_updatePosition, fnc_sendIntel).

---

## 7. Système « After Action Review » (AAR) automatique

**Constat actuel :** le dispositif de relecture post-mission est partiel et ne couvre pas toute la chaîne décisionnelle.

**Feature :**
- **Replay mission** consolidé (trajectoires unités, chronologie intel, événements clés).
- **Extraction automatique** des erreurs tactiques, délais de réaction, points de rupture C2.
- **Export PDF / formation** : génération d’un dossier AAR standardisé exploitable en instruction.

**Impact :** boucle complète **opération → analyse → formation**, avec capitalisation progressive de la doctrine.

---

## 8. Système d’autorité dynamique (chaîne de commandement)

**Constat actuel :** la chaîne de commandement est majoritairement statique.

**Feature :**
- **Commandement temps réel** : transfert d’autorité en mission (délégation explicite).
- **Fallback automatique** : en cas de perte leader, bascule vers successeur doctrinal.
- **Hiérarchie visible sur carte** : visualisation claire des niveaux de commandement.

**Impact :** réalisme tactique renforcé et meilleure cohérence avec les schémas de commandement militaires.

---

## 9. Système « Fog of War »

**Constat actuel :** visibilité globale trop proche d’un mode omniscient.

**Feature :**
- Masquage de l’information selon **distance**, **rôle**, **capteurs disponibles**.
- **Intel dégradé** : précision variable (ellipse/zone), latence de transmission, fraîcheur des données.

**Impact :** passage d’un outil de supervision totale à un simulateur C2 crédible sous incertitude.

---

## 10. Intelligence artificielle décisionnelle (serveur)

**Constat actuel :** absence d’assistant d’aide à la décision en temps réel.

**Feature :**
- Détection continue des **zones dangereuses**, unités isolées, ruptures de dispositif.
- Suggestions automatiques : **repositionnement**, **évacuation**, **CAS recommandé**.

**Impact :** ajout d’un assistant état-major orienté réduction du temps de décision.

---

## 11. Système logistique opérationnel

**Constat actuel :** module logistique tactique absent du cycle mission.

**Feature :**
- Gestion des stocks et consommables : **carburant**, **munitions**, **médical**.
- Routage ravitaillement et priorisation des convois.
- Intégration directe au BFT enrichi (icônes/indicateurs fuel/ammo).

**Impact :** profondeur stratégique accrue et meilleure préparation du tempo opérationnel.

---

## 12. Workflow judiciaire / traçabilité

**Constat actuel :** la traçabilité opérationnelle n’est pas encore structurée comme un dossier probatoire.

**Feature :**
- Journal inviolable des **ordres**, **décisions**, **actions**.
- Export dossier complet (chaîne de responsabilité, horodatage, preuves associées).

**Impact :** alignement direct avec les usages réels d’enquête, de conformité et de responsabilité.

---

## 13. Simulation multi-théâtres interconnectés

**Constat actuel :** les contextes tactiques sont traités de manière isolée.

**Feature :**
- Plusieurs cartes simultanées.
- Commandement centralisé avec unités réparties sur différents théâtres.

**Impact :** montée d’échelle vers un niveau brigade / théâtre complet.

---

## 14. Système de stress / fatigue opérateur

**Constat actuel :** facteur humain peu représenté dans les boucles de décision.

**Feature :**
- Modélisation de la fatigue et du stress avec effets sur la **précision** et le **temps de réaction**.
- Remontée des états humains dans le BFT (indicateurs exploitables par le commandement).

**Impact :** ajout d’un facteur humain réaliste dans la conduite des opérations.

---

## Priorisation complémentaire (modules 7 → 14)

1. **Fog of War** + autorité dynamique — socle de réalisme C2 et discipline de l’information.
2. **AAR automatique** — ferme la boucle RETEX et alimente la formation.
3. **Logistique opérationnelle** — impact direct sur tempo, autonomie et décisions tactiques.
4. **IA décisionnelle** — assistance progressive, d’abord en recommandation non prescriptive.
5. **Workflow judiciaire / traçabilité** — conformité et responsabilité (usage sensible).
6. **Stress / fatigue opérateur** — enrichissement humain de la simulation.
7. **Multi-théâtres interconnectés** — montée en échelle finale (brigade / théâtre).
